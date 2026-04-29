import Alpine from 'alpinejs';
import { EditorView, basicSetup } from 'codemirror';
import { EditorState } from '@codemirror/state';
import { javascript } from '@codemirror/lang-javascript';
import { json } from '@codemirror/lang-json';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { markdown } from '@codemirror/lang-markdown';
import { xml } from '@codemirror/lang-xml';
import { php } from '@codemirror/lang-php';
import { python } from '@codemirror/lang-python';
import { oneDark } from '@codemirror/theme-one-dark';

function getLanguage(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    const map = {
        js: javascript, ts: javascript, json, html, css, xml,
        md: markdown, rmd: markdown, php, py: python,
    };
    return map[ext]?.() ?? [];
}

function isCompilable(name) {
    return /\.(tex|md|rmd|typ)$/i.test(name);
}

function isExecutable(name) {
    return /\.r$/i.test(name);
}

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

async function api(url, options = {}) {
    const res = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers,
        },
        ...options,
    });
    if (!res.ok) throw new Error(`API ${res.status}`);
    return res.json();
}

window.editorApp = function (projectId) {
    return {
        projectId,
        projectName: '',
        nodes: [],
        activeNode: null,
        editorView: null,
        compiler: 'pdflatex',
        saving: false,
        saved: false,
        pdfUrl: null,
        rPlots: [],
        rVars: [],
        _saveTimeout: null,

        async init() {
            const data = await api(`/api/editor/${this.projectId}`);
            this.projectName = data.project.name;
            this.nodes = data.nodes;
            this._renderTree();

            // Auto-open file from URL ?file=id
            const params = new URLSearchParams(window.location.search);
            const fileId = params.get('file');
            if (fileId) {
                const node = this.nodes.find(n => n.id === parseInt(fileId));
                if (node) this.openFile(node);
            }
        },

        _sortNodes(list) {
            return list.sort((a, b) => {
                if (a.type !== b.type) return a.type === 'folder' ? -1 : 1;
                return a.name.localeCompare(b.name);
            });
        },

        _renderTree() {
            const container = document.getElementById('filetree');
            if (!container) return;
            container.innerHTML = '';
            if (this.nodes.length === 0) {
                container.innerHTML = '<p class="px-3 py-4 text-center text-xs text-gray-400">Geen bestanden</p>';
                return;
            }
            const roots = this._sortNodes(this.nodes.filter(n => !n.parent_id));
            this._renderNodes(container, roots, 0);
        },

        _renderNodes(parent, nodes, depth) {
            for (const node of nodes) {
                if (node.type === 'folder') {
                    const wrapper = document.createElement('div');
                    const btn = document.createElement('button');
                    btn.className = 'flex w-full items-center gap-1.5 rounded px-2 py-1 text-left text-sm hover:bg-gray-200';
                    btn.style.paddingLeft = (depth * 12 + 8) + 'px';
                    btn.innerHTML = `<span class="text-yellow-500">📂</span><span class="truncate text-gray-700">${this._esc(node.name)}</span>`;
                    const children = document.createElement('div');
                    const childNodes = this._sortNodes(this.nodes.filter(n => n.parent_id === node.id));
                    this._renderNodes(children, childNodes, depth + 1);
                    let open = true;
                    btn.addEventListener('click', () => {
                        open = !open;
                        children.style.display = open ? '' : 'none';
                        btn.querySelector('.text-yellow-500').textContent = open ? '📂' : '📁';
                    });
                    wrapper.append(btn, children);
                    parent.appendChild(wrapper);
                } else {
                    const btn = document.createElement('button');
                    btn.className = 'flex w-full items-center gap-1.5 rounded px-2 py-1 text-left text-sm hover:bg-gray-200';
                    btn.style.paddingLeft = (depth * 12 + 8) + 'px';
                    btn.setAttribute('data-node-id', node.id);
                    btn.innerHTML = `<span class="text-gray-400">📄</span><span class="truncate text-gray-700">${this._esc(node.name)}</span>`;
                    btn.addEventListener('click', () => this.openFile(node));
                    parent.appendChild(btn);
                }
            }
        },

        _esc(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        },

        async openFile(node) {
            if (node.type !== 'file') return;
            const data = await api(`/api/editor/${this.projectId}/nodes/${node.id}`);
            this.activeNode = data;
            this.pdfUrl = null;
            this.compileOutput = '';
            this.rOutput = [];
            this.rPlots = [];
            this.rVars = [];
            this._initEditor(data.content ?? '', data.name);
        },

        _initEditor(content, filename) {
            if (this.editorView) {
                this.editorView.destroy();
            }

            const self = this;
            const extensions = [
                basicSetup,
                getLanguage(filename),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        self._scheduleAutoSave();
                    }
                }),
                EditorView.theme({
                    '&': { height: '100%' },
                    '.cm-scroller': { overflow: 'auto' },
                }),
            ];

            if (document.documentElement.classList.contains('dark')) {
                extensions.push(oneDark);
            }

            this.editorView = new EditorView({
                state: EditorState.create({ doc: content, extensions }),
                parent: this.$refs.editorContainer,
            });
        },

        _scheduleAutoSave() {
            clearTimeout(this._saveTimeout);
            this.saved = false;
            this._saveTimeout = setTimeout(() => this._save(), 800);
        },

        async _save() {
            if (!this.activeNode || !this.editorView) return;
            this.saving = true;
            try {
                await api(`/api/editor/${this.projectId}/nodes/${this.activeNode.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({ content: this.editorView.state.doc.toString() }),
                });
                this.saved = true;
                setTimeout(() => { this.saved = false; }, 2000);
            } finally {
                this.saving = false;
            }
        },

        async createItem(type) {
            const name = prompt(type === 'file' ? 'Bestandsnaam:' : 'Mapnaam:');
            if (!name?.trim()) return;
            const node = await api(`/api/editor/${this.projectId}/nodes`, {
                method: 'POST',
                body: JSON.stringify({ name: name.trim(), type, parent_id: null }),
            });
            this.nodes.push(node);
            if (type === 'file') this.openFile(node);
        },

        compiling: false,
        compileOutput: '',
        rOutput: [],

        async compile() {
            if (!this.activeNode) return;
            await this._save();
            this.compiling = true;
            this.compileOutput = '';
            try {
                const res = await api(`/api/editor/${this.projectId}/nodes/${this.activeNode.id}/compile`, {
                    method: 'POST',
                    body: JSON.stringify({ compiler: this.compiler }),
                });
                this.compileOutput = res.output ?? '';
                if (res.pdf_url) {
                    this.pdfUrl = res.pdf_url + '?t=' + Date.now();
                }
            } catch (e) {
                this.compileOutput = 'Compilatie mislukt: ' + e.message;
            } finally {
                this.compiling = false;
            }
        },

        async executeR() {
            if (!this.activeNode || !this.editorView) return;
            const sel = this.editorView.state.selection.main;
            let code;
            if (sel.from !== sel.to) {
                code = this.editorView.state.sliceDoc(sel.from, sel.to);
            } else {
                const line = this.editorView.state.doc.lineAt(sel.head);
                code = line.text;
            }
            try {
                const res = await api(`/api/editor/${this.projectId}/nodes/${this.activeNode.id}/execute-r`, {
                    method: 'POST',
                    body: JSON.stringify({ code }),
                });
                if (res.output) {
                    this.rOutput = [...this.rOutput, ...res.output];
                }
                if (res.variables) {
                    this.rVars = res.variables;
                }
                if (res.plots?.length) {
                    this.rPlots = [...this.rPlots, ...res.plots];
                }
            } catch (e) {
                this.rOutput = [...this.rOutput, { type: 'error', text: e.message }];
            }
        },

        isCompilable,
        isExecutable,
    };
};

// Start Alpine
window.Alpine = Alpine;
Alpine.start();
