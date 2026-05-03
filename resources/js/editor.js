import { EditorView, keymap, lineNumbers, highlightActiveLine } from '@codemirror/view';
import { EditorState, Compartment } from '@codemirror/state';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { syntaxHighlighting, defaultHighlightStyle, bracketMatching, foldGutter } from '@codemirror/language';
import { searchKeymap } from '@codemirror/search';
import { autocompletion, completionKeymap } from '@codemirror/autocomplete';
import { markdown } from '@codemirror/lang-markdown';
import { json } from '@codemirror/lang-json';
import { xml } from '@codemirror/lang-xml';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { javascript } from '@codemirror/lang-javascript';
import { php } from '@codemirror/lang-php';
import { python } from '@codemirror/lang-python';

const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';

const app = document.getElementById('app');
const PROJECT_ID = Number(app.dataset.projectId);
const CAN_WRITE = app.dataset.canWrite === '1';

const COMPILABLE = ['tex', 'md', 'rmd', 'typ'];
const RUNNABLE = ['r'];

const state = {
    tree: [],
    expanded: new Set(),
    activePath: null,
    activeFile: null,
    compiler: app.dataset.compiler || 'pdflatex',
    primaryFile: app.dataset.primaryFile || null,
    rOutput: [],
    rVars: [],
    rPlots: [],
    plotIndex: 0,
};

let editorView = null;
const langCompartment = new Compartment();
const readOnlyCompartment = new Compartment();
let saveTimer = null;
let currentLoadToken = 0;

const langFor = (ext) => {
    switch (ext) {
        case 'md': case 'rmd': return markdown();
        case 'json': return json();
        case 'xml': return xml();
        case 'html': case 'htm': return html();
        case 'css': return css();
        case 'js': case 'mjs': case 'ts': return javascript();
        case 'php': return php();
        case 'py': return python();
        default: return [];
    }
};

const api = async (method, url, body) => {
    const opts = {
        method,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
        },
        credentials: 'same-origin',
    };
    if (body instanceof FormData) {
        opts.body = body;
    } else if (body !== undefined) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    }
    const res = await fetch(url, opts);
    if (!res.ok) {
        const text = await res.text();
        throw new Error(`${res.status}: ${text.slice(0, 300)}`);
    }
    const ct = res.headers.get('content-type') || '';
    return ct.includes('json') ? res.json() : res.text();
};

const apiUrl = (suffix) => `/api/projects/${PROJECT_ID}${suffix}`;

const treeEl = document.getElementById('filetree');

function renderTree() {
    treeEl.innerHTML = '';
    treeEl.appendChild(renderTreeChildren(state.tree));
    bindRootDropZone();
}

function renderTreeChildren(nodes) {
    const ul = document.createElement('ul');
    ul.className = 'pl-3';
    for (const node of nodes) ul.appendChild(renderTreeNode(node));
    return ul;
}

function renderTreeNode(node) {
    const li = document.createElement('li');
    li.className = 'mb-0.5';

    const row = document.createElement('div');
    row.className = `filetree-row group flex cursor-pointer items-center gap-1 rounded px-1 py-0.5 hover:bg-gray-200 ${state.activePath === node.path ? 'bg-amber-100' : ''}`;
    row.draggable = CAN_WRITE;
    row.dataset.path = node.path;
    row.dataset.type = node.type;

    if (node.type === 'folder') {
        const open = state.expanded.has(node.path);
        const chev = document.createElement('span');
        chev.className = 'inline-block w-3 text-gray-400';
        chev.textContent = open ? '▾' : '▸';
        row.append(chev, icon('folder'));
        const label = document.createElement('span');
        label.className = 'flex-1 truncate';
        label.textContent = node.name;
        row.appendChild(label);
        if (node.is_linked) {
            const link = document.createElement('span');
            link.className = 'ml-1 text-sky-500';
            link.textContent = '🔗';
            link.title = 'Bevat gelinkte bestanden';
            row.appendChild(link);
        }
        row.addEventListener('click', () => toggleFolder(node.path));
        row.addEventListener('contextmenu', (e) => showContextMenu(e, node));
        bindDropTarget(row, node.path);
        bindDragSource(row, node.path);
        li.appendChild(row);
        if (open) li.appendChild(renderTreeChildren(node.children || []));
    } else {
        const spacer = document.createElement('span');
        spacer.className = 'inline-block w-3';
        row.append(spacer, icon('file', node.extension));
        const label = document.createElement('span');
        label.className = 'flex-1 truncate';
        label.textContent = node.name;
        label.dataset.testid = 'file-label';
        row.appendChild(label);
        if (state.primaryFile === node.path) {
            const star = document.createElement('span');
            star.className = 'ml-1 text-amber-500';
            star.textContent = '★';
            star.title = 'Primair bestand: dit wordt gecompileerd';
            row.appendChild(star);
        }
        if (node.is_linked) {
            const link = document.createElement('span');
            link.className = 'ml-1 text-sky-500';
            link.textContent = '🔗';
            link.title = 'Gelinkt vanuit een ander project (alleen-lezen)';
            row.appendChild(link);
        }
        row.addEventListener('click', () => openFile(node.path));
        row.addEventListener('contextmenu', (e) => showContextMenu(e, node));
        bindDragSource(row, node.path);
        li.appendChild(row);
    }
    return li;
}

