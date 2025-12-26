# Contributing

Contributions are welcome and will be fully credited.

## Pull Requests

- **[PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)** - The easiest way to apply the conventions is to run `composer format`.

- **Add tests!** - Your patch won't be accepted if it doesn't have tests.

- **Document any change in behaviour** - Make sure the `README.md` and any other relevant documentation are kept up-to-date.

- **Consider our release cycle** - We try to follow [SemVer v2.0.0](https://semver.org/). Randomly breaking public APIs is not an option.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please [squash them](https://www.git-scm.com/book/en/v2/Git-Tools-Rewriting-History#Changing-Multiple-Commit-Messages) before submitting.

## Development Setup

1. Fork and clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Create a branch for your feature:
   ```bash
   git checkout -b feature/my-feature
   ```

## Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run code style fixer
composer format

# Run static analysis
composer analyse
```

## Code Style

This package uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting and [PHPStan](https://phpstan.org/) for static analysis.

Before submitting a pull request, ensure:

```bash
# Format code
composer format

# Check for static analysis errors
composer analyse

# Run all tests
composer test
```

## Reporting Issues

When reporting issues, please include:

- PHP version
- Laravel version
- Package version
- Steps to reproduce
- Expected behavior
- Actual behavior

**Happy coding!**