# MonkeysLegion I18n - Tests

This directory contains the test suite for the MonkeysLegion I18n package.

## Running Tests

### Run All Tests

```bash
composer test
```

Or directly with PHPUnit:

```bash
vendor/bin/phpunit
```

### Run Specific Test Suites

```bash
# Unit tests only
vendor/bin/phpunit --testsuite=Unit

# Integration tests only
vendor/bin/phpunit --testsuite=Integration

# Feature tests only
vendor/bin/phpunit --testsuite=Feature
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/Unit/TranslatorTest.php
```

### Run With Coverage

```bash
composer test-coverage
```

This generates HTML coverage report in `coverage/html/index.html`

### Run Quality Checks

```bash
# Run PHPStan + Tests
composer quality

# Run PHPStan only
composer phpstan
```

## Test Structure

```
tests/
├── bootstrap.php           # Test bootstrap file
├── fixtures/               # Test fixtures (created during tests)
├── tmp/                    # Temporary files (auto-cleaned)
│
├── Unit/                   # Unit tests (isolated components)
│   ├── TranslatorTest.php
│   ├── PluralizerTest.php
│   ├── MessageFormatterTest.php
│   └── ...
│
├── Integration/            # Integration tests (components working together)
│   ├── FileLoaderTest.php
│   ├── DatabaseLoaderTest.php
│   └── ...
│
└── Feature/               # Feature tests (complete workflows)
    ├── TranslationWorkflowTest.php
    └── ...
```

## Test Categories

### Unit Tests

Test individual classes in isolation:
- `TranslatorTest` - Core translator functionality
- `PluralizerTest` - Pluralization rules
- `MessageFormatterTest` - Parameter replacement
- `LocaleManagerTest` - Locale detection

### Integration Tests

Test components working together:
- `FileLoaderTest` - Loading from files
- `DatabaseLoaderTest` - Loading from database
- `CacheLoaderTest` - Caching functionality

### Feature Tests

Test complete user workflows:
- `TranslationWorkflowTest` - End-to-end translation
- `LocaleDetectionTest` - Locale detection flow
- `DatabaseManagementTest` - File + Database workflow

## Writing Tests

### Example Unit Test

```php
<?php

namespace MonkeysLegion\I18n\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use MonkeysLegion\I18n\YourClass;

final class YourClassTest extends TestCase
{
    #[Test]
    public function it_does_something(): void
    {
        $instance = new YourClass();
        
        $result = $instance->doSomething();
        
        $this->assertSame('expected', $result);
    }
}
```

### Using Data Providers

```php
#[Test]
#[DataProvider('pluralizationProvider')]
public function it_pluralizes_correctly(int $count, string $expected): void
{
    $result = $this->pluralizer->choose($message, $count, 'en');
    
    $this->assertSame($expected, $result);
}

public static function pluralizationProvider(): array
{
    return [
        [1, 'One item'],
        [5, '5 items'],
        [0, '0 items'],
    ];
}
```

### Testing with Fixtures

```php
protected function setUp(): void
{
    $this->fixturesPath = TEMP_DIR . '/my-test';
    mkdir($this->fixturesPath, 0755, true);
    
    // Create test files
    file_put_contents(
        $this->fixturesPath . '/test.json',
        json_encode(['key' => 'value'])
    );
}

protected function tearDown(): void
{
    // Clean up
    $this->removeDirectory($this->fixturesPath);
}
```

## Coverage Goals

- **Unit Tests**: Aim for 90%+ coverage
- **Integration Tests**: Focus on critical paths
- **Feature Tests**: Cover main user workflows

## Best Practices

1. **One assertion per test** (when practical)
2. **Descriptive test names** - `it_does_something_specific`
3. **Arrange-Act-Assert** pattern
4. **Clean up after tests** - Use `tearDown()`
5. **Use data providers** for multiple similar cases
6. **Mock external dependencies** in unit tests
7. **Use real components** in integration tests

## Continuous Integration

Tests run automatically on:
- Pull requests
- Commits to main branch
- Before releases

### CI Requirements

- ✅ All tests must pass
- ✅ Code coverage > 80%
- ✅ PHPStan level 9 passes

## Troubleshooting

### Tests Fail with "File not found"

Make sure temp directory exists and is writable:

```bash
mkdir -p tests/tmp
chmod 755 tests/tmp
```

### Memory Limit Errors

Increase PHP memory limit:

```bash
php -d memory_limit=-1 vendor/bin/phpunit
```

### Slow Tests

Run tests in parallel:

```bash
vendor/bin/phpunit --parallel=4
```

## Contributing

When adding new features:

1. Write tests first (TDD)
2. Ensure all tests pass
3. Maintain coverage above 80%
4. Add integration tests for complex features
5. Add feature tests for new workflows

## Questions?

See the main [README.md](../README.md) for more information.
