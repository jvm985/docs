{{-- Large upload widget — included from drives/show.blade.php --}}
{{-- Requires: $drive (SharedDrive) and $canWrite (bool) --}}
@if($canWrite)
<div x-data="largeUpload({{ $drive->id }})"
     x-init="init()"
     @open-large-upload.window="pickFile()"
     class="fixed bottom-4 right-4 z-40 w-96 max-w-[calc(100vw-2rem)]"
     data-testid="large-upload-widget">

    <input type="file" x-ref="filePicker" class="hidden" @change="onFilePicked($event)">

    {{-- Panel --}}
    <div x-show="state !== 'idle'" x-cloak
         class="rounded-xl border border-gray-200 bg-white p-4 shadow-2xl">
        <div class="mb-2 flex items-center justify-between gap-2">
            <h3 class="truncate text-sm font-semibold text-gray-900" x-text="filename || 'Upload'" data-testid="upload-filename"></h3>
            <button type="button" @click="dismiss()" class="text-gray-400 hover:text-gray-700" aria-label="Verbergen" x-show="state === 'done' || state === 'idle'">×</button>
        </div>

        {{-- Progress bar --}}
        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
            <div class="h-2 rounded-full bg-amber-500 transition-all"
                 :style="`width: ${progressPct}%`"
                 :class="state === 'error' ? 'bg-red-500' : (state === 'done' ? 'bg-emerald-500' : 'bg-amber-500')"></div>
        </div>
        <p class="mt-1 flex items-center justify-between text-xs text-gray-500">
            <span x-text="statusText" data-testid="upload-status"></span>
            <span x-text="progressText"></span>
        </p>

        {{-- Buttons --}}
        <div class="mt-3 flex justify-end gap-2">
            <template x-if="state === 'uploading' || state === 'finishing'">
                <button type="button" @click="cancel()" class="rounded-lg border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-100" data-testid="upload-cancel">Annuleren</button>
            </template>
            <template x-if="state === 'error'">
                <button type="button" @click="resume()" class="rounded-lg bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600" data-testid="upload-resume">Hervat</button>
            </template>
            <template x-if="state === 'error'">
                <button type="button" @click="cancel()" class="rounded-lg border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-100">Annuleren</button>
            </template>
            <template x-if="state === 'done'">
                <a :href="projectUrl" class="rounded-lg bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600">Open project</a>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script>
