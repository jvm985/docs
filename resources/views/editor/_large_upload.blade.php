{{-- Floating chunked-upload widget for the editor — vanilla JS, no Alpine. --}}
<input type="file" id="large-upload-file" class="hidden">

<button type="button" id="large-upload-fab"
        title="Upload groot bestand"
        data-testid="large-upload-fab"
        class="fixed bottom-4 right-4 z-30 flex h-12 w-12 items-center justify-center rounded-full bg-amber-500 text-white shadow-lg hover:bg-amber-600">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>

<div id="large-upload-panel"
     class="fixed bottom-4 right-4 z-40 hidden w-96 max-w-[calc(100vw-2rem)] rounded-xl border border-gray-200 bg-white p-4 shadow-2xl"
     data-testid="large-upload-panel">
    <div class="mb-2 flex items-center justify-between gap-2">
        <h3 class="truncate text-sm font-semibold text-gray-900" id="large-upload-filename" data-testid="upload-filename">Upload</h3>
        <button type="button" id="large-upload-close" class="text-gray-400 hover:text-gray-700" aria-label="Verbergen">×</button>
    </div>
    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
        <div id="large-upload-bar" class="h-2 rounded-full bg-amber-500 transition-all" style="width:0%"></div>
    </div>
    <p class="mt-1 flex items-center justify-between text-xs text-gray-500">
        <span id="large-upload-status" data-testid="upload-status">Bezig…</span>
        <span id="large-upload-progress"></span>
    </p>
    <div class="mt-3 flex justify-end gap-2">
        <button type="button" id="large-upload-cancel" class="rounded-lg border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-100 hidden" data-testid="upload-cancel">Annuleren</button>
        <button type="button" id="large-upload-resume" class="rounded-lg bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600 hidden" data-testid="upload-resume">Hervat</button>
    </div>
</div>

