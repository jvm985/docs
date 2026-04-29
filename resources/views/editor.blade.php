<!DOCTYPE html>
<html lang="nl" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $project->name }} — Docs</title>
    @vite(['resources/css/app.css', 'resources/js/editor.js'])
</head>
<body class="h-full bg-gray-100 text-gray-900" x-data="editorApp({{ $project->id }})">

    {{-- Top bar --}}
    <header class="flex h-10 items-center justify-between border-b bg-white px-4">
        <div class="flex items-center gap-3">
            <a href="/admin/projects" class="text-sm text-gray-500 hover:text-gray-800">&larr; Projecten</a>
            <span class="text-sm font-semibold" x-text="projectName"></span>
        </div>
        <div class="flex items-center gap-2">
            <template x-if="activeNode && isCompilable(activeNode.name)">
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
            <template x-if="activeNode && isExecutable(activeNode.name)">
                <button @click="executeR()" class="rounded bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600">Uitvoeren</button>
            </template>
        </div>
    </header>

    <div class="flex" style="height: calc(100vh - 2.5rem);">

        {{-- Links: Filetree --}}
        <aside class="flex w-60 flex-shrink-0 flex-col border-r bg-gray-50">
            <div class="flex items-center justify-between border-b px-3 py-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Bestanden</span>
                <div class="flex gap-1">
                    <button @click="createItem('file')" title="Nieuw bestand" class="rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                    </button>
                    <button @click="createItem('folder')" title="Nieuwe map" class="rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/></svg>
                    </button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto py-1" id="filetree"></div>
        </aside>

        {{-- Midden: CodeMirror editor --}}
        <main class="flex flex-1 flex-col overflow-hidden">
            <div class="flex h-8 items-center border-b bg-white px-4">
                <span class="text-xs text-gray-500" x-text="activeNode ? activeNode.name : 'Selecteer een bestand'"></span>
                <span x-show="saving" class="ml-2 text-xs text-gray-400">Opslaan...</span>
                <span x-show="saved" class="ml-2 text-xs text-green-500">✓</span>
            </div>
            <div class="relative flex-1">
                <div x-ref="editorContainer" class="h-full w-full"></div>
                <template x-if="!activeNode">
                    <div class="absolute inset-0 flex items-center justify-center bg-gray-50">
                        <p class="text-sm text-gray-400">Open een bestand om te bewerken</p>
                    </div>
                </template>
            </div>
        </main>

        {{-- Rechts: Output --}}
        <aside class="flex w-80 flex-shrink-0 flex-col border-l bg-white">
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
                <template x-if="rOutput.length > 0 || rPlots.length > 0">
                    <div class="flex flex-1 flex-col overflow-hidden">
                        {{-- R console output (bovenste helft) --}}
                        <div class="flex-1 overflow-y-auto border-b p-2 font-mono text-xs">
                            <template x-for="(entry, i) in rOutput" :key="i">
                                <div class="mb-0.5">
                                    <span x-show="entry.type === 'code'" class="block text-blue-600" x-text="'> ' + entry.text"></span>
                                    <span x-show="entry.type === 'output'" class="block text-gray-800" x-text="entry.text"></span>
                                    <span x-show="entry.type === 'error'" class="block text-red-500" x-text="entry.text"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Tabs: variabelen / plots (onderste helft) --}}
                        <div class="flex flex-col" style="height: 40%;" x-data="{ tab: 'vars' }">
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
                                <div x-show="tab==='plots'" class="space-y-2 p-2">
                                    <template x-if="rPlots.length === 0">
                                        <p class="py-2 text-center text-xs text-gray-400">Geen plots</p>
                                    </template>
                                    <template x-for="(p, i) in rPlots" :key="i">
                                        <img :src="p" class="w-full rounded border">
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
