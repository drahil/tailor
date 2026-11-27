# Tailor

A Laravel REPL that remembers your work: save sessions, replay them, run them as files, and export them as Artisan commands.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://php.net)
[![CI](https://github.com/drahil/tailor/actions/workflows/ci.yml/badge.svg)](https://github.com/drahil/tailor/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg)](phpstan.neon)

## What is Tailor?

Tailor is a standalone REPL for Laravel, built on PsySH. Your App classes are imported automatically, autocomplete understands Eloquent models and query chains, and every session can be saved, replayed, extended and exported.

## Features

- **Sessions** - Save what you typed, then view, replay, extend or delete it later
- **Session Metadata** - Add descriptions and tags to organize your sessions
- **Session Export** - Turn a session into an Artisan command
- **Auto-imported Classes** - Use `Post` instead of `App\Models\Post`, no `use` statements needed
- **Eloquent Autocomplete** - Attributes, accessors, scopes and query chains such as `Post::popular()->wh<Tab>`
- **Isolated History** - Per-project command history that doesn't pollute your global PsySH history
- **Safe in Production** - Sessions ask for confirmation in production unless tagged `safe-for-prod`

## Installation

```bash
composer require --dev drahil/tailor
```

## Usage

Start Tailor:
```bash
php artisan tailor
```

Run PHP files first, then keep working in the shell:
```bash
php artisan tailor scripts/setup.php
```

### Session Commands

**Save a session:**
```bash
session:save my-work                          # Save current session
session:save my-work -d "API testing"         # Save with description
session:save my-work -t api -t testing        # Save with tags
session:save my-work --force                  # Overwrite existing session
```

**List sessions:**
```bash
session:ls                                    # List all saved sessions
sessions                                      # Alias for session:ls
```

**View session details:**
```bash
session:view my-work                          # View session metadata and commands
view my-work                                  # Alias for session:view
```

**Execute a session:**
```bash
session:execute my-work                       # Run all commands from a session
exec my-work                                  # Alias for session:execute
```
Execution stops at the first command that fails and reports how many commands were not run.

**Add more commands to a session:**
```bash
session:execute my-work                       # Replay it; my-work becomes the active session
# ...run a few more commands...
session:append                                # Append the new commands to my-work
session:append other-work                     # Append this run's new commands to another session
```

**Running sessions in production:**

In production (`APP_ENV=production`), `session:execute` lists the session's commands and asks for confirmation unless the session is tagged `safe-for-prod`.
```bash
session:edit my-work --add-tag safe-for-prod  # Mark a reviewed session as safe to run in production
session:execute my-work --force               # Run an untagged session in production without asking
```
The tag can be changed with `tailor.production.safe_tag` in `config/tailor.php`.

**Run a session from the terminal:** sessions are PHP files, so Tailor can run one directly and leave you at the prompt with its variables.
```bash
php artisan tailor storage/tailor/sessions/my-work.php
```

**Export a session as an Artisan command:**
```bash
session:export my-work                                     # app/Console/Commands/MyWork.php
session:export my-work --path=app/Console/Commands/Tailor  # Another folder, namespace App\Console\Commands\Tailor
session:export my-work --force                             # Overwrite an existing file
```
Run it with `php artisan tailor:my-work`, no Tailor shell needed. The namespace follows the folder through Composer's PSR-4 map. Exported commands always ask for confirmation in production, also for sessions tagged `safe-for-prod`; `--force` skips the question. Class names become `use` statements, including facades such as `DB` and global classes such as `Exception`. Like in Tailor, the value of each command is printed (as JSON, so models respect `$hidden`; other objects by class name), except for assignments and calls such as `dump()`; `-q` hides it.

**Rename a session:**
```bash
session:rename session-2026-11-25-101500 blog-stats   # Give an auto-named session a real name
session:rename old-work new-work --force              # Replace an existing session without asking
```

**Delete a session:**
```bash
session:delete my-work                        # Delete a session (with confirmation)
session:delete my-work --force                # Delete without confirmation
delete my-work -f                             # Short flag version
```

## Configuration

Tailor uses sensible defaults for session storage. Sessions are stored in `storage/tailor/sessions` by default, one PHP file per session that you can edit directly:

```php
<?php
/**
 * @description Quick numbers about posts
 * @tags stats, safe-for-prod
 * @created 2026-11-15T21:54:00+00:00
 * @updated 2026-11-15T21:56:00+00:00
 */

Post::count();
Post::published()->count();
```

Each statement is one command, and statements can span several lines. Sessions saved as JSON by earlier versions are converted automatically.

You can customize storage paths by publishing the configuration file:
```bash
php artisan vendor:publish --tag=tailor-config
```

Then edit `config/tailor.php` to customize storage paths and other settings.

Each Tailor window records only the commands typed in it, so two windows open at once never mix their sessions. Shell commands such as `ls` or `doc` are not recorded. PsySH's experimental interactive readline (`useExperimentalReadline`) is not supported: with it enabled, commands are not recorded and `session:save` has nothing to save.

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13 (Laravel 13 needs PHP 8.3)
- PsySH 0.12.22 or higher, installed with Tailor

## Development

Run tests:
```bash
composer test
```

Run static analysis:
```bash
composer phpstan
```

Run all checks:
```bash
composer check
```

## License

MIT License. See [LICENSE](LICENSE) for details.
