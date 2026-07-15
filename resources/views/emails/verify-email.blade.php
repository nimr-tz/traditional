<x-email.layout
    title="Confirm your email address"
    preheader="Confirm your email address to activate your conference account."
    eyebrow="Account verification"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
    :footer="'This message was sent because an account was created using '.$emailAddress.'.'"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Thank you for creating your conference account. Please confirm that
        <strong style="color: #0f1b2d; font-weight: 700;">{{ $emailAddress }}</strong>
        belongs to you so we can securely activate your account.
    </p>

    <x-email.button :url="$verificationUrl">Verify email address</x-email.button>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 0 0 26px; background-color: #eef7e8;">
        <tr>
            <td style="padding: 17px 20px; color: #24422b; font-size: 14px; line-height: 22px;">
                After verification, you will be taken to your dashboard to continue your registration. For your security, this link expires in {{ $expiryMinutes }} minutes.
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 10px; color: #5b687a; font-size: 13px; line-height: 21px;">
        If the button does not work, copy and paste this address into your browser:
    </p>
    <p style="margin: 0; padding: 14px 16px; background-color: #f6f8fb; border: 1px solid #e3e8f0; color: #135eeb; font-size: 12px; line-height: 19px; word-break: break-all;">
        <a href="{{ $verificationUrl }}" style="color: #135eeb; text-decoration: underline;">{{ $verificationUrl }}</a>
    </p>

    <hr style="margin: 32px 0 24px; border: 0; border-top: 1px solid #e4eaf3;">

    <p style="margin: 0 0 18px; color: #5b687a; font-size: 13px; line-height: 21px;">
        If you did not create this account, you can safely ignore this email. No further action is required.
    </p>

    <x-email.signoff />
</x-email.layout>