function icon(type, ext) {
    const s = document.createElement('span');
    s.className = 'inline-block w-4 text-center text-xs';
    if (type === 'folder') s.textContent = '📁';
    else {
        const map = { tex: '📄', md: '📝', r: 'R', rmd: 'R', typ: '📐', json: '{}', xml: '<>', pdf: '📕' };
        s.textContent = map[ext] || '·';
    }
    return s;
}

function toggleFolder(path) {
    if (state.expanded.has(path)) state.expanded.delete(path);
    else state.expanded.add(path);
    renderTree();
}

let dragSourcePath = null;

function bindDragSource(row, path) {
    if (!CAN_WRITE) return;
    row.addEventListener('dragstart', (e) => {
        dragSourcePath = path;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', path);
    });
    row.addEventListener('dragend', () => {
        dragSourcePath = null;
        document.querySelectorAll('.filetree-row.drop-target').forEach(el => el.classList.remove('drop-target'));
    });
}

function bindDropTarget(row, folderPath) {
    if (!CAN_WRITE) return;
    row.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = dragSourcePath ? 'move' : 'copy';
        row.classList.add('drop-target');
    });
    row.addEventListener('dragleave', () => row.classList.remove('drop-target'));
    row.addEventListener('drop', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        row.classList.remove('drop-target');
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            await uploadFiles(e.dataTransfer.files, folderPath);
            return;
        }
        if (dragSourcePath && dragSourcePath !== folderPath) {
            await moveNode(dragSourcePath, folderPath);
        }
    });
}

function bindRootDropZone() {
    treeEl.ondragover = (e) => { e.preventDefault(); e.dataTransfer.dropEffect = dragSourcePath ? 'move' : 'copy'; };
    treeEl.ondrop = async (e) => {
        if (e.target !== treeEl) return;
        e.preventDefault();
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            await uploadFiles(e.dataTransfer.files, '');
        } else if (dragSourcePath) {
            await moveNode(dragSourcePath, '');
        }
    };
}

async function loadTree(preservedPath) {
    const data = await api('GET', apiUrl('/tree'));
    state.tree = data.tree;
    renderTree();
    if (preservedPath) state.activePath = preservedPath;
}

async function openFile(path) {
    const token = ++currentLoadToken;
    state.activePath = path;
    renderTree();
    const data = await api('GET', apiUrl(`/file?path=${encodeURIComponent(path)}`));
    if (token !== currentLoadToken) return;

    state.activeFile = { path, ...data };
    document.getElementById('active-file-name').textContent = data.name;
    document.getElementById('save-indicator').classList.add('hidden');
    document.getElementById('saved-indicator').classList.add('hidden');
    const editable = CAN_WRITE && !data.is_linked;
    const hint = document.getElementById('readonly-hint');
    hint.classList.toggle('hidden', editable);
    hint.textContent = data.is_linked ? '— gelinkt, alleen-lezen' : '— alleen lezen';

    document.getElementById('editor-empty').classList.add('hidden');
    document.getElementById('editor-mount').classList.add('hidden');
    document.getElementById('image-viewer').classList.add('hidden');
    document.getElementById('binary-notice').classList.add('hidden');

    if (data.kind === 'text') {
        document.getElementById('editor-mount').classList.remove('hidden');
        showEditor(data.content, data.extension);
    } else if (data.kind === 'viewable' && data.extension === 'pdf') {
        const pdf = document.getElementById('pdf-frame');
        pdf.src = data.url;
        pdf.classList.remove('hidden');
        document.getElementById('output-empty').classList.add('hidden');
    } else if (data.kind === 'viewable') {
        const v = document.getElementById('image-viewer');
        v.innerHTML = `<img src="${data.url}" class="mx-auto max-w-full">`;
        v.classList.remove('hidden');
    } else {
        const b = document.getElementById('binary-notice');
        b.classList.remove('hidden');
        b.textContent = `Binair bestand (${formatSize(data.size || 0)}) — niet bewerkbaar.`;
    }

    renderToolbar();
    await loadLastCompileLog();
}

