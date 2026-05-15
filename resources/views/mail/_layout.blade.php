{{--
    Shared layout for all transactional emails. Email-safe HTML only:
    inline styles, table-based scaffold, absolute image URLs.
--}}
@php
    $brand = config('codereview.brand_name', config('app.name', 'QodeShark'));
    $social = config('codereview.social', []);
    $support = config('codereview.support_email', 'hello@qodeshark.com');
    $tagline = config('codereview.tagline', '');
    // Cropped shark mark — wider than tall (~2:1). Live at /email-logo.png.
    $logoUrl = rtrim(config('app.url'), '/') . '/email-logo.png';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $title ?? $brand }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f5f8; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#0f172a; -webkit-font-smoothing:antialiased;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f5f8;">
    <tr><td align="center" style="padding:32px 16px;">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; width:100%;">
            {{-- Logo header (shark mark, ~2:1 aspect, rendered ~56px tall ≈ 114px wide) --}}
            <tr><td align="center" style="padding:8px 0 24px;">
                <a href="{{ rtrim(config('app.url'), '/') }}" style="text-decoration:none;">
                    <img src="{{ $logoUrl }}" alt="{{ $brand }}" height="56"
                         style="height:56px; width:auto; display:block; border:0; outline:none;">
                </a>
            </td></tr>

            {{-- Card --}}
            <tr><td style="background:#ffffff; border:1px solid #e6e8ef; border-radius:16px; padding:40px 36px; box-shadow:0 1px 2px rgba(15,23,42,0.04);">
                @yield('content')
            </td></tr>

            {{-- Footer --}}
            <tr><td align="center" style="padding:28px 16px 8px;">
                @if ($tagline)
                    <div style="color:#64748b; font-size:13px; line-height:1.6; margin-bottom:16px;">
                        {{ $tagline }}
                    </div>
                @endif

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 16px;">
                    <tr>
                        @if (! empty($social['instagram']))
                            <td style="padding:0 10px;">
                                <a href="{{ $social['instagram'] }}" style="color:#0f172a; text-decoration:none; font-weight:600; font-size:13px;">Instagram</a>
                            </td>
                        @endif
                        @if (! empty($social['linkedin']))
                            <td style="padding:0 10px; border-left:1px solid #e6e8ef;">
                                <a href="{{ $social['linkedin'] }}" style="color:#0f172a; text-decoration:none; font-weight:600; font-size:13px;">LinkedIn</a>
                            </td>
                        @endif
                        @if (! empty($social['trustpilot']))
                            <td style="padding:0 10px; border-left:1px solid #e6e8ef;">
                                <a href="{{ $social['trustpilot'] }}" style="color:#0f172a; text-decoration:none; font-weight:600; font-size:13px;">Trustpilot</a>
                            </td>
                        @endif
                        @if (! empty($social['x']))
                            <td style="padding:0 10px; border-left:1px solid #e6e8ef;">
                                <a href="{{ $social['x'] }}" style="color:#0f172a; text-decoration:none; font-weight:600; font-size:13px;">X</a>
                            </td>
                        @endif
                    </tr>
                </table>

                <div style="color:#94a3b8; font-size:12px; line-height:1.6;">
                    Questions? Email <a href="mailto:{{ $support }}" style="color:#64748b; text-decoration:underline;">{{ $support }}</a>
                </div>
                <div style="color:#94a3b8; font-size:12px; line-height:1.6; margin-top:6px;">
                    &copy; {{ date('Y') }} {{ $brand }}. All rights reserved.
                </div>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
