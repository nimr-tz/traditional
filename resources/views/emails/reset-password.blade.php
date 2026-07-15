<x-email.layout
    title="Reset your password"
    preheader="Use this secure link to reset your conference account password."
    eyebrow="Account security"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
    :footer="'This password reset message was sent to '.$emailAddress.'.'"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        We received a request to reset the password for your conference account. Use the button below to choose a new password.
    </p>

    <x-email.button :url="$resetUrl">Reset my password</x-email.button>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 0 0 26px; background-color: #eef7e8;">
        <tr>
            <td style="padding: 17px 20px; color: #24422b; font-size: 14px; line-height: 22px;">
                For your security, this password reset link expires in {{ $expiryMinutes }} minutes.
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 10px; color: #5b687a; font-size: 13px; line-height: 21px;">
        If the button does not work, copy and paste this address into your browser:
    </p>
    <p style="margin: 0; padding: 14px 16px; background-color: #f6f8fb; border: 1px solid #e3e8f0; color: #135eeb; font-size: 12px; line-height: 19px; word-break: break-all;">
        <a href="{{ $resetUrl }}" style="color: #135eeb; text-decoration: underline;">{{ $resetUrl }}</a>
    </p>

    <hr style="margin: 32px 0 24px; border: 0; border-top: 1px solid #e4eaf3;">

    <p style="margin: 0 0 18px; color: #5b687a; font-size: 13px; line-height: 21px;">
        If you did not request a password reset, you can safely ignore this email. No changes will be made to your account.
    </p>

    <x-email.signoff />
</x-email.layout>
