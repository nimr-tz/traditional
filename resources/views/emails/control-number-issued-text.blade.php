Your control number is ready

Dear {{ $recipientName }},

Your control number for {{ $conferenceName }} has been issued. Use it to pay your registration fee through your preferred GePG channel (bank, mobile money, or another supported payment method).

Control number: {{ $user->control_number }}
Amount due: {{ $user->currency }} {{ number_format((float) $user->fee_amount, 2) }}
Payable to: {{ $gepgPayeeName }}

Once your payment is received, we'll confirm it by email and your registration will be complete.

Kind regards,
{{ config('app.name') }} Team
