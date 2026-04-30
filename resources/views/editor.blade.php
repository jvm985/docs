<!DOCTYPE html>
<html lang="nl" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $project->name }} — Docs</title>
    @vite(['resources/css/app.css', 'resources/js/editor.js'])
    <style>
        .resize-handle { width: 5px; cursor: col-resize; background: transparent; transition: background .15s; flex-shrink: 0; }
        .resize-handle:hover, .resize-handle.active { background: #f59e0b; }
        .resize-handle-h { height: 5px; cursor: row-resize; background: transparent; transition: background .15s; flex-shrink: 0; }
        .resize-handle-h:hover, .resize-handle-h.active { background: #f59e0b; }
    </style>
</head>
<body class="h-full bg-gray-100 text-gray-900" x-data="editorApp({{ $project->id }}, {{ $project->user_id === auth()->id() ? 'true' : 'false' }})">

    {{-- Top bar --}}
    <header class="flex h-10 items-center justify-between border-b bg-white px-4">
        <div class="flex items-center gap-3">
            <a href="/projects" class="text-sm text-gray-500 hover:text-gray-800">&larr; Projecten</a>
            <span class="text-sm font-semibold" x-text="projectName"></span>
            <template x-if="!isOwner">
                <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-500">Alleen lezen</span>
            </template>
        </div>
        <div class="flex items-center gap-2">
            <template x-if="isOwner && activeNode && isCompilable(activeNode.name)">
                <div class="flex items-center gap-2">
                    <template x-if="activeNode.name.endsWith('.tex')">
                        <select x-model="compiler" class="rounded border border-gray-300 px-2 py-0.5 text-xs">
                            <option value="pdflatex">pdflatex</option>
                            <option value="xelatex">xelatex</option>
                            <option value="lualatex">lualatex</option>
                        </select>
                    </template>
                    <button @click="compile()" :disabled="compiling" class="rounded bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600 disabled:opacity-50" x-text="compiling ? 'Bezig...' : 'Compileren'"></button>
                </div>
            </template>
            <template x-if="isOwner && activeNode && isExecutable(activeNode.name)">
                <button @click="executeR()" class="rounded bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600">Uitvoeren</button>
            </template>
        </div>
    </header>

    <div class="flex" style="height: calc(100vh - 2.5rem);" x-data="resizablePanels()">

        {{-- Links: Filetree --}}
        <aside class="flex flex-shrink-0 flex-col border-r bg-gray-50 overflow-hidden" :style="'width:' + leftW + 'px'">
            <div class="flex items-center justify-between border-b px-3 py-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Bestanden</span>
                @if($project->user_id === auth()->id())
                    <div class="flex gap-1">
                        <button @click="createItem('file')" title="Nieuw bestand" class="rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        </button>
                        <button @click="createItem('folder')" title="Nieuwe map" class="rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/></svg>
                        </button>
                        <label title="Bestanden uploaden" class="cursor-pointer rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                            <svg class="h-4 w-4 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                            <input type="file" class="hidden" multiple onchange="window._handleUpload(this)">
                        </label>
                        <label title="Map uploaden" class="cursor-pointer rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                            <svg class="h-4 w-4 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25H15m0-3-3-3m0 0-3 3m3-3V15"/></svg>
                            <input type="file" class="hidden" webkitdirectory onchange="window._handleUpload(this)">
                        </label>
                    </div>
                @endif
            </div>
            <div class="flex-1 overflow-y-auto py-1" id="filetree"></div>
        </aside>

        {{-- Resize handle links --}}
        <div class="resize-handle" @mousedown="startResize('left', $event)"></div>

        {{-- Midden: CodeMirror editor --}}
        <main class="flex flex-1 flex-col overflow-hidden" style="min-width:200px">
            <div class="flex h-8 items-center border-b bg-white px-4">
                <span class="text-xs text-gray-500" x-text="activeNode ? activeNode.name : 'Selecteer een bestand'"></span>
                <span x-show="saving" class="ml-2 text-xs text-gray-400">Opslaan...</span>
                <span x-show="saved" class="ml-2 text-xs text-green-500">✓</span>
            </div>
            <div class="relative flex-1">
                <div id="codemirror-container" class="h-full w-full"></div>
                <template x-if="!activeNode">
                    <div class="absolute inset-0 flex items-center justify-center bg-gray-50">
                        <p class="text-sm text-gray-400">Open een bestand om te bewerken</p>
                    </div>
                </template>
            </div>
        </main>

        {{-- Resize handle rechts --}}
        <div class="resize-handle" @mousedown="startResize('right', $event)"></div>

        {{-- Rechts: Output --}}
        <aside class="flex flex-shrink-0 flex-col border-l bg-white overflow-hidden" :style="'width:' + rightW + 'px'">
            <div class="flex h-8 items-center border-b px-3">
                <span class="text-xs font-semibold text-gray-500">Output</span>
                <span x-show="compiling" class="ml-2 text-xs text-amber-500">Bezig...</span>
            </div>
            <div class="flex flex-1 flex-col overflow-hidden">
                {{-- PDF viewer --}}
                <template x-if="pdfUrl">
                    <iframe :src="pdfUrl" class="h-full w-full"></iframe>
                </template>

                {{-- R: output + variabelen/plots --}}
                <template x-if="rOutput.length > 0 || rPlots.length > 0 || rVars.length > 0">
                    <div class="flex flex-1 flex-col overflow-hidden" x-data="{ rSplitY: 60 }">
                        {{-- R console output --}}
                        <div class="flex items-center justify-between border-b bg-gray-50 px-2 py-1">
                            <span class="text-xs font-semibold text-gray-500">Console</span>
                            <button @click="clearOutput()" class="text-xs text-gray-400 hover:text-red-500">Wissen</button>
                        </div>
                        <div class="overflow-y-auto p-2 font-mono text-xs" :style="'height:' + rSplitY + '%'">
                            <template x-for="(entry, i) in rOutput" :key="i">
                                <div class="mb-0.5">
                                    <span x-show="entry.type === 'code'" class="block text-blue-600" x-text="'> ' + entry.text"></span>
                                    <span x-show="entry.type === 'output'" class="block text-gray-800" x-text="entry.text"></span>
                                    <span x-show="entry.type === 'error'" class="block text-red-500" x-text="entry.text"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Resize handle horizontaal --}}
                        <div class="resize-handle-h" @mousedown="startResizeH($event, $el.parentElement, v => rSplitY = v)"></div>

                        {{-- Tabs: variabelen / plots --}}
                        <div class="flex flex-1 flex-col overflow-hidden" x-data="{ tab: 'vars', plotIndex: 0 }">
                            <div class="flex border-b">
                                <button @click="tab='vars'" :class="tab==='vars' ? 'border-b-2 border-amber-500 text-amber-600' : 'text-gray-500'" class="px-3 py-1 text-xs font-medium">Variabelen</button>
                                <button @click="tab='plots'" :class="tab==='plots' ? 'border-b-2 border-amber-500 text-amber-600' : 'text-gray-500'" class="px-3 py-1 text-xs font-medium">
                                    Plots <span x-show="rPlots.length" class="ml-1 rounded-full bg-amber-100 px-1.5 text-amber-700" x-text="rPlots.length"></span>
                                </button>
                            </div>
                            <div class="flex-1 overflow-y-auto">
                                <div x-show="tab==='vars'" class="p-2">
                                    <template x-if="rVars.length === 0">
                                        <p class="py-2 text-center text-xs text-gray-400">Geen variabelen</p>
                                    </template>
                                    <template x-for="v in rVars" :key="v.name">
                                        <div class="mb-1 flex items-baseline gap-2 rounded px-2 py-0.5 text-xs hover:bg-gray-50">
                                            <span class="font-mono font-bold text-blue-600" x-text="v.name"></span>
                                            <span class="text-gray-400" x-text="v.class"></span>
                                            <span class="ml-auto max-w-24 truncate text-gray-500" x-text="v.preview"></span>
                                        </div>
                                    </template>
                                </div>
                                <div x-show="tab==='plots'" class="p-2" x-data="{ plotZoom: false }">
                                    <template x-if="rPlots.length === 0">
                                        <p class="py-2 text-center text-xs text-gray-400">Geen plots</p>
                                    </template>
                                    <template x-if="rPlots.length > 0">
                                        <div>
                                            <div class="mb-2 flex items-center justify-between">
                                                <div class="flex items-center gap-1">
                                                    <button @click="plotIndex = Math.max(0, plotIndex-1)" :disabled="plotIndex===0" class="rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-200 disabled:opacity-30">◀</button>
                                                    <span class="text-xs text-gray-500" x-text="(plotIndex+1)+'/'+rPlots.length"></span>
                                                    <button @click="plotIndex = Math.min(rPlots.length-1, plotIndex+1)" :disabled="plotIndex>=rPlots.length-1" class="rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-200 disabled:opacity-30">▶</button>
                                                </div>
                                                <button @click="clearPlots(); plotIndex=0" class="text-xs text-gray-400 hover:text-red-500">Wissen</button>
                                            </div>
                                            <div x-show="plotZoom" @click="plotZoom=false" class="fixed inset-0 z-40 bg-black/50"></div>
                                            <img :src="rPlots[plotIndex]" class="w-full cursor-zoom-in rounded border" @click="plotZoom = !plotZoom" :class="plotZoom ? 'fixed inset-4 z-50 h-auto w-auto max-h-[90vh] max-w-[90vw] m-auto object-contain shadow-2xl cursor-zoom-out bg-white p-2 rounded-lg' : ''"
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Compile output log --}}
                <template x-if="compileOutput && !pdfUrl">
                    <pre class="p-2 text-xs text-gray-600 whitespace-pre-wrap overflow-y-auto" x-text="compileOutput"></pre>
                </template>

                {{-- Geen output --}}
                <template x-if="!pdfUrl && rOutput.length === 0 && rPlots.length === 0 && !compileOutput">
                    <p class="py-8 text-center text-xs text-gray-400">Geen output beschikbaar</p>
                </template>
            </div>
        </aside>
    </div>

</body>
</html>
