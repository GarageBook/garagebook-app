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

    @php($featuredPage = \App\Support\InternalContentLinks::featuredPage())

    @if($featuredPage)
        <aside class="gb-content-promo gb-content-promo--inline">
            <p class="gb-content-promo__eyebrow">
                Aanbevolen gids
            </p>

            <h2 class="gb-content-promo__title">
                Zoek je een slim alternatief voor een universeel onderhoudsboekje?
            </h2>

            <p class="gb-content-promo__text">
                Lees waarom digitale onderhoudshistorie in 2026 vaak praktischer is dan een los boekje.
            </p>

            <a href="/{{ $featuredPage->slug }}" class="gb-content-promo__link">
                Bekijk {{ $featuredPage->title }}
            </a>
        </aside>
    @endif

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
