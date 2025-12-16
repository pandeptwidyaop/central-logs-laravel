# Contributing to Central Logs Laravel

Thank you for considering contributing to Central Logs Laravel! We welcome contributions from the community.

## Development Setup

1. Fork the repository
2. Clone your fork
3. Create a new branch for your feature/fix
4. Make your changes
5. Run tests
6. Submit a pull request

### Using Docker

```bash
# Start development environment
./dev.sh up

# Install dependencies
./dev.sh composer install

# Run tests
./dev.sh test

# Run code quality checks
./dev.sh phpstan
./dev.sh format
```

### Without Docker

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer phpstan

# Format code
composer format
```

## Coding Standards

- Follow PSR-12 coding standards
- Use Laravel Pint for code formatting
- Maintain PHPStan level 8 compliance
- Write tests for new features
- Update documentation as needed

## Pull Request Process

1. **Create a feature branch** from `develop`
2. **Write tests** for your changes
3. **Run the test suite** to ensure all tests pass
4. **Run code quality tools**:
   ```bash
   composer phpstan
   composer format
   ```
5. **Update documentation** if needed (README, CHANGELOG)
6. **Submit pull request** to `develop` branch
7. **Wait for review** and address feedback

## Testing Guidelines

- Write unit tests for new functionality
- Ensure all tests pass before submitting PR
- Aim for high code coverage
- Test both success and failure scenarios

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage
```

## Commit Message Format

Use clear, descriptive commit messages:

```
feat: Add support for custom log formatters
fix: Resolve batch flush memory leak
docs: Update installation instructions
test: Add tests for exception logging
refactor: Simplify batch aggregator logic
```

## Reporting Bugs

When reporting bugs, please include:

- PHP version
- Laravel version
- Package version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Error messages/logs

## Feature Requests

We welcome feature requests! Please:

- Check if the feature already exists
- Describe the use case
- Explain why it would be useful
- Provide examples if possible

## Code of Conduct

- Be respectful and inclusive
- Welcome newcomers
- Focus on constructive feedback
- Help others learn and grow

## Questions?

Feel free to:
- Open an issue for discussion
- Ask questions in pull requests
- Reach out to maintainers

Thank you for contributing! 🎉