function formatSize(n) {
    if (n < 1024) return `${n} B`;
    if (n < 1024*1024) return `${(n/1024).toFixed(1)} KB`;
    return `${(n/(1024*1024)).toFixed(1)} MB`;
}

function isCurrentEditable() {
    return CAN_WRITE && !(state.activeFile && state.activeFile.is_linked);
}

function showEditor(content, ext) {
    const mount = document.getElementById('editor-mount');
    const editable = isCurrentEditable();
    if (editorView) {
        editorView.dispatch({
            changes: { from: 0, to: editorView.state.doc.length, insert: content },
            effects: [
                langCompartment.reconfigure(langFor(ext)),
                readOnlyCompartment.reconfigure(EditorState.readOnly.of(!editable)),
            ],
        });
        return;
    }
    const startState = EditorState.create({
        doc: content,
        extensions: [
            lineNumbers(),
            history(),
            foldGutter(),
            bracketMatching(),
            highlightActiveLine(),
            syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
            autocompletion(),
            keymap.of([
                ...defaultKeymap,
                ...historyKeymap,
                ...searchKeymap,
                ...completionKeymap,
                indentWithTab,
                { key: 'Mod-Enter', preventDefault: true, run: () => { runCompileOrR(); return true; } },
                { key: 'Mod-s',     preventDefault: true, run: () => { saveImmediately(); return true; } },
            ]),
            EditorView.updateListener.of(update => {
                if (update.docChanged && isCurrentEditable()) scheduleSave();
            }),
            langCompartment.of(langFor(ext)),
            readOnlyCompartment.of(EditorState.readOnly.of(!editable)),
        ],
    });
    editorView = new EditorView({ state: startState, parent: mount });
}

function scheduleSave() {
    document.getElementById('saved-indicator').classList.add('hidden');
    document.getElementById('save-indicator').classList.remove('hidden');
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveImmediately, 600);
}

async function saveImmediately() {
    if (!editorView || !state.activeFile || !isCurrentEditable()) return;
    const content = editorView.state.doc.toString();
    try {
        await api('PUT', apiUrl('/file'), { path: state.activeFile.path, content });
        document.getElementById('save-indicator').classList.add('hidden');
        document.getElementById('saved-indicator').classList.remove('hidden');
    } catch (e) {
        console.error(e);
        document.getElementById('save-indicator').textContent = 'opslaan mislukt';
    }
}

const toolbar = document.getElementById('toolbar');

function renderToolbar() {
    toolbar.innerHTML = '';
    if (!state.activeFile) return;
    const ext = state.activeFile.extension;
    const editable = isCurrentEditable();

    if (state.activeFile.is_linked && CAN_WRITE) {
        const refresh = document.createElement('button');
        refresh.className = 'rounded border border-sky-300 bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700 hover:bg-sky-100';
        refresh.textContent = '↻ Refresh';
        refresh.title = 'Bestand opnieuw kopiëren uit het bronproject';
        refresh.dataset.testid = 'refresh-link-btn';
        refresh.addEventListener('click', refreshLink);
        toolbar.appendChild(refresh);
    }

    if (editable && COMPILABLE.includes(ext)) {
        if (ext === 'tex') {
            const sel = document.createElement('select');
            sel.className = 'rounded border border-gray-300 px-2 py-0.5 text-xs';
            for (const c of ['pdflatex','xelatex','lualatex']) {
                const opt = document.createElement('option');
                opt.value = c; opt.textContent = c;
                if (c === state.compiler) opt.selected = true;
                sel.appendChild(opt);
            }
            sel.addEventListener('change', async () => {
                state.compiler = sel.value;
                if (CAN_WRITE) {
                    try { await api('PATCH', apiUrl('/settings'), { compiler: sel.value }); } catch (e) {}
                }
            });
            toolbar.appendChild(sel);
        }
        const btn = document.createElement('button');
        btn.className = 'rounded bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600';
        btn.textContent = 'Compileren';
        btn.dataset.testid = 'compile-btn';
        btn.addEventListener('click', () => compile(false));
        toolbar.appendChild(btn);

        if (ext === 'tex') {
            const cleanBtn = document.createElement('button');
            cleanBtn.className = 'rounded border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:bg-gray-100';
            cleanBtn.textContent = 'Schoon';
            cleanBtn.title = 'Verwijder oude .aux/.toc/.log en compileer opnieuw';
            cleanBtn.dataset.testid = 'clean-compile-btn';
            cleanBtn.addEventListener('click', () => compile(true));
            toolbar.appendChild(cleanBtn);
        }

        const logBtn = document.createElement('button');
        logBtn.className = 'rounded border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:bg-gray-100';
        logBtn.textContent = 'Log';
        logBtn.addEventListener('click', toggleCompileLog);
        toolbar.appendChild(logBtn);
    } else if (editable && RUNNABLE.includes(ext)) {
        const runBtn = document.createElement('button');
        runBtn.className = 'rounded bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600';
        runBtn.textContent = 'Uitvoeren';
        runBtn.dataset.testid = 'run-btn';
        runBtn.addEventListener('click', () => runR());
        toolbar.appendChild(runBtn);
    } else if (state.activeFile.is_linked && CAN_WRITE && RUNNABLE.includes(ext)) {
        const note = document.createElement('span');
        note.className = 'text-xs text-gray-400';
        note.textContent = 'Gelinkt bestand kan niet uitgevoerd worden — refresh of kopieer naar dit project';
        toolbar.appendChild(note);
    }
}

