<div class="row blogs">
    @foreach ($blogs as $blog)
        <div class="col-lg-4 col-md-6">
            <div class="featured-imagebox featured-imagebox-post style3">
                <div class="ct-post-thumbnail featured-thumbnail">
                    <img class="img-fluid" src="{{ $blog->thumbnail?->url ?? 'https://placehold.co/1200x800' }}"
                        alt="{{ $blog->title }}">
                </div>
                <div class="featured-content featured-content-post">
                    <div class="post-header">
                        <div class="cat_block-wrapper">
                            <span class="cat_block">
                                <time class="entry-date published" datetime="{{ $blog->created_at }}">
                                    {{ $blog->created_at->format('d M, Y') }}
                                </time>
                            </span>
                        </div>
                        <div class="post-title featured-title">
                            <h4><a href="{{ $blog->url }}" tabindex="0">{{ $blog->title }}</a></h4>
                        </div>
                    </div>
                    <div class="post-desc featured-desc">
                        <p>{{ $blog->short_desc }}...</p>
                    </div>
                    <div class="post-bottom ct-post-link">
                        <a class="btn btn-inline btn--sm btn--darkgrey" href="{{ $blog->url }}" tabindex="0">
                            READ MORE
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="col-lg-12">
        @if (!$blogs->count())
            <div class="featured-content">
                <div class="featured-title">
                    <h5 class="m-0">Empty</h5>
                    <p class="title">
                        No blogs to display at this time. Please check back later for
                        any updates.
                    </p>
                </div>
            </div>
        @else
            {{ $blogs->links() }}
        @endif
    </div>
</div>
