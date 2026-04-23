<div class="article-meta mb-4">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        @if ($featured)
            <span class="badge bg-primary fs-6">Featured</span>
        @endif
        <span class="badge bg-success fs-6">{{ $category }}</span>
        <span class="text-white-50">
            <i class="fas fa-calendar me-1"></i>
            {{ $datetime->format('F j, Y') }}
        </span>
        <span class="text-white-50">
            <i class="fas fa-clock me-1"></i>
            {{ $readtime }} min read
        </span>
    </div>
</div>
