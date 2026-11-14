# PTCGL Replays

This web app turns a Pokémon TCG Live battle log into a replay you can watch.

Paste the battle log from a finished match and the app rebuilds the game one action at a time: board, bench, energy, damage, prizes and special conditions. You can play it, pause it, jump to a turn, or scrub through the timeline.

## Features

- Step-by-step board replay with play/pause, turn navigation and a clickable timeline
- Keyboard shortcuts for playback
- Card art and details pulled from [TCGdex](https://tcgdex.dev)
- Share a replay with a link, or list it publicly
- Browse publicly listed replays
- Accounts are optional: guests can upload, but their replays stay unlisted and are deleted after 90 days

## How it works

1. You paste a battle log.
2. The server parses it into a list of actions (draws, plays, attacks, knockouts…) and stores both the raw log and the parsed result.
3. A background job looks up every card name on TCGdex and caches the art.
4. In the browser, the board is recomputed from the action list each time you move through the replay.

The original log is stored with each replay. After fixing a parser bug, run `php artisan replays:reparse` to rebuild every existing replay with the fix.

## Running it locally

### Requirements

- PHP 8.3+ with the SQLite extension
- Composer
- Node.js and npm

### Setup

```bash
git clone <https://github.com/hugolgs-dev/ptcgl-replays-viewer.git>
cd ptcgl-replays-viewer
composer setup                   # installs deps, creates .env, migrates the SQLite DB, builds assets
php artisan cards:seed-standard  # optional: preloads Standard-legal card art (~170 requests to TCGdex)
composer run dev                     # starts the server, queue worker, logs and Vite
```

Then open `http://localhost:8000`.

A sample log to try is in `resources/samples/festival_lead_vs_zoroark.txt`.

### Tests

```bash
php artisan test --compact
npm run test:js
```

## Built with

Laravel, Inertia, Vue 3, Nuxt UI, Tailwind CSS.

## Disclaimer

This is a fan-made project, not affiliated with or endorsed by Nintendo, The Pokémon Company or The Pokémon Company International. Pokémon and card images are trademarks of their respective owners.
