@extends('layouts.public')

@section('title', $page->title)

@section('content')

<div class="gb-content-shell">

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

            @if(session('contact_status'))
                <div class="gb-contact-card__status">
                    {{ session('contact_status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="gb-contact-card__error">
                    Controleer de ingevulde velden en probeer het opnieuw.
                </div>
            @endif

            <form method="POST" action="{{ route('contact.submit') }}" class="gb-contact-form">
                @csrf

                <div class="gb-contact-form__field">
                    <label for="name" class="gb-contact-form__label">Naam</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        class="gb-contact-form__input"
                        required
                    >
                    @error('name')
                        <div class="gb-contact-form__field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="gb-contact-form__field">
                    <label for="email" class="gb-contact-form__label">E-mailadres</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        class="gb-contact-form__input"
                        required
                    >
                    @error('email')
                        <div class="gb-contact-form__field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="gb-contact-form__field">
                    <label for="message" class="gb-contact-form__label">Vragen of opmerkingen</label>
                    <textarea
                        id="message"
                        name="message"
                        class="gb-contact-form__textarea"
                        rows="6"
                        required
                    >{{ old('message') }}</textarea>
                    @error('message')
                        <div class="gb-contact-form__field-error">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="gb-button gb-button--primary gb-contact-form__submit">
                    Verstuur
                </button>
            </form>
        </div>
    @endif

</div>

@endsection
