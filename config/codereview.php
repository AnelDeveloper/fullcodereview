<?php

/**
 * QodeShark — category catalog & bundle discount.
 *
 * Single source of truth for what we sell and how it's priced. Mirrored on
 * the landing page (codereview-landingpage). Keep them in sync.
 *
 * `bundle_discount_pct` maps "selected count" → "% off subtotal". Computed
 * server-side in StripeController so the client cannot lie about it.
 */

return [
    'min_total_cents' => 2000,

    // Brand identity for transactional emails (sender name, subjects,
    // welcome heading). Decoupled from APP_NAME so the deployed env can
    // keep "Code Review" without leaking into customer-facing emails.
    'brand_name' => 'QodeShark - Code Review',

    // Surfaced in transactional email footers (verification, audit reports).
    // Update here, not in the blades.
    'social' => [
        'instagram'  => 'https://www.instagram.com/qodeshark/',
        'linkedin'   => 'https://www.linkedin.com/company/qodeshark-ai/',
        'trustpilot' => 'https://www.trustpilot.com/review/fullcodereview.com',
        'x'          => 'https://x.com/QodeShark',
    ],
    'support_email' => env('SUPPORT_EMAIL', 'hello@qodeshark.com'),
    'tagline' => 'AI-powered code audits for shipping teams',

    // Generate a Claude-authored business-language summary on top of every
    // scan. Adds ~$0.05 per scan. Set CODEREVIEW_EXECUTIVE_SUMMARY=false to
    // disable.
    'generate_executive_summary' => env('CODEREVIEW_EXECUTIVE_SUMMARY', true),

    'bundle_discount_pct' => [
        2 => 10,
        3 => 15,
        4 => 20,
    ],

    'categories' => [
        'security' => [
            'key' => 'security',
            'name' => 'Security',
            'price_cents' => 2000,
            'tagline' => 'Find the gaps that get apps breached.',
            'includes' => [
                'Auth gaps & broken access control (IDOR)',
                'Injection (SQL, NoSQL, command)',
                'XSS, CSRF, SSRF',
                'Mass assignment & input validation',
                'Leaked secrets in code & history',
                'Missing rate limits & brute-force surfaces',
                'Insecure deserialization',
                'Dependency CVEs',
            ],
        ],
        'database' => [
            'key' => 'database',
            'name' => 'Database',
            'price_cents' => 6000,
            'tagline' => 'Schema, queries, and integrity.',
            'includes' => [
                'Relationships & foreign key correctness',
                'Indexing strategy (missing, unused, redundant)',
                'N+1 queries & over-fetching',
                'Unsafe migrations (locking, downtime)',
                'Transaction boundaries & isolation',
                'Constraints (NOT NULL, UNIQUE, CHECK)',
                'Data types, precision, timezone handling',
                'Query plans on hot paths',
            ],
        ],
        'backend' => [
            'key' => 'backend',
            'name' => 'Backend',
            'price_cents' => 5000,
            'tagline' => 'APIs, auth, and the server contract.',
            'includes' => [
                'REST / GraphQL API design & status codes',
                'Authentication (sessions, JWT, OAuth)',
                'Authorization, roles & permissions',
                'Security headers (CSP, HSTS, CORS)',
                'Token handling (refresh, rotation, storage)',
                'Middleware ordering & error handling',
                'Idempotency on mutations & retries',
                'Logging, tracing, observability',
            ],
        ],
        'frontend' => [
            'key' => 'frontend',
            'name' => 'Frontend',
            'price_cents' => 5000,
            'tagline' => 'Components, state, and the client.',
            'includes' => [
                'Component structure & reusability',
                'State management (props, stores, context)',
                'Rendering performance & re-renders',
                'Bundle size & code-splitting',
                'Accessibility (ARIA, keyboard, contrast)',
                'Form validation & UX edge cases',
                'Hydration & SSR pitfalls',
                'SEO basics (meta, semantics, headings)',
            ],
        ],
    ],
];
