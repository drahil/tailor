# Tailor

Enhanced Laravel Tinker with session management and isolated history.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://php.net)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)](tests)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg)](phpstan.neon)

## What is Tailor?

Tailor extends Laravel Tinker with powerful session management, allowing you to save your REPL sessions, execute them later, and maintain isolated command history per project.

## Features

- **Session Management** - Save, load, view, and execute your Tinker sessions
- **Session Metadata** - Add descriptions and tags to organize your sessions
- **Isolated History** - Per-project command history that doesn't pollute your global PsySH history
- **Session Tracking** - Track commands and variables during your REPL session
- **Session Execution** - Replay saved sessions to reproduce your work

## Installation

```bash
composer require --dev drahil/tailor
```

## Usage

Start Tailor:
```bash
php artisan tailor
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
session:list                                  # List all saved sessions
sessions                                      # Alias for session:list
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

**Delete a session:**
```bash
session:delete my-work                        # Delete a session (with confirmation)
session:delete my-work --force                # Delete without confirmation
delete my-work -f                             # Short flag version
```

## Configuration

Tailor uses sensible defaults for session storage. Sessions are stored in `storage/tailor/sessions` by default.

You can customize storage paths by publishing the configuration file:
```bash
php artisan vendor:publish --tag=tailor-config
```

Then edit `config/tailor.php` to customize storage paths and other settings.

## Requirements

- PHP 8.2 or higher
- Symfony Console ^7.3
- PsySH ^0.12.12

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
