@extends('layouts.public')

@section('title', 'Blogs - GarageBook')

@section('content')

<div class="gb-page-shell">
    <h1 class="gb-page-title gb-page-title--tight">
        Blogs
    </h1>

    <div class="gb-card-grid">
        @foreach($blogs as $blog)
            <a href="/blogs/{{ $blog->slug }}" class="gb-card-link">
                <div class="gb-card-surface">
                    @if($blog->hero_image)
                        <img
                            src="/blog-image/{{ $blog->hero_image }}"
                            alt="{{ $blog->title }}"
                            class="gb-card-image"
                        >
                    @endif

                    <div class="gb-card-body">
                        <h2 class="gb-card-title">
                            {{ $blog->title }}
                        </h2>

                        <p class="gb-card-text">
                            {{ $blog->excerpt }}
                        </p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</div>

@endsection
