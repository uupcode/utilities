# Contributing

Contributions are welcome. Please follow these guidelines.

## Requirements

- PHP 8.1+
- Composer

## Setup

```bash
git clone https://github.com/uupcode/utilities
cd utilities
composer install
```

## Running Tests

```bash
composer test
```

## Static Analysis

```bash
composer analyse
```

## Code Style

Check for violations:

```bash
composer cs
```

Auto-fix:

```bash
composer cs:fix
```

## Submitting a Pull Request

1. Fork the repository and create a feature branch from `main`.
2. Write tests for any new functionality.
3. Ensure `composer test` and `composer analyse` pass with no errors.
4. Ensure `composer cs` reports no violations.
5. Submit a pull request with a clear description of the change and why it is needed.

## Reporting Issues

Please use [GitHub Issues](https://github.com/uupcode/utilities/issues) to report bugs.
Include your PHP version, WordPress version, and a minimal reproduction case.