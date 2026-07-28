<x-email.layout
    :title="$subject"
    :preheader="$subject"
    eyebrow="Announcement"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    @foreach($paragraphs as $paragraph)
        <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
            {!! nl2br(e(trim($paragraph))) !!}
        </p>
    @endforeach

    <x-email.signoff />
</x-email.layout>
