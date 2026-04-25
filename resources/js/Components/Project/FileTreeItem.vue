<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps({
    item: Object,
    initiallyOpen: Boolean,
});

const emit = defineEmits(['select', 'create', 'delete', 'rename', 'copy', 'move-item', 'toggle-folder']);

const isOpen = ref(props.initiallyOpen || false);
const menuOpen = ref(false);
const isDragOver = ref(false);
let hoverTimer = null;

const toggle = () => {
    if (props.item.type === 'folder') {
        isOpen.value = !isOpen.value;
        emit('toggle-folder', { id: props.item.id, open: isOpen.value });
    }
};

const toggleMenu = (event) => {
    event.stopPropagation();
    menuOpen.value = !menuOpen.value;
};

const closeMenu = () => {
    menuOpen.value = false;
};

const onDragStart = (e) => {
    // Sla het ID van het gesleepte item op in de globale drag data
    e.dataTransfer.setData('text/plain', props.item.id.toString());
    e.dataTransfer.effectAllowed = 'move';
};

const onDragEnter = (e) => {
    if (props.item.type === 'folder') {
        isDragOver.value = true;
        if (!isOpen.value) {
            hoverTimer = setTimeout(() => {
                isOpen.value = true;
            }, 500);
        }
    }
};

const onDragLeave = (e) => {
    // Alleen uitzetten als we echt het element verlaten (niet naar een kind gaan)
    if (!e.currentTarget.contains(e.relatedTarget)) {
        isDragOver.value = false;
        if (hoverTimer) {
            clearTimeout(hoverTimer);
            hoverTimer = null;
        }
    }
};

const onDrop = (e) => {
    isDragOver.value = false;
    if (hoverTimer) clearTimeout(hoverTimer);

    if (props.item.type !== 'folder') return;

    const draggedId = e.dataTransfer.getData('text/plain');
    if (draggedId && parseInt(draggedId) !== props.item.id) {
        emit('move-item', { fileId: parseInt(draggedId), parentId: props.item.id });
    }
};

const onDragAdd = (evt) => {
    isDragOver.value = false;
    const fileId = evt.item.getAttribute('data-id');
    if (fileId) {
        emit('move-item', { fileId: parseInt(fileId), parentId: props.item.id });
    }
};

onMounted(() => {
    window.addEventListener('click', closeMenu);
});

onUnmounted(() => {
    window.removeEventListener('click', closeMenu);
    if (hoverTimer) clearTimeout(hoverTimer);
});
</script>

<template>
    <li 
        class="select-none mb-0.5" 
        :data-id="props.item.id"
        draggable="true"
        @dragstart="onDragStart"
    >
        <!-- Folder/File Row -->
        <div 
            class="flex items-center py-1.5 px-2 hover:bg-gray-200 cursor-pointer rounded group relative transition-all border border-transparent"
            :class="{ 'bg-blue-100 border-blue-500 shadow-sm scale-[1.02] z-10': isDragOver }"
            @click="props.item.type === 'folder' ? toggle() : emit('select', props.item)"
            @dragenter.prevent="onDragEnter"
            @dragover.prevent
            @dragleave="onDragLeave"
            @drop.stop="onDrop"
        >
            <!-- Drag Handle (The Icon) -->
            <span class="drag-handle cursor-grab active:cursor-grabbing mr-2 text-xs w-4 flex items-center justify-center hover:scale-125 transition-transform">
                <template v-if="props.item.type === 'folder'">
                    {{ isOpen ? '▼' : '▶' }}
                </template>
                <template v-else>📄</template>
            </span>

            <span class="flex-grow truncate text-sm" :class="{ 'font-bold': props.item.type === 'folder' }" :title="props.item.name">
                {{ props.item.name }}
            </span>
            
            <div class="hidden group-hover:flex ml-2 items-center gap-1">
                <button v-if="props.item.type === 'folder'" @click.stop="emit('create', props.item.id, 'file')" class="text-blue-500 hover:text-blue-700 p-1 font-bold" title="Nieuw bestand">+</button>
                
                <div class="relative">
                    <button @click.stop="toggleMenu" class="text-gray-400 hover:text-gray-700 px-1 font-bold">⋮</button>
                    
                    <div v-if="menuOpen" class="absolute right-0 mt-1 w-32 bg-white border border-gray-200 shadow-lg rounded z-50 py-1">
                        <button @click.stop="emit('rename', props.item); closeMenu()" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-100 font-normal">Hernoemen</button>
                        <button @click.stop="emit('copy', props.item); closeMenu()" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-100 font-normal">Kopiëren</button>
                        <button @click.stop="emit('delete', props.item.id); closeMenu()" class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-100 text-red-600 font-normal">Verwijderen</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Children list -->
        <draggable 
            v-if="props.item.type === 'folder'"
            v-show="isOpen"
            v-model="props.item.children" 
            item-key="id" 
            tag="ul" 
            class="pl-6 mt-0.5 border-l border-gray-200 ml-3 min-h-[25px] transition-all"
            :class="{ 'bg-blue-50/30 rounded': isDragOver }"
            :group="{ name: 'files' }"
            handle=".drag-handle"
            ghost-class="opacity-50"
            @add="onDragAdd"
        >
            <template #item="{ element }">
                <FileTreeItem 
                    :item="element"
                    @select="file => emit('select', file)"
                    @create="(pid, type) => emit('create', pid, type)"
                    @delete="id => emit('delete', id)"
                    @rename="f => emit('rename', f)"
                    @copy="f => emit('copy', f)"
                    @move-item="data => emit('move-item', data)"
                    @toggle-folder="data => emit('toggle-folder', data)"
                    :initiallyOpen="element.children && element.children.length > 0"
                />
            </template>
            <!-- Placeholder for empty folders -->
            <template #header v-if="!props.item.children || props.item.children.length === 0">
                <div v-show="isDragOver" class="text-[10px] text-blue-400 py-1 italic">Drop hier...</div>
            </template>
        </draggable>
    </li>
</template>

<style scoped>
.group:hover .hidden {
    display: flex;
}
.drag-handle {
    color: #9ca3af;
}
.drag-handle:hover {
    color: #4b5563;
}
/* Voorkom dat de tekst geselecteerd wordt tijdens het slepen */
li[draggable="true"] {
    user-select: none;
}
</style>
