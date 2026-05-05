@extends('layouts.public')

@section('title', 'Blogs over motoronderhoud en onderhoudshistorie | GarageBook')
@section('meta_description', 'Lees praktische blogs over motoronderhoud, onderhoudshistorie, digitaal onderhoud bijhouden en de invloed daarvan op vertrouwen en verkoopwaarde.')

@section('content')

<section class="gb-page-shell">
    <header>
        <h1 class="gb-page-title gb-page-title--tight">
            Blogs over motoronderhoud en onderhoudshistorie
        </h1>

        <p class="gb-home-lead">
            Praktische gidsen voor motorrijders die onderhoud slimmer willen bijhouden, hun historie beter willen documenteren en de waarde van hun motor zichtbaar willen maken.
        </p>
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
                <a href="https://garagebook.nl/blog/{{ $blog->slug }}/" class="gb-card-link">
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
