// GeoGebra integratie voor .ggb bestanden.
//
// Workflow analoog aan R: het ggb-bestand is de bron, de applet rendert het en
// alle wijzigingen (interactief of via de input-balk van GeoGebra) worden in
// de rechter zijbalk getoond als "console" (transcript van nieuwe/gewijzigde
// objecten) en "Objecten" (live snapshot). Autosave schrijft `getBase64()`
// terug naar het .ggb bestand.

const GGB_SRC = 'https://www.geogebra.org/apps/deployggb.js';

let scriptPromise = null;
function loadDeployGgb() {
    if (scriptPromise) return scriptPromise;
    scriptPromise = new Promise((resolve, reject) => {
        if (window.GGBApplet) return resolve();
        const s = document.createElement('script');
        s.src = GGB_SRC;
        s.async = true;
        s.onload = () => resolve();
        s.onerror = () => reject(new Error(`Kon ${GGB_SRC} niet laden`));
        document.head.appendChild(s);
    });
    return scriptPromise;
}

let activeSession = null;

export function unmountGeoGebra() {
    if (activeSession) {
        try { activeSession.dispose(); } catch (e) { /* noop */ }
        activeSession = null;
    }
    const mount = document.getElementById('geogebra-mount');
    if (mount) {
        mount.classList.add('hidden');
        mount.innerHTML = '';
    }
    const out = document.getElementById('ggb-output');
    if (out) {
        out.classList.remove('flex');
        out.classList.add('hidden');
    }
}

/**
 * Mount de GeoGebra-applet voor een .ggb bestand.
 *
 * @param {object} cfg
 * @param {string} cfg.url       Asset-URL van het .ggb bestand
 * @param {string} cfg.path      Relatief pad in het project (voor save)
 * @param {boolean} cfg.editable Schrijfrechten?
 * @param {function} cfg.onSave  async (base64) => void
 */
export async function mountGeoGebra(cfg) {
    unmountGeoGebra();
    const mount = document.getElementById('geogebra-mount');
    const out = document.getElementById('ggb-output');
    if (!mount || !out) return;

    mount.classList.remove('hidden');
    out.classList.remove('hidden');
    out.classList.add('flex');

    document.getElementById('ggb-console').innerHTML = '';
    document.getElementById('ggb-objects').innerHTML = '<p class="py-2 text-center text-xs text-gray-400">Geen objecten</p>';
    document.getElementById('ggb-object-count').classList.add('hidden');

    const containerId = 'ggb-applet-' + Math.random().toString(36).slice(2, 9);
    mount.innerHTML = `<div id="${containerId}" class="h-full w-full"></div>`;

    try {
        await loadDeployGgb();
    } catch (e) {
        mount.innerHTML = `<div class="p-4 text-sm text-red-500">${e.message}</div>`;
        return;
    }

    const session = {
        path: cfg.path,
        editable: cfg.editable,
        api: null,
        saveTimer: null,
        suppressUntilLoaded: true,
        dispose() {
            if (this.saveTimer) clearTimeout(this.saveTimer);
        },
    };
    activeSession = session;

    const params = {
        id: containerId,
        appName: 'classic',
        filename: cfg.url,
        width: mount.clientWidth || 800,
        height: mount.clientHeight || 600,
        showToolBar: cfg.editable,
        showMenuBar: false,
        showAlgebraInput: true,
        showResetIcon: false,
        enableShiftDragZoom: true,
        useBrowserForJS: false,
        appletOnLoad: (api) => {
            session.api = api;
            session.suppressUntilLoaded = false;
            refreshObjectList(api);
            if (cfg.editable) {
                api.registerAddListener(name => onChange(session, 'add', name));
                api.registerUpdateListener(name => onChange(session, 'update', name));
                api.registerRemoveListener(name => onChange(session, 'remove', name));
                api.registerClearListener(() => onChange(session, 'clear', ''));
                api.registerRenameListener((oldName, newName) => onChange(session, 'rename', `${oldName} → ${newName}`));
            }
            window.addEventListener('resize', session._onResize = () => {
                try { api.setSize(mount.clientWidth, mount.clientHeight); } catch (e) {}
            });
        },
    };
    new window.GGBApplet(params, true).inject(containerId);

    session.dispose = (orig => function () {
        orig.call(this);
        if (this._onResize) window.removeEventListener('resize', this._onResize);
        try { this.api?.remove(); } catch (e) {}
    })(session.dispose);

    document.getElementById('ggb-clear-console').onclick = () => {
        document.getElementById('ggb-console').innerHTML = '';
    };
}

