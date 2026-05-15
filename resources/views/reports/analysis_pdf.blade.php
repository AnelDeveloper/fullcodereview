<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>QodeShark — {{ $a->repo_full_name }}</title>
    <style>
        @page { margin: 36px 32px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1c1f2b; font-size: 11px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #e0e2eb; }
        h3 { font-size: 12px; margin: 14px 0 6px; color: #6b6f87; text-transform: uppercase; letter-spacing: 1px; }
        .meta { color: #6b6f87; font-size: 10px; margin-bottom: 16px; }

        /* Verified ribbon */
        .verified-stamp { background: #e9f7ef; border: 1px solid #bfe3cd; border-radius: 8px; padding: 10px 14px; margin: 12px 0 18px; }
        .verified-stamp .title { color: #18794e; font-weight: bold; font-size: 12px; }
        .verified-stamp .sub { color: #4a6b56; font-size: 10px; margin-top: 2px; }

        /* Readiness block */
        .readiness { width: 100%; border: 1px solid #e0e2eb; border-radius: 8px; padding: 14px 16px; margin-bottom: 14px; }
        .readiness .header-row { width: 100%; }
        .readiness .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6b6f87; }
        .readiness .score-num { font-size: 30px; font-weight: bold; }
        .readiness .status-pill { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-launch_ready { background: #d4edda; color: #155724; }
        .status-needs_attention { background: #fff3cd; color: #856404; }
        .status-blocked { background: #f8d7da; color: #721c24; }

        .bar-track { background: #ececf2; height: 8px; border-radius: 4px; margin-top: 10px; overflow: hidden; }
        .bar-fill { height: 100%; }

        .blockers { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .blockers td { padding: 8px 12px; border: 1px solid #e0e2eb; }
        .blockers .num { font-size: 18px; font-weight: bold; }
        .blockers .num.critical { color: #b00020; }
        .blockers .num.high { color: #b34700; }
        .blockers .ttl { font-size: 11px; font-weight: bold; }
        .blockers .sub { font-size: 9px; color: #6b6f87; }

        .scores { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .scores td { padding: 12px; border: 1px solid #e0e2eb; text-align: center; width: 25%; }
        .scores .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6b6f87; }
        .scores .value { font-size: 22px; font-weight: bold; }

        /* Severity breakdown bars */
        .breakdown { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .breakdown td { padding: 6px 8px; vertical-align: middle; border: 1px solid #e0e2eb; }
        .breakdown .row-label { width: 120px; font-weight: bold; font-size: 11px; }
        .breakdown .seg-row { padding: 3px 0; }
        .seg-bar { display: inline-block; height: 14px; vertical-align: middle; }
        .seg-critical { background: #b00020; }
        .seg-high     { background: #e85a18; }
        .seg-medium   { background: #f0b41a; }
        .seg-low      { background: #3b82f6; }
        .seg-info     { background: #b6b9c4; }
        .seg-zero     { color: #b6b9c4; font-size: 9px; padding-left: 4px; }
        .seg-count    { font-size: 9px; color: #6b6f87; padding-left: 6px; vertical-align: middle; }

        /* Executive summary */
        .exec-summary { background: #f8f9fc; border: 1px solid #e0e2eb; border-radius: 8px; padding: 14px 16px; margin: 22px 0 18px; page-break-inside: avoid; }
        .exec-summary .exec-heading { margin: 0 0 10px; }
        .exec-summary .plain { font-size: 11.5px; line-height: 1.55; margin-bottom: 10px; }
        .exec-summary ul.risks { margin: 0; padding-left: 0; list-style: none; }
        .exec-summary ul.risks li { padding: 6px 0; border-bottom: 1px dotted #d8dae3; }
        .exec-summary ul.risks li:last-child { border-bottom: none; }
        .exec-summary ul.risks .r-title { font-weight: bold; font-size: 10.5px; }
        .exec-summary ul.risks .r-impact { color: #4a4d5e; font-size: 10.5px; }
        .exec-summary .priorities { margin-top: 8px; }
        .exec-summary .priorities .p-item { padding: 6px 0; border-bottom: 1px dotted #d8dae3; }
        .exec-summary .priorities .p-item:last-child { border-bottom: none; }
        .exec-summary .priorities .p-file { color: #6b6f87; font-family: 'DejaVu Sans Mono', monospace; font-size: 9.5px; }
        .exec-summary .priorities .p-fix { color: #4a4d5e; font-size: 10.5px; margin-top: 2px; }

        .next-steps { padding-left: 18px; margin: 4px 0 0; }
        .next-steps li { padding: 3px 0; line-height: 1.4; }

        /* Reviewer notes */
        .reviewer-notes { background: #eef4ff; border-left: 4px solid #4f6df0; border-radius: 4px; padding: 10px 14px; margin-bottom: 14px; page-break-inside: avoid; }
        .reviewer-notes .who { font-size: 10px; color: #4a4d5e; margin-bottom: 4px; font-weight: bold; }
        .reviewer-notes .body { font-size: 11px; line-height: 1.5; color: #1c1f2b; }

        /* Issues */
        .issue { border: 1px solid #e0e2eb; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; page-break-inside: avoid; }
        .issue .title { font-weight: bold; font-size: 12px; }
        .issue .file { color: #6b6f87; font-size: 10px; font-family: 'DejaVu Sans Mono', monospace; }
        .issue .desc { margin: 6px 0; }
        .issue .fix { background: #f4f5fa; padding: 6px 8px; border-radius: 4px; font-size: 10.5px; }
        .badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-right: 6px; }
        .b-critical { background: #ffd6d6; color: #b00020; }
        .b-high     { background: #ffe2cc; color: #b34700; }
        .b-medium   { background: #fff3cc; color: #946100; }
        .b-low      { background: #d6e9ff; color: #0a4ea0; }
        .b-info     { background: #e8e9ef; color: #444; }
        .footer { margin-top: 24px; color: #6b6f87; font-size: 9px; text-align: center; }
        .none { color: #2e7d32; font-size: 11px; }
    </style>
</head>
<body>
    <h1>Code Audit Report</h1>
    <div class="meta">
        <strong>{{ $a->repo_full_name }}</strong> ·
        {{ $a->files_scanned }} files · {{ number_format($a->lines_analyzed) }} lines ·
        Generated {{ $a->created_at?->format('M j, Y H:i') }}
    </div>

    @php
        $issues = $a->issues_json ?? ['security' => [], 'performance' => [], 'quality' => []];
        $execSummary = $a->executive_summary_json;
        $isVerified = in_array($a->verification_status, ['human_verified', 'finalized'], true);

        // For severity bars: count by severity per bucket
        $countBySeverity = function ($bucket) {
            $c = ['critical'=>0,'high'=>0,'medium'=>0,'low'=>0,'info'=>0];
            foreach ($bucket as $i) {
                $s = strtolower($i['severity'] ?? 'medium');
                if (isset($c[$s])) $c[$s]++;
            }
            return $c;
        };

        $readinessLabel = [
            'launch_ready' => 'Launch Ready',
            'needs_attention' => 'Needs Attention',
            'blocked' => 'Blocked',
        ][$a->readiness_status ?? ''] ?? 'Pending';

        $readinessColor = [
            'launch_ready' => '#22c55e',
            'needs_attention' => '#eab308',
            'blocked' => '#ef4444',
        ][$a->readiness_status ?? ''] ?? '#9ca3af';
    @endphp

    {{-- Human-Verified stamp --}}
    @if ($isVerified)
        <div class="verified-stamp">
            <span class="title">✓ Verified by Senior Engineer</span>
            <div class="sub">
                @if ($a->reviewer_id && $a->reviewer)
                    {{ $a->reviewer->name }}
                @else
                    Senior engineer
                @endif
                @if ($a->verified_at)
                    · {{ $a->verified_at->format('M j, Y') }}
                @endif
                @if ($a->verification_status === 'finalized')
                    · Report finalized
                @endif
            </div>
        </div>
    @endif

    {{-- Production Readiness block --}}
    @if ($a->readiness_score !== null)
        <div class="readiness">
            <table class="header-row">
                <tr>
                    <td style="vertical-align: top;">
                        <div class="label">Production readiness</div>
                        <div class="score-num" style="color: {{ $readinessColor }};">
                            {{ $a->readiness_score }}<span style="font-size: 16px; color: #6b6f87; font-weight: normal;">/100</span>
                        </div>
                    </td>
                    <td style="text-align: right; vertical-align: top;">
                        <span class="status-pill status-{{ $a->readiness_status }}">{{ $readinessLabel }}</span>
                    </td>
                </tr>
            </table>
            <div class="bar-track">
                <div class="bar-fill" style="width: {{ $a->readiness_score }}%; background: {{ $readinessColor }};"></div>
            </div>
            <table class="blockers">
                <tr>
                    <td width="50%">
                        <div class="num critical">{{ $a->critical_blocker_count ?? 0 }}</div>
                        <div class="ttl">Critical blockers</div>
                        <div class="sub">Must fix before launch</div>
                    </td>
                    <td width="50%">
                        <div class="num high">{{ $a->high_blocker_count ?? 0 }}</div>
                        <div class="ttl">High-severity</div>
                        <div class="sub">Fix in next sprint</div>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    {{-- Reviewer notes (visible to customer) --}}
    @if ($isVerified && $a->reviewer_notes)
        <div class="reviewer-notes">
            <div class="who">REVIEWER NOTES — {{ $a->reviewer?->name ?? 'Senior engineer' }}</div>
            <div class="body">{!! nl2br(e($a->reviewer_notes)) !!}</div>
        </div>
    @endif

    {{-- 4-score table --}}
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

    {{-- Severity breakdown bars --}}
    @php
        $sectionsForBars = ['security' => 'Security', 'performance' => 'Performance', 'quality' => 'Quality'];
        $totalIssuesCount = collect($issues)->flatten(1)->count();
    @endphp
    @if ($totalIssuesCount > 0)
        <h2>Severity breakdown</h2>
        <table class="breakdown">
            @foreach ($sectionsForBars as $key => $title)
                @php
                    $c = $countBySeverity($issues[$key] ?? []);
                    $total = array_sum($c);
                    $pct = fn($n) => $total > 0 ? max(2, round($n / $total * 100)) : 0;
                @endphp
                <tr>
                    <td class="row-label">{{ $title }}</td>
                    <td>
                        @if ($total === 0)
                            <span class="seg-zero">No issues</span>
                        @else
                            @foreach (['critical','high','medium','low','info'] as $sev)
                                @if ($c[$sev] > 0)
                                    <span class="seg-bar seg-{{ $sev }}" style="width: {{ $pct($c[$sev]) }}%;"></span>
                                @endif
                            @endforeach
                            <span class="seg-count">
                                @php
                                    $parts = [];
                                    foreach (['critical','high','medium','low','info'] as $sev) {
                                        if ($c[$sev] > 0) $parts[] = $c[$sev] . ' ' . $sev;
                                    }
                                @endphp
                                {{ implode(' · ', $parts) }}
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- Executive summary block (only when present).
         The heading is inside the .exec-summary container so DomPDF treats
         them as a single page-break unit — otherwise the heading shows on
         one page and the content on the next, looking empty. --}}
    @if ($execSummary && (
            ! empty($execSummary['plain_english'])
            || ! empty($execSummary['business_risks'])
            || ! empty($execSummary['top_critical'])
            || ! empty($execSummary['next_steps'])
        ))
        <div class="exec-summary">
            <h2 class="exec-heading">Executive summary</h2>
            @if (! empty($execSummary['plain_english']))
                <div class="plain">{{ $execSummary['plain_english'] }}</div>
            @endif

            @if (! empty($execSummary['business_risks']))
                <h3>Business risks</h3>
                <ul class="risks">
                    @foreach ($execSummary['business_risks'] as $r)
                        <li>
                            <div class="r-title">{{ $r['title'] ?? '' }}</div>
                            <div class="r-impact">{{ $r['impact'] ?? '' }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if (! empty($execSummary['top_critical']))
                <h3>Top {{ count($execSummary['top_critical']) }} priorities</h3>
                <div class="priorities">
                    @foreach ($execSummary['top_critical'] as $p)
                        <div class="p-item">
                            <span class="badge b-{{ $p['severity'] ?? 'medium' }}">{{ $p['severity'] ?? 'medium' }}</span>
                            <strong>{{ $p['title'] ?? '' }}</strong>
                            <div class="p-file">{{ $p['file'] ?? '' }}@if (! empty($p['line'])):{{ $p['line'] }}@endif</div>
                            @if (! empty($p['fix_summary']))
                                <div class="p-fix">{{ $p['fix_summary'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if (! empty($execSummary['next_steps']))
                <h3>Recommended next steps</h3>
                <ol class="next-steps">
                    @foreach ($execSummary['next_steps'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
            @endif
        </div>
    @endif

    {{-- Per-category issue listings (existing structure preserved) --}}
    @php
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
                    <div>
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
        QodeShark · AI-powered audit
        @if ($isVerified) · Verified by senior engineer @endif
    </div>
</body>
</html>
