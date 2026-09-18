<?php

namespace App\Models;

use App\Enums\Visibility;
use Database\Factories\ReplayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $slug
 * @property int|null $user_id
 * @property Visibility $visibility
 * @property array<string, mixed> $content
 * @property string|null $raw_log
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $player_one  (listing scope only)
 * @property-read string|null $player_two  (listing scope only)
 * @property-read string|null $winner  (listing scope only)
 * @property-read int|string|null $total_turns  (listing scope only)
 */
#[Fillable(['user_id', 'content', 'raw_log'])]
class Replay extends Model
{
    /** @use HasFactory<ReplayFactory> */
    use HasFactory;

    use MassPrunable;

    /**
     * Days an anonymous (guest) replay is kept before `model:prune` removes it.
     */
    public const GUEST_RETENTION_DAYS = 90;

    /**
     * Length of the public, shareable replay slug.
     */
    public const SLUG_LENGTH = 7;

    protected static function booted(): void
    {
        static::creating(function (Replay $replay): void {
            $replay->slug ??= self::generateUniqueSlug();
        });
    }

    /**
     * Generate a random base62 slug that isn't already taken.
     */
    public static function generateUniqueSlug(): string
    {
        do {
            $slug = Str::random(self::SLUG_LENGTH);
        } while (self::where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * Guest replays can never be managed by anyone, so expire them.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->whereNull('user_id')
            ->where('created_at', '<', now()->subDays(self::GUEST_RETENTION_DAYS));
    }

    /**
     * Listing projection: pull the handful of summary fields out of the JSON
     * column instead of hydrating the whole action list for every row.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeListing(Builder $query): Builder
    {
        return $query->select([
            'id',
            'slug',
            'visibility',
            'created_at',
            'content->players[0]->name as player_one',
            'content->players[1]->name as player_two',
            'content->winner as winner',
            'content->totalTurns as total_turns',
        ]);
    }

    /**
     * Route-model binding resolves replays by their public slug.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Distinct card names referenced across the parsed actions.
     *
     * @return list<string>
     */
    public function cardNames(): array
    {
        $names = [];

        foreach ($this->content['actions'] ?? [] as $action) {
            foreach ($action['cards'] ?? [] as $card) {
                if (is_string($card) && $card !== '') {
                    $names[] = $card;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Whether the given user (or guest, when null) may view this replay.
     */
    public function isViewableBy(?User $user): bool
    {
        if ($this->visibility !== Visibility::Private) {
            return true;
        }

        return $user !== null && $user->id === $this->user_id;
    }

    /**
     * Whether the given user owns this replay and may edit it.
     */
    public function isEditableBy(?User $user): bool
    {
        return $user !== null && $this->user_id !== null && $user->id === $this->user_id;
    }

    /**
     * Lightweight projection for hub and "my replays" listings.
     *
     * @return array{slug: string, players: list<string>, winner: string|null, totalTurns: int, visibility: string, createdAt: string|null}
     */
    public function summary(): array
    {
        $projected = $this->hasAttribute('player_one');

        $players = $projected
            ? [$this->player_one, $this->player_two]
            : array_column($this->content['players'] ?? [], 'name');

        return [
            'slug' => $this->slug,
            'players' => array_values(array_filter($players, fn ($name): bool => is_string($name) && $name !== '')),
            'winner' => $projected ? $this->winner : ($this->content['winner'] ?? null),
            'totalTurns' => (int) ($projected ? $this->total_turns : ($this->content['totalTurns'] ?? 0)),
            'visibility' => $this->visibility->value,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'visibility' => Visibility::class,
        ];
    }
}
