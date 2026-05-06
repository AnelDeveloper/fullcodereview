<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Code Review — {{ $a->repo_full_name }}</title>
    <style>
        @page { margin: 36px 32px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1c1f2b; font-size: 11px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #e0e2eb; }
        .meta { color: #6b6f87; font-size: 10px; margin-bottom: 16px; }
        .scores { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .scores td { padding: 12px; border: 1px solid #e0e2eb; text-align: center; width: 25%; }
        .scores .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6b6f87; }
        .scores .value { font-size: 22px; font-weight: bold; }
        .issue { border: 1px solid #e0e2eb; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; page-break-inside: avoid; }
        .issue .head { display: block; }
        .issue .title { font-weight: bold; font-size: 12px; }
        .issue .file { color: #6b6f87; font-size: 10px; font-family: 'DejaVu Sans Mono', monospace; }
        .issue .desc { margin: 6px 0; }
        .issue .fix { background: #f4f5fa; padding: 6px 8px; border-radius: 4px; font-size: 10.5px; }
        .badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-right: 6px; }
        .b-critical { background: #ffd6d6; color: #b00020; }
        .b-high { background: #ffe2cc; color: #b34700; }
        .b-medium { background: #fff3cc; color: #946100; }
        .b-low { background: #d6e9ff; color: #0a4ea0; }
        .b-info { background: #e8e9ef; color: #444; }
        .footer { margin-top: 24px; color: #6b6f87; font-size: 9px; text-align: center; }
        .none { color: #2e7d32; font-size: 11px; }
    </style>
</head>
<body>
    <h1>Code Review Report</h1>
    <div class="meta">
        <strong>{{ $a->repo_full_name }}</strong> ·
        {{ $a->files_scanned }} files · {{ number_format($a->lines_analyzed) }} lines ·
        Generated {{ $a->created_at?->format('M j, Y H:i') }}
    </div>

    <table class="scores">
        <tr>
            <td>
                <div class="label">Overall</div>
                <div class="value">{{ $a->overall_score }}/100</div>
            </td>
            <td>
                <div class="label">Security</div>
                <div class="value">{{ $a->security_score }}/100</div>
            </td>
            <td>
                <div class="label">Performance</div>
                <div class="value">{{ $a->performance_score }}/100</div>
            </td>
            <td>
                <div class="label">Quality</div>
                <div class="value">{{ $a->quality_score }}/100</div>
            </td>
        </tr>
    </table>

    @php
        $issues = $a->issues_json ?? ['security' => [], 'performance' => [], 'quality' => []];
        $sections = [
            'security' => 'Security',
            'performance' => 'Performance',
            'quality' => 'Code Quality',
        ];
    @endphp

    @foreach ($sections as $key => $title)
        <h2>{{ $title }}</h2>
        @if (empty($issues[$key]))
            <div class="none">No issues detected.</div>
        @else
            @foreach ($issues[$key] as $issue)
                <div class="issue">
                    <div class="head">
                        <span class="badge b-{{ $issue['severity'] }}">{{ $issue['severity'] }}</span>
                        <span class="title">{{ $issue['title'] }}</span>
                    </div>
                    <div class="file">{{ $issue['file'] }}{{ isset($issue['line']) && $issue['line'] ? ':' . $issue['line'] : '' }}</div>
                    <div class="desc">{{ $issue['description'] }}</div>
                    @if (! empty($issue['suggestion']))
                        <div class="fix"><strong>Suggested fix:</strong> {{ $issue['suggestion'] }}</div>
                    @endif
                </div>
            @endforeach
        @endif
    @endforeach

    <div class="footer">
        Code Review · AI-powered repository review
    </div>
</body>
</html>
