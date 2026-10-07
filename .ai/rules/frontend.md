---
paths:
  - 'resources/**'
---

# Frontend

## No framework

Blade renders the page. Vanilla ES modules in `resources/js/chat/` enhance it, and `resources/js/app.js` only calls `initChat()`. Do not add React, Vue, Alpine or jQuery without asking.

## DOM hooks are data attributes

JS finds elements through `data-*` attributes (`data-chat-log`, `data-composer-slot`, …), never through CSS classes. When you rename one, update the Blade view and the JS module together.

## HTML injection

Assign `innerHTML` only from static templates or from `renderMarkdown()` in `markdown.js`, which escapes its input. Put any other text in with `textContent`. Server data reaches JS as `@json(...)` inside `<script type="application/json">`.

## Network

Only `api.js` calls `fetch`. It sends the CSRF token and turns every failure into a user-safe `Error`.

## Styling

Tailwind 4 with semantic tokens defined in `resources/css/app.css` (`@theme`). Use the tokens, not raw colour shades. Icons are Blade components (`<x-icon-send />`).

## Seeing changes

If a change does not show up in the browser, the user needs `npm run build` or `php ~/composer2.phar run dev`.
