<!DOCTYPE html>
<html lang="nl" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $project->name }} — Docs</title>
    @vite(['resources/css/app.css', 'resources/js/editor.js'])
    <style>
        [x-cloak]{display:none!important}
        .resize-h { width: 5px; cursor: col-resize; background: transparent; transition: background .15s; flex-shrink: 0; }
        .resize-h:hover, .resize-h.active { background: #1f8c4d; }
        .resize-v { height: 5px; cursor: row-resize; background: transparent; transition: background .15s; flex-shrink: 0; }
        .resize-v:hover, .resize-v.active { background: #1f8c4d; }
        /* Tijdens een actieve drag de iframe (pdf-frame) + andere captors
         * neutraliseren zodat de muis-events bij window aankomen. Zonder dit
         * vangt de iframe mousemove + mouseup zodra de cursor erover gaat,
         * waardoor (a) de PDF alleen groter kan worden en (b) mouseup wordt
         * gemist en de handler "hangt" tot de volgende hover. */
        body.is-resizing { user-select: none; }
        body.is-resizing iframe { pointer-events: none; }
        body.is-resizing.cursor-col { cursor: col-resize; }
        body.is-resizing.cursor-row { cursor: row-resize; }
        .filetree-row.drop-target { background-color: #fef3c7; outline: 2px dashed #f59e0b; outline-offset: -2px; }
        .filetree-row { user-select: none; }
        .cm-editor { height: 100%; }
        .cm-scroller { font-family: 'SF Mono', Menlo, Consolas, monospace; }
    </style>
</head>
<body class="h-full bg-gray-100 text-gray-900">

<div id="app"
     data-project-id="{{ $project->id }}"
     data-project-name="{{ $project->name }}"
     data-can-write="{{ $canWrite ? '1' : '0' }}"
     data-is-owner="{{ $project->isOwnedBy(auth()->user()) ? '1' : '0' }}"
     data-primary-file="{{ $project->primary_file ?? '' }}"
     data-compiler="{{ $project->compiler ?? 'pdflatex' }}"
     class="flex h-full flex-col">

    <header class="flex h-11 items-center justify-between border-b border-brand-950/40 bg-brand-900 px-4 text-gray-200">
        <div class="flex items-center gap-3">
            <a href="{{ route('projects.index') }}" class="flex items-center gap-2 text-sm text-gray-300 transition hover:text-white">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-amber-500 text-xs font-bold text-white">D</span>
                <span>&larr; Projecten</span>
            </a>
            <span class="text-sm font-semibold text-white">{{ $project->name }}</span>
            @if(! $canWrite)
                <span class="rounded-full bg-gray-700 px-2 py-0.5 text-xs text-gray-200" data-testid="readonly-badge">Alleen lezen</span>
            @endif
        </div>
        <div id="toolbar" class="flex items-center gap-2"></div>
    </header>

    <div class="flex flex-1 overflow-hidden">

        <aside id="left-pane" class="flex flex-col border-r bg-gray-50" style="width: 260px; min-width: 180px">
            <div class="flex h-8 items-center justify-between border-b px-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Bestanden</span>
                @if($canWrite)
                    <div id="filetree-actions" class="flex gap-1"></div>
                @endif
            </div>
            <div id="filetree" class="flex-1 overflow-y-auto py-1 text-sm" data-testid="filetree"></div>
        </aside>

        <div class="resize-h" data-resize="left"></div>

        <main class="flex flex-1 flex-col overflow-hidden" style="min-width: 200px">
            <div class="flex h-8 items-center border-b bg-white px-4 text-xs text-gray-500">
                <span id="active-file-name" data-testid="active-file-name">Selecteer een bestand</span>
                <span id="save-indicator" class="ml-2 hidden text-gray-400">opslaan…</span>
                <span id="saved-indicator" class="ml-2 hidden text-green-500">✓</span>
                <span id="readonly-hint" class="ml-2 hidden text-gray-400">— alleen lezen</span>
            </div>
            <div id="editor-host" class="relative flex-1">
                <div id="editor-empty" class="absolute inset-0 flex items-center justify-center bg-gray-50 text-sm text-gray-400">Open een bestand om te bewerken</div>
                <div id="editor-mount" class="hidden h-full w-full"></div>
                <div id="image-viewer" class="hidden h-full w-full overflow-auto bg-gray-100 p-4 text-center"></div>
                <div id="binary-notice" class="hidden h-full w-full p-4 text-sm text-gray-500"></div>
                <div id="dataframe-viewer" class="hidden h-full w-full flex-col bg-white"></div>
                <div id="geogebra-mount" class="hidden h-full w-full"></div>
            </div>
        </main>

        <div class="resize-h" data-resize="right"></div>

        <aside id="right-pane" class="flex flex-col border-l bg-white" style="width: 480px; min-width: 240px">
            <div class="flex h-8 items-center justify-between border-b px-3">
                <span class="text-xs font-semibold text-gray-500">Output</span>
                <span id="compile-status" class="text-xs text-amber-500"></span>
            </div>

            <div id="output-empty" class="p-8 text-center text-xs text-gray-400">Geen output</div>

            <iframe id="pdf-frame" class="hidden h-full w-full" data-testid="pdf-frame"></iframe>

            <pre id="compile-log" class="hidden h-full overflow-y-auto whitespace-pre-wrap p-2 text-xs text-gray-600"></pre>

            <div id="r-output" class="hidden flex-1 flex-col overflow-hidden">
                <div class="flex items-center justify-between border-b bg-gray-50 px-2 py-1 text-xs">
                    <span class="font-semibold text-gray-500">Console</span>
                    <div class="flex gap-2">
                        <button id="r-reset" class="text-gray-400 hover:text-red-500" title="Console, variabelen, plots en attached packages wissen">Sessie wissen</button>
                    </div>
                </div>
                <div id="r-console" class="overflow-y-auto p-2 font-mono text-xs" style="height: 50%" data-testid="r-console"></div>
                <div class="resize-v" data-resize="r-split"></div>
                <div class="flex flex-1 flex-col overflow-hidden">
                    <div class="flex border-b text-xs">
                        <button class="r-tab px-3 py-1 font-medium text-amber-600 border-b-2 border-amber-500" data-tab="vars">Variabelen</button>
                        <button class="r-tab px-3 py-1 font-medium text-gray-500" data-tab="plots">Plots <span id="plot-count" class="ml-1 hidden rounded-full bg-amber-100 px-1.5 text-amber-700"></span></button>
                    </div>
                    <div id="r-vars" class="flex-1 overflow-y-auto p-2 text-xs"></div>
                    <div id="r-plots" class="hidden flex-1 flex-col overflow-hidden p-2"></div>
                </div>
            </div>

            <div id="ggb-output" class="hidden flex-1 flex-col overflow-hidden" data-testid="ggb-output">
                <div class="flex items-center justify-between border-b bg-gray-50 px-2 py-1 text-xs">
                    <span class="font-semibold text-gray-500">Console</span>
                    <button id="ggb-clear-console" class="text-gray-400 hover:text-red-500" title="Console leegmaken">Wissen</button>
                </div>
                <div class="flex items-center gap-1 border-b bg-white px-2 py-1">
                    <span class="font-mono text-xs text-gray-400">›</span>
                    <input id="ggb-input"
                           class="flex-1 rounded border border-gray-200 px-2 py-1 font-mono text-xs focus:border-amber-400 focus:outline-none"
                           placeholder="GeoGebra commando — bv. f(x)=x^2 — Enter om uit te voeren"
                           data-testid="ggb-input">
                    <button id="ggb-run" class="rounded bg-amber-500 px-2 py-1 text-xs font-medium text-white hover:bg-amber-600" title="Uitvoeren (Enter)">Run</button>
                </div>
                <div id="ggb-console" class="overflow-y-auto p-2 font-mono text-xs" style="height: 40%" data-testid="ggb-console"></div>
                <div class="resize-v" data-resize="ggb-split"></div>
                <div class="flex flex-1 flex-col overflow-hidden">
                    <div class="flex border-b text-xs">
                        <button class="px-3 py-1 font-medium text-amber-600 border-b-2 border-amber-500">Objecten <span id="ggb-object-count" class="ml-1 hidden rounded-full bg-amber-100 px-1.5 text-amber-700"></span></button>
                    </div>
                    <div id="ggb-objects" class="flex-1 overflow-y-auto p-2 text-xs" data-testid="ggb-objects"></div>
                </div>
            </div>
        </aside>

    </div>
</div>

@if($canWrite)
    @include('editor._large_upload', ['project' => $project])
@endif

</body>
</html>
