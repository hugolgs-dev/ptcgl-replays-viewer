<?php

namespace App\Services\Tcgdex;

use App\Contracts\CardLookup;
use App\Models\Card;
use Illuminate\Support\Facades\Http;

class TcgdexCardLookup implements CardLookup
{
    private const BASE_URI = 'https://api.tcgdex.net/v2/en';

    public function find(string $name): ?array
    {
        // Exact match first; the plain filter is a substring match that would
        // turn "Pikachu" into "Surfing Pikachu". Fall back to it only when the
        // exact query is empty (e.g. log name differs from the printed name).
        $brief = $this->firstCard(['name' => 'eq:'.$name]) ?? $this->firstCard(['name' => $name]);

        if ($brief === null) {
            return null;
        }

        $detail = $this->get('/cards/'.$brief['id']);

        return [
            'tcgdex_id' => (string) $brief['id'],
            'name' => (string) $brief['name'],
            'image' => isset($brief['image']) ? (string) $brief['image'] : null,
            'category' => Card::categoryFromTcgdex(is_array($detail) ? $detail : []),
            'hp' => is_array($detail) && isset($detail['hp']) ? (int) $detail['hp'] : null,
        ];
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>|null
     */
    private function firstCard(array $query): ?array
    {
        $cards = $this->get('/cards', $query);
        $brief = is_array($cards) ? ($cards[0] ?? null) : null;

        return is_array($brief) && isset($brief['id'], $brief['name']) ? $brief : null;
    }

    /**
     * Throw on transport/HTTP errors so the queued job retries instead of
     * caching a permanent miss for a card that only failed transiently.
     *
     * @param  array<string, string>  $query
     */
    private function get(string $path, array $query = []): mixed
    {
        return Http::timeout(5)->get(self::BASE_URI.$path, $query)->throw()->json();
    }
}
