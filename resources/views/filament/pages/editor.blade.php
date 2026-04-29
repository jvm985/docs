<x-filament-panels::page wire:poll.5s.visible="pollUpdates">
    @vite('resources/js/editor.js')

    <style>
        .fi-page-content > div { max-width: none !important; padding: 0 !important; }
        .fi-page { padding: 0 !important; }
        .fi-main { padding: 0 !important; }
    </style>

    {{-- Context menu --}}
    <div x-data="contextMenu()"
         @node-context.window="show($event.detail)"
         @click.window="hide()"
         @contextmenu.window.prevent="hide()">
        <div x-show="visible"
             x-cloak
             :style="`top:${y}px;left:${x}px`"
             class="fixed z-50 min-w-36 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
            <button @click="$wire.openNode(nodeId); hide()"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4"/>
                Openen
            </button>
            <button @click="promptRename(); hide()"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                <x-heroicon-o-pencil class="h-4 w-4"/>
                Hernoemen
            </button>
            <button @click="$wire.dispatch('share-node', { nodeId }); hide()"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                <x-heroicon-o-share class="h-4 w-4"/>
                Delen
            </button>
            <hr class="my-1 border-gray-200 dark:border-gray-700"/>
            <button @click="$wire.deleteNode(nodeId); hide()"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                <x-heroicon-o-trash class="h-4 w-4"/>
                Verwijderen
            </button>
        </div>
    </div>

    {{-- Create node dialog --}}
    <div x-data="createNodeDialog()"
         @create-node-dialog.window="open($event.detail)">
        <div x-show="visible" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/50">
            <div class="w-72 rounded-xl bg-white p-4 shadow-xl dark:bg-gray-800" @click.stop>
                <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300" x-text="title"></p>
                <input x-ref="nameInput"
                       x-model="name"
                       @keydown.enter="confirm()"
                       @keydown.escape="cancel()"
                       class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"/>
                <div class="mt-3 flex justify-end gap-2">
                    <button @click="cancel()" class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700">Annuleer</button>
                    <button @click="confirm()" class="rounded bg-primary-600 px-3 py-1.5 text-sm text-white hover:bg-primary-700">Aanmaken</button>
                </div>
            </div>
        </div>
    </div>

    <div class="flex h-[calc(100vh-4rem)] gap-0 overflow-hidden">

        {{-- Links: Filetree --}}
        <div class="flex w-60 flex-shrink-0 flex-col border-r border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
             @dragover.prevent
             @drop.prevent="$wire.moveNode($event.dataTransfer.getData('nodeId'), null)">

            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-3 py-2 dark:border-gray-700">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Bestanden</span>
                <div class="flex gap-1">
                    <button @click="$dispatch('create-node-dialog', { type: 'file', parentId: null })"
                            title="Nieuw bestand"
                            class="rounded p-1 text-gray-500 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-300">
                        <x-heroicon-o-document-plus class="h-4 w-4"/>
                    </button>
                    <button @click="$dispatch('create-node-dialog', { type: 'folder', parentId: null })"
                            title="Nieuwe map"
                            class="rounded p-1 text-gray-500 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-300">
                        <x-heroicon-o-folder-plus class="h-4 w-4"/>
                    </button>
                    <label title="Bestand uploaden"
                           class="cursor-pointer rounded p-1 text-gray-500 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-300">
                        <x-heroicon-o-arrow-up-tray class="h-4 w-4"/>
                        <input type="file" class="hidden" multiple @change="handleUpload($event, null)"/>
                    </label>
                </div>
            </div>

            {{-- Filetree --}}
            <div class="flex-1 overflow-y-auto py-1">
                @if($this->project->rootNodes->isEmpty())
                    <p class="px-3 py-4 text-center text-xs text-gray-400">Geen bestanden</p>
                @else
                    <x-docs.filetree-node :nodes="$this->project->rootNodes" :depth="0" :activeNode="$activeNode"/>
                @endif
            </div>
        </div>

        {{-- Midden: Editor --}}
        <div class="flex flex-1 flex-col overflow-hidden"
             @keydown.ctrl.enter.window="handleCtrlEnter()"
             x-data="{ handleCtrlEnter() { document.dispatchEvent(new CustomEvent('editor-ctrl-enter')); } }">

            {{-- Toolbar --}}
            <div class="flex h-10 items-center gap-2 border-b border-gray-200 bg-white px-4 dark:border-gray-700 dark:bg-gray-800">
                @if($activeNode)
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $activeNode->name }}
                    </span>

                    @if($activeNode->isCompilable())
                        <div class="ml-auto flex items-center gap-2">
                            @if($activeNode->extension() === 'tex')
                                <select wire:model.live="activeCompiler"
                                        class="rounded border border-gray-300 bg-white px-2 py-1 text-xs focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    <option value="pdflatex">pdflatex</option>
                                    <option value="xelatex">xelatex</option>
                                    <option value="lualatex">lualatex</option>
                                </select>
                            @endif

                            <button wire:click="compile"
                                    class="flex items-center gap-1 rounded bg-primary-600 px-3 py-1 text-xs font-medium text-white hover:bg-primary-700">
                                <x-heroicon-o-play class="h-3.5 w-3.5"/>
                                Compileren
                            </button>

                            @php $latestLog = $activeNode->compileLogs->first(); @endphp
                            @if($latestLog)
                                <button @click="$dispatch('show-log-modal', @js(['log' => $latestLog->output, 'status' => $latestLog->status]))"
                                        class="flex items-center gap-1 rounded border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                                    <x-heroicon-o-document-text class="h-3.5 w-3.5"/>
                                    Laatste output
                                </button>
                            @endif
                        </div>
                    @elseif($activeNode->isExecutable())
                        <div class="ml-auto flex items-center gap-2">
                            <button @click="document.dispatchEvent(new CustomEvent('editor-run-selection'))"
                                    class="flex items-center gap-1 rounded bg-primary-600 px-3 py-1 text-xs font-medium text-white hover:bg-primary-700">
                                <x-heroicon-o-play class="h-3.5 w-3.5"/>
                                Uitvoeren
                            </button>
                        </div>
                    @endif
                @else
                    <span class="text-sm text-gray-400 dark:text-gray-500">Open een bestand om te beginnen</span>
                @endif
            </div>

            {{-- Monaco --}}
            <div class="relative flex-1"
                 x-data="monacoEditor(@js($activeNode?->content ?? ''), @js($activeNode?->editorLanguage() ?? 'plaintext'), @js((bool)$activeNode))"
                 x-init="init()">
                <div x-ref="editor" class="h-full w-full"></div>
                <template x-if="!hasActiveFile">
                    <div class="absolute inset-0 flex items-center justify-center bg-gray-50 dark:bg-gray-900">
                        <div class="text-center text-gray-300 dark:text-gray-600">
                            <x-heroicon-o-document class="mx-auto h-12 w-12"/>
                            <p class="mt-2 text-sm">Selecteer een bestand</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Rechts: Output --}}
        <div class="flex w-80 flex-shrink-0 flex-col border-l border-gray-200 dark:border-gray-700">

            @if($activeNode?->isExecutable())
                {{-- R output --}}
                <div class="flex h-1/2 flex-col border-b border-gray-200 dark:border-gray-700"
                     x-data="rOutputPanel()">
                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-3 py-1.5 dark:border-gray-700 dark:bg-gray-900">
                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">R Output</span>
                        <button @click="entries = []" class="text-xs text-gray-400 hover:text-gray-600">Wissen</button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-2 font-mono text-xs" x-ref="output">
                        <template x-for="(e, i) in entries" :key="i">
                            <div class="mb-0.5">
                                <span x-show="e.type==='code'" class="block text-blue-500 dark:text-blue-400" x-text="'> '+e.text"></span>
                                <span x-show="e.type==='output'" class="block text-gray-800 dark:text-gray-200" x-text="e.text"></span>
                                <span x-show="e.type==='error'" class="block text-red-500" x-text="e.text"></span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Variabelen / Plots --}}
                <div class="flex flex-1 flex-col overflow-hidden" x-data="rSidePanel()">
                    <div class="flex border-b border-gray-200 dark:border-gray-700">
                        <button @click="tab='vars'"
                                :class="tab==='vars' ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400' : 'text-gray-500'"
                                class="px-4 py-2 text-xs font-medium">Variabelen</button>
                        <button @click="tab='plots'"
                                :class="tab==='plots' ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400' : 'text-gray-500'"
                                class="px-4 py-2 text-xs font-medium">Plots</button>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <div x-show="tab==='vars'" class="p-2">
                            <template x-if="vars.length===0">
                                <p class="py-6 text-center text-xs text-gray-400">Geen variabelen</p>
                            </template>
                            <template x-for="v in vars" :key="v.name">
                                <div class="mb-1 flex items-baseline gap-2 rounded px-2 py-1 text-xs hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <span class="font-mono font-bold text-blue-600 dark:text-blue-400" x-text="v.name"></span>
                                    <span class="text-gray-400 dark:text-gray-500" x-text="v.class"></span>
                                    <span class="ml-auto max-w-24 truncate text-gray-500" x-text="v.preview"></span>
                                </div>
                            </template>
                        </div>
                        <div x-show="tab==='plots'" class="space-y-2 p-2">
                            <template x-if="plots.length===0">
                                <p class="py-6 text-center text-xs text-gray-400">Geen plots</p>
                            </template>
                            <template x-for="(p,i) in plots" :key="i">
                                <img :src="p" class="w-full rounded border border-gray-200 dark:border-gray-700"/>
                            </template>
                        </div>
                    </div>
                </div>

            @elseif($activeNode?->isCompilable())
                {{-- PDF viewer --}}
                <div class="flex h-full flex-col" x-data="pdfViewer(@js($activeNode->compileLogs->first()?->pdf_path ? Storage::url($activeNode->compileLogs->first()->pdf_path) : null))">
                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-3 py-1.5 dark:border-gray-700 dark:bg-gray-900">
                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">PDF</span>
                        <a x-show="url" :href="url" target="_blank" class="text-xs text-primary-600 hover:underline">Openen</a>
                    </div>
                    <div class="flex-1 bg-gray-100 dark:bg-gray-900">
                        <template x-if="url">
                            <iframe :src="url" class="h-full w-full" type="application/pdf"></iframe>
                        </template>
                        <template x-if="!url">
                            <div class="flex h-full items-center justify-center text-gray-300 dark:text-gray-600">
                                <div class="text-center">
                                    <x-heroicon-o-document-text class="mx-auto h-12 w-12"/>
                                    <p class="mt-2 text-sm">Compileer om PDF te zien</p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            @else
                <div class="flex h-full items-center justify-center text-gray-400 dark:text-gray-500">
                    <p class="text-sm">Geen output beschikbaar</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Compile log modal --}}
    <div x-data="{ show: false, log: '', status: '' }"
         @show-log-modal.window="show = true; log = $event.detail.log; status = $event.detail.status">
        <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click="show=false">
            <div class="max-h-[80vh] w-2/3 overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-gray-900" @click.stop>
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Compiler output</h3>
                    <button @click="show=false" class="text-gray-400 hover:text-gray-600"><x-heroicon-o-x-mark class="h-5 w-5"/></button>
                </div>
                <pre class="max-h-[70vh] overflow-y-auto p-4 font-mono text-xs text-gray-700 dark:text-gray-300" x-text="log"></pre>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Alpine helpers
        window.contextMenu = function() {
            return {
                visible: false, x: 0, y: 0, nodeId: null, nodeName: '',
                show({ id, name, x, y }) { this.nodeId = id; this.nodeName = name; this.x = x; this.y = y; this.visible = true; },
                hide() { this.visible = false; },
                promptRename() {
                    const n = prompt('Nieuwe naam:', this.nodeName);
                    if (n?.trim()) this.$wire.renameNode(this.nodeId, n.trim());
                },
            };
        };

        window.createNodeDialog = function() {
            return {
                visible: false, type: 'file', parentId: null, name: '',
                get title() { return this.type === 'file' ? 'Nieuwe bestandsnaam:' : 'Nieuwe mapnaam:'; },
                open({ type, parentId }) { this.type = type; this.parentId = parentId; this.name = ''; this.visible = true; this.$nextTick(() => this.$refs.nameInput?.focus()); },
                confirm() { if (this.name.trim()) { this.$wire.createNode(this.name.trim(), this.parentId, this.type); } this.visible = false; },
                cancel() { this.visible = false; },
            };
        };

        window.rOutputPanel = function() {
            return {
                entries: [],
                init() {
                    window.addEventListener('r-output', e => {
                        this.entries.push(e.detail);
                        this.$nextTick(() => { const el = this.$refs.output; if (el) el.scrollTop = el.scrollHeight; });
                    });
                },
            };
        };

        window.rSidePanel = function() {
            return {
                tab: 'vars', vars: [], plots: [],
                init() {
                    window.addEventListener('r-variables', e => { this.vars = e.detail; });
                    window.addEventListener('r-plot', e => { this.plots.push(e.detail); this.tab = 'plots'; });
                },
            };
        };

        window.pdfViewer = function(initialUrl) {
            return {
                url: initialUrl,
                init() {
                    window.addEventListener('pdf-ready', e => { this.url = e.detail.url + '?t=' + Date.now(); });
                },
            };
        };

        window.handleUpload = async function(event, parentId) {
            const files = Array.from(event.target.files);
            for (const file of files) {
                const isBinary = /\.(png|jpg|jpeg|gif|pdf|svg|webp)$/i.test(file.name);
                let content;
                if (isBinary) {
                    content = await new Promise(r => { const fr = new FileReader(); fr.onload = e => r(e.target.result); fr.readAsDataURL(file); });
                } else {
                    content = await file.text();
                }
                window.Livewire.dispatch('upload-file', { name: file.name, content, parentId });
            }
            event.target.value = '';
        };
    </script>
    @endpush
</x-filament-panels::page>
