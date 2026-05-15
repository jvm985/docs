@props(['key', 'label', 'activeKey' => null, 'activeDir' => 'desc'])

@php
    $isActive = $activeKey === $key;
    $nextDir = $isActive && $activeDir === 'asc' ? 'desc' : 'asc';
    $params = array_merge(request()->query(), ['sort' => $key, 'dir' => $nextDir]);
@endphp

<a href="{{ url()->current().'?'.http_build_query($params) }}"
   class="inline-flex items-center gap-1 hover:text-gray-700 {{ $isActive ? 'text-gray-900' : '' }}"
   data-testid="sort-{{ $key }}">
    <span>{{ $label }}</span>
    @if($isActive)
        @if($activeDir === 'asc')
            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" aria-label="oplopend"><path d="M12 4l-8 12h16z"/></svg>
        @else
            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" aria-label="aflopend"><path d="M12 20l8-12H4z"/></svg>
        @endif
    @else
        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" class="opacity-30"><path d="M7 10l5-5 5 5zM7 14l5 5 5-5z"/></svg>
    @endif
</a>
