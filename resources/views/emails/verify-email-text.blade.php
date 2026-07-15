Confirm your email address

Dear {{ $recipientName ?: 'Participant' }},

Thank you for creating your conference account for {{ $conferenceName }}.

Please confirm that {{ $emailAddress }} belongs to you so we can securely activate your account.

Verify your email address:
{!! $verificationUrl !!}

After verification, you will be taken to your dashboard to continue your registration. For your security, this link expires in {{ $expiryMinutes }} minutes.

If you did not create this account, you can safely ignore this email. No further action is required.

Kind regards,
{{ config('app.name') }} Team
