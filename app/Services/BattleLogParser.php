<?php

declare(strict_types=1);

namespace App\Services;

/**
 * @phpstan-type Action array{id: int, turn: int, phase: string, player: string, details: list<string>, type: string, description: string, cards?: list<string>, target?: string, damage?: int}
 */
class BattleLogParser
{
    /**
     * Parse a raw battle log string into a structured game array.
     *
     * Action ids are assigned sequentially from 0, so `actions[$i]['id'] === $i`.
     *
     * @return array{
     *     players: array{0: array{name: string}, 1: array{name: string}},
     *     actions: list<Action>,
     *     totalTurns: int,
     *     winner: ?string,
     *     coinFlipWinner: ?string
     * }
     */
    public function parse(string $log): array
    {
        // PTCGL mixes straight and curly apostrophes ("My11days’s Tatsugiri").
        $log = str_replace(["\u{2019}", "\u{2018}"], "'", $log);

        $lines = array_filter(array_map('trim', explode("\n", $log)), fn (string $line) => $line !== '');

        $actions = [];
        $actionId = 0;
        $currentTurn = 0;
        $currentPlayer = '';
        $phase = 'setup';
        $winner = null;
        $coinFlipWinner = null;

        $players = [['name' => ''], ['name' => '']];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip "Setup" header
            if ($line === 'Setup') {
                $phase = 'setup';

                continue;
            }

            // Turn header. Current logs: "SomePlayer's Turn"; older ones: "Turn # 1 - SomePlayer's Turn".
            if (preg_match('/^(?:Turn # (\d+) - )?(.+)\'s Turn$/', $line, $turnMatch)) {
                $phase = 'game';
                $currentTurn = $turnMatch[1] !== '' ? (int) $turnMatch[1] : $currentTurn + 1;
                $currentPlayer = $turnMatch[2];

                continue;
            }

            // Between-turns header; its effects are attributed to the current turn.
            if ($line === 'Pokémon Checkup') {
                continue;
            }

            // Detail lines. "- " is stripped; "• " lists are kept intact so the
            // board reducer can tell a card list from a sentence.
            if (str_starts_with($line, '-') || str_starts_with($line, "\u{2022}")) {
                $detail = str_starts_with($line, '-') ? trim(substr($line, 1)) : $line;
                if (count($actions) > 0) {
                    $lastIndex = count($actions) - 1;
                    $actions[$lastIndex]['details'][] = $detail;
                }

                continue;
            }

            // Parse main action line
            $action = $this->parseActionLine($line, $currentTurn, $phase, $currentPlayer);

            if ($action !== null) {
                $action['id'] = $actionId++;
                $actions[] = $action;

                // Track winner
                if ($action['type'] === 'win') {
                    $winner = $action['player'];
                }

                // Track coin flip winner
                if ($action['type'] === 'coin_result') {
                    $coinFlipWinner = $action['player'];
                }

                // Discover player names
                if ($players[0]['name'] === '' && $action['player'] !== '') {
                    $players[0]['name'] = $action['player'];
                } elseif (
                    $players[1]['name'] === ''
                    && $action['player'] !== ''
                    && $action['player'] !== $players[0]['name']
                ) {
                    $players[1]['name'] = $action['player'];
                }
            }
        }

