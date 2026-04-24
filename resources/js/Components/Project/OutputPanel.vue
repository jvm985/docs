<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';

const props = defineProps({
    type: String, 
    content: String, 
    rOutput: Object, 
});

const activeTab = ref('plots');
const consoleContainer = ref(null);

const scrollToBottom = () => {
    if (consoleContainer.value) {
        consoleContainer.value.scrollTop = consoleContainer.value.scrollHeight;
    }
};

watch(() => props.rOutput, () => {
    nextTick(scrollToBottom);
}, { deep: true });

onMounted(() => {
    if (props.type === 'r') scrollToBottom();
});
</script>

<template>
    <div class="h-full bg-white border-l border-gray-200 overflow-hidden flex flex-col">
        <!-- PDF Viewer -->
        <div v-if="type === 'pdf'" class="flex-grow">
            <iframe v-if="content" :src="content + '#navpanes=0&pagemode=none&view=FitH'" class="w-full h-full border-none"></iframe>
            <div v-else class="p-4 text-gray-400">Compiling PDF...</div>
        </div>
        
        <!-- R Output -->
        <div v-else-if="type === 'r'" class="flex flex-col h-full bg-[#1e1e1e]">
            <!-- Console -->
            <div ref="consoleContainer" class="flex-grow p-4 font-mono text-sm overflow-auto scroll-smooth">
                <!-- Structured Output -->
                <div v-if="rOutput?.structured_output?.length">
                    <div v-for="(line, idx) in rOutput.structured_output" :key="idx" class="mb-1">
                        <div v-if="(Array.isArray(line.type) ? line.type[0] : line.type) === 'code'" class="text-[#4ec9b0] flex">
                            <span class="text-gray-500 mr-2 shrink-0">></span>
                            <span class="whitespace-pre-wrap">{{ Array.isArray(line.content) ? line.content[0] : line.content }}</span>
                        </div>
                        <div v-else-if="(Array.isArray(line.type) ? line.type[0] : line.type) === 'output'" class="text-[#dcdcdc] pl-4 whitespace-pre-wrap">
                            {{ Array.isArray(line.content) ? line.content[0] : line.content }}
                        </div>
                        <div v-else-if="(Array.isArray(line.type) ? line.type[0] : line.type) === 'error'" class="text-[#f48771] pl-4 italic">
                            {{ Array.isArray(line.content) ? line.content[0] : line.content }}
                        </div>
                    </div>
                </div>
                
                <!-- Fallback/System Log -->
                <div class="mt-6 pt-4 border-t border-[#333]">
                    <div class="text-gray-600 text-[10px] uppercase font-bold mb-2">System Output</div>
                    <pre class="text-gray-500 text-[10px] whitespace-pre-wrap">{{ content || rOutput?.output || 'No output.' }}</pre>
                </div>
            </div>
            
            <!-- Bottom Panel -->
            <div class="h-1/2 border-t border-[#333] flex flex-col bg-[#252526]">
                <div class="flex bg-[#2d2d2d] border-b border-[#3e3e42] shrink-0">
                    <button dusk="tab-plots" @click="activeTab = 'plots'" :class="['px-4 py-2 text-xs font-bold uppercase transition-colors', activeTab === 'plots' ? 'bg-[#252526] text-white border-b-2 border-blue-500' : 'text-[#858585] hover:text-white']">Plots</button>
                    <button dusk="tab-variables" @click="activeTab = 'variables'" :class="['px-4 py-2 text-xs font-bold uppercase transition-colors', activeTab === 'variables' ? 'bg-[#252526] text-white border-b-2 border-blue-500' : 'text-[#858585] hover:text-white']">Variables</button>
                </div>
                
                <div class="flex-grow p-4 overflow-auto bg-[#252526] text-[#cccccc]">
                    <div v-if="activeTab === 'plots'">
                        <img v-for="plot in rOutput?.plots" :key="plot" :src="plot" class="max-w-full h-auto mb-4 border border-[#3e3e42]" />
                        <div v-if="!rOutput?.plots?.length" class="text-[#858585] text-center mt-8">No plots generated</div>
                    </div>
                    
                    <div v-if="activeTab === 'variables'">
                        <table class="w-full text-sm font-mono border-collapse">
                            <tr v-for="v in rOutput?.variables" :key="v.name" class="border-b border-[#333] hover:bg-[#2a2d2e]">
                                <td class="py-1 text-[#4ec9b0]">{{ v.name }}</td>
                                <td class="py-1 text-[#ce9178]">{{ v.type }}</td>
                                <td class="py-1 text-[#dcdcdc] truncate max-w-[150px]" :title="v.value">{{ v.value }}</td>
                            </tr>
                        </table>
                        <div v-if="!rOutput?.variables?.length" class="text-[#858585] text-center mt-8">No variables available</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Text/Log -->
        <div v-else class="p-4 font-mono text-sm bg-gray-50 flex-grow overflow-auto">
            <pre class="whitespace-pre-wrap text-gray-700">{{ content || 'No output' }}</pre>
        </div>
    </div>
</template>
