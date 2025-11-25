@component('mail::message')
# Hello {{ $user->name }},

You requested a password reset for your {{ config('app.name') }} account.

@component('mail::button', ['url' => $url])
Reset Password
@endcomponent

This link will expire in {{ config('auth.passwords.'.config('auth.defaults.passwords').'.expire') }} minutes.

If you did not request this, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