<script>
(function() {
    const PROJECT_ID = {{ $project->id }};
    const STORAGE_KEY = 'largeUpload:project:' + PROJECT_ID;
    const fab     = document.getElementById('large-upload-fab');
    const panel   = document.getElementById('large-upload-panel');
    const picker  = document.getElementById('large-upload-file');
    const filenameEl = document.getElementById('large-upload-filename');
    const statusEl   = document.getElementById('large-upload-status');
    const progressEl = document.getElementById('large-upload-progress');
    const barEl      = document.getElementById('large-upload-bar');
    const cancelBtn  = document.getElementById('large-upload-cancel');
    const resumeBtn  = document.getElementById('large-upload-resume');
    const closeBtn   = document.getElementById('large-upload-close');

    let state = 'idle';
    let file = null;
    let uploadId = null;
    let chunkSize = 5 * 1024 * 1024;
    let totalChunks = 0;
    let receivedChunks = new Set();
    let bytesSent = 0;
    let totalSize = 0;
    let startTime = 0;
    let abortController = null;

    function fmtBytes(b) {
        if (b < 1024) return Math.round(b) + ' B';
        if (b < 1024*1024) return (b/1024).toFixed(1) + ' KB';
        if (b < 1024*1024*1024) return (b/1024/1024).toFixed(1) + ' MB';
        return (b/1024/1024/1024).toFixed(2) + ' GB';
    }
    function showPanel() { panel.classList.remove('hidden'); fab.classList.add('hidden'); }
    function hidePanel() { panel.classList.add('hidden'); fab.classList.remove('hidden'); }
    function setStatus(text)  { statusEl.textContent = text; }
    function setProgress() {
        const pct = totalChunks > 0 ? Math.round(receivedChunks.size/totalChunks*100) : 0;
        barEl.style.width = pct + '%';
        progressEl.textContent = totalChunks > 0 ? `${receivedChunks.size} / ${totalChunks}` : '';
    }
    function setColor(cls) {
        barEl.classList.remove('bg-amber-500','bg-red-500','bg-emerald-500');
        barEl.classList.add(cls);
    }
    function showError(msg) {
        state = 'error';
        setStatus(msg || 'Verbinding verloren');
        setColor('bg-red-500');
        resumeBtn.classList.remove('hidden');
        cancelBtn.classList.remove('hidden');
    }
    function showUploading() {
        state = 'uploading';
        setColor('bg-amber-500');
        resumeBtn.classList.add('hidden');
        cancelBtn.classList.remove('hidden');
    }
    function showDone() {
        state = 'done';
        setStatus('Klaar! Bestand toegevoegd.');
        setColor('bg-emerald-500');
        resumeBtn.classList.add('hidden');
        cancelBtn.classList.add('hidden');
        localStorage.removeItem(STORAGE_KEY);
        setTimeout(() => window.location.reload(), 1200);
    }
    function loadSaved() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null'); }
        catch { return null; }
    }
    function saveSaved() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({uploadId, filename: file?.name, size: totalSize}));
    }

    fab.addEventListener('click', () => picker.click());
    closeBtn.addEventListener('click', () => hidePanel());

    picker.addEventListener('change', async (e) => {
        const f = e.target.files[0];
        if (!f) return;
        file = f;
        e.target.value = '';

        const saved = loadSaved();
        if (saved && saved.filename === f.name && saved.size === f.size) {
            uploadId = saved.uploadId;
            await resume();
        } else {
            localStorage.removeItem(STORAGE_KEY);
            uploadId = null;
            await start();
        }
    });

    cancelBtn.addEventListener('click', cancel);
    resumeBtn.addEventListener('click', resume);

    async function start() {
        filenameEl.textContent = file.name;
        totalSize = file.size;
        bytesSent = 0;
        receivedChunks = new Set();
        startTime = Date.now();
        showPanel();
        showUploading();
        setStatus('Voorbereiden…');

        try {
            const init = await fetch(`/api/projects/${PROJECT_ID}/uploads`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ filename: file.name, size: file.size }),
            });
            if (!init.ok) {
                const text = await init.text();
                throw new Error(`Init faalde (HTTP ${init.status})${text ? ': '+text.substring(0,200) : ''}`);
            }
            const manifest = await init.json();
            uploadId = manifest.upload_id;
            chunkSize = manifest.chunk_size;
            totalChunks = manifest.total_chunks;
            receivedChunks = new Set(manifest.received_chunks);
            saveSaved();
            await uploadAll();
        } catch (err) {
            showError(err.message);
        }
    }

    async function resume() {
        if (!file) {
            picker.click();
            return;
        }
        showPanel();
        showUploading();
        setStatus('Status ophalen…');
        startTime = Date.now();
        try {
            const resp = await fetch(`/api/projects/${PROJECT_ID}/uploads/${uploadId}`, { headers: {'Accept': 'application/json'} });
            if (!resp.ok) {
                const text = await resp.text();
                throw new Error(`Status faalde (HTTP ${resp.status})${text ? ': '+text.substring(0,200) : ''}`);
            }
            const manifest = await resp.json();
            chunkSize = manifest.chunk_size;
            totalChunks = manifest.total_chunks;
            receivedChunks = new Set(manifest.received_chunks);
            bytesSent = receivedChunks.size * chunkSize;
            await uploadAll();
        } catch (err) {
            showError(err.message);
        }
    }

    async function uploadAll() {
        abortController = new AbortController();
        for (let i = 0; i < totalChunks; i++) {
            if (receivedChunks.has(i)) continue;
            try {
                await uploadChunkWithRetry(i, 0);
            } catch (err) {
                if (err.name === 'AbortError') return;
                showError(err.message);
                return;
            }
            if (state !== 'uploading') return;
            setProgress();
            const elapsed = (Date.now() - startTime) / 1000;
            const speed = elapsed > 0 ? bytesSent / elapsed : 0;
            setStatus('Bezig met uploaden · ' + fmtBytes(speed) + '/s');
        }
        await finish();
    }

    async function uploadChunkWithRetry(index, attempt) {
        const start = index * chunkSize;
        const end = Math.min(start + chunkSize, totalSize);
        const blob = file.slice(start, end);
        try {
            const resp = await fetch(`/api/projects/${PROJECT_ID}/uploads/${uploadId}/chunks/${index}`, {
                method: 'PUT',
                body: blob,
                signal: abortController.signal,
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            receivedChunks.add(index);
            bytesSent += blob.size;
        } catch (err) {
            if (err.name === 'AbortError') throw err;
            if (attempt < 5) {
                const delay = Math.min(1000 * Math.pow(2, attempt), 30000);
                await new Promise(r => setTimeout(r, delay));
                return uploadChunkWithRetry(index, attempt + 1);
            }
            throw err;
        }
    }

    async function finish() {
        state = 'finishing';
        setStatus('Bestand wordt samengevoegd…');
        try {
            const resp = await fetch(`/api/projects/${PROJECT_ID}/uploads/${uploadId}/finish`, {
                method: 'POST',
                headers: {'Accept': 'application/json'},
            });
            if (!resp.ok) {
                const text = await resp.text();
                throw new Error(`Finish faalde (HTTP ${resp.status})${text ? ': '+text.substring(0,200) : ''}`);
            }
            showDone();
        } catch (err) {
            showError(err.message);
        }
    }

    async function cancel() {
        if (abortController) abortController.abort();
        if (uploadId) {
            try {
                await fetch(`/api/projects/${PROJECT_ID}/uploads/${uploadId}`, { method: 'DELETE' });
            } catch {}
        }
        localStorage.removeItem(STORAGE_KEY);
        uploadId = null;
        file = null;
        state = 'idle';
        hidePanel();
    }

    // On load, check if there is an unfinished upload to offer resume
    const saved = loadSaved();
    if (saved) {
        uploadId = saved.uploadId;
        totalSize = saved.size;
        filenameEl.textContent = saved.filename + ' (onderbroken)';
        showPanel();
        showError('Vorige upload onderbroken — kies hetzelfde bestand om verder te gaan.');
        resumeBtn.textContent = 'Bestand kiezen';
        resumeBtn.classList.remove('hidden');
    }
})();
</script>
