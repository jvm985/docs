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

window.editorApp = function (projectId, isOwner) {
    return {
        projectId,
        isOwner,
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

            // Drop naar root (buiten mappen)
            container.addEventListener('dragover', (e) => e.preventDefault());
            container.addEventListener('drop', (e) => {
                e.preventDefault();
                const draggedId = parseInt(e.dataTransfer.getData('nodeId'));
                if (draggedId) this._moveNode(draggedId, null);
            });
            if (this.nodes.length === 0) {
                container.innerHTML = '<p class="px-3 py-4 text-center text-xs text-gray-400">Geen bestanden</p>';
                return;
            }
            const roots = this._sortNodes(this.nodes.filter(n => !n.parent_id));
            this._renderNodes(container, roots, 0);
        },

        _renderNodes(parent, nodes, depth) {
            const projectId = this.projectId;
            for (const node of nodes) {
                const btn = document.createElement('button');
                btn.className = 'flex w-full items-center gap-1.5 rounded px-2 py-1 text-left text-sm hover:bg-gray-200';
                btn.style.paddingLeft = (depth * 12 + 8) + 'px';
                btn.setAttribute('draggable', 'true');
                btn.setAttribute('data-node-id', node.id);

                if (node.type === 'folder') {
                    const wrapper = document.createElement('div');
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

                    // Drop target voor mappen
                    btn.addEventListener('dragover', (e) => { e.preventDefault(); btn.classList.add('bg-amber-100'); });
                    btn.addEventListener('dragleave', () => btn.classList.remove('bg-amber-100'));
                    btn.addEventListener('drop', (e) => {
                        e.preventDefault();
                        btn.classList.remove('bg-amber-100');
                        const draggedId = parseInt(e.dataTransfer.getData('nodeId'));
                        if (draggedId && draggedId !== node.id) this._moveNode(draggedId, node.id);
                    });

                    wrapper.append(btn, children);
                    parent.appendChild(wrapper);
                } else {
                    btn.innerHTML = `<span class="text-gray-400">📄</span><span class="truncate text-gray-700">${this._esc(node.name)}</span>`;
                    btn.addEventListener('click', () => {
                        window.location.href = `/editor/${projectId}?file=${node.id}`;
                    });
                    parent.appendChild(btn);
                }

                // Drag start
                btn.addEventListener('dragstart', (e) => {
                    e.dataTransfer.setData('nodeId', node.id.toString());
                });

                // Context menu (rechtermuisklik)
                btn.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    this._showContextMenu(e.clientX, e.clientY, node);
                });
            }
        },

        _esc(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        },

        _showContextMenu(x, y, node) {
            // Verwijder bestaand menu
            document.getElementById('ctx-menu')?.remove();

            const menu = document.createElement('div');
            menu.id = 'ctx-menu';
            menu.className = 'fixed z-50 min-w-36 rounded-lg border bg-white py-1 shadow-lg text-sm';
            menu.style.cssText = `top:${y}px;left:${x}px`;

            const items = this.isOwner ? [
                { label: 'Hernoemen', action: () => this._renameNode(node) },
                { label: 'Kopiëren', action: () => this._duplicateNode(node) },
                { label: 'Verwijderen', action: () => this._deleteNode(node), cls: 'text-red-500' },
            ] : [
                { label: 'Kopieer naar mijn project...', action: () => this._copyToMyProject(node) },
            ];

            for (const item of items) {
                const btn = document.createElement('button');
                btn.className = `w-full px-3 py-1.5 text-left hover:bg-gray-100 ${item.cls || ''}`;
                btn.textContent = item.label;
                btn.addEventListener('click', () => { menu.remove(); item.action(); });
                menu.appendChild(btn);
            }

            document.body.appendChild(menu);
            const close = () => { menu.remove(); document.removeEventListener('click', close); };
            setTimeout(() => document.addEventListener('click', close), 0);
        },

        async _renameNode(node) {
            const name = prompt('Nieuwe naam:', node.name);
            if (!name?.trim() || name.trim() === node.name) return;
            await api(`/api/editor/${this.projectId}/nodes/${node.id}/rename`, {
                method: 'PATCH',
                body: JSON.stringify({ name: name.trim() }),
            });
            window.location.reload();
        },

        async _deleteNode(node) {
            if (!confirm(`'${node.name}' verwijderen?`)) return;
            await api(`/api/editor/${this.projectId}/nodes/${node.id}`, { method: 'DELETE' });
            window.location.reload();
        },

        async _moveNode(nodeId, newParentId) {
            await api(`/api/editor/${this.projectId}/nodes/${nodeId}/move`, {
                method: 'PATCH',
                body: JSON.stringify({ parent_id: newParentId }),
            });
            window.location.reload();
        },

        async _copyToMyProject(node) {
            // Haal eigen projecten op
            const res = await fetch('/api/my-projects', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const projects = await res.json();

            if (projects.length === 0) {
                alert('Je hebt nog geen eigen projecten. Maak er eerst één aan.');
                return;
            }

            let targetId;
            if (projects.length === 1) {
                targetId = projects[0].id;
            } else {
                const names = projects.map((p, i) => `${i + 1}. ${p.name}`).join('\n');
                const choice = prompt(`Naar welk project?\n${names}\n\nTyp het nummer:`);
                if (!choice) return;
                const idx = parseInt(choice) - 1;
                if (idx < 0 || idx >= projects.length) return;
                targetId = projects[idx].id;
            }

            await api(`/api/editor/${this.projectId}/copy-nodes`, {
                method: 'POST',
                body: JSON.stringify({ node_ids: [node.id], target_project_id: targetId }),
            });

            alert(`'${node.name}' gekopieerd naar je project.`);
        },

        async _duplicateNode(node) {
            const data = await api(`/api/editor/${this.projectId}/nodes/${node.id}`);
            await api(`/api/editor/${this.projectId}/nodes`, {
                method: 'POST',
                body: JSON.stringify({
                    name: node.name.replace(/(\.[^.]+)$/, ' (kopie)$1'),
                    type: node.type,
                    parent_id: node.parent_id,
                    content: data.content,
                }),
            });
            window.location.reload();
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
                parent: document.getElementById('codemirror-container'),
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
            if (type === 'file') {
                window.location.href = `/editor/${this.projectId}?file=${node.id}`;
            } else {
                window.location.reload();
            }
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

        // Upload
        async uploadFiles(input) {
            const files = Array.from(input.files);
            if (!files.length) return;

            const fileData = [];
            for (const file of files) {
                const path = file.webkitRelativePath || file.name;
                const content = await file.text();
                fileData.push({ name: file.name, path, content });
            }

            const res = await api(`/api/editor/${this.projectId}/upload`, {
                method: 'POST',
                body: JSON.stringify({ files: fileData }),
            });

            this.nodes = res.nodes;
            this._renderTree();
            input.value = '';
        },

        clearOutput() {
            this.rOutput = [];
            this.compileOutput = '';
        },

        clearPlots() {
            this.rPlots = [];
        },

        isCompilable,
        isExecutable,
    };
};

