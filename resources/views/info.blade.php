<!DOCTYPE html>
<html lang="nl" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Info — Docs</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900">

<div class="flex min-h-screen">
    <x-drive-sidebar scope="info" />

    <main class="flex-1">
        <div class="sticky top-0 z-30 flex items-center justify-end gap-3 border-b border-gray-200 bg-white/90 px-6 py-3 backdrop-blur">
            <span class="hidden text-sm text-gray-700 sm:inline">{{ auth()->user()?->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100">Uitloggen</button>
            </form>
        </div>

        <div class="mx-auto max-w-3xl px-6 py-10">
            <header class="mb-8">
                <h1 class="text-3xl font-semibold tracking-tight">Over Docs</h1>
                <p class="mt-2 text-gray-600">Een browser-editor voor wetenschappelijke documenten. Schrijf, compileer, deel.</p>
            </header>

            {{-- Compileerbaar --}}
            <section class="mb-8">
                <h2 class="mb-3 text-lg font-semibold text-gray-900">Wat kan je compileren?</h2>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="px-4 py-2.5">Extensie</th><th class="px-4 py-2.5">Compiler</th><th class="px-4 py-2.5">Voor</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr><td class="px-4 py-2 font-mono">.tex</td><td class="px-4 py-2">pdflatex · xelatex · lualatex</td><td class="px-4 py-2">Klassiek LaTeX</td></tr>
                            <tr><td class="px-4 py-2 font-mono">.md</td><td class="px-4 py-2">Pandoc → XeLaTeX</td><td class="px-4 py-2">Markdown</td></tr>
                            <tr><td class="px-4 py-2 font-mono">.rmd</td><td class="px-4 py-2">rmarkdown → XeLaTeX</td><td class="px-4 py-2">R-code in Markdown</td></tr>
                            <tr><td class="px-4 py-2 font-mono">.rnw · .rtex</td><td class="px-4 py-2">knitr → pdflatex</td><td class="px-4 py-2">R-code in LaTeX (noweb)</td></tr>
                            <tr><td class="px-4 py-2 font-mono">.typ</td><td class="px-4 py-2">Typst</td><td class="px-4 py-2">Modern alternatief voor LaTeX</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-gray-500">R-bestanden (<span class="font-mono">.r</span>) kun je ook rechtstreeks in de editor uitvoeren.</p>
            </section>

            {{-- Editor --}}
            <section class="mb-8">
                <h2 class="mb-3 text-lg font-semibold text-gray-900">Editor &amp; preview</h2>
                <p class="text-sm text-gray-700">Syntax-highlighting voor 20+ talen — <span class="font-mono text-gray-600">.tex · .py · .js · .css · .yaml · .json · .php · .sh · .r</span> en meer. Afbeeldingen en PDFs (<span class="font-mono text-gray-600">.jpg .png .svg .pdf</span>) worden in een viewer getoond.</p>
            </section>

            {{-- Drive concepten --}}
            <section class="mb-8">
                <h2 class="mb-3 text-lg font-semibold text-gray-900">Mijn Drive · Met mij gedeeld · Gedeelde drives</h2>
                <div class="space-y-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h6l2 2h10v10a2 2 0 0 1-2 2H3z"/></svg>
                            <h3 class="font-medium">Mijn Drive</h3>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">Jouw persoonlijke werkruimte. Projecten die jij maakt, hier. Jij beslist wie ze ziet.</p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 8l4 4-4 4M7 8l-4 4 4 4M14 4l-4 16"/></svg>
                            <h3 class="font-medium">Met mij gedeeld</h3>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">Projecten die anderen met jou hebben gedeeld via de <strong>Delen</strong>-knop. Eigenaar blijft de ander; jij krijgt lees- of bewerk-rechten.</p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7l9-4 9 4-9 4-9-4zm0 5l9 4 9-4M3 17l9 4 9-4"/></svg>
                            <h3 class="font-medium">Gedeelde drives</h3>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">Gezamenlijke werkruimte voor teams. Iedereen die lid is, kan álle projecten in die drive zien — geen één persoonlijke eigenaar. Goed voor onderzoeksgroepen of klasprojecten.</p>
                    </div>
                </div>
            </section>

            {{-- Rechten --}}
            <section class="mb-8">
                <h2 class="mb-3 text-lg font-semibold text-gray-900">Rechten</h2>
                <ul class="space-y-1.5 text-sm">
                    <li class="flex items-baseline gap-2"><span class="inline-flex w-16 shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-center text-xs font-medium text-gray-700">Lezen</span><span class="text-gray-700">project openen, PDF compileren, files downloaden</span></li>
                    <li class="flex items-baseline gap-2"><span class="inline-flex w-16 shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-center text-xs font-medium text-amber-700">Bewerken</span><span class="text-gray-700">alles van <em>Lezen</em>, plus files wijzigen, uploaden, verwijderen</span></li>
                    <li class="flex items-baseline gap-2"><span class="inline-flex w-16 shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-center text-xs font-medium text-emerald-700">Eigenaar</span><span class="text-gray-700">alles van <em>Bewerken</em>, plus delen en verwijderen</span></li>
                </ul>
            </section>

            {{-- Prullenbak --}}
            <section class="mb-8">
                <h2 class="mb-3 text-lg font-semibold text-gray-900">Prullenbak</h2>
                <p class="text-sm text-gray-700">Verwijderde projecten en drives belanden in de <strong>Prullenbak</strong> en blijven daar tot je ze handmatig herstelt of definitief verwijdert.</p>
            </section>

            <footer class="mt-12 border-t border-gray-200 pt-4 text-xs text-gray-500">
                <a href="{{ route('projects.index') }}" class="text-amber-700 hover:underline">← terug naar Mijn Drive</a>
            </footer>
        </div>
    </main>
</div>

</body>
</html>
