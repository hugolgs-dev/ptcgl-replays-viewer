<?php

namespace App\Console\Commands;

use App\Jobs\ResolveReplayCards;
use App\Models\Replay;
use App\Services\BattleLogParser;
use Illuminate\Console\Command;

class ReparseReplays extends Command
{
    protected $signature = 'replays:reparse';

    protected $description = 'Re-run the battle log parser over every replay that still has its raw log';

    public function handle(BattleLogParser $parser): int
    {
        $count = 0;

        foreach (Replay::whereNotNull('raw_log')->lazyById() as $replay) {
            $replay->content = $parser->parse((string) $replay->raw_log);
            $replay->save();

            ResolveReplayCards::dispatch($replay->cardNames());
            $count++;
        }

        $this->info("Reparsed {$count} replay(s).");

        return self::SUCCESS;
    }
}
