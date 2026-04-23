<article class="post ct-blog-classic clearfix">
    <!-- post-featured-wrapper -->
    <div class="ct-post-featured-wrapper ct-featured-wrapper">
        <div class="ct-post-featured">
            <img class="img-fluid" src="{{ $blog->thumbnail?->url ?? 'https://placehold.co/1200x800' }}" alt="blog-img" />
        </div>
    </div>
    <!-- ct-blog-single-content -->
    <div class="ct-blog-single-content">
        <div class="ct-post-entry-header">
            <div class="post-meta">
                <time class="entry-date published me-3" datetime="{{ $blog->created_at }}">
                    {{ $blog->created_at->format('d M, Y') }}
                </time>
                <span class="ct-meta-line byline">
                    @foreach ($blog->tags as $tag)
                        @if ($loop->first)
                            <i class="fa fa-tag"></i>
                        @endif
                        <a href="javascript:void(0);" tabindex="0">
                            {{ $tag->label }}
                        </a>
                    @endforeach
                </span>
            </div>
            <h3>{{ $blog->title }}</h3>
            <div class="entry-content">
                {!! $blog->description !!}
            </div>
        </div>
    </div><!-- ct-blog-single-content end -->
</article>
