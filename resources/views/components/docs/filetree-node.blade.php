@props(['nodes', 'depth' => 0, 'activeNode' => null])

@foreach($nodes as $node)
    @if($node->type === 'folder')
        <div x-data="{ open: true }">
            <button type="button"
                    @click="open = !open"
                    class="flex w-full cursor-pointer items-center gap-1.5 rounded px-2 py-1 text-left text-sm hover:bg-gray-200 dark:hover:bg-gray-700"
                    style="padding-left: {{ $depth * 12 + 8 }}px">
                <span x-show="!open"><x-heroicon-s-folder class="h-4 w-4 text-yellow-500"/></span>
                <span x-show="open"><x-heroicon-s-folder-open class="h-4 w-4 text-yellow-500"/></span>
                <span class="truncate text-gray-700 dark:text-gray-300">{{ $node->name }}</span>
            </button>

            <div x-show="open" x-collapse>
                <x-docs.filetree-node :nodes="$node->children" :depth="$depth + 1" :activeNode="$activeNode"/>
            </div>
        </div>
    @else
        <button type="button"
                wire:click="openNode({{ $node->id }})"
                class="{{ $activeNode?->id === $node->id ? 'bg-primary-50 dark:bg-primary-900/20' : '' }} flex w-full cursor-pointer items-center gap-1.5 rounded px-2 py-1 text-left text-sm hover:bg-gray-200 dark:hover:bg-gray-700"
                style="padding-left: {{ $depth * 12 + 8 }}px">
            <x-heroicon-o-document class="h-4 w-4 flex-shrink-0 text-gray-400"/>
            <span class="truncate text-gray-700 dark:text-gray-300">{{ $node->name }}</span>
        </button>
    @endif
@endforeach
