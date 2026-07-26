<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact message | GadgetDrop</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
<tr><td align="center" style="padding:32px 16px;">

    <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.07);">

        <tr>
            <td style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);padding:28px 40px;">
                <p style="margin:0 0 4px;font-size:12px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,0.6);">Contact form</p>
                <p style="margin:0;font-size:24px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;">New message: {{ ucfirst($topic) }}</p>
            </td>
        </tr>

        <tr><td style="height:4px;background:linear-gradient(90deg,#4f46e5,#7c3aed,#4f46e5);"></td></tr>

        <tr>
            <td style="padding:32px 40px;">
                <p style="margin:0 0 6px;font-size:13px;color:#6b7280;">From</p>
                <p style="margin:0 0 20px;font-size:16px;font-weight:700;color:#111827;">
                    {{ $senderName }}
                    <span style="font-weight:400;color:#6b7280;">&lt;{{ $senderEmail }}&gt;</span>
                </p>

                <p style="margin:0 0 6px;font-size:13px;color:#6b7280;">Message</p>
                <div style="font-size:15px;line-height:1.7;color:#374151;background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:20px 24px;white-space:pre-wrap;">{{ $messageBody }}</div>

                <p style="margin:24px 0 0;font-size:13px;color:#9ca3af;">
                    Reply directly to this email to answer. The reply-to address is the sender's.
                </p>
            </td>
        </tr>

    </table>

</td></tr>
</table>

</body>
</html>
