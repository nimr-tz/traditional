Payment confirmed

Dear {{ $recipientName }},

We have confirmed your payment for {{ $conferenceName }}.

Amount confirmed: {{ $user->currency }} {{ number_format((float) $user->fee_amount, 2) }}

Your registration payment is complete.

Kind regards,
{{ config('app.name') }} Team
