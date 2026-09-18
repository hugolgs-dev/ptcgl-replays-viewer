<?php

namespace Tests\Unit;

use App\Services\BattleLogParser;
use PHPUnit\Framework\TestCase;

class BattleLogParserTest extends TestCase
{
    private function parseLine(string $line): array
    {
        $game = (new BattleLogParser)->parse("Setup\nAsh drew 7 cards for the opening hand.\nTurn # 1 - Ash's Turn\n{$line}");

        return end($game['actions']);
    }

    public function test_abilities_are_detected_when_names_contain_on_or_for(): void
    {
        $action = $this->parseLine("Ash's Lumineon V used Luminous Sign.");

        $this->assertSame('use_ability', $action['type']);
        $this->assertSame('Lumineon V', $action['target']);

        $this->assertSame('use_ability', $this->parseLine("Ash's Gholdengo ex used Coin Bonus.")['type']);
    }

    public function test_attacks_are_not_mistaken_for_abilities(): void
    {
        $action = $this->parseLine("Ash's Charizard ex used Burning Darkness on Gary's Pikachu for 180 damage.");

        $this->assertSame('use_attack', $action['type']);
        $this->assertSame('Pikachu', $action['target']);
        $this->assertSame(180, $action['damage']);
    }

    public function test_wins_requires_a_whole_word(): void
    {
        $this->assertSame('play_card', $this->parseLine('Ash played Twins.')['type']);

        $action = $this->parseLine('Ash wins.');
        $this->assertSame('win', $action['type']);
        $this->assertSame('Ash', $action['player']);
    }

    public function test_forced_switches_carry_the_incoming_pokemon(): void
    {
        $action = $this->parseLine("Gary's Toucannon was switched with Gary's Tatsugiri to become the Active Pokémon.");

        $this->assertSame('switch_active', $action['type']);
        $this->assertSame('Gary', $action['player']);
        $this->assertSame(['Toucannon'], $action['cards']);
    }

    public function test_a_real_log_parses_end_to_end(): void
    {
        $game = (new BattleLogParser)->parse(file_get_contents(__DIR__.'/../Fixtures/froslass-vs-toucannon.txt'));

        $this->assertSame(['Alice', 'Bob'], [$game['players'][0]['name'], $game['players'][1]['name']]);
        $this->assertSame(9, $game['totalTurns']);
        $this->assertSame('Alice', $game['winner']);
        $this->assertSame('Alice', $game['coinFlipWinner']);

        $byType = array_count_values(array_column($game['actions'], 'type'));
        $this->assertSame(5, $byType['use_attack']);
        $this->assertSame(7, $byType['knockout']);
        $this->assertLessThan(0.15, ($byType['unknown'] ?? 0) / count($game['actions']));

        // Curly apostrophe in the target's name must not break attack detection.
        $clutch = collect($game['actions'])->firstWhere('description', "Alice's Yveltal used Clutch on Bob's Tatsugiri for 20 damage.");
        $this->assertSame(['use_attack', 'Tatsugiri', 20], [$clutch['type'], $clutch['target'], $clutch['damage']]);

        // Turn headers without numbers still count, and the checkup header is not an action.
        $this->assertSame(1, collect($game['actions'])->firstWhere('description', 'Alice drew Staryu.')['turn']);
        $this->assertNull(collect($game['actions'])->firstWhere('description', 'Pokémon Checkup'));

        $mega = collect($game['actions'])->firstWhere('description', 'Alice evolved Staryu to Mega Starmie ex in the Active Spot.');
        $this->assertSame(['Staryu', 'Mega Starmie ex'], $mega['cards']);
    }
}