function largeUpload(driveId) {
    return {
        driveId,
        state: 'idle', // idle | uploading | error | finishing | done
        file: null,
        filename: '',
        size: 0,
        uploadId: null,
        chunkSize: 5 * 1024 * 1024,
        totalChunks: 0,
        receivedChunks: new Set(),
        currentIndex: 0,
        bytesSent: 0,
        startTime: 0,
        speedBps: 0,
        errorMsg: '',
        projectUrl: '',
        abortController: null,
        storageKey() { return `largeUpload:drive:${this.driveId}`; },
        init() {
            // Resume detection: if a stored uploadId exists, ask user to pick the same file again.
            const saved = this.loadSaved();
            if (saved) {
                this.filename = saved.filename;
                this.size = saved.size;
                this.uploadId = saved.uploadId;
                this.state = 'error';
                this.errorMsg = 'Vorige upload onderbroken — kies hetzelfde bestand om verder te gaan.';
            }
        },
        loadSaved() {
            try { return JSON.parse(localStorage.getItem(this.storageKey()) || 'null'); }
            catch { return null; }
        },
        saveSaved(data) { localStorage.setItem(this.storageKey(), JSON.stringify(data)); },
        clearSaved() { localStorage.removeItem(this.storageKey()); },
        pickFile() { this.$refs.filePicker.click(); },
        async onFilePicked(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.file = file;

            const saved = this.loadSaved();
            if (saved && saved.filename === file.name && saved.size === file.size && saved.uploadId === this.uploadId) {
                // Resume existing upload
                await this.resume();
            } else {
                // Fresh upload
                this.clearSaved();
                this.uploadId = null;
                await this.start();
            }
            e.target.value = '';
        },
        async start() {
            this.filename = this.file.name;
            this.size = this.file.size;
            this.state = 'uploading';
            this.errorMsg = '';
            this.bytesSent = 0;
            this.startTime = Date.now();

            try {
                const init = await fetch(`/api/drives/${this.driveId}/uploads`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ filename: this.file.name, size: this.file.size }),
                }).then(r => r.json());

                this.uploadId = init.upload_id;
                this.chunkSize = init.chunk_size;
                this.totalChunks = init.total_chunks;
                this.receivedChunks = new Set(init.received_chunks);
                this.saveSaved({ uploadId: this.uploadId, filename: this.filename, size: this.size });

                await this.uploadAllChunks();
            } catch (err) {
                this.fail(err);
            }
        },
        async resume() {
            if (!this.file) {
                this.errorMsg = 'Kies hetzelfde bestand om verder te gaan.';
                this.pickFile();
                return;
            }
            this.state = 'uploading';
            this.errorMsg = '';
            this.startTime = Date.now();
            try {
                const status = await fetch(`/api/drives/${this.driveId}/uploads/${this.uploadId}`).then(r => r.json());
                this.chunkSize = status.chunk_size;
                this.totalChunks = status.total_chunks;
                this.receivedChunks = new Set(status.received_chunks);
                this.bytesSent = this.receivedChunks.size * this.chunkSize;
                await this.uploadAllChunks();
            } catch (err) {
                this.fail(err);
            }
        },
        async uploadAllChunks() {
            this.abortController = new AbortController();
            for (let i = 0; i < this.totalChunks; i++) {
                if (this.receivedChunks.has(i)) continue;
                this.currentIndex = i;
                await this.uploadChunkWithRetry(i);
                if (this.state !== 'uploading') return; // aborted
            }
            await this.finish();
        },
        async uploadChunkWithRetry(index, attempt = 0) {
            const start = index * this.chunkSize;
            const end = Math.min(start + this.chunkSize, this.size);
            const blob = this.file.slice(start, end);

            try {
                const resp = await fetch(`/api/drives/${this.driveId}/uploads/${this.uploadId}/chunks/${index}`, {
                    method: 'PUT',
                    body: blob,
                    signal: this.abortController.signal,
                });
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                this.receivedChunks.add(index);
                this.bytesSent += blob.size;
                const elapsed = (Date.now() - this.startTime) / 1000;
                this.speedBps = elapsed > 0 ? this.bytesSent / elapsed : 0;
            } catch (err) {
                if (err.name === 'AbortError') return;
                if (attempt < 5) {
                    const delay = Math.min(1000 * Math.pow(2, attempt), 30000);
                    await new Promise(r => setTimeout(r, delay));
                    return this.uploadChunkWithRetry(index, attempt + 1);
                }
                throw err;
            }
        },
        async finish() {
            this.state = 'finishing';
            try {
                const result = await fetch(`/api/drives/${this.driveId}/uploads/${this.uploadId}/finish`, {
                    method: 'POST',
                }).then(r => r.json());
                this.projectUrl = result.project_url;
                this.state = 'done';
                this.clearSaved();
                // Refresh the drive page after a short delay so the new project shows up
                setTimeout(() => window.location.reload(), 1500);
            } catch (err) {
                this.fail(err);
            }
        },
        async cancel() {
            if (this.abortController) this.abortController.abort();
            if (this.uploadId) {
                try { await fetch(`/api/drives/${this.driveId}/uploads/${this.uploadId}`, { method: 'DELETE' }); }
                catch {}
            }
            this.clearSaved();
            this.state = 'idle';
            this.uploadId = null;
            this.file = null;
            this.filename = '';
        },
        dismiss() { this.state = 'idle'; },
        fail(err) {
            this.state = 'error';
            this.errorMsg = err.message || 'Verbinding verloren';
        },
        get progressPct() {
            if (this.totalChunks === 0) return 0;
            return Math.min(100, Math.round(this.receivedChunks.size / this.totalChunks * 100));
        },
        get progressText() {
            return this.totalChunks > 0 ? `${this.receivedChunks.size} / ${this.totalChunks}` : '';
        },
        get statusText() {
            if (this.state === 'uploading') {
                const speed = this.speedBps > 0 ? ` · ${this.fmtBytes(this.speedBps)}/s` : '';
                return `Bezig met uploaden${speed}`;
            }
            if (this.state === 'finishing') return 'Bestand wordt samengevoegd…';
            if (this.state === 'done') return 'Klaar! Project aangemaakt.';
            if (this.state === 'error') return this.errorMsg || 'Er ging iets fout';
            return '';
        },
        fmtBytes(b) {
            if (b < 1024) return Math.round(b) + ' B';
            if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
            if (b < 1024 * 1024 * 1024) return (b / 1024 / 1024).toFixed(1) + ' MB';
            return (b / 1024 / 1024 / 1024).toFixed(2) + ' GB';
        },
    };
}
</script>
@endpush
@endif
