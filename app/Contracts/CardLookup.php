<?php

namespace App\Contracts;

interface CardLookup
{
    /**
     * Resolve a raw card name to its tcgdex data, or null when no match exists.
     *
     * `category` is the trainer sub-type (Item, Supporter, Stadium, Tool) for
     * trainers, otherwise the top-level category (Pokemon, Energy).
     *
     * @return array{tcgdex_id: string, name: string, image: ?string, category: ?string, hp: ?int}|null
     */
    public function find(string $name): ?array;
}
