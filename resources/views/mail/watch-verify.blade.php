<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Confirm your price watch | GadgetDrop</title>
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

            <p style="margin:0 0 14px;font-family:Consolas,'Courier New',monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#4f46e5;">Price watch</p>

            <h1 style="margin:0 0 12px;font-size:22px;font-weight:800;color:#111827;line-height:1.3;letter-spacing:-0.3px;">
                One click and we're watching the price on your {{ $watch->product->name }}
            </h1>

            <p style="margin:0 0 18px;font-size:15px;color:#4b5563;line-height:1.65;">
                Here's the deal. Until <strong style="color:#111827;">{{ $watch->expires_at->format('M j, Y') }}</strong>, the end of your {{ config('watch.window_days') }}-day return window,
                we'll compare this product's tracked price against
                <strong style="color:#111827;">${{ number_format((float) $watch->purchase_price, 2) }}</strong>, our tracked price for your purchase date.
                If it drops by at least ${{ number_format(config('watch.min_drop_abs'), 0) }} or {{ round(config('watch.min_drop_pct') * 100) }}%
                (whichever is larger), you get <strong style="color:#111827;">one email</strong> with the return-and-rebuy move. If it never drops, your inbox stays quiet.
            </p>

            <a href="{{ $verifyUrl }}"
                style="display:inline-block;background:#4f46e5;color:#ffffff;font-size:14px;font-weight:700;padding:12px 24px;border-radius:6px;text-decoration:none;">
                Confirm my price watch
            </a>

            <p style="margin:18px 0 0;font-size:12px;color:#9ca3af;line-height:1.6;">
                This confirmation link expires in 48 hours. Didn't ask for this? Ignore this email. The watch never activates without the click, and the record is deleted automatically.
            </p>

        </td></tr>

        <tr>
            <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:22px 40px;text-align:center;">
                <p style="margin:0 0 8px;font-size:12px;color:#9ca3af;line-height:1.6;">
                    You're getting this because someone entered this address on a
                    <a href="{{ url('/') }}" style="color:#6b7280;text-decoration:underline;">gadgetdrop.tech</a> review to watch a price.
                    Your address is deleted after the return window closes.
                </p>
                <a href="{{ $unsubscribeUrl }}" style="font-size:12px;color:#9ca3af;text-decoration:underline;">
                    Cancel this watch
                </a>
            </td>
        </tr>

    </table>

</td></tr>
</table>
</body>
</html>
