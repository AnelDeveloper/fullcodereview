<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Your code audit report</title></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#0f1117; color:#e8eaf0; padding:32px;">
    <div style="max-width:520px; margin:0 auto; background:#1a1d2b; border:1px solid #2a2f44; border-radius:16px; padding:32px;">
        <h1 style="margin:0 0 12px; font-size:22px;">Your code audit is ready</h1>
        <p style="margin:0 0 20px; color:#a8acbf;">
            We just finished auditing <strong style="color:#e8eaf0;">{{ $a->repo_full_name }}</strong>.
            The full PDF report is attached.
        </p>
        <div style="background:#0f1117; border:1px solid #2a2f44; border-radius:12px; padding:14px; margin-bottom:20px;">
            <div style="font-size:13px; line-height:1.6;">
                <strong>Overall:</strong> {{ $a->overall_score }}/100 ·
                <strong>Security:</strong> {{ $a->security_score }} ·
                <strong>Performance:</strong> {{ $a->performance_score }} ·
                <strong>Quality:</strong> {{ $a->quality_score }}
            </div>
        </div>
        <p style="margin:0 0 0; color:#a8acbf; font-size:13px;">
            Open the attached PDF for the full breakdown of issues and suggested fixes.
        </p>
    </div>
</body>
</html>
