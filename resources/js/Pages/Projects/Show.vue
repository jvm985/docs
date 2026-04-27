<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FileTree from '@/Components/Project/FileTree.vue';
import Editor from '@/Components/Project/Editor.vue';
import OutputPanel from '@/Components/Project/OutputPanel.vue';
import axios from 'axios';

const props = defineProps({
    project: Object,
});

const currentFile = ref(null);
const outputType = ref('pdf');
const outputContent = ref('');
const lastLog = ref('');
const rOutput = ref(null);
const isCompiling = ref(false);
const isShowingLog = ref(false);
const lastResult = ref({ type: 'pdf', content: '', rOutput: null });
const lastCompileTime = ref(0);
let abortController = null;

// Panel resizing state
const leftWidth = ref(250); // px
const rightWidth = ref(400); // px
const isResizingLeft = ref(false);
const isResizingRight = ref(false);

const startResizingLeft = () => { isResizingLeft.value = true; document.body.style.cursor = 'col-resize'; };
const startResizingRight = () => { isResizingRight.value = true; document.body.style.cursor = 'col-resize'; };

const stopResizing = () => {
    isResizingLeft.value = false;
    isResizingRight.value = false;
    document.body.style.cursor = 'default';
};

const onMouseMove = (e) => {
    if (isResizingLeft.value) {
        leftWidth.value = Math.max(150, Math.min(e.clientX, 500));
    } else if (isResizingRight.value) {
        const newWidth = window.innerWidth - e.clientX;
        rightWidth.value = Math.max(200, Math.min(newWidth, window.innerWidth - leftWidth.value - 200));
    }
};

onMounted(() => {
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', stopResizing);
});

onUnmounted(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', stopResizing);
});

const onFileSelected = async (file) => {
    if (file.type === 'folder') return;
    
    try {
        const response = await axios.get(route('files.show', file.id));
        currentFile.value = response.data;
    } catch (error) {
        console.error('Failed to load file content', error);
        currentFile.value = file;
    }
};

const saveFile = async (content) => {
    if (!currentFile.value) return;
    try {
        await axios.patch(route('files.update', currentFile.value.id), { content: content });
        currentFile.value.content = content;
    } catch (error) { console.error('Auto-save failed'); }
};

const stopCompilation = () => {
    if (abortController) {
        abortController.abort();
        isCompiling.value = false;
        outputContent.value = 'Compilation stopped by user.';
    }
};

const compileFile = async (compiler = null, code = null) => {
    if (!currentFile.value) return;

    isCompiling.value = true;
    abortController = new AbortController();

    try {
        const response = await axios.post(route('files.compile', currentFile.value.id), {
            compiler: compiler,
            code: code
        }, {
            signal: abortController.signal
        });

        console.log('Compile Response:', response.data);
        
        lastLog.value = response.data.output || 'No log output returned from server.';
        
        // Sla het echte resultaat op
        lastResult.value = {
            type: response.data.type,
            content: response.data.type === 'pdf' ? response.data.url : response.data.output,
            rOutput: response.data.result
        };

        // Alleen de view updaten als we niet in de log-modus zitten
        if (!isShowingLog.value) {
            outputType.value = lastResult.value.type;
            outputContent.value = lastResult.value.content;
            rOutput.value = lastResult.value.rOutput;
        }
        
        lastCompileTime.value = Date.now();
    } catch (error) {
        if (!axios.isCancel(error)) {
            lastLog.value = error.response?.data?.output || error.message;
            if (!isShowingLog.value) {
                outputType.value = 'text';
                outputContent.value = 'Compilation failed';
            }
        }
    } finally {
        isCompiling.value = false;
        abortController = null;
    }
};

const toggleLog = () => {
    isShowingLog.value = !isShowingLog.value;
    
    if (isShowingLog.value) {
        outputType.value = 'text';
        outputContent.value = lastLog.value;
    } else {
        outputType.value = lastResult.value.type;
        outputContent.value = lastResult.value.content;
        rOutput.value = lastResult.value.rOutput;
    }
};
</script>

<template>
    <Head :title="project.name" />

    <div class="h-screen flex flex-col" :class="{ 'select-none': isResizingLeft || isResizingRight }">
        <header class="bg-white shadow h-14 flex items-center px-4 justify-between shrink-0 z-10 border-b border-gray-200">
            <div class="flex items-center gap-4">
                <h1 class="text-lg font-bold text-gray-800">{{ project.name }}</h1>
            </div>
            <a :href="route('projects.index')" class="text-sm text-blue-600 hover:text-blue-800 font-medium">← Terug naar projecten</a>
        </header>

        <main class="flex-grow flex overflow-hidden bg-gray-100 relative">
            <div v-if="isResizingLeft || isResizingRight" class="absolute inset-0 z-30 cursor-col-resize"></div>

            <div :style="{ width: leftWidth + 'px' }" class="shrink-0 h-full bg-white relative">
                <FileTree 
                    :project="project" 
                    :files="project.files" 
                    @file-selected="onFileSelected" 
                />
            </div>

            <div 
                @mousedown="startResizingLeft"
                class="w-1.5 hover:w-2 bg-transparent hover:bg-blue-400 cursor-col-resize shrink-0 h-full transition-all z-20 border-x border-gray-200"
            ></div>
            
            <div class="flex-grow h-full bg-white flex flex-col min-w-0">
                <Editor 
                    v-if="currentFile" 
                    :file="currentFile" 
                    :isCompiling="isCompiling"
                    :isShowingLog="isShowingLog"
                    @save="saveFile" 
                    @compile="compileFile"
                    @stop-compilation="stopCompilation"
                    @show-log="toggleLog"
                />
                <div v-else class="flex flex-col items-center justify-center h-full text-gray-400 bg-gray-50">
                    <span class="text-4xl mb-2">📄</span>
                    <p>Selecteer een bestand om te bewerken</p>
                </div>
            </div>

            <div 
                @mousedown="startResizingRight"
                class="w-1.5 hover:w-2 bg-transparent hover:bg-blue-400 cursor-col-resize shrink-0 h-full transition-all z-20 border-x border-gray-200"
            ></div>
            
            <div :style="{ width: rightWidth + 'px' }" class="shrink-0 h-full bg-white overflow-hidden">
                <OutputPanel 
                    :key="lastCompileTime"
                    :type="outputType" 
                    :content="outputContent" 
                    :rOutput="rOutput" 
                />
            </div>
        </main>
    </div>
</template>

<style>
body {
    overflow: hidden;
}
</style>
