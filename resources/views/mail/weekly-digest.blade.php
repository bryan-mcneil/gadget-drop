<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>This Week's Drop | GadgetDrop</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
<tr><td align="center" style="padding:32px 16px;">

    {{-- ── Outer card ── --}}
    <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.07);">

        {{-- ── Header ── --}}
        <tr>
            <td style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);padding:36px 40px 32px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td>
                            <p style="margin:0 0 4px;font-size:12px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,0.6);">Weekly Drop</p>
                            <p style="margin:0;font-size:28px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;">Gadget<span style="color:#c4b5fd;">Drop</span></p>
                        </td>
                        <td align="right">
                            <p style="margin:0;font-size:13px;color:rgba(255,255,255,0.7);">{{ now()->format('F j, Y') }}</p>
                        </td>
                    </tr>
                </table>
                <p style="margin:20px 0 0;font-size:16px;color:rgba(255,255,255,0.9);line-height:1.6;">
                    Another week, another haul. Here's what dropped on GadgetDrop: the gear worth your attention this week. ⚡
                </p>
            </td>
        </tr>

        {{-- ── Divider line ── --}}
        <tr><td style="height:4px;background:linear-gradient(90deg,#4f46e5,#7c3aed,#4f46e5);"></td></tr>

        <tr><td style="padding:36px 40px 0;">

            {{-- ── Featured post ── --}}
            @if(!empty($posts))
            @php $featured = $posts[0]; @endphp
            <p style="margin:0 0 16px;font-size:11px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:#6366f1;">This Week's Top Drop</p>

            @if($featured['featured_image'])
            <a href="{{ $featured['url'] }}" style="display:block;margin-bottom:16px;">
                <img src="{{ $featured['featured_image'] }}" alt="{{ $featured['title'] }}"
                    style="width:100%;max-height:260px;object-fit:cover;border-radius:12px;display:block;">
            </a>
            @endif

            <h1 style="margin:0 0 10px;font-size:22px;font-weight:800;color:#111827;line-height:1.3;">
                <a href="{{ $featured['url'] }}" style="color:#111827;text-decoration:none;">{{ $featured['title'] }}</a>
            </h1>
            @if($featured['excerpt'])
            <p style="margin:0 0 16px;font-size:15px;color:#6b7280;line-height:1.6;">{{ $featured['excerpt'] }}</p>
            @endif
            <a href="{{ $featured['url'] }}"
                style="display:inline-block;background:#4f46e5;color:#ffffff;font-size:14px;font-weight:700;padding:12px 24px;border-radius:8px;text-decoration:none;">
                Read the Drop →
            </a>

            {{-- ── Divider ── --}}
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:32px 0;">
                <tr><td style="border-top:1px solid #e5e7eb;"></td></tr>
            </table>
            @endif

            {{-- ── Spotlight product ── --}}
            @if($spotlight)
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                style="background:#faf5ff;border:1px solid #e9d5ff;border-radius:12px;margin-bottom:32px;overflow:hidden;">
                <tr>
                    @if($spotlight['image_url'])
                    <td width="120" style="padding:20px 0 20px 20px;vertical-align:middle;">
                        <img src="{{ $spotlight['image_url'] }}" alt="{{ $spotlight['name'] }}"
                            style="width:100px;height:100px;object-fit:contain;display:block;border-radius:8px;background:#fff;">
                    </td>
                    @endif
                    <td style="padding:20px;vertical-align:middle;">
                        <p style="margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#7c3aed;">Featured Pick</p>
                        <p style="margin:0 0 4px;font-size:16px;font-weight:800;color:#111827;line-height:1.3;">{{ $spotlight['name'] }}</p>
                        @if($spotlight['price'])
                        <p style="margin:0 0 12px;font-size:20px;font-weight:800;color:#4f46e5;">${{ number_format($spotlight['price'], 2) }}</p>
                        @endif
                        <a href="{{ $spotlight['affiliate_url'] }}"
                            style="display:inline-block;background:#f97316;color:#ffffff;font-size:13px;font-weight:700;padding:10px 20px;border-radius:7px;text-decoration:none;">
                            View on Amazon →
                        </a>
                    </td>
                </tr>
            </table>
            @endif

            {{-- ── More drops ── --}}
            @if(count($posts) > 1)
            <p style="margin:0 0 16px;font-size:11px;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;color:#6366f1;">More From This Week</p>

            @foreach(array_slice($posts, 1) as $post)
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
                <tr>
                    @if($post['featured_image'])
                    <td width="80" style="vertical-align:top;padding-right:16px;">
                        <a href="{{ $post['url'] }}">
                            <img src="{{ $post['featured_image'] }}" alt="{{ $post['title'] }}"
                                style="width:80px;height:64px;object-fit:cover;border-radius:8px;display:block;">
                        </a>
                    </td>
                    @endif
                    <td style="vertical-align:top;">
                        <p style="margin:0 0 3px;font-size:13px;color:#9ca3af;">{{ $post['published_at'] }}</p>
                        <p style="margin:0 0 4px;font-size:15px;font-weight:700;line-height:1.3;">
                            <a href="{{ $post['url'] }}" style="color:#111827;text-decoration:none;">{{ $post['title'] }}</a>
                        </p>
                        @if($post['excerpt'])
                        <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.5;">{{ Str::limit($post['excerpt'], 90) }}</p>
                        @endif
                    </td>
                </tr>
            </table>
            @endforeach

            {{-- ── Divider ── --}}
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0;">
                <tr><td style="border-top:1px solid #e5e7eb;"></td></tr>
            </table>
            @endif

            {{-- ── Tech Tip ── --}}
            @if($techTip)
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:20px;margin-bottom:32px;">
                <tr>
                    <td>
                        <p style="margin:0 0 6px;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#059669;">
                            ⚡ Quick Tech Tip
                        </p>
                        <p style="margin:0 0 8px;font-size:16px;font-weight:700;color:#111827;">
                            <a href="{{ $techTip['url'] }}" style="color:#111827;text-decoration:none;">{{ $techTip['title'] }}</a>
                        </p>
                        @if($techTip['excerpt'])
                        <p style="margin:0 0 12px;font-size:14px;color:#374151;line-height:1.6;">{{ $techTip['excerpt'] }}</p>
                        @endif
                        <a href="{{ $techTip['url'] }}"
                            style="font-size:13px;font-weight:700;color:#059669;text-decoration:none;">
                            Read the tip →
                        </a>
                    </td>
                </tr>
            </table>
            @endif

            {{-- ── CTA banner ── --}}
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                style="background:linear-gradient(135deg,#eef2ff 0%,#faf5ff 100%);border-radius:12px;margin-bottom:36px;">
                <tr>
                    <td style="padding:24px;text-align:center;">
                        <p style="margin:0 0 4px;font-size:18px;font-weight:800;color:#111827;">Miss last week's drop?</p>
                        <p style="margin:0 0 16px;font-size:14px;color:#6b7280;">Everything's on the site, browse all our picks.</p>
                        <a href="{{ url('/') }}"
                            style="display:inline-block;background:#4f46e5;color:#ffffff;font-size:14px;font-weight:700;padding:12px 28px;border-radius:8px;text-decoration:none;">
                            Browse GadgetDrop →
                        </a>
                    </td>
                </tr>
            </table>

        </td></tr>

        {{-- ── Footer ── --}}
        <tr>
            <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:24px 40px;text-align:center;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#374151;">
                    Gadget<span style="color:#6366f1;">Drop</span>
                </p>
                <p style="margin:0 0 12px;font-size:12px;color:#9ca3af;line-height:1.6;">
                    GadgetDrop participates in the Amazon Associates program.<br>
                    We earn a small commission on qualifying purchases at no extra cost to you.
                </p>
                <a href="{{ $unsubscribeUrl }}"
                    style="font-size:12px;color:#9ca3af;text-decoration:underline;">
                    Unsubscribe
                </a>
            </td>
        </tr>

    </table>

</td></tr>
</table>
</body>
</html>
