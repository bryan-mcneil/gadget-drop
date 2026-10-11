<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>The Weekly Drop | GadgetDrop</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:'Figtree','Segoe UI',Arial,sans-serif;-webkit-font-smoothing:antialiased;">

{{-- Inbox preview text (hidden in the email body) --}}
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">
    @if(!empty($posts)){{ $posts[0]['title'] }}. Plus today's Drop Price puzzle.@else This week on GadgetDrop. Plus today's Drop Price puzzle.@endif
    &#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
<tr><td align="center" style="padding:32px 16px;">

    {{-- ── Outer card ── --}}
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">

        {{-- ── Header: white, wordmark, tagline ── --}}
        <tr>
            <td style="padding:32px 40px 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="vertical-align:middle;">
                            <span style="font-size:24px;font-weight:800;color:#111827;letter-spacing:-0.5px;">Gadget<span style="color:#4f46e5;">Drop</span></span>
                        </td>
                        <td align="right" style="vertical-align:middle;">
                            <span style="font-family:Consolas,'Courier New',monospace;font-size:12px;color:#9ca3af;">{{ now()->format('M j, Y') }}</span>
                        </td>
                    </tr>
                </table>
                <p style="margin:10px 0 0;font-size:15px;color:#4b5563;">
                    Know when it&rsquo;s <em>actually</em> a deal. Here&rsquo;s what dropped this week.
                </p>
            </td>
        </tr>

        {{-- ── Signature divider: the carry-forward price line, ending at the low ── --}}
        <tr>
            <td style="padding:22px 40px 0;">
                {{-- One continuous carry-forward line: horizontal runs joined by vertical connectors, ending at the low. --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                    <tr>
                        <td width="22%" style="vertical-align:top;padding-top:6px;"><div style="border-top:3px solid #4f46e5;font-size:0;line-height:0;">&nbsp;</div></td>
                        <td width="3" style="vertical-align:top;padding-top:1px;"><div style="width:3px;height:8px;background:#4f46e5;font-size:0;line-height:0;">&nbsp;</div></td>
                        <td width="20%" style="vertical-align:top;padding-top:1px;"><div style="border-top:3px solid #4f46e5;font-size:0;line-height:0;">&nbsp;</div></td>
                        <td width="3" style="vertical-align:top;padding-top:1px;"><div style="width:3px;height:13px;background:#4f46e5;font-size:0;line-height:0;">&nbsp;</div></td>
                        <td width="24%" style="vertical-align:top;padding-top:11px;"><div style="border-top:3px solid #4f46e5;font-size:0;line-height:0;">&nbsp;</div></td>
                        <td width="3" style="vertical-align:top;padding-top:11px;"><div style="width:3px;height:9px;background:#4f46e5;font-size:0;line-height:0;">&nbsp;</div></td>
                        <td width="20%" style="vertical-align:top;padding-top:17px;"><div style="border-top:3px solid #4f46e5;font-size:0;line-height:0;">&nbsp;</div></td>
                        <td width="18" style="vertical-align:top;padding-top:14px;padding-left:5px;"><div style="width:10px;height:10px;background:#10b981;border-radius:50%;font-size:0;line-height:0;">&nbsp;</div></td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr><td style="padding:30px 40px 0;">

            {{-- ── Featured post ── --}}
            @if(!empty($posts))
            @php $featured = $posts[0]; @endphp
            <p style="margin:0 0 14px;font-family:Consolas,'Courier New',monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#4f46e5;">This week&rsquo;s drop</p>

            @if($featured['featured_image'])
            <a href="{{ $featured['url'] }}" style="display:block;margin-bottom:18px;">
                <img src="{{ $featured['featured_image'] }}" alt="{{ $featured['title'] }}" width="520"
                    style="width:100%;max-width:520px;height:auto;max-height:280px;object-fit:cover;border-radius:8px;display:block;border:1px solid #e5e7eb;">
            </a>
            @endif

            <h1 style="margin:0 0 10px;font-size:24px;font-weight:800;color:#111827;line-height:1.25;letter-spacing:-0.3px;">
                <a href="{{ $featured['url'] }}" style="color:#111827;text-decoration:none;">{{ $featured['title'] }}</a>
            </h1>
            @if($featured['excerpt'])
            <p style="margin:0 0 18px;font-size:15px;color:#4b5563;line-height:1.6;">{{ $featured['excerpt'] }}</p>
            @endif
            <a href="{{ $featured['url'] }}"
                style="display:inline-block;background:#4f46e5;color:#ffffff;font-size:14px;font-weight:700;padding:12px 24px;border-radius:6px;text-decoration:none;">
                Read the review
            </a>
            @endif

            {{-- ── Spotlight product ── --}}
            @if($spotlight)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;margin-top:32px;">
                <tr>
                    @if($spotlight['image_url'])
                    <td width="120" style="padding:20px 0 20px 20px;vertical-align:middle;">
                        <img src="{{ $spotlight['image_url'] }}" alt="{{ $spotlight['name'] }}" width="100" height="100"
                            style="width:100px;height:100px;object-fit:contain;display:block;border-radius:6px;background:#ffffff;border:1px solid #e5e7eb;">
                    </td>
                    @endif
                    <td style="padding:20px;vertical-align:middle;">
                        <p style="margin:0 0 4px;font-family:Consolas,'Courier New',monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#6b7280;">From the review</p>
                        <p style="margin:0 0 6px;font-size:16px;font-weight:800;color:#111827;line-height:1.35;">{{ $spotlight['name'] }}</p>
                        @if($spotlight['price_checked_at'])
                        <p style="margin:0 0 14px;font-family:Consolas,'Courier New',monospace;font-size:12px;color:#374151;">
                            <span style="display:inline-block;width:8px;height:8px;background:#10b981;border-radius:50%;">&nbsp;</span>&nbsp;
                            Price checked {{ $spotlight['price_checked_at'] }}
                        </p>
                        @else
                        <p style="margin:0 0 14px;font-size:13px;color:#6b7280;">Current price on Amazon.</p>
                        @endif
                        <a href="{{ $spotlight['out_url'] }}" rel="nofollow sponsored"
                            style="display:inline-block;background:#f97316;color:#ffffff;font-size:13px;font-weight:700;padding:10px 20px;border-radius:6px;text-decoration:none;">
                            Check price on Amazon
                        </a>
                    </td>
                </tr>
            </table>
            @endif

            {{-- ── More drops ── --}}
            @if(count($posts) > 1)
            <p style="margin:36px 0 16px;font-family:Consolas,'Courier New',monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#4f46e5;">More from this week</p>

            @foreach(array_slice($posts, 1) as $post)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
                <tr>
                    @if($post['featured_image'])
                    <td width="88" style="vertical-align:top;padding-right:16px;">
                        <a href="{{ $post['url'] }}">
                            <img src="{{ $post['featured_image'] }}" alt="{{ $post['title'] }}" width="72" height="58"
                                style="width:72px;height:58px;object-fit:cover;border-radius:6px;display:block;border:1px solid #e5e7eb;">
                        </a>
                    </td>
                    @endif
                    <td style="vertical-align:top;">
                        <p style="margin:0 0 2px;font-family:Consolas,'Courier New',monospace;font-size:11px;color:#9ca3af;">{{ $post['published_at'] }}</p>
                        <p style="margin:0 0 4px;font-size:15px;font-weight:700;line-height:1.35;">
                            <a href="{{ $post['url'] }}" style="color:#111827;text-decoration:none;">{{ $post['title'] }}</a>
                        </p>
                        @if($post['excerpt'])
                        <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.5;">{{ Str::limit($post['excerpt'], 90) }}</p>
                        @endif
                    </td>
                </tr>
            </table>
            @endforeach
            @endif

            {{-- ── Drop Price: the daily game ── --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                style="background:#111827;border-radius:8px;margin-top:32px;">
                <tr>
                    <td style="padding:26px 28px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="vertical-align:middle;">
                                    <p style="margin:0 0 6px;font-family:Consolas,'Courier New',monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#10b981;">Daily game</p>
                                    <p style="margin:0 0 6px;font-size:19px;font-weight:800;color:#ffffff;line-height:1.3;">Think you know what it sells for?</p>
                                    <p style="margin:0 0 16px;font-size:14px;color:#9ca3af;line-height:1.55;">Drop Price gives you a real gadget and four guesses at its price. A new puzzle drops every day.</p>
                                    <a href="{{ route('drop-price.index') }}"
                                        style="display:inline-block;background:#ffffff;color:#111827;font-size:13px;font-weight:700;padding:10px 20px;border-radius:6px;text-decoration:none;">
                                        Play today&rsquo;s puzzle
                                    </a>
                                </td>
                                <td width="110" align="right" style="vertical-align:middle;">
                                    <span style="font-family:Consolas,'Courier New',monospace;font-size:30px;font-weight:700;color:#374151;">$??.<span style="color:#10b981;">??</span></span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            {{-- ── Tech Tip ── --}}
            @if($techTip)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:32px;">
                <tr>
                    <td style="border-left:3px solid #4f46e5;padding:4px 0 4px 20px;">
                        <p style="margin:0 0 6px;font-family:Consolas,'Courier New',monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#6b7280;">Tech tip</p>
                        <p style="margin:0 0 6px;font-size:16px;font-weight:700;color:#111827;line-height:1.35;">
                            <a href="{{ $techTip['url'] }}" style="color:#111827;text-decoration:none;">{{ $techTip['title'] }}</a>
                        </p>
                        @if($techTip['excerpt'])
                        <p style="margin:0 0 10px;font-size:14px;color:#4b5563;line-height:1.6;">{{ $techTip['excerpt'] }}</p>
                        @endif
                        <a href="{{ $techTip['url'] }}" style="font-size:13px;font-weight:700;color:#4f46e5;text-decoration:none;">
                            Read the tip &rarr;
                        </a>
                    </td>
                </tr>
            </table>
            @endif

            {{-- ── Deals: quiet editorial pointer ── --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:32px 0 36px;">
                <tr><td style="border-top:1px solid #e5e7eb;padding-top:20px;">
                    <p style="margin:0;font-size:14px;color:#4b5563;line-height:1.6;">
                        The Deals page lists gear from our reviews currently selling below its tracked 90-day average.
                        <a href="{{ route('deals') }}" style="font-weight:700;color:#4f46e5;text-decoration:none;">See what&rsquo;s actually a deal &rarr;</a>
                    </p>
                </td></tr>
            </table>

        </td></tr>

        {{-- ── Footer ── --}}
        <tr>
            <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:24px 40px;text-align:center;">
                <p style="margin:0 0 8px;font-size:14px;font-weight:800;color:#111827;">
                    Gadget<span style="color:#4f46e5;">Drop</span>
                </p>
                <p style="margin:0 0 8px;font-size:12px;color:#9ca3af;line-height:1.6;">
                    You&rsquo;re getting this because you joined The Drop at
                    <a href="{{ url('/') }}" style="color:#6b7280;text-decoration:underline;">gadgetdrop.tech</a>.
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
