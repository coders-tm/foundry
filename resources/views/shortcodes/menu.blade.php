<ul class="{{ $class }}">
    @foreach ($items as $item)
        <li class="mega-menu-item {{ $item['active'] }}">
            <a class="mega-menu-link" href="{{ $item['href'] }}">{{ $item['label'] }}</a>
            @if (isset($item['items']) && count($item['items']))
                @include('includes.sub-menu', $item)
            @endif
        </li>
    @endforeach
</ul>
