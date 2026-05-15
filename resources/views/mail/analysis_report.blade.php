@extends('mail._layout', ['title' => 'Your code audit report'])

@section('content')
    <h1 style="margin:0 0 14px; font-size:22px; font-weight:700; color:#0f172a; line-height:1.3;">
        Your code audit is ready
    </h1>
    <p style="margin:0 0 22px; color:#475569; font-size:15px; line-height:1.65;">
        We just finished auditing <strong style="color:#0f172a;">{{ $a->repo_full_name }}</strong>.
        The full PDF report is attached to this email.
    </p>

    <div style="background:#f8fafc; border:1px solid #e6e8ef; border-radius:12px; padding:18px 20px; margin:0 0 24px;">
        <div style="font-size:11px; font-weight:600; letter-spacing:0.06em; text-transform:uppercase; color:#94a3b8; margin-bottom:10px;">
            Scores
        </div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px; line-height:1.7; color:#0f172a;">
            <tr>
                <td style="padding:2px 0;"><strong>Overall</strong></td>
                <td align="right" style="padding:2px 0; font-variant-numeric:tabular-nums;">{{ $a->overall_score }}/100</td>
            </tr>
            <tr>
                <td style="padding:2px 0;">Security</td>
                <td align="right" style="padding:2px 0; font-variant-numeric:tabular-nums;">{{ $a->security_score }}</td>
            </tr>
            <tr>
                <td style="padding:2px 0;">Performance</td>
                <td align="right" style="padding:2px 0; font-variant-numeric:tabular-nums;">{{ $a->performance_score }}</td>
            </tr>
            <tr>
                <td style="padding:2px 0;">Quality</td>
                <td align="right" style="padding:2px 0; font-variant-numeric:tabular-nums;">{{ $a->quality_score }}</td>
            </tr>
        </table>
    </div>

    <p style="margin:0; color:#475569; font-size:14px; line-height:1.65;">
        Open the attached PDF for the full breakdown of issues and suggested fixes.
    </p>
@endsection