function renderFiletreeActions() {
    const host = document.getElementById('filetree-actions');
    if (!host) return;
    host.innerHTML = '';
    const make = (label, title, onClick) => {
        const b = document.createElement('button');
        b.className = 'rounded p-1 text-xs text-gray-400 hover:bg-gray-200 hover:text-gray-700';
        b.title = title;
        b.textContent = label;
        b.addEventListener('click', onClick);
        return b;
    };
    host.appendChild(make('+F', 'Nieuw bestand', () => createInteractive('file')));
    host.appendChild(make('+M', 'Nieuwe map', () => createInteractive('folder')));
    host.appendChild(make('⤴', 'Toevoegen…', (e) => openImportMenu(e.currentTarget)));
}

function openImportMenu(anchor) {
    closeImportMenu();
    const rect = anchor.getBoundingClientRect();
    const menu = document.createElement('div');
    menu.className = 'fixed z-50 min-w-56 rounded border border-gray-200 bg-white py-1 text-xs shadow-lg';
    menu.style.top = (rect.bottom + 4) + 'px';
    menu.style.left = rect.left + 'px';
    menu.dataset.testid = 'import-menu';

    const item = (label, onClick) => {
        const b = document.createElement('button');
        b.className = 'block w-full px-3 py-1.5 text-left hover:bg-amber-50';
        b.textContent = label;
        b.addEventListener('click', () => { closeImportMenu(); onClick(); });
        menu.appendChild(b);
    };

    item('📄 Bestanden van schijf…', () => triggerFilePicker(false));
    item('📁 Map van schijf…', () => triggerFilePicker(true));
    item('🔗 Importeer uit een ander project…', () => openProjectBrowser());

    document.body.appendChild(menu);
    importMenuEl = menu;
    setTimeout(() => document.addEventListener('click', closeImportMenu, { once: true }), 0);
}

let importMenuEl = null;
function closeImportMenu() {
    if (importMenuEl) { importMenuEl.remove(); importMenuEl = null; }
}

function triggerFilePicker(folder) {
    const inp = document.createElement('input');
    inp.type = 'file';
    inp.multiple = true;
    if (folder) inp.webkitdirectory = true;
    inp.style.display = 'none';
    document.body.appendChild(inp);
    inp.addEventListener('change', () => {
        uploadFiles(inp.files, '').finally(() => inp.remove());
    });
    inp.click();
}

async function createInteractive(type) {
    const name = prompt(type === 'folder' ? 'Naam van de nieuwe map:' : 'Naam van het nieuwe bestand:');
    if (!name) return;
    try {
        await api('POST', apiUrl('/file'), { path: name, type });
        await loadTree(state.activePath);
    } catch (e) { alert('Kan niet aanmaken: ' + e.message); }
}

async function uploadFiles(files, folderPath) {
    if (!CAN_WRITE || !files || !files.length) return;
    const fd = new FormData();
    fd.append('folder', folderPath || '');
    for (let i = 0; i < files.length; i++) {
        const f = files[i];
        fd.append('files[]', f);
        const rel = f.webkitRelativePath || f.name;
        fd.append('paths[]', rel);
    }
    try {
        await api('POST', apiUrl('/upload'), fd);
        await loadTree(state.activePath);
    } catch (e) { alert('Upload mislukt: ' + e.message); }
}

async function moveNode(path, newParent) {
    try {
        await api('PATCH', apiUrl('/file/move'), { path, parent: newParent });
        await loadTree(state.activePath);
    } catch (e) { alert('Verplaatsen mislukt: ' + e.message); }
}

