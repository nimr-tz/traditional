Registration fee waived

Dear {{ $recipientName }},

Your registration fee for {{ $conferenceName }} has been waived by our finance team. No payment is required.

@if($notes)
Note:
{{ $notes }}

@endif
Your registration is now complete and confirmed.

Kind regards,
{{ config('app.name') }} Team
