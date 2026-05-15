@extends('mail._layout', ['title' => 'Confirm your email'])

@section('content')
    <h1 style="margin:0 0 14px; font-size:22px; font-weight:700; color:#0f172a; line-height:1.3;">
        Welcome to {{ $appName }}
    </h1>
    <p style="margin:0 0 22px; color:#475569; font-size:15px; line-height:1.65;">
        Confirm your email so we can deliver your code audit reports, receipts,
        and any reviewer notes from our team.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
        <tr>
            <td style="background:#0f172a; border-radius:10px;">
                <a href="{{ $url }}"
                   style="display:inline-block; padding:14px 28px; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; line-height:1;">
                    Confirm email address
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 6px; color:#64748b; font-size:13px; line-height:1.6;">
        This link expires in 24 hours.
    </p>
    <p style="margin:0; color:#94a3b8; font-size:13px; line-height:1.6;">
        If you didn&rsquo;t create an account, no further action is required.
    </p>

    <div style="border-top:1px solid #e6e8ef; margin:28px 0 18px;"></div>

    <p style="margin:0 0 6px; color:#94a3b8; font-size:12px; line-height:1.6;">
        Trouble with the button? Paste this URL into your browser:
    </p>
    <p style="margin:0; font-size:12px; line-height:1.5;">
        <a href="{{ $url }}" style="color:#475569; word-break:break-all;">{{ $url }}</a>
    </p>
@endsection
