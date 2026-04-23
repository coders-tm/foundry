<ul class="mega-submenu">
    @foreach ($items as $item)
        <li class="mega-menu-item {{ $item['active'] }}">
            <a href="{{ $item['href'] }}">
                {{ $item['label'] }}
            </a>
            @if (isset($item['items']) && count($item['items']))
                @include('includes.sub-menu', $item)
            @endif
        </li>
    @endforeach
</ul>
