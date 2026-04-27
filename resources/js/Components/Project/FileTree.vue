<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import draggable from 'vuedraggable';
import FileTreeItem from './FileTreeItem.vue';

const props = defineProps({
    project: Object,
    files: Array,
});

const emit = defineEmits(['file-selected']);

const selectedFileId = ref(null);
const localTree = ref([]);
const openFolders = ref(new Set());

const buildTree = (files) => {
    const map = {};
    const result = [];
    
    files.forEach(file => {
        map[file.id] = { ...file, children: [] };
    });
    
    files.forEach(file => {
        if (file.parent_id) {
            if (map[file.parent_id]) {
                map[file.parent_id].children.push(map[file.id]);
            }
        } else {
            result.push(map[file.id]);
        }
    });
    
    return result;
};

watch(() => props.files, (newFiles) => {
    localTree.value = buildTree(newFiles);
}, { immediate: true, deep: true });

const selectFile = (file) => {
    if (file.type === 'file') {
        selectedFileId.value = file.id;
        emit('file-selected', file);
    }
};

const handleToggleFolder = ({ id, open }) => {
    if (open) {
        openFolders.value.add(id);
    } else {
        openFolders.value.delete(id);
    }
};

const moveItem = async ({ fileId, parentId }) => {
    try {
        if (parentId) openFolders.value.add(parentId);
        
        await axios.post(route('files.move', fileId), {
            parent_id: parentId
        });
        
        // Use router.reload to keep component state
        router.reload({ only: ['project'] });
    } catch (error) {
        console.error('Move failed');
    }
};

const onDragAddRoot = (evt) => {
    const fileId = evt.item.getAttribute('data-id');
    if (fileId) {
        moveItem({ fileId: parseInt(fileId), parentId: null });
    }
};

const createFile = async (parentId = null, type = 'file') => {
    const name = prompt(`Enter ${type} name:`);
    if (!name) return;
    try {
        await axios.post(route('files.store'), {
            project_id: props.project.id,
            parent_id: parentId,
            name: name,
            type: type,
        });
        router.reload({ only: ['project'] });
    } catch (error) { alert('Error'); }
};

const deleteFile = async (fileId) => {
    if (!confirm('Are you sure?')) return;
    try {
        await axios.delete(route('files.destroy', fileId));
        router.reload({ only: ['project'] });
    } catch (error) { alert('Error'); }
};

const renameFile = async (file) => {
    const newName = prompt('Enter new name:', file.name);
    if (!newName || newName === file.name) return;
    try {
        await axios.patch(route('files.update', file.id), { name: newName });
        router.reload({ only: ['project'] });
    } catch (error) { alert('Error'); }
};

const copyFile = async (file) => {
    try {
        await axios.post(route('files.duplicate', file.id));
        router.reload({ only: ['project'] });
    } catch (error) { alert('Error'); }
};

const onFileUpload = async (event) => {
    const files = event.target.files;
    if (!files.length) return;
    
    const formData = new FormData();
    formData.append('project_id', props.project.id);
    
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
        // Gebruik webkitRelativePath om de mappenstructuur te behouden
        formData.append('paths[]', files[i].webkitRelativePath || files[i].name);
    }

    try {
        await axios.post(route('files.upload'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        router.reload({ only: ['project'] });
    } catch (error) { 
        alert('Upload failed: ' + (error.response?.data?.message || error.message)); 
    }
};
</script>

<template>
    <div dusk="file-tree" class="p-4 overflow-y-auto h-full border-r border-gray-200 bg-gray-50 flex flex-col">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-sm uppercase text-gray-500">Files</h3>
            <div class="flex gap-2">
                <button @click="createFile(null, 'file')" class="text-blue-500 hover:text-blue-700" title="New File">📄+</button>
                <button @click="createFile(null, 'folder')" class="text-green-500 hover:text-green-700" title="New Folder">📁+</button>
                
                <!-- Upload Files -->
                <label class="cursor-pointer text-orange-500 hover:text-orange-700" title="Upload Files">
                    ⬆️📄
                    <input type="file" class="hidden" multiple @change="onFileUpload" />
                </label>

                <!-- Upload Folder -->
                <label class="cursor-pointer text-orange-600 hover:text-orange-800" title="Upload Folder">
                    ⬆️📁
                    <input type="file" class="hidden" webkitdirectory directory @change="onFileUpload" />
                </label>
            </div>
        </div>
        
        <draggable 
            v-model="localTree" 
            item-key="id" 
            tag="ul" 
            :group="{ name: 'files', pull: 'clone', put: true }"
            :sort="false"
            handle=".drag-handle"
            @add="onDragAddRoot"
            class="flex-grow min-h-[50px]"
        >
            <template #item="{ element }">
                <FileTreeItem 
                    :item="element" 
                    :initiallyOpen="openFolders.has(element.id)"
                    @select="selectFile"
                    @create="createFile"
                    @delete="deleteFile"
                    @rename="renameFile"
                    @copy="copyFile"
                    @move-item="moveItem"
                    @toggle-folder="handleToggleFolder"
                />
            </template>
        </draggable>

        <div v-if="localTree.length === 0" class="mt-4 text-gray-400 text-sm text-center italic">
            No files in this project.
        </div>
    </div>
</template>
