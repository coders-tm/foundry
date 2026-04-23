<div class="card-body">
    <h5 class="card-title">{{ $title }}</h5>
    <div class="related-posts">
        @foreach ($blogs as $blog)
            <div class="related-post mb-3">
                <div class="d-flex">
                    @if (isset($blog['thumbnail']['url']))
                        <img src="{{ $blog['thumbnail']['url'] }}" alt="{{ $blog->title }}" class="me-3 rounded"
                            width="60" height="60" />
                    @else
                        <img src="/statics/img/gym/blog1.jpg" alt="{{ $blog->title }}" class="me-3 rounded"
                            width="60" height="60" />
                    @endif
                    <div>
                        <h6 class="mb-1">
                            <a href="/blog/{{ $blog->slug }}" class="text-decoration-none">
                                {{ $blog->title }}
                            </a>
                        </h6>
                        <small class="text-muted">{{ $blog->created_at?->format('M d, Y') }}</small>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
