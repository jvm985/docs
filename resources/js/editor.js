import * as monaco from 'monaco-editor';
import editorWorker from 'monaco-editor/esm/vs/editor/editor.worker?worker';
import jsonWorker from 'monaco-editor/esm/vs/language/json/json.worker?worker';

// Configure Monaco workers
self.MonacoEnvironment = {
    getWorker(_, label) {
        if (label === 'json') {
            return new jsonWorker();
        }
        return new editorWorker();
    },
};

// ─── Monaco Editor Alpine component ────────────────────────────────────────

window.monacoEditor = function (initialContent, language, hasFile) {
    return {
        editor: null,
        hasActiveFile: hasFile,

        init() {
            this.editor = monaco.editor.create(this.$refs.editor, {
                value: initialContent ?? '',
                language: language ?? 'plaintext',
                theme: document.documentElement.classList.contains('dark') ? 'vs-dark' : 'vs',
                automaticLayout: true,
                fontSize: 14,
                minimap: { enabled: false },
                scrollBeyondLastLine: false,
                wordWrap: 'on',
                lineNumbers: 'on',
            });

            // Auto-save on change (debounced 500ms)
            let saveTimeout;
            this.editor.onDidChangeModelContent(() => {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    window.Livewire.dispatch('save-content', { content: this.editor.getValue() });
                }, 500);
            });

            // Ctrl+Enter: compile or run
            this.editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter, () => {
                this._runOrCompile();
            });

            // Listen from toolbar run button
            document.addEventListener('editor-run-selection', () => this._runOrCompile());
            document.addEventListener('editor-ctrl-enter', () => this._runOrCompile());

            // Dark mode observer
            new MutationObserver(() => {
                monaco.editor.setTheme(
                    document.documentElement.classList.contains('dark') ? 'vs-dark' : 'vs'
                );
            }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },

        _runOrCompile() {
            if (!this.editor) return;
            const selection = this.editor.getSelection();
            const model = this.editor.getModel();
            let code;

            if (selection && !selection.isEmpty()) {
                code = model.getValueInRange(selection);
            } else {
                code = model.getLineContent(selection.startLineNumber);
            }

            window.Livewire.dispatch('execute-r', { code });
            window.Livewire.dispatch('compile');
        },

        destroy() {
            this.editor?.dispose();
        },
    };
};

// ─── Filetree Alpine component ──────────────────────────────────────────────

window.fileTree = function (initialNodes, projectId) {
    return {
        nodes: initialNodes,
        projectId,
        expandedFolders: new Set(),
        renamingNodeId: null,
        renamingName: '',
        contextMenu: { visible: false, x: 0, y: 0, nodeId: null },

        getChildren(parentId) {
            return this.nodes
                .filter(n => n.parent_id === parentId)
                .sort((a, b) => {
                    if (a.type !== b.type) return a.type === 'folder' ? -1 : 1;
                    return a.name.localeCompare(b.name);
                });
        },

        toggleFolder(nodeId) {
            if (this.expandedFolders.has(nodeId)) {
                this.expandedFolders.delete(nodeId);
            } else {
                this.expandedFolders.add(nodeId);
            }
            this.expandedFolders = new Set(this.expandedFolders);
        },

        openFile(node) {
            if (node.type !== 'file') return;
            window.Livewire.dispatch('open-file', { nodeId: node.id });
            window.dispatchEvent(new CustomEvent('open-node', {
                detail: { content: node.content, language: editorLanguage(node.name), nodeId: node.id }
            }));
        },

        createItem(type, parentId) {
            const name = prompt(type === 'file' ? 'Bestandsnaam:' : 'Mapnaam:');
            if (!name?.trim()) return;
            window.Livewire.dispatch('create-node', { name: name.trim(), parentId, type });
        },

        startRename(node, event) {
            event.stopPropagation();
            this.renamingNodeId = node.id;
            this.renamingName = node.name;
            this.$nextTick(() => this.$refs['rename-' + node.id]?.focus());
        },

        confirmRename(node) {
            if (this.renamingName.trim() && this.renamingName !== node.name) {
                window.Livewire.dispatch('rename-node', { nodeId: node.id, name: this.renamingName.trim() });
            }
            this.renamingNodeId = null;
        },

        deleteNode(nodeId, event) {
            event.stopPropagation();
            if (!confirm('Verwijder dit item?')) return;
            window.Livewire.dispatch('delete-node', { nodeId });
        },

        async uploadFiles(event, parentId) {
            const files = Array.from(event.target.files);
            for (const file of files) {
                const content = await file.text();
                window.Livewire.dispatch('upload-file', { name: file.name, content, parentId });
            }
            event.target.value = '';
        },

        // Drag and drop
        dragStart(event, nodeId) {
            event.dataTransfer.setData('nodeId', nodeId);
        },

        drop(event, newParentId) {
            event.preventDefault();
            const nodeId = parseInt(event.dataTransfer.getData('nodeId'));
            if (nodeId === newParentId) return;
            window.Livewire.dispatch('move-node', { nodeId, newParentId });
        },

        // Listen for Livewire node updates
        init() {
            window.addEventListener('nodes-updated', (e) => {
                this.nodes = e.detail.nodes;
            });
        },
    };
};

// ─── Output panel Alpine component ─────────────────────────────────────────

window.outputPanel = function () {
    return {
        activeTab: 'variables',
        rOutputEntries: [],
        rVariables: [],
        rPlots: [],

        init() {
            window.addEventListener('r-output', (e) => {
                this.rOutputEntries.push({ id: Date.now() + Math.random(), ...e.detail });
                this.$nextTick(() => {
                    const el = this.$refs.rOutput;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            });

            window.addEventListener('r-variables', (e) => {
                this.rVariables = e.detail.variables;
            });

            window.addEventListener('r-plot', (e) => {
                this.rPlots.push(e.detail.dataUrl);
                this.activeTab = 'plots';
            });
        },

        clearOutput() {
            this.rOutputEntries = [];
        },
    };
};

// ─── PDF Viewer Alpine component ────────────────────────────────────────────

window.pdfViewer = function () {
    return {
        pdfUrl: null,

        init() {
            window.addEventListener('pdf-ready', (e) => {
                this.pdfUrl = e.detail.url + '?t=' + Date.now();
            });

            window.addEventListener('show-compile-log', () => {
                window.Livewire.dispatch('show-log');
            });
        },
    };
};

// ─── Helpers ────────────────────────────────────────────────────────────────

function editorLanguage(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    const map = {
        tex: 'latex', md: 'markdown', rmd: 'markdown', r: 'r',
        json: 'json', xml: 'xml', txt: 'plaintext', typ: 'plaintext',
        js: 'javascript', ts: 'typescript', php: 'php', py: 'python',
        css: 'css', html: 'html',
    };
    return map[ext] ?? 'plaintext';
}
