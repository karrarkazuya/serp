{{--
    <x-tree :nodes="$nodes" empty-text="No records found." />

    Each node in $nodes must have:
      id       int
      name     string
      url      string      — link to detail page
      avatar   string|null — image URL
      initials string      — fallback initials (2 chars)
      subtitle string|null — secondary label (e.g. job title)
      meta     string|null — tertiary label  (e.g. department)
      badge    string|null — optional status text
      badge_color string|null — tailwind color key: green|blue|orange|red|gray
      children array       — same structure, recursive
--}}
<div {{ $attributes->class(['flex-1 overflow-auto']) }}>
    @if(empty($nodes))
        <div class="py-20 text-center text-sm text-gray-400">{{ $emptyText }}</div>
    @else
        <div class="p-4 space-y-2">
            @foreach($nodes as $node)
                @include('components._tree-node', ['node' => $node, 'depth' => 0])
            @endforeach
        </div>
    @endif
</div>