function onChange(session, kind, name) {
    if (session.suppressUntilLoaded || !session.api) return;
    logConsoleEntry(session.api, kind, name);
    refreshObjectList(session.api);
    if (session.editable && session.path) scheduleSave(session);
}

function logConsoleEntry(api, kind, name) {
    const host = document.getElementById('ggb-console');
    if (!host) return;
    const row = document.createElement('div');
    let prefix = '';
    let cls = 'text-gray-800';
    let body = '';
    if (kind === 'add') {
        prefix = '+ '; cls = 'text-green-700';
        body = `${name} = ${safeValue(api, name)}`;
    } else if (kind === 'update') {
        prefix = '~ '; cls = 'text-blue-700';
        body = `${name} = ${safeValue(api, name)}`;
    } else if (kind === 'remove') {
        prefix = '- '; cls = 'text-red-500';
        body = name;
    } else if (kind === 'rename') {
        prefix = '↻ '; cls = 'text-amber-600';
        body = name;
    } else if (kind === 'clear') {
        prefix = '⨯ '; cls = 'text-gray-500';
        body = 'constructie gewist';
    }
    row.className = `block ${cls}`;
    row.textContent = prefix + body;
    host.appendChild(row);
    host.scrollTop = host.scrollHeight;
}

function safeValue(api, name) {
    try {
        const v = api.getValueString(name);
        return v ?? '';
    } catch (e) {
        return '';
    }
}

function refreshObjectList(api) {
    const host = document.getElementById('ggb-objects');
    if (!host) return;
    let names = [];
    try { names = api.getAllObjectNames() || []; } catch (e) { /* noop */ }
    const count = document.getElementById('ggb-object-count');
    if (!names.length) {
        host.innerHTML = '<p class="py-2 text-center text-xs text-gray-400">Geen objecten</p>';
        count.classList.add('hidden');
        return;
    }
    count.textContent = names.length;
    count.classList.remove('hidden');
    host.innerHTML = '';
    for (const name of names) {
        const row = document.createElement('div');
        row.className = 'mb-1 flex items-baseline gap-2 rounded px-2 py-0.5 text-xs hover:bg-gray-50';
        const nameEl = document.createElement('span');
        nameEl.className = 'font-mono font-bold text-blue-600';
        nameEl.textContent = name;
        const typeEl = document.createElement('span');
        typeEl.className = 'text-gray-400';
        try { typeEl.textContent = api.getObjectType(name) || ''; } catch (e) {}
        const valEl = document.createElement('span');
        valEl.className = 'ml-auto max-w-xs truncate text-gray-500 font-mono';
        valEl.textContent = safeValue(api, name);
        row.append(nameEl, typeEl, valEl);
        host.appendChild(row);
    }
}

function scheduleSave(session) {
    const indicator = document.getElementById('save-indicator');
    const saved = document.getElementById('saved-indicator');
    if (indicator) { indicator.classList.remove('hidden'); }
    if (saved) { saved.classList.add('hidden'); }
    if (session.saveTimer) clearTimeout(session.saveTimer);
    session.saveTimer = setTimeout(async () => {
        if (!session.api) return;
        try {
            const base64 = session.api.getBase64();
            await postBinary(session.path, base64);
            if (indicator) indicator.classList.add('hidden');
            if (saved) saved.classList.remove('hidden');
        } catch (e) {
            console.error('GeoGebra save failed', e);
            if (indicator) indicator.textContent = 'opslaan mislukt';
        }
    }, 800);
}

async function postBinary(path, base64) {
    const app = document.getElementById('app');
    const projectId = Number(app.dataset.projectId);
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const res = await fetch(`/api/projects/${projectId}/file/binary`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ path, base64 }),
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
}
