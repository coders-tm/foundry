@if ($categories->count())
    <div class="blog-categories card-body {{ $class ?? '' }}">
        <h5 class="card-title">Categories</h5>
        @if ($layout === 'list')
            <ul class="list-unstyled mb-0">
                @foreach ($categories as $item)
                    <li>
                        <a class="text-decoration-none d-flex justify-content-between"
                            href="{{ url('/blog?category=' . urlencode($item['category'])) }}">
                            <span> {{ $item['category'] }}</span>
                            @if ($count === 'true')
                                <span class="badge bg-light text-dark">{{ $item['count'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @elseif($layout === 'inline')
            @foreach ($categories as $item)
                <a href="{{ url('/blog?category=' . urlencode($item['category'])) }}"
                    class="badge bg-light text-dark m-1">
                    {{ $item['category'] }}
                    @if ($count === 'true')
                        <span class="badge bg-secondary rounded-pill">{{ $item['count'] }}</span>
                    @endif
                </a>
            @endforeach
        @else
            <div class="list-group">
                @foreach ($categories as $item)
                    <a href="{{ url('/blog?category=' . urlencode($item['category'])) }}"
                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        {{ $item['category'] }}
                        @if ($count === 'true')
                            <span class="badge bg-primary rounded-pill">{{ $item['count'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif
