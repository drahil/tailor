# Tailor

Enhanced Laravel Tinker with session management and code export.

[![License](https://img.shields.io/packagist/l/yourname/tailor.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/yourname/tailor.svg)](https://php.net)

## What is Tailor?

Tailor extends Laravel Tinker with the ability to save your work sessions, resume them later, and export your queries to PHP files or tests.

## Features

- **Session Management** - Save and load your Tinker sessions
- **Code Export** - Export commands to PHP scripts or test files
- **Enhanced Autocomplete** - Smart completion for models and Eloquent methods
- **Command History** - Track and review your commands
- **Code Blocks** - Group related commands together

## Installation

```bash
composer require --dev yourname/tailor
```

## Usage

Start Tailor:
```bash
php artisan tailor
```

Load a saved session:
```bash
php artisan tailor --session=my-work
```

### Basic Commands

**Session Management:**
```bash
session:save my-work    # Save current session
session:execute my-work # Execute a saved session
session:delete my-work  # Delete a saved session
session:list            # List all sessions
```

**Code Export:**
```bash
export php MyScript     # Export to PHP class
export test MyTest      # Export to test file
```

**Utilities:**
```bash
history                 # View command history
help                    # Show available commands
```

## Configuration

Optionally publish the config file:
```bash
php artisan vendor:publish --tag=tailor-config
```

Edit `config/tailor.php` to customize storage paths, export settings, and autocomplete behavior.

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x

## Documentation

For detailed information, see:
- [IMPLEMENTATION_PLAN.md](../plans/IMPLEMENTATION_PLAN.md) - Development roadmap
- [TECHNICAL_ARCHITECTURE.md](../plans/TECHNICAL_ARCHITECTURE.md) - Technical details
- [USER_WORKFLOW_EXAMPLES.md](../plans/USER_WORKFLOW_EXAMPLES.md) - Usage examples

## Contributing

Contributions welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

MIT License. See [LICENSE](LICENSE) for details.
