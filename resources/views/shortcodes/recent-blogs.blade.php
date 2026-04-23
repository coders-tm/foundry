<aside class="widget widget--recent-post with-title">
    <h3 class="widget--title">Recent Posts</h3>
    <ul class="widget-post ct-recent-post-list">
        @foreach ($blogs as $blog)
            <li>
                <a href="{{ $blog->url }}">
                    <img class="img-fluid" src="{{ $blog->thumbnail?->url ?? 'https://placehold.co/300x200' }}"
                        alt="post-img">
                </a>
                <div class="post-detail">
                    <span class="post-date">{{ $blog->created_at->format('d M, Y') }}</span>
                    <a href="{{ $blog->url }}">{{ $blog->title }}</a>
                </div>
            </li>
        @endforeach
    </ul>
</aside>
