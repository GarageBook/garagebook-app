@extends('layouts.public')

@section('title', $page->title . ' - GarageBook')
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($page->content), 155))

@section('content')

<article class="gb-content-shell">

    <h1 class="gb-page-title">
        {{ $page->title }}
    </h1>

    @if($page->hero_image)
        <img
            src="{{ asset('storage/' . $page->hero_image) }}"
            alt="{{ $page->title }}"
            class="gb-hero"
        >
    @endif

    <div>
        {!! $page->content !!}
    </div>

    @if($page->slug === 'contact')
        <div class="gb-contact-card">
            <h2 class="gb-contact-card__title">
                Neem contact op
            </h2>

            <p class="gb-contact-card__intro">
                Stel je vraag of laat een opmerking achter. We reageren zo snel mogelijk.
            </p>

            @if(request()->boolean('contact_sent'))
                <div class="gb-contact-card__status">
                    Je bericht is verzonden. We nemen zo snel mogelijk contact met je op.
                </div>
            @endif

            <form method="POST" action="https://api.web3forms.com/submit" class="gb-contact-form">
                <input type="hidden" name="access_key" value="cbee6c88-fd19-48a6-bc15-e21d10b8849d">
                <input type="hidden" name="subject" value="Nieuw contactbericht via GarageBook">
                <input type="hidden" name="from_name" value="GarageBook contactformulier">
                <input type="hidden" name="redirect" value="{{ url('/contact?contact_sent=1') }}">
                <input type="checkbox" name="botcheck" class="gb-contact-form__botcheck" tabindex="-1" autocomplete="off">

                <div class="gb-contact-form__field">
                    <label for="name" class="gb-contact-form__label">Naam</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        class="gb-contact-form__input"
                        required
                    >
                </div>

                <div class="gb-contact-form__field">
                    <label for="email" class="gb-contact-form__label">E-mailadres</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        class="gb-contact-form__input"
                        required
                    >
                </div>

                <div class="gb-contact-form__field">
                    <label for="message" class="gb-contact-form__label">Vragen of opmerkingen</label>
                    <textarea
                        id="message"
                        name="message"
                        class="gb-contact-form__textarea"
                        rows="6"
                        required
                    ></textarea>
                </div>

                <button type="submit" class="gb-button gb-button--primary gb-contact-form__submit">
                    Verstuur
                </button>
            </form>
        </div>
    @endif

</article>

@endsection
