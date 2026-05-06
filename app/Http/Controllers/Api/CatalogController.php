<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class CatalogController extends Controller
{
    public function index()
    {
        $cfg = config('codereview');

        // Sort discount tiers by selected-count for predictable client rendering
        $tiers = $cfg['bundle_discount_pct'] ?? [];
        ksort($tiers);

        return response()->json([
            'minTotalCents' => $cfg['min_total_cents'],
            'bundleDiscountPct' => $tiers,
            'categories' => array_values(array_map(fn ($c) => [
                'key' => $c['key'],
                'name' => $c['name'],
                'priceCents' => $c['price_cents'],
                'tagline' => $c['tagline'] ?? '',
                'includes' => $c['includes'] ?? [],
            ], $cfg['categories'])),
        ]);
    }
}
