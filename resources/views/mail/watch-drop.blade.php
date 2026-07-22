<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>The price dropped | GadgetDrop</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:'Figtree','Segoe UI',Arial,sans-serif;-webkit-font-smoothing:antialiased;">

{{-- Inbox preview text (hidden in the email body) --}}
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">
    Return the original, rebuy at the lower price, keep the difference. {{ $daysLeft }} {{ Str::plural('day', $daysLeft) }} left in your window.
    &#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
<tr><td align="center" style="padding:32px 16px;">

    <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">

        <tr>
            <td style="padding:32px 40px 0;">
                <span style="font-size:24px;font-weight:800;color:#111827;letter-spacing:-0.5px;">Gadget<span style="color:#4f46e5;">Drop</span></span>
            </td>
        </tr>

        <tr><td style="padding:26px 40px 34px;">

            <p style="margin:0 0 14px;font-family:Consolas,'Courier New',monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#10b981;">Price drop alert</p>

            <h1 style="margin:0 0 12px;font-size:24px;font-weight:800;color:#111827;line-height:1.3;letter-spacing:-0.3px;">
                It dropped ${{ number_format($savings, 2) }} — and you're still inside your return window
            </h1>

            <p style="margin:0 0 20px;font-size:15px;color:#4b5563;line-height:1.65;">
                The {{ $watch->product->name }} you bought is now tracking at
                <strong style="color:#111827;">${{ number_format($currentPrice, 2) }}</strong>@if($checkedAt) (our last check: {{ $checkedAt }})@endif —
                down from the ${{ number_format($paid, 2) }} we tracked on your purchase date.
                You have <strong style="color:#111827;">{{ $daysLeft }} {{ Str::plural('day', $daysLeft) }}</strong> left to do something about it.
            </p>

            {{-- The numbers, side by side --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:22px;">
                <tr>
                    <td width="33%" align="center" style="padding:18px 8px;">
                        <p style="margin:0 0 2px;font-family:Consolas,'Courier New',monospace;font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#9ca3af;">You paid</p>
                        <p style="margin:0;font-size:20px;font-weight:800;color:#6b7280;">${{ number_format($paid, 2) }}</p>
                    </td>
                    <td width="33%" align="center" style="padding:18px 8px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
                        <p style="margin:0 0 2px;font-family:Consolas,'Courier New',monospace;font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#9ca3af;">Now tracking</p>
                        <p style="margin:0;font-size:20px;font-weight:800;color:#111827;">${{ number_format($currentPrice, 2) }}</p>
                    </td>
                    <td width="33%" align="center" style="padding:18px 8px;">
                        <p style="margin:0 0 2px;font-family:Consolas,'Courier New',monospace;font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#9ca3af;">Back in your pocket</p>
                        <p style="margin:0;font-size:20px;font-weight:800;color:#10b981;">${{ number_format($savings, 2) }}</p>
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 10px;font-size:15px;font-weight:700;color:#111827;">The return-and-rebuy move, step by step:</p>
            <ol style="margin:0 0 22px;padding-left:20px;font-size:14px;color:#4b5563;line-height:1.8;">
                <li>Check your original order is still marked <strong style="color:#111827;">Free Returns</strong> — that's what makes this a no-cost move.</li>
                <li>Order the item again at the new lower price <em>first</em>, so you're never without it.</li>
                <li>Return the original from Your Orders, citing "found a better price." Amazon no longer price-matches — return-and-rebuy is the move they themselves point to.</li>
                <li>Refund lands when the return is scanned; the difference stays with you.</li>
            </ol>

            <p style="margin:0 0 18px;font-size:13px;color:#6b7280;line-height:1.6;">
                Prices move — confirm the current price and the return terms on your order before pulling the trigger. This is our tracked price, not a live Amazon quote.
            </p>

            <a href="{{ $reviewUrl }}"
                style="display:inline-block;background:#4f46e5;color:#ffffff;font-size:14px;font-weight:700;padding:12px 24px;border-radius:6px;text-decoration:none;">
                See the price history on the review
            </a>

        </td></tr>

        <tr>
            <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:22px 40px;text-align:center;">
                <p style="margin:0 0 8px;font-size:12px;color:#9ca3af;line-height:1.6;">
                    That's the one email this watch will ever send — it's done now.
                    You asked us to watch this price at <a href="{{ url('/') }}" style="color:#6b7280;text-decoration:underline;">gadgetdrop.tech</a>.
                </p>
                <a href="{{ $unsubscribeUrl }}" style="font-size:12px;color:#9ca3af;text-decoration:underline;">
                    Delete this watch and my email
                </a>
            </td>
        </tr>

    </table>

</td></tr>
</table>
</body>
</html>
