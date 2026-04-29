// Monaco Editor wordt lazy geladen wanneer een bestand geopend wordt
let monacoModule = null;

async function getMonaco() {
    if (!monacoModule) {
        monacoModule = await import('monaco-editor');

        const editorWorker = await import('monaco-editor/esm/vs/editor/editor.worker?worker');
        const jsonWorker = await import('monaco-editor/esm/vs/language/json/json.worker?worker');

        self.MonacoEnvironment = {
            getWorker(_, label) {
                if (label === 'json') {
                    return new jsonWorker.default();
                }
                return new editorWorker.default();
            },
        };
    }
    return monacoModule;
}

// ─── Monaco Editor Alpine component ────────────────────────────────────────

window.monacoEditor = function (initialContent, language, hasFile) {
    return {
        editor: null,
        hasActiveFile: hasFile,

        async init() {
            if (!hasFile) return;

            const monaco = await getMonaco();

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
                    this.$wire.saveContent(this.editor.getValue());
                }, 500);
            });

            // Ctrl+Enter: compile or run
            this.editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter, () => {
                this._runOrCompile();
            });

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

            this.$wire.dispatch('execute-r', { code });
            this.$wire.dispatch('compile');
        },

        destroy() {
            this.editor?.dispose();
        },
    };
};

// Overige Alpine components (contextMenu, rOutputPanel, rSidePanel, pdfViewer, handleUpload)
// zijn inline gedefinieerd in editor.blade.php

// ─── Helpers ────────────────────────────────────────────────────────────────

window.editorLanguage = function (filename) {
    const ext = filename.split('.').pop().toLowerCase();
    const map = {
        tex: 'latex', md: 'markdown', rmd: 'markdown', r: 'r',
        json: 'json', xml: 'xml', txt: 'plaintext', typ: 'plaintext',
        js: 'javascript', ts: 'typescript', php: 'php', py: 'python',
        css: 'css', html: 'html',
    };
    return map[ext] ?? 'plaintext';
};
