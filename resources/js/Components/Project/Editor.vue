<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { EditorView, basicSetup } from 'codemirror';
import { markdown } from '@codemirror/lang-markdown';
import { javascript } from '@codemirror/lang-javascript';
import { json } from '@codemirror/lang-json';
import { xml } from '@codemirror/lang-xml';
import { keymap } from "@codemirror/view";
import { Prec } from "@codemirror/state";
import { StreamLanguage } from "@codemirror/language";
import { r } from "@codemirror/legacy-modes/mode/r";
import { stex } from "@codemirror/legacy-modes/mode/stex";
import { typstLanguage } from "../../typst-lang";

const props = defineProps({
    file: Object,
    isCompiling: Boolean,
});

const emit = defineEmits(['save', 'compile', 'show-log', 'stop-compilation']);

const editorContainer = ref(null);
const selectedCompiler = ref('pdflatex');
let view = null;
let saveTimeout = null;

const ext = computed(() => props.file?.extension?.toLowerCase() || '');

const getExtensions = (extension) => {
    const extensions = [
        basicSetup,
        Prec.highest(keymap.of([
            {
                key: "Ctrl-Enter",
                run: () => {
                    handleCompile();
                    return true;
                }
            },
            {
                key: "Cmd-Enter",
                run: () => {
                    handleCompile();
                    return true;
                }
            }
        ]))
    ];
    
    const e = extension.toLowerCase();
    if (e === 'md' || e === 'rmd') extensions.push(markdown());
    if (e === 'typ') extensions.push(typstLanguage);
    if (e === 'js') extensions.push(javascript());
    if (e === 'json') extensions.push(json());
    if (e === 'xml') extensions.push(xml());
    if (e === 'r') extensions.push(StreamLanguage.define(r));
    if (e === 'tex') extensions.push(StreamLanguage.define(stex));
    
    return extensions;
};

const autoSave = (content) => {
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(() => {
        emit('save', content);
    }, 1000);
};

const updatePreferredCompiler = () => {
    axios.patch(route('files.update', props.file.id), {
        preferred_compiler: props.file.preferred_compiler
    });
};

const handleCompile = () => {
    let code = null;
    if (ext.value === 'r' && view) {
        const selection = view.state.selection.main;
        if (!selection.empty) {
            code = view.state.doc.sliceString(selection.from, selection.to);
        } else {
            const line = view.state.doc.lineAt(selection.from);
            code = line.text;
        }
    }
    emit('compile', props.file.preferred_compiler, code);
};

const initEditor = () => {
    if (view) view.destroy();
    view = new EditorView({
        doc: props.file?.content || '',
        extensions: [
            ...getExtensions(ext.value),
            EditorView.updateListener.of((update) => {
                if (update.docChanged) {
                    autoSave(update.state.doc.toString());
                }
            }),
        ],
        parent: editorContainer.value,
    });
    
    // Attach to DOM for testing purposes
    editorContainer.value._cm = view;
};

watch(() => props.file?.id, (newId) => {
    if (newId) initEditor();
});

onMounted(() => {
    if (props.file) initEditor();
});
</script>

<template>
    <div class="flex flex-col h-full">
        <div class="flex justify-between items-center p-2 bg-gray-100 border-b border-gray-300">
            <div class="flex items-center gap-4">
                <span class="text-sm font-mono font-bold">{{ file?.name }}</span>
            </div>
            <div class="flex gap-4 items-center">
                <span class="text-[10px] text-green-500 font-bold font-mono">🚀 DOCS-V9-FINAL</span>
                
                <!-- Compiler Selector for LaTeX -->
                <div v-if="ext === 'tex'" class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 uppercase font-bold">Engine:</span>
                    <select 
                        v-model="file.preferred_compiler" 
                        @change="updatePreferredCompiler"
                        class="text-xs border-gray-300 rounded py-0.5 px-2 bg-gray-50 focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="pdflatex">pdfLaTeX</option>
                        <option value="xelatex">XeLaTeX</option>
                        <option value="lualatex">LuaLaTeX</option>
                    </select>
                </div>

                <button 
                    v-if="['tex', 'typ', 'md', 'rmd', 'r'].includes(ext)"
                    dusk="compile-button"
                    @click="isCompiling ? $emit('stop-compilation') : handleCompile()"
                    :class="['text-white px-3 py-1 rounded text-sm flex items-center gap-2 transition-colors', isCompiling ? 'bg-red-500 hover:bg-red-600' : 'bg-blue-600 hover:bg-blue-700']"
                >
                    <svg v-if="isCompiling" class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    {{ isCompiling ? 'Stop' : 'Compile/Run' }}
                </button>
                <button 
                    v-if="['tex', 'typ', 'md', 'rmd', 'r'].includes(ext)"
                    @click="$emit('show-log')"
                    class="bg-gray-500 text-white px-3 py-1 rounded text-sm hover:bg-gray-600"
                >
                    Show Log
                </button>
            </div>
        </div>
        <div ref="editorContainer" dusk="editor-container" class="flex-grow overflow-auto h-0"></div>
    </div>
</template>

<style>
.cm-editor { height: 100%; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
.cm-editor.cm-focused { outline: none; }
</style>
