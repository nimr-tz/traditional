@props(['url'])
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 30px 0;">
    <tr>
        <td bgcolor="#135eeb" style="background-color: #135eeb;">
            <a href="{{ $url }}" class="email-button" style="display: inline-block; padding: 14px 26px; color: #ffffff; font-size: 16px; line-height: 20px; font-weight: 700; text-decoration: none;">
                {{ $slot }}
            </a>
        </td>
    </tr>
</table>
