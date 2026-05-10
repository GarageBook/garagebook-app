<h1>{{ __('emails.contact_heading') }}</h1>

<p><strong>{{ __('emails.name_label') }}:</strong> {{ $name }}</p>
<p><strong>{{ __('emails.email_label') }}:</strong> {{ $email }}</p>

<p><strong>{{ __('emails.message_label') }}:</strong></p>
<p>{!! nl2br(e($body)) !!}</p>