        return [
            'players' => $players,
            'actions' => $actions,
            'totalTurns' => $currentTurn,
            'winner' => $winner,
            'coinFlipWinner' => $coinFlipWinner,
        ];
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /**
     * @return Action|null
     */
    private function parseActionLine(string $line, int $turn, string $phase, string $currentPlayer): ?array
    {
        // Extract leading player name
        $player = $currentPlayer;
        if (preg_match('/^(\w+)\s/', $line, $pm)) {
            $player = $pm[1];
        }

        $base = [
            'id' => 0,
            'turn' => $turn,
            'phase' => $phase,
            'player' => $player,
            'details' => [],
        ];

        // Coin flip
        if (str_contains($line, 'chose') && str_contains($line, 'coin flip')) {
            return array_merge($base, ['type' => 'coin_flip', 'description' => $line]);
        }

        // Coin toss result
        if (str_contains($line, 'won the coin toss')) {
            return array_merge($base, ['type' => 'coin_result', 'description' => $line]);
        }

        // First turn decision
        if (str_contains($line, 'decided to go')) {
            return array_merge($base, ['type' => 'first_turn_decision', 'description' => $line]);
        }

        // Draw opening hand
        if (str_contains($line, 'drew') && str_contains($line, 'opening hand')) {
            return array_merge($base, ['type' => 'draw_opening_hand', 'description' => $line]);
        }

        // Play to Active Spot
        if (str_contains($line, 'played') && str_contains($line, 'to the Active Spot')) {
            preg_match('/played (.+) to the Active Spot/', $line, $m);

            return array_merge($base, [
                'type' => 'play_to_active',
                'description' => $line,
                'cards' => $m ? [$m[1]] : [],
            ]);
        }

        // Play to Bench
        if (str_contains($line, 'played') && str_contains($line, 'to the Bench')) {
            preg_match('/played (.+) to the Bench/', $line, $m);

            return array_merge($base, [
                'type' => 'play_to_bench',
                'description' => $line,
                'cards' => $m ? [$m[1]] : [],
            ]);
        }

        // Draw card(s)
        if (preg_match('/^\w+ drew /', $line)) {
            return array_merge($base, ['type' => 'draw_card', 'description' => $line]);
        }

        // Play Item card
        $itemKeywords = ['Ball', 'Pokégear', 'Vessel', 'Blender', 'Catcher', 'Switch', 'Stretcher'];
        if (str_contains($line, 'played') && $this->containsAny($line, $itemKeywords)) {
            preg_match('/played (.+)\./', $line, $m);

            return array_merge($base, [
                'type' => 'item',
                'description' => $line,
                'cards' => $m ? [$m[1]] : [],
            ]);
        }

        // Play Supporter
        $supporterKeywords = ["Boss's Orders", "Professor's Research", 'Arven', 'Carmine', 'Iono', 'Crispin'];
        if (str_contains($line, 'played') && $this->containsAny($line, $supporterKeywords)) {
            preg_match('/played (.+)\./', $line, $m);

            return array_merge($base, [
                'type' => 'supporter',
                'description' => $line,
                'cards' => $m ? [$m[1]] : [],
            ]);
        }

        // Play Stadium
        if (str_contains($line, 'to the Stadium spot')) {
            preg_match('/played (.+) to the Stadium/', $line, $m);

            return array_merge($base, [
                'type' => 'stadium',
                'description' => $line,
                'cards' => $m ? [$m[1]] : [],
            ]);
        }

        // Attach Energy
        if (str_contains($line, 'attached') && str_contains($line, 'Energy')) {
            return array_merge($base, ['type' => 'attach_energy', 'description' => $line]);
        }

        // Attach Tool
        if (str_contains($line, 'attached') && str_contains($line, 'to')) {
            return array_merge($base, ['type' => 'attach_tool', 'description' => $line]);
        }

        // Use Attack (with damage) — checked before abilities: both use "used"
        if (preg_match('/(\w+)\'s (.+) used (.+) on .+\'s (.+) for (\d+) damage/', $line, $m)) {
            return array_merge($base, [
                'type' => 'use_attack',
                'description' => $line,
                'player' => $m[1],
                'target' => $m[4],
                'damage' => (int) $m[5],
            ]);
        }

        // Use Ability: "<Player>'s <Pokemon> used <Ability>."
        if (preg_match('/^(\w+)\'s (.+) used (.+)\.$/', $line, $m)) {
            return array_merge($base, [
                'type' => 'use_ability',
                'description' => $line,
                'player' => $m[1],
                'target' => $m[2],
            ]);
        }

        // Retreat
        if (str_contains($line, 'retreated')) {
            return array_merge($base, ['type' => 'retreat', 'description' => $line]);
        }

        // Switch Active / Now in Active Spot
        if (str_contains($line, 'is now in the Active Spot')) {
            preg_match('/(.+?)\'s (.+) is now in the Active Spot/', $line, $m);

            return array_merge($base, [
                'type' => 'switch_active',
                'description' => $line,
                'player' => $m ? $m[1] : $player,
                'cards' => $m ? [$m[2]] : [],
            ]);
        }

        // Evolve
        if (str_contains($line, 'evolved')) {
            preg_match('/evolved (.+) to (.+) (?:on|in) the/', $line, $m);

            return array_merge($base, [
                'type' => 'evolve',
                'description' => $line,
                'cards' => $m ? [$m[1], $m[2]] : [],
            ]);
        }

        // Knockout
        if (str_contains($line, 'was Knocked Out')) {
            preg_match('/(\w+)\'s (.+) was Knocked Out/', $line, $m);

            return array_merge($base, [
                'type' => 'knockout',
                'description' => $line,
                'player' => $m ? $m[1] : $player,
                'cards' => $m ? [$m[2]] : [],
            ]);
        }

        // Take Prize
        if (str_contains($line, 'took') && str_contains($line, 'Prize')) {
            return array_merge($base, ['type' => 'take_prize', 'description' => $line]);
        }

        // End turn
        if (str_contains($line, 'ended their turn')) {
            return array_merge($base, ['type' => 'end_turn', 'description' => $line]);
        }

        // Win
        if (preg_match('/\b(\w+) wins\b/', $line, $m)) {
            return array_merge($base, [
                'type' => 'win',
                'description' => $line,
                'player' => $m[1],
            ]);
        }

        // Exp. Share activation
        if (str_contains($line, 'Exp. Share was activated')) {
            return array_merge($base, ['type' => 'exp_share', 'description' => $line]);
        }

        // Discard
        if (str_contains($line, 'discarded')) {
            return array_merge($base, ['type' => 'discard', 'description' => $line]);
        }

        // Shuffle
        if (str_contains($line, 'shuffled')) {
            return array_merge($base, ['type' => 'shuffle_deck', 'description' => $line]);
        }

        // Was switched (from Boss's Orders, etc.)
        // "<Owner>'s <Incoming> was switched with <Owner>'s <Outgoing> to become the Active Pokémon."
        if (str_contains($line, 'was switched with')) {
            preg_match('/^(\w+)\'s (.+?) was switched with/', $line, $m);

            return array_merge($base, [
                'type' => 'switch_active',
                'description' => $line,
                'player' => $m ? $m[1] : $player,
                'cards' => $m ? [$m[2]] : [],
            ]);
        }

        // Generic played card
        if (str_contains($line, 'played')) {
            preg_match('/played (.+?)\.?$/', $line, $m);

            return array_merge($base, [
                'type' => 'play_card',
                'description' => $line,
                'cards' => $m ? [$m[1]] : [],
            ]);
        }

        // Fallback
        if ($line !== '' && ! str_starts_with($line, 'Damage breakdown')) {
            return array_merge($base, ['type' => 'unknown', 'description' => $line]);
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