async function renamePath(path) {
    const oldName = path.split('/').pop();
    const newName = prompt('Nieuwe naam:', oldName);
    if (!newName || newName === oldName) return;
    try {
        const res = await api('PATCH', apiUrl('/file/rename'), { path, name: newName });
        if (state.activePath === path) state.activePath = res.path;
        await loadTree(state.activePath);
    } catch (e) { alert('Hernoemen mislukt: ' + e.message); }
}

async function deletePath(path) {
    if (!confirm(`Verwijder ${path}?`)) return;
    try {
        await api('DELETE', apiUrl('/file'), { path });
        if (state.activePath === path) {
            state.activePath = null;
            state.activeFile = null;
            document.getElementById('active-file-name').textContent = 'Selecteer een bestand';
            document.getElementById('editor-empty').classList.remove('hidden');
            document.getElementById('editor-mount').classList.add('hidden');
        }
        await loadTree(state.activePath);
    } catch (e) { alert('Verwijderen mislukt: ' + e.message); }
}

let contextMenuEl = null;
function showContextMenu(e, node) {
    e.preventDefault();
    closeContextMenu();
    if (!CAN_WRITE) return;
    const menu = document.createElement('div');
    menu.className = 'fixed z-50 min-w-32 rounded border border-gray-200 bg-white py-1 text-xs shadow-lg';
    menu.style.left = e.clientX + 'px';
    menu.style.top = e.clientY + 'px';
    const item = (label, fn) => {
        const b = document.createElement('button');
        b.className = 'block w-full px-3 py-1 text-left hover:bg-amber-50';
        b.textContent = label;
        b.addEventListener('click', () => { closeContextMenu(); fn(); });
        menu.appendChild(b);
    };
    item('Hernoem', () => renamePath(node.path));
    item('Verwijder', () => deletePath(node.path));
    if (node.type === 'file' && COMPILABLE.includes(node.extension)) {
        const isPrimary = state.primaryFile === node.path;
        item(isPrimary ? '★ Niet langer primair' : '☆ Maak primair', () => togglePrimaryFile(node.path));
    }
    document.body.appendChild(menu);
    contextMenuEl = menu;
    setTimeout(() => document.addEventListener('click', closeContextMenu, { once: true }), 0);
}
function closeContextMenu() {
    if (contextMenuEl) { contextMenuEl.remove(); contextMenuEl = null; }
}

async function togglePrimaryFile(path) {
    const next = state.primaryFile === path ? null : path;
    try {
        const res = await api('PATCH', apiUrl('/settings'), { primary_file: next ?? '' });
        state.primaryFile = res.primary_file;
        await loadTree(state.activePath);
    } catch (e) {
        alert('Kon primair bestand niet opslaan: ' + e.message);
    }
}

async function runCompileOrR() {
    if (!state.activeFile) return;
    if (COMPILABLE.includes(state.activeFile.extension)) await compile(false);
    else if (RUNNABLE.includes(state.activeFile.extension)) await runR();
}

async function compile(clean = false) {
    if (!state.activeFile) return;
    let path = state.activeFile.path;
    let ext = state.activeFile.extension;
    if (state.primaryFile) {
        path = state.primaryFile;
        ext = path.split('.').pop().toLowerCase();
    }
    if (!COMPILABLE.includes(ext)) return;
    await saveImmediately();
    document.getElementById('compile-status').textContent = clean ? 'schoon compileren…' : 'bezig…';
    try {
        const res = await api('POST', apiUrl('/compile'), {
            path,
            compiler: ext === 'tex' ? state.compiler : null,
            clean,
        });
        document.getElementById('compile-status').textContent = res.status === 'success' ? 'klaar' : 'fout';
        showCompileOutput(res);
    } catch (e) {
        document.getElementById('compile-status').textContent = 'fout';
        showCompileOutput({ status: 'failed', log: String(e.message), pdf_url: null });
    }
}

