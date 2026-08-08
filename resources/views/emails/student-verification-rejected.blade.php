<x-email.layout
    title="Your registration category has been updated"
    preheader="We have moved your registration to the standard participant rate — nothing is needed from you."
    eyebrow="Registration update"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Thank you for registering for the {{ $conferenceName }}, and for taking the time to submit your supporting
        document.
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        We are sorry to say that your registration does not qualify for the student rate. Please do not read this as any
        judgement on your studies or your standing — the student rate covers a narrower group than the word suggests, and
        many registrants who are genuinely studying, including doctoral candidates, fall outside it.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #f6f8fb; border-left: 4px solid #135eeb;">
        <tr>
            <td style="padding: 18px 20px; color: #27364b; font-size: 14px; line-height: 22px;">
                <strong style="color: #0f1b2d;">Note from the review team</strong><br>
                {{ $notes }}
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        So that nothing is left for you to sort out, we have already moved your registration for you:
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 0 0 26px; background-color: #f3f9ee; border-left: 4px solid #4c8a1f;">
        <tr>
            <td style="padding: 18px 20px; color: #27364b; font-size: 15px; line-height: 24px;">
                <strong style="color: #0f1b2d;">Registration category</strong><br>
                {{ $categoryLabel }}
                @if ($feeAmount)
                    <br><br>
                    <strong style="color: #0f1b2d;">Amount payable</strong><br>
                    {{ $feeAmount }}
                @endif
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        You do not need to submit another document, and you do not need to change anything yourself.
        @if ($keptControlNumber)
            Your control number <strong>{{ $keptControlNumber }}</strong> remains valid — please go ahead and pay it as
            usual.
        @else
            Please request your control number from your payment page when you are ready.
        @endif
    </p>

    <x-email.button :url="$paymentUrl">Go to your payment page</x-email.button>

    <p style="margin: 0 0 18px; color: #5b687a; font-size: 13px; line-height: 21px;">
        If you believe the student rate should apply to you, you are welcome to upload a different document from your
        profile and we will review it again. Please note that your registration stays at the participant rate while we
        do — so you are free to pay it in the meantime and you will not lose your place. If the new document is approved,
        we will move you onto the student rate ourselves and cancel the participant bill.
    </p>

    <x-email.signoff />
</x-email.layout>
