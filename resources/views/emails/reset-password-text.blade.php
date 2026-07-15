Reset your password

Dear {{ $recipientName }},

We received a request to reset the password for your conference account. Use the link below to choose a new password.

Reset my password:
{!! $resetUrl !!}

For your security, this password reset link expires in {{ $expiryMinutes }} minutes.

If you did not request a password reset, you can safely ignore this email. No changes will be made to your account.

Kind regards,
{{ config('app.name') }} Team
