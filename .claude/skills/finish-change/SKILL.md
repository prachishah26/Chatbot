---
name: finish-change
description: Mandatory checklist to run before saying any code change in this project is done, committing, or opening a PR. Formats, tests, and checks the change against the project's structure and security rules.
---

# Finish a change

Run every step. If a step fails, fix it and rerun from that step. Report results honestly: say which steps passed, which were skipped and why, and quote any failure output.

## 1. Format

```bash
vendor/bin/pint --dirty --format agent
```

## 2. Autoload (only if classes were added, moved or renamed)

```bash
php ~/composer2.phar dump-autoload
```

## 3. Tests

```bash
php artisan test --compact
```

All tests must pass. No coverage driver is installed, so do not report a coverage percentage.

## 4. Front end (only if `resources/` changed)

```bash
npm run build
```

## 5. Structure check

Run these; each should print nothing:

```bash
# Old namespaces that must not come back
grep -rn 'App\\Chat\\Support\|App\\Chat\\Providers' app tests database config resources
# Vendor names leaking into code in the shared LLM layer or value objects (doc comments are fine)
grep -rn -i 'gemini\|ollama' app/Chat/Data app/Chat/Llm/*.php app/Chat/Contracts | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|/\*)'
# Raw JSON responses outside ApiResponse
grep -rn 'response()->json' app --include=*.php | grep -v 'app/Http/Responses/ApiResponse.php'
# Direct HTTP calls in providers (must go through LlmHttpClient)
grep -rn 'Http::\|->http->' app/Chat/Llm/*/
# env() outside config/
grep -rn "env(" app routes resources/views
# Controller methods that are not resource verbs (split into a new controller instead)
grep -rnP 'public function (?!index|show|create|store|edit|update|destroy|__construct)\w+\(' app/Http/Controllers
# Class names that break the naming table in .ai/rules/structure.md
find app -name '*Store.php' -o -name '*Manager.php' -o -name '*Helper.php'
# Unscoped conversation lookups
grep -rn 'Conversation::find\|Conversation::where' app | grep -v 'app/Chat/Conversations/'
```

Then confirm by reading the diff (`git diff --stat` and the changed files):
- Every new or moved file sits where `.ai/rules/structure.md` says it belongs, and the map was updated if needed.
- Every new class has a test.
- No secret, `.env` value, `dd()`, `dump()` or `var_dump()` was added.
- New non-GET routes have a `throttle:` limiter and a FormRequest.
- No user-facing string includes an exception message or upstream response body.

## 6. Commit (only when the user asks)

Use a conventional-commit message (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`). Work on a branch, not `main`.