async function openProjectBrowser() {
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-6';
    overlay.dataset.testid = 'project-browser';
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

    const panel = document.createElement('div');
    panel.className = 'flex h-[80vh] w-full max-w-3xl flex-col rounded-lg bg-white shadow-2xl';
    overlay.appendChild(panel);

    const head = document.createElement('div');
    head.className = 'flex items-center justify-between border-b px-4 py-2';
    head.innerHTML = `<h3 class="text-sm font-semibold">Importeer uit een ander project</h3>`;
    const close = document.createElement('button');
    close.className = 'text-gray-400 hover:text-red-500';
    close.textContent = '✕';
    close.addEventListener('click', () => overlay.remove());
    head.appendChild(close);
    panel.appendChild(head);

    const body = document.createElement('div');
    body.className = 'flex flex-1 overflow-hidden';
    panel.appendChild(body);

    const left = document.createElement('div');
    left.className = 'w-1/3 overflow-y-auto border-r bg-gray-50 text-xs';
    const right = document.createElement('div');
    right.className = 'flex flex-1 flex-col overflow-hidden';
    body.append(left, right);

    const treeWrap = document.createElement('div');
    treeWrap.className = 'flex-1 overflow-y-auto p-2 text-xs';
    right.appendChild(treeWrap);

    const footer = document.createElement('div');
    footer.className = 'flex items-center justify-between gap-2 border-t bg-gray-50 px-4 py-2 text-xs';
    const status = document.createElement('span');
    status.className = 'text-gray-500';
    const actions = document.createElement('div');
    actions.className = 'flex items-center gap-3';
    const importBtn = document.createElement('button');
    importBtn.className = 'rounded bg-amber-500 px-3 py-1 font-medium text-white hover:bg-amber-600 disabled:opacity-50';
    importBtn.textContent = 'Importeer';
    importBtn.disabled = true;
    importBtn.dataset.testid = 'import-confirm';
    actions.append(importBtn);
    footer.append(status, actions);
    right.appendChild(footer);

    let selectedProject = null;
    let selectedPath = null;

    importBtn.addEventListener('click', async () => {
        if (!selectedProject || selectedPath === null) return;
        importBtn.disabled = true;
        status.textContent = 'Bezig met importeren…';
        try {
            await api('POST', apiUrl('/import'), {
                source_project_id: selectedProject.id,
                source_path: selectedPath,
                target_parent: '',
                mode: 'copy',
            });
            overlay.remove();
            await loadTree(state.activePath);
        } catch (e) {
            status.textContent = 'Fout: ' + e.message;
            importBtn.disabled = false;
        }
    });

    document.body.appendChild(overlay);

    try {
        const list = await api('GET', '/api/accessible-projects');
        const projects = list.projects.filter(p => p.id !== PROJECT_ID);
        if (!projects.length) {
            left.innerHTML = '<p class="p-3 text-gray-400">Geen toegankelijke projecten.</p>';
            return;
        }
        left.innerHTML = '';
        for (const p of projects) {
            const row = document.createElement('button');
            row.className = 'block w-full border-b px-3 py-2 text-left hover:bg-amber-50';
            row.innerHTML = `<div class="font-medium">${escapeHtml(p.name)}</div><div class="text-xs text-gray-400">${p.access}</div>`;
            row.addEventListener('click', async () => {
                left.querySelectorAll('button').forEach(b => b.classList.remove('bg-amber-50','font-semibold'));
                row.classList.add('bg-amber-50','font-semibold');
                selectedProject = p;
                selectedPath = null;
                importBtn.disabled = true;
                status.textContent = 'Tree laden…';
                treeWrap.innerHTML = '';
                try {
                    const data = await api('GET', `/api/browse-project/${p.id}`);
                    status.textContent = '';
                    treeWrap.appendChild(renderBrowserTree(data.tree, '', (path) => {
                        selectedPath = path;
                        importBtn.disabled = false;
                        status.textContent = `Geselecteerd: ${path || '(root)'}`;
                    }));
                } catch (e) {
                    status.textContent = 'Fout: ' + e.message;
                }
            });
            left.appendChild(row);
        }
    } catch (e) {
        left.innerHTML = `<p class="p-3 text-red-500">Fout: ${escapeHtml(e.message)}</p>`;
    }
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function renderBrowserTree(nodes, parent, onPick) {
    const ul = document.createElement('ul');
    ul.className = 'pl-3';
    for (const n of nodes) {
        const li = document.createElement('li');
        li.className = 'mb-0.5';
        const row = document.createElement('div');
        row.className = 'flex cursor-pointer items-center gap-1 rounded px-1 py-0.5 hover:bg-amber-50';
        row.dataset.testid = 'browser-row';
        const label = document.createElement('span');
        label.className = 'flex-1 truncate';
        label.textContent = (n.type === 'folder' ? '📁 ' : '· ') + n.name;
        row.appendChild(label);
        let isOpen = false;
        let kids = null;
        row.addEventListener('click', () => {
            ul.querySelectorAll('div.bg-amber-100').forEach(d => d.classList.remove('bg-amber-100'));
            row.classList.add('bg-amber-100');
            onPick(n.path);
            if (n.type === 'folder') {
                if (!kids) {
                    kids = renderBrowserTree(n.children || [], n.path, onPick);
                    li.appendChild(kids);
                    isOpen = true;
                } else {
                    isOpen = !isOpen;
                    kids.style.display = isOpen ? '' : 'none';
                }
            }
        });
        li.appendChild(row);
        ul.appendChild(li);
    }
    return ul;
}

async function refreshLink() {
    if (!state.activeFile || !state.activeFile.is_linked) return;
    try {
        await api('POST', apiUrl('/refresh-link'), { path: state.activeFile.path });
        await openFile(state.activeFile.path);
    } catch (e) { alert('Refresh mislukt: ' + e.message); }
}

async function loadLastCompileLog() {
    const f = state.activeFile;
    if (!f || !COMPILABLE.includes(f.extension)) return;
    try {
        const res = await api('GET', apiUrl(`/compile/log?path=${encodeURIComponent(f.path)}`));
        if (res.pdf_url || res.log) showCompileOutput(res);
    } catch {}
}

function showCompileOutput(res) {
    document.getElementById('output-empty').classList.add('hidden');
    document.getElementById('r-output').classList.add('hidden');
    document.getElementById('r-output').classList.remove('flex');
    const pdf = document.getElementById('pdf-frame');
    const log = document.getElementById('compile-log');
    if (res.pdf_url) {
        pdf.src = res.pdf_url;
        pdf.classList.remove('hidden');
        log.classList.add('hidden');
    } else {
        pdf.classList.add('hidden');
        pdf.src = 'about:blank';
        log.textContent = res.log || 'Geen output';
        log.classList.remove('hidden');
    }
}

function toggleCompileLog() {
    const log = document.getElementById('compile-log');
    const pdf = document.getElementById('pdf-frame');
    if (log.classList.contains('hidden')) {
        pdf.classList.add('hidden');
        log.classList.remove('hidden');
        document.getElementById('output-empty').classList.add('hidden');
    } else if (pdf.src && pdf.src !== 'about:blank') {
        log.classList.add('hidden');
        pdf.classList.remove('hidden');
    }
}

async function runR() {
    if (!editorView || !state.activeFile) return;
    if (state.activeFile.extension !== 'r') return;
    const sel = editorView.state.selection.main;
    let code;
    if (!sel.empty) {
        code = editorView.state.sliceDoc(sel.from, sel.to);
    } else {
        const line = editorView.state.doc.lineAt(sel.head);
        code = line.text;
    }
    if (!code.trim()) return;
    document.getElementById('compile-status').textContent = 'R draait…';
    try {
        const res = await api('POST', apiUrl('/r/execute'), { code, path: state.activeFile.path });
        document.getElementById('compile-status').textContent = '';
        appendROutput(res.output || []);
        state.rVars = res.variables || [];
        state.rPlots = res.plots || [];
        state.plotIndex = 0;
        renderRSidebar();
        showROutput();
    } catch (e) {
        document.getElementById('compile-status').textContent = 'fout';
        appendROutput([{ type: 'error', text: e.message }]);
        showROutput();
    }
}

function appendROutput(entries) {
    state.rOutput.push(...entries);
    const host = document.getElementById('r-console');
    for (const entry of entries) {
        const div = document.createElement('div');
        const cls = entry.type === 'code' ? 'text-blue-600' : entry.type === 'error' ? 'text-red-500' : 'text-gray-800';
        div.className = `block ${cls}`;
        div.textContent = (entry.type === 'code' ? '> ' : '') + entry.text;
        host.appendChild(div);
    }
    host.scrollTop = host.scrollHeight;
}

function renderRSidebar() {
    const varsEl = document.getElementById('r-vars');
    varsEl.innerHTML = '';
    if (!state.rVars.length) {
        const p = document.createElement('p');
        p.className = 'py-2 text-center text-xs text-gray-400';
        p.textContent = 'Geen variabelen';
        varsEl.appendChild(p);
    } else {
        for (const v of state.rVars) {
            const row = document.createElement('div');
            row.className = 'mb-1 flex items-baseline gap-2 rounded px-2 py-0.5 text-xs hover:bg-gray-50';
            const a = document.createElement('span'); a.className = 'font-mono font-bold text-blue-600'; a.textContent = v.name;
            const b = document.createElement('span'); b.className = 'text-gray-400'; b.textContent = v.class;
            const c = document.createElement('span'); c.className = 'ml-auto max-w-xs truncate text-gray-500'; c.textContent = v.preview;
            row.append(a, b, c);
            varsEl.appendChild(row);
        }
    }
    const plotEl = document.getElementById('r-plots');
    plotEl.innerHTML = '';
    const count = document.getElementById('plot-count');
    if (state.rPlots.length) {
        count.textContent = state.rPlots.length;
        count.classList.remove('hidden');
        const nav = document.createElement('div');
        nav.className = 'mb-2 flex items-center justify-center gap-2 text-xs text-gray-500';
        const prev = document.createElement('button'); prev.textContent = '◀'; prev.className = 'px-2 hover:text-amber-500';
        const idxLabel = document.createElement('span');
        const next = document.createElement('button'); next.textContent = '▶'; next.className = 'px-2 hover:text-amber-500';
        prev.onclick = () => { state.plotIndex = Math.max(0, state.plotIndex-1); renderRSidebar(); };
        next.onclick = () => { state.plotIndex = Math.min(state.rPlots.length-1, state.plotIndex+1); renderRSidebar(); };
        idxLabel.textContent = `${state.plotIndex+1} / ${state.rPlots.length}`;
        nav.append(prev, idxLabel, next);
        plotEl.appendChild(nav);
        const img = document.createElement('img');
        img.src = state.rPlots[Math.min(state.plotIndex, state.rPlots.length-1)];
        img.className = 'min-h-0 w-full flex-1 cursor-zoom-in self-center rounded border object-contain';
        img.addEventListener('click', () => openPlotZoom(img.src));
        plotEl.appendChild(img);
    } else {
        count.classList.add('hidden');
        const p = document.createElement('p');
        p.className = 'py-2 text-center text-xs text-gray-400';
        p.textContent = 'Geen plots';
        plotEl.appendChild(p);
    }
}

function openPlotZoom(src) {
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-50 flex cursor-zoom-out items-center justify-center bg-black/70 p-4';
    const img = document.createElement('img');
    img.src = src;
    img.className = 'max-h-full max-w-full';
    overlay.appendChild(img);
    overlay.addEventListener('click', () => overlay.remove());
    document.body.appendChild(overlay);
}

function showROutput() {
    document.getElementById('output-empty').classList.add('hidden');
    document.getElementById('compile-log').classList.add('hidden');
    document.getElementById('pdf-frame').classList.add('hidden');
    const r = document.getElementById('r-output');
    r.classList.remove('hidden');
    r.classList.add('flex');
}

document.getElementById('r-clear')?.addEventListener('click', () => {
    state.rOutput = [];
    document.getElementById('r-console').innerHTML = '';
});

document.getElementById('r-reset')?.addEventListener('click', async () => {
    if (!confirm('R-sessie resetten? Alle variabelen worden gewist.')) return;
    await api('POST', apiUrl('/r/reset'));
    state.rOutput = []; state.rVars = []; state.rPlots = [];
    document.getElementById('r-console').innerHTML = '';
    renderRSidebar();
});

document.querySelectorAll('.r-tab').forEach(b => {
    b.addEventListener('click', () => {
        const tab = b.dataset.tab;
        document.querySelectorAll('.r-tab').forEach(o => {
            const active = o.dataset.tab === tab;
            o.classList.toggle('text-amber-600', active);
            o.classList.toggle('border-b-2', active);
            o.classList.toggle('border-amber-500', active);
            o.classList.toggle('text-gray-500', !active);
        });
        document.getElementById('r-vars').classList.toggle('hidden', tab !== 'vars');
        document.getElementById('r-plots').classList.toggle('hidden', tab !== 'plots');
    });
});

function bindResize() {
    const left = document.getElementById('left-pane');
    const right = document.getElementById('right-pane');
    document.querySelectorAll('[data-resize]').forEach(handle => {
        handle.addEventListener('mousedown', (e) => {
            e.preventDefault();
            const which = handle.dataset.resize;
            const startX = e.clientX;
            const startY = e.clientY;
            const leftStart = left.offsetWidth;
            const rightStart = right.offsetWidth;
            const consoleEl = document.getElementById('r-console');
            const consoleStart = consoleEl ? consoleEl.offsetHeight : 0;
            const onMove = (ev) => {
                if (which === 'left') left.style.width = Math.max(160, leftStart + (ev.clientX - startX)) + 'px';
                else if (which === 'right') right.style.width = Math.max(220, rightStart - (ev.clientX - startX)) + 'px';
                else if (which === 'r-split' && consoleEl) consoleEl.style.height = Math.max(60, consoleStart + (ev.clientY - startY)) + 'px';
            };
            const onUp = () => {
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
            };
            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
        });
    });
}

async function init() {
    renderFiletreeActions();
    bindResize();
    await loadTree();
    const url = new URL(window.location.href);
    const initial = url.searchParams.get('path');
    if (initial) await openFile(initial);
}

init().catch(e => {
    console.error(e);
    alert('Editor kon niet starten: ' + e.message);
});
