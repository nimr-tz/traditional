<x-email.layout
    :title="$isReplacement ? 'Replacement student document' : 'Student verification required'"
    preheader="A student document is awaiting verification."
    eyebrow="Reviewer action required"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        {{ $student->full_name }} has {{ $isReplacement ? 'uploaded a replacement student document' : 'registered using a student category' }}. Verify the document before student-rate payment is enabled.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #f6f8fb; border-left: 4px solid #4c8a1f;">
        <tr>
            <td style="padding: 18px 20px; color: #27364b; font-size: 14px; line-height: 22px;">
                <strong style="color: #0f1b2d;">{{ $student->full_name }}</strong><br>
                {{ $student->email }}<br>
                {{ str_replace('_', ' ', ucfirst($student->fee_category)) }}
            </td>
        </tr>
    </table>

    <x-email.button :url="$reviewUrl">Review student document</x-email.button>

    <x-email.signoff />
</x-email.layout>
