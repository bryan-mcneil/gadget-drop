<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Your return window is closing | GadgetDrop</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:'Figtree','Segoe UI',Arial,sans-serif;-webkit-font-smoothing:antialiased;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
<tr><td align="center" style="padding:32px 16px;">

    <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">

        <tr>
            <td style="padding:32px 40px 0;">
                <span style="font-size:24px;font-weight:800;color:#111827;letter-spacing:-0.5px;">Gadget<span style="color:#4f46e5;">Drop</span></span>
            </td>
        </tr>

        <tr><td style="padding:26px 40px 34px;">

            <p style="margin:0 0 14px;font-family:Consolas,'Courier New',monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#4f46e5;">Watch report</p>

            <h1 style="margin:0 0 12px;font-size:22px;font-weight:800;color:#111827;line-height:1.3;letter-spacing:-0.3px;">
                Your return window on the {{ $watch->product->name }} closes {{ $watch->expires_at->format('M j') }}
            </h1>

            <p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.65;">
                Quick honest wrap-up: the price never dropped far enough below the
                ${{ number_format($paid, 2) }} we tracked on your purchase date to make a return-and-rebuy worth it.
                And here's the good news: {{ $verdictLine }}
            </p>

            <p style="margin:0 0 22px;font-size:15px;color:#4b5563;line-height:1.65;">
                This watch retires with the window, and your email is deleted with it. That's the whole promise, kept.
            </p>

            <a href="{{ $reviewUrl }}"
                style="display:inline-block;background:#4f46e5;color:#ffffff;font-size:14px;font-weight:700;padding:12px 24px;border-radius:6px;text-decoration:none;">
                See the full price history
            </a>

            {{-- Quiet invite, never auto-subscribe --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:28px;">
                <tr><td style="border-top:1px solid #e5e7eb;padding-top:18px;">
                    <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">
                        Liked having someone watch the price for you? The weekly Drop covers what's <em>actually</em> a deal across everything we track. Join only if that sounds useful:
                        <a href="{{ url('/') }}#subscribe" style="font-weight:700;color:#4f46e5;text-decoration:none;">Join the Drop &rarr;</a>
                    </p>
                </td></tr>
            </table>

        </td></tr>

        <tr>
            <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:22px 40px;text-align:center;">
                <p style="margin:0 0 8px;font-size:12px;color:#9ca3af;line-height:1.6;">
                    You asked us to watch this price at <a href="{{ url('/') }}" style="color:#6b7280;text-decoration:underline;">gadgetdrop.tech</a>.
                </p>
                <a href="{{ $unsubscribeUrl }}" style="font-size:12px;color:#9ca3af;text-decoration:underline;">
                    Delete this watch and my email now
                </a>
            </td>
        </tr>

    </table>

</td></tr>
</table>
</body>
</html>
