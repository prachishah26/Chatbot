---
paths:
  - '**'
---

# Commands

## Composer is not on PATH

Always run Composer as `php ~/composer2.phar <command>`. Never type bare `composer`; it does not exist on this machine.

- Dev server (warms Ollama, then `artisan dev`): `php ~/composer2.phar run dev`
- Install: `php ~/composer2.phar install`
- After adding or moving classes: `php ~/composer2.phar dump-autoload`
- Test script: `php ~/composer2.phar run test`

## PHP

`php` is PHP 8.5; `composer.json` requires `^8.4.1`. If `php -v` ever reports 7.x, stop and tell the user rather than working around it. `/usr/bin/php8.5` is the fallback binary.

## Verify every change, in this order

1. `vendor/bin/pint --dirty --format agent`
2. `php artisan test --compact` (or a narrower file or `--filter` first)
3. `npm run build` when anything under `resources/` changed

There is no coverage driver (pcov/xdebug) installed, so `--coverage` will not work. Say so instead of claiming a coverage number.

## Artisan

- Scaffold with `php artisan make:<type> --no-interaction` (`make:class`, `make:test --phpunit [--unit]`, `make:provider`, …), then fill in the file.
- Inspect routes with `php artisan route:list --except-vendor`.
- Warm the local model: `php artisan chat:warm`.
