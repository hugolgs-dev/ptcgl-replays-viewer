<?php

namespace App\Jobs;

use App\Services\CardResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResolveReplayCards implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds between retries */
    public array $backoff = [10, 60];

    /**
     * @param  list<string>  $cardNames
     */
    public function __construct(public array $cardNames) {}

    public function handle(CardResolver $resolver): void
    {
        $resolver->resolveMissing($this->cardNames);
    }
}
