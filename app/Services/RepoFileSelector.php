<?php

namespace App\Services;

class RepoFileSelector
{
    public const CHARS_PER_TOKEN = 3;
    public const BASE_TOKEN_OVERHEAD = 15_000;
    public const MAX_INPUT_TOKENS = 850_000;

    public const CODE_EXTENSIONS = [
        'ts','tsx','js','jsx','mjs','cjs',
        'py','rb','go','rs','java','kt','swift',
        'c','cc','cpp','h','hpp',
        'cs','php','scala','clj','ex','exs',
        'vue','svelte','astro',
        'sql','sh','bash','zsh',
        'prisma','graphql','gql',
    ];

    public const CONFIG_FILES = [
        'package.json','tsconfig.json','next.config.js','next.config.ts','next.config.mjs',
        'nuxt.config.ts','nuxt.config.js','svelte.config.js','astro.config.mjs',
        'vite.config.ts','vite.config.js','webpack.config.js','remix.config.js',
        'drizzle.config.ts','knexfile.js','knexfile.ts',
        'requirements.txt','pyproject.toml','pipfile','manage.py','alembic.ini',
        'gemfile','rakefile','config.ru',
        'go.mod','cargo.toml','pom.xml','build.gradle','build.gradle.kts',
        'composer.json','mix.exs',
        'dockerfile','docker-compose.yml','docker-compose.yaml','.env.example','.env.sample',
    ];

    public const SKIP_DIRS = [
        'node_modules/','dist/','build/','.next/','.nuxt/','out/',
        'vendor/','__pycache__/','target/','.git/','coverage/',
        '.turbo/','.cache/','public/','assets/','static/',
    ];

    public const SKIP_FILE_PATTERNS = [
        '/package-lock\.json$/i',
        '/yarn\.lock$/i',
        '/pnpm-lock\.yaml$/i',
        '/composer\.lock$/i',
        '/\.min\.(js|css)$/i',
        '/\.map$/i',
        '/\.(png|jpe?g|gif|svg|ico|webp|avif|mp4|mp3|wav|pdf|zip|tar|gz|woff2?|ttf|eot)$/i',
    ];

    public static function shouldKeepFile(array $entry): bool
    {
        $path = $entry['path'] ?? '';
        $lower = strtolower($path);
        foreach (self::SKIP_DIRS as $d) {
            if (str_starts_with($lower, $d) || str_contains($lower, '/' . $d)) return false;
        }
        foreach (self::SKIP_FILE_PATTERNS as $re) {
            if (preg_match($re, $lower)) return false;
        }
        $size = $entry['size'] ?? null;
        if ($size !== null && ($size > GithubService::MAX_FILE_BYTES || $size === 0)) return false;

        $base = strtolower(basename($path));
        if (in_array($base, self::CONFIG_FILES, true)) return true;

        $ext = pathinfo($base, PATHINFO_EXTENSION);
        return in_array($ext, self::CODE_EXTENSIONS, true);
    }

    public static function rankFile(string $path): int
    {
        $lower = strtolower($path);
        $base = strtolower(basename($path));
        $rank = 100;

        if (str_ends_with($lower, '.prisma') || str_ends_with($lower, '.sql')) $rank -= 70;
        if (str_ends_with($lower, 'schema.rb') || str_ends_with($lower, 'structure.sql')) $rank -= 70;
        if ($base === 'models.py' || str_ends_with($lower, '/models.py')) $rank -= 65;
        if (str_contains($lower, '/migrations/') || str_contains($lower, '/migrate/') || str_contains($lower, 'migration')) $rank -= 60;
        if (str_contains($lower, '/schema') || str_ends_with($lower, 'schema.ts') || str_ends_with($lower, 'schema.js')) $rank -= 60;
        if (str_contains($lower, '/models/') || str_contains($lower, '/entities/') || str_contains($lower, '/repositories/')) $rank -= 55;
        if (str_contains($lower, '/db/') || str_contains($lower, '/database/') || str_contains($lower, 'queries')) $rank -= 50;
        if (preg_match('/\.model\.(ts|js)$/', $lower) || preg_match('/\.entity\.(ts|js)$/', $lower)) $rank -= 50;

        if (str_contains($lower, '/server/') || str_contains($lower, '/api/') || str_contains($lower, '/routes/') || str_contains($lower, '/controllers/')) $rank -= 40;
        if (str_contains($lower, '/handlers/') || str_contains($lower, '/views/') || str_contains($lower, '/resolvers/')) $rank -= 38;
        if (str_ends_with($lower, '+server.ts') || str_ends_with($lower, '+server.js')) $rank -= 40;
        if (preg_match('#route\.(ts|js|tsx|jsx)$#', $lower)) $rank -= 38;
        if (str_contains($lower, '/middleware') || str_ends_with($lower, 'middleware.ts') || str_ends_with($lower, 'middleware.js')) $rank -= 30;
        if (str_contains($lower, 'auth') || str_contains($lower, 'login') || str_contains($lower, 'password') || str_contains($lower, 'session')) $rank -= 30;

        if (str_starts_with($lower, 'src/') || str_starts_with($lower, 'app/') || str_starts_with($lower, 'lib/')) $rank -= 15;
        if (str_contains($lower, '/components/') || str_contains($lower, '/pages/') || str_contains($lower, '/views/components/')) $rank -= 10;

        if (str_starts_with($lower, 'test') || str_contains($lower, '/test/') || str_contains($lower, '/tests/') ||
            str_contains($lower, '__test') || str_contains($lower, '.spec.') || str_contains($lower, '.test.')) $rank += 20;
        if (str_contains($lower, 'example') || str_contains($lower, 'demo') || str_contains($lower, 'docs/')) $rank += 15;

        $rank += substr_count($lower, '/');
        return $rank;
    }

    public static function estimateFileTokens(string $path, string $content): int
    {
        $wrapper = strlen($path) + 50;
        return (int) ceil((strlen($content) + $wrapper) / self::CHARS_PER_TOKEN);
    }
}