// ─── Resizable panels ───────────────────────────────────────────────────────

window.resizablePanels = function () {
    return {
        leftW: 240,
        rightW: 320,
        _resizing: null,
        _startX: 0,
        _startW: 0,

        startResize(side, e) {
            e.preventDefault();
            this._resizing = side;
            this._startX = e.clientX;
            this._startW = side === 'left' ? this.leftW : this.rightW;
            e.target.classList.add('active');
            const handle = e.target;

            const onMove = (ev) => {
                const dx = ev.clientX - this._startX;
                if (this._resizing === 'left') {
                    this.leftW = Math.max(120, Math.min(500, this._startW + dx));
                } else {
                    this.rightW = Math.max(150, Math.min(600, this._startW - dx));
                }
            };
            const onUp = () => {
                handle.classList.remove('active');
                this._resizing = null;
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },

        startResizeH(e, container, setter) {
            e.preventDefault();
            const startY = e.clientY;
            const totalH = container.offsetHeight;
            const handle = e.target;
            handle.classList.add('active');

            // Get current percentage from current top panel height
            const topPanel = container.children[0];
            const startPct = (topPanel.offsetHeight / totalH) * 100;

            const onMove = (ev) => {
                const dy = ev.clientY - startY;
                const newPct = startPct + (dy / totalH) * 100;
                setter(Math.max(15, Math.min(85, newPct)));
            };
            const onUp = () => {
                handle.classList.remove('active');
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        },
    };
};

// Globale upload handler (buiten Alpine om async/proxy problemen te vermijden)
window._handleUpload = async function (input) {
    const files = Array.from(input.files);
    if (!files.length) return;

    const projectId = window.location.pathname.split('/')[2];
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const formData = new FormData();

    files.forEach((file, i) => {
        formData.append(`files[${i}]`, file);
        formData.append(`paths[${i}]`, file.webkitRelativePath || file.name);
    });

    try {
        const res = await fetch(`/api/editor/${projectId}/upload`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: formData,
        });
        if (!res.ok) throw new Error(`Upload mislukt (${res.status})`);
    } catch (e) {
        alert(e.message);
    }

    input.value = '';
    window.location.reload();
};

// Start Alpine
window.Alpine = Alpine;
Alpine.start();
