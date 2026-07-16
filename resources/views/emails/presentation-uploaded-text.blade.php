{{ $isReplacement ? 'Replacement presentation received' : 'Presentation received' }}

Dear {{ $recipientName }},

We have received your {{ $isReplacement ? 'replacement ' : '' }}presentation file for {{ $conferenceName }}.

Abstract: {{ $submission->title }}

The organizers will review it before the conference. You'll be notified by email once it's approved.

Kind regards,
{{ config('app.name') }} Team
