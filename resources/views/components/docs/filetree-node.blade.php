@props(['nodes', 'depth' => 0, 'activeNode' => null])

@foreach($nodes as $node)
    @if($node->type === 'folder')
        <div class="group"
             x-data="{ open: true }"
             draggable="true"
             @dragstart.stop="$event.dataTransfer.setData('nodeId', {{ $node->id }})"
             @dragover.prevent
             @drop.prevent="$wire.moveNode($event.dataTransfer.getData('nodeId'), {{ $node->id }})">

            <div @click="open = !open"
                 class="flex cursor-pointer items-center gap-1.5 rounded px-2 py-1 text-sm hover:bg-gray-200 dark:hover:bg-gray-700"
                 style="padding-left: {{ $depth * 12 + 8 }}px"
                 @contextmenu.prevent="$dispatch('node-context', { id: {{ $node->id }}, name: '{{ addslashes($node->name) }}', x: $event.clientX, y: $event.clientY })">
                <span x-show="!open"><x-heroicon-s-folder class="h-4 w-4 text-yellow-500"/></span>
                <span x-show="open"><x-heroicon-s-folder-open class="h-4 w-4 text-yellow-500"/></span>
                <span class="truncate text-gray-700 dark:text-gray-300">{{ $node->name }}</span>
            </div>

            <div x-show="open" x-collapse>
                <x-docs.filetree-node :nodes="$node->children" :depth="$depth + 1" :activeNode="$activeNode"/>
            </div>
        </div>
    @else
        <div class="group"
             draggable="true"
             @dragstart.stop="$event.dataTransfer.setData('nodeId', {{ $node->id }})"
             @click="$wire.openNode({{ $node->id }})"
             @contextmenu.prevent="$dispatch('node-context', { id: {{ $node->id }}, name: '{{ addslashes($node->name) }}', x: $event.clientX, y: $event.clientY })"
             class="{{ $activeNode?->id === $node->id ? 'bg-primary-50 dark:bg-primary-900/20' : '' }} flex cursor-pointer items-center gap-1.5 rounded px-2 py-1 text-sm hover:bg-gray-200 dark:hover:bg-gray-700"
             style="padding-left: {{ $depth * 12 + 8 }}px">
            <x-heroicon-o-document class="h-4 w-4 flex-shrink-0 text-gray-400"/>
            <span class="truncate text-gray-700 dark:text-gray-300">{{ $node->name }}</span>
        </div>
    @endif
@endforeach
