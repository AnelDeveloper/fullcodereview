<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Your redeem code</title></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#0f1117; color:#e8eaf0; padding:32px;">
    <div style="max-width:520px; margin:0 auto; background:#1a1d2b; border:1px solid #2a2f44; border-radius:16px; padding:32px;">
        <h1 style="margin:0 0 12px; font-size:24px;">Thanks for your purchase</h1>
        <p style="margin:0 0 24px; color:#a8acbf;">Here's your redeem code. It's single-use and valid for 30 days.</p>

        <div style="background:#0f1117; border:1px solid #6c5ce7; border-radius:12px; padding:18px; text-align:center; margin-bottom:24px;">
            <div style="font-size:11px; letter-spacing:1px; color:#a8acbf; text-transform:uppercase; margin-bottom:8px;">Your redeem code</div>
            <div style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:22px; font-weight:bold; color:#a29bfe; letter-spacing:1px;">{{ $code->code }}</div>
        </div>

        <p style="margin:0 0 8px; font-size:14px; color:#a8acbf;">To use it, head to the app, click "Redeem code", paste it in, and pick a GitHub repository to analyze.</p>
        <p style="margin:24px 0 0; font-size:12px; color:#6b6f87;">If you didn't make this purchase, you can ignore this email.</p>
    </div>
</body>
</html>
