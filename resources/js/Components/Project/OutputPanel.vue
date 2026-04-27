<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';

const props = defineProps({
    type: String, 
    content: String, 
    rOutput: Object, 
});

const activeTab = ref('plots');
const consoleContainer = ref(null);
const currentPlotIndex = ref(0);

// Resizing logic for R panels
const consoleHeight = ref(50); // percentage
const isResizing = ref(false);

const startResizing = (e) => {
    isResizing.value = true;
    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', stopResizing);
};

const handleMouseMove = (e) => {
    if (!isResizing.value) return;
    const container = document.querySelector('.r-output-container');
    if (!container) return;
    
    const rect = container.getBoundingClientRect();
    const offsetY = e.clientY - rect.top;
    const percentage = (offsetY / rect.height) * 100;
    
    if (percentage > 10 && percentage < 90) {
        consoleHeight.value = percentage;
    }
};

const stopResizing = () => {
    isResizing.value = false;
    document.removeEventListener('mousemove', handleMouseMove);
    document.removeEventListener('mouseup', stopResizing);
};

watch(() => props.rOutput?.structured_output, () => {
    nextTick(() => {
        if (consoleContainer.value) {
            consoleContainer.value.scrollTop = consoleContainer.value.scrollHeight;
        }
    });
}, { deep: true });

watch(() => props.rOutput?.plots, (newPlots) => {
    if (newPlots?.length > 0) {
        currentPlotIndex.value = newPlots.length - 1;
        activeTab.value = 'plots';
    }
});

const nextPlot = () => {
    if (props.rOutput?.plots && currentPlotIndex.value < props.rOutput.plots.length - 1) {
        currentPlotIndex.value++;
    }
};

const prevPlot = () => {
    if (currentPlotIndex.value > 0) {
        currentPlotIndex.value--;
    }
};
</script>

<template>
    <div class="flex flex-col h-full bg-[#1e1e1e] border-l border-[#333]">
        <!-- PDF Viewer -->
        <div v-if="type === 'pdf'" class="flex-grow relative h-full">
            <iframe 
                v-if="content" 
                :src="content" 
                class="w-full h-full border-none"
                style="color-scheme: light;"
            ></iframe>
            <div v-else class="flex items-center justify-center h-full text-gray-500">
                Wachten op PDF...
            </div>
        </div>

        <!-- R Output -->
        <div v-else-if="type === 'r'" class="flex flex-col h-full r-output-container overflow-hidden">
            <!-- Console Panel -->
            <div 
                class="flex flex-col overflow-hidden bg-[#1e1e1e]"
                :style="{ height: consoleHeight + '%' }"
            >
                <div class="flex items-center justify-between px-4 py-2 bg-[#2d2d2d] border-b border-[#3e3e42] shrink-0">
                    <span class="text-gray-400 text-xs font-bold uppercase tracking-wider">R Console</span>
                </div>
                
                <div ref="consoleContainer" class="flex-grow overflow-auto p-4 font-mono text-sm">
                    <div v-for="(line, idx) in rOutput?.structured_output" :key="idx" class="mb-1 leading-relaxed">
                        <div v-if="line.type === 'code'" class="text-[#4fc1ff] flex">
                            <span class="text-gray-600 mr-2 shrink-0">></span>
                            <span class="whitespace-pre-wrap">{{ line.content }}</span>
                        </div>
                        <div v-else-if="line.type === 'output'" class="text-[#cccccc] pl-4 whitespace-pre-wrap">
                            {{ line.content }}
                        </div>
                        <div v-else-if="line.type === 'error'" class="text-[#f48771] pl-4 italic">
                            {{ line.content }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resize Handle -->
            <div 
                @mousedown="startResizing"
                class="h-1 bg-[#333] hover:bg-blue-500 cursor-ns-resize transition-colors shrink-0 z-10"
            ></div>

            <!-- Bottom Panel (Plots/Variables) -->
            <div class="flex-grow flex flex-col bg-[#252526] overflow-hidden" :style="{ height: (100 - consoleHeight) + '%' }">
                <div class="flex bg-[#2d2d2d] border-b border-[#3e3e42] shrink-0">
                    <button 
                        @click="activeTab = 'plots'"
                        :class="['px-4 py-2 text-xs font-bold uppercase tracking-wider transition-colors', activeTab === 'plots' ? 'bg-[#1e1e1e] text-white border-t-2 border-blue-500' : 'text-gray-500 hover:text-gray-300']"
                    >
                        Plots ({{ rOutput?.plots?.length || 0 }})
                    </button>
                    <button 
                        @click="activeTab = 'variables'"
                        :class="['px-4 py-2 text-xs font-bold uppercase tracking-wider transition-colors', activeTab === 'variables' ? 'bg-[#1e1e1e] text-white border-t-2 border-blue-500' : 'text-gray-500 hover:text-gray-300']"
                    >
                        Variables
                    </button>
                </div>

                <!-- Plots View -->
                <div v-if="activeTab === 'plots'" class="flex-grow relative flex items-center justify-center p-4 overflow-hidden">
                    <template v-if="rOutput?.plots?.length > 0">
                        <!-- Navigation Arrows -->
                        <button 
                            v-if="currentPlotIndex > 0"
                            @click="prevPlot"
                            class="absolute left-2 p-2 bg-black/50 text-white rounded-full hover:bg-black/80 z-10"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>

                        <div class="flex flex-col items-center gap-4 w-full h-full justify-center">
                            <img 
                                :src="rOutput.plots[currentPlotIndex]" 
                                class="max-w-full max-h-[85%] object-contain shadow-2xl bg-white"
                                :key="currentPlotIndex"
                            />
                            <div class="text-gray-500 text-[10px] uppercase font-bold">
                                Plot {{ currentPlotIndex + 1 }} van {{ rOutput.plots.length }}
                            </div>
                        </div>

                        <button 
                            v-if="currentPlotIndex < rOutput.plots.length - 1"
                            @click="nextPlot"
                            class="absolute right-2 p-2 bg-black/50 text-white rounded-full hover:bg-black/80 z-10"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </template>
                    <div v-else class="text-gray-600 text-xs italic">
                        Geen grafieken gegenereerd.
                    </div>
                </div>

                <!-- Variables View -->
                <div v-else class="flex-grow overflow-auto p-0">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-[#2d2d2d] text-gray-500 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 border-b border-[#3e3e42]">Naam</th>
                                <th class="px-4 py-2 border-b border-[#3e3e42]">Type</th>
                                <th class="px-4 py-2 border-b border-[#3e3e42]">Waarde</th>
                            </tr>
                        </thead>
                        <tbody class="text-[#cccccc]">
                            <tr v-for="v in rOutput?.variables" :key="v.name" class="hover:bg-[#2a2d2e] border-b border-[#333]">
                                <td class="px-4 py-2 text-[#4fc1ff]">{{ v.name }}</td>
                                <td class="px-4 py-2 text-gray-500 italic">{{ v.type }}</td>
                                <td class="px-4 py-2 truncate max-w-xs">{{ v.value }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Text/Log -->
        <div v-else class="p-4 font-mono text-sm bg-gray-50 flex-grow overflow-auto">
            <pre class="whitespace-pre-wrap text-gray-700">{{ content || 'No output' }}</pre>
        </div>
    </div>
</template>

<style scoped>
.r-output-container {
    user-select: none;
}
.r-output-container pre, .r-output-container div {
    user-select: text;
}
</style>
