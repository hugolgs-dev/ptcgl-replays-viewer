<?php

namespace App\Services;

use App\Contracts\CardLookup;
use App\Models\Card;

class CardResolver
{
    public function __construct(private CardLookup $lookup) {}

    /**
     * Resolve any card names not already cached, storing both hits and misses.
     *
     * @param  list<string>  $rawNames
     */
    public function resolveMissing(array $rawNames): void
    {
        $missing = $this->filterUncached($rawNames);

        foreach ($missing as $key => $raw) {
            $result = $this->lookup->find($raw);

            Card::updateOrCreate(['log_name' => $key], [
                'name' => $result['name'] ?? null,
                'tcgdex_id' => $result['tcgdex_id'] ?? null,
                'image' => $result['image'] ?? null,
                'category' => $result['category'] ?? null,
                'resolved_at' => now(),
            ]);
        }
    }

    /**
     * Map normalized lookup keys to a representative raw name, excluding cached ones.
     *
     * @param  list<string>  $rawNames
     * @return array<string, string>
     */
    private function filterUncached(array $rawNames): array
    {
        $byKey = [];

        foreach ($rawNames as $raw) {
            $raw = trim($raw);

            if ($raw === '') {
                continue;
            }

            $byKey[Card::normalizeName($raw)] = $raw;
        }

        if ($byKey === []) {
            return [];
        }

        $cached = Card::whereIn('log_name', array_keys($byKey))->pluck('log_name');

        foreach ($cached as $key) {
            unset($byKey[$key]);
        }

        return $byKey;
    }
}
