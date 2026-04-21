@extends('layouts.public')

@section('title', 'Blogs - GarageBook')
@section('meta_description', 'Lees blogs van GarageBook over motoronderhoud, onderhoudshistorie, documentatie en de waarde van een goed bijgehouden motor.')

@section('content')

<section class="gb-page-shell">
    <header>
        <h1 class="gb-page-title gb-page-title--tight">
            Blogs
        </h1>
    </header>

    <div class="gb-card-grid">
        @foreach($blogs as $blog)
            <article class="gb-card-surface">
                <a href="/blogs/{{ $blog->slug }}" class="gb-card-link">
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
                </a>
            </article>
        @endforeach
    </div>
</section>

@endsection
