<?php

namespace App\Console\Commands;

use App\Models\Card;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SeedStandardCards extends Command
{
    protected $signature = 'cards:seed-standard';

    protected $description = 'Preload every Standard-legal card (art, HP, category) from tcgdex so replays resolve locally';

    private const BASE_URI = 'https://api.tcgdex.net/v2/en';

    public function handle(): int
    {
        // Legality is only reliable per card on tcgdex (every set reports
        // standard=false), and the card list accepts the filter directly.
        /** @var list<array{id: string, name: string, image?: string}> $cards */
        $cards = Http::timeout(30)->get(self::BASE_URI.'/cards', ['legal.standard' => 'true'])->throw()->json();

        $cards = array_values(array_filter($cards, fn (array $card): bool => isset($card['image'])));

        // Card briefs carry no date; fetch each set once so the newest print of
        // a name wins the upsert (ids are "<set>-<number>").
        $setOf = fn (array $card): string => substr($card['id'], 0, (int) strrpos($card['id'], '-'));
        $releaseDates = [];

        foreach (array_unique(array_map($setOf, $cards)) as $setId) {
            $set = Http::timeout(10)->get(self::BASE_URI.'/sets/'.$setId)->throw()->json();
            $releaseDates[$setId] = (string) ($set['releaseDate'] ?? '');
        }

        usort($cards, fn (array $a, array $b): int => strcmp($releaseDates[$setOf($a)], $releaseDates[$setOf($b)]));

        $bar = $this->output->createProgressBar(count($cards));

        // HP and category only exist on the per-card endpoint: ~3k requests, pooled.
        foreach (array_chunk($cards, 25) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (array $card) => $pool->as($card['id'])->timeout(10)->get(self::BASE_URI.'/cards/'.$card['id']),
                $chunk,
            ));

            foreach ($chunk as $card) {
                $response = $responses[$card['id']] ?? null;
                $detail = $response instanceof Response && $response->successful() ? (array) $response->json() : [];

                Card::updateOrCreate(['log_name' => Card::normalizeName($card['name'])], [
                    'name' => $card['name'],
                    'tcgdex_id' => $card['id'],
                    'image' => $card['image'],
                    'category' => Card::categoryFromTcgdex($detail),
                    'hp' => isset($detail['hp']) ? (int) $detail['hp'] : null,
                    'resolved_at' => now(),
                ]);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info(sprintf('Seeded %d cards from %d set(s).', count($cards), count($releaseDates)));

        return self::SUCCESS;
    }
}
