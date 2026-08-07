{{ $subject }}

Dear {{ $recipientName }},

@foreach($paragraphs as $paragraph)
{{ trim($paragraph) }}

@endforeach
Kind regards,
{{ config('app.name') }} Team
