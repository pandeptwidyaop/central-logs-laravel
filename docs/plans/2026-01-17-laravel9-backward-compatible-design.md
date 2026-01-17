# Laravel 9 Support - Backward Compatible Design

**Date**: 2026-01-17
**Status**: Approved
**Approach**: Single Codebase with ArrayAccess Bridge Pattern

## Overview

Add Laravel 9 support to Central Logs Laravel package using **single codebase backward compatibility** approach - no branch splitting, minimal maintenance overhead.

### Current State
- **Laravel**: 10/11/12 only (`^10.0|^11.0|^12.0`)
- **PHP**: ^8.1
- **Monolog**: ^3.0 (uses `LogRecord` objects, `Level` enum)
- **Version**: 1.2.3

### Target State
- **Laravel**: 9/10/11/12 (`^9.0|^10.0|^11.0|^12.0`)
- **PHP**: ^8.0 (downgrade to support L9)
- **Monolog**: ^2.0|^3.0 (dual version support)
- **Version**: 1.3.0 (minor bump - backward compatible)

### Motivation

Client project on Laravel 9 with long-term timeline (1-2 tahun), risky to upgrade. Priority: **simplicity & minimal maintenance overhead**.

### Design Philosophy

**Single Codebase Approach**: Leverage Monolog 3's backward compatibility layer (`ArrayAccess` implementation in `LogRecord`) to support both versions in one codebase without complex conditionals.

## Technical Approach

### Core Strategy: ArrayAccess Bridge Pattern

Monolog 3's `LogRecord` implements `ArrayAccess` for backward compatibility. We will:

1. **Type hint as union**: `array|LogRecord` (PHP 8.0+)
2. **Access using array syntax**: `$record['key']` works on both array and object
3. **Level detection**: Runtime check for Monolog version

### Key Differences: Monolog 2 vs 3

| Feature | Monolog 2 | Monolog 3 |
|---------|-----------|-----------|
| Record Type | `array` | `LogRecord` object |
| Level Type | `int` constant | `Level` enum |
| Access Pattern | `$record['key']` | `$record->key` or `$record['key']` (BC) |
| Laravel Support | Laravel 9 | Laravel 10+ |
| PHP Requirement | ^8.0 | ^8.1+ |

### Files to Modify

1. **composer.json** - Relax dependencies
2. **CentralLogsHandler.php** - Type hints, level handling
3. **LogTransformer.php** - Array access pattern, level mapping
4. **CI/CD workflows** - Matrix testing
5. **Documentation** - Compatibility matrix

## Implementation Plan

### Phase 1: Update Dependencies

**File**: `composer.json`

Update the following sections:

```json
{
  "require": {
    "php": "^8.0",
    "illuminate/support": "^9.0|^10.0|^11.0|^12.0",
    "illuminate/contracts": "^9.0|^10.0|^11.0|^12.0",
    "illuminate/queue": "^9.0|^10.0|^11.0|^12.0",
    "illuminate/console": "^9.0|^10.0|^11.0|^12.0",
    "monolog/monolog": "^2.0|^3.0",
    "guzzlehttp/guzzle": "^7.8"
  },
  "require-dev": {
    "orchestra/testbench": "^7.0|^8.0|^9.0|^10.0",
    "phpunit/phpunit": "^9.5|^10.0|^11.0",
    "mockery/mockery": "^1.6",
    "phpstan/phpstan": "^1.10",
    "laravel/pint": "^1.13"
  }
}
```

**Verification**: Run `composer update --dry-run` to check for conflicts.

### Phase 2: Update CentralLogsHandler

**File**: `src/Handler/CentralLogsHandler.php`

**Changes**:

1. Update constructor to handle both Level enum and int:
   - Add `normalizeLevel()` helper
   - Type hint: `int|\Monolog\Level $level = null`

2. Update `write()` method:
   - Type hint: `array|\Monolog\LogRecord $record`
   - Keep array access syntax (already compatible)

3. Update `handleError()` method:
   - Type hint: `array|\Monolog\LogRecord $record`

4. Update `logToFallback()` method:
   - Add `getLevelName()` helper for PSR level conversion
   - Handle both int and Level enum

5. Add helper methods:
   ```php
   protected function normalizeLevel(int|Level|null $level): int|Level
   {
       if ($level === null) {
           return class_exists(Level::class) ? Level::Debug : 100;
       }
       return $level;
   }

   protected function getLevelName(int|Level $level): string
   {
       if ($level instanceof Level) {
           return $level->toPsrLogLevel();
       }
       return match($level) {
           100 => 'debug',
           200 => 'info',
           250 => 'notice',
           300 => 'warning',
           400 => 'error',
           500 => 'critical',
           550 => 'alert',
           600 => 'emergency',
           default => 'info',
       };
   }
   ```

**Verification**: Run PHPStan to check type compatibility.

### Phase 3: Update LogTransformer

**File**: `src/Support/LogTransformer.php`

**Changes**:

1. Update `transform()` method:
   - Type hint: `array|\Monolog\LogRecord $record`
   - Change property access to array access:
     - `$record->message` → `$record['message']`
     - `$record->level` → `$record['level']`
     - `$record->datetime` → `$record['datetime']`
     - `$record->context` → `$record['context']`
     - `$record->extra` → `$record['extra']`

2. Update `mapLevel()` method to handle both int and Level enum:
   ```php
   protected function mapLevel(int|\Monolog\Level $level): string
   {
       // Monolog 3: Level enum
       if ($level instanceof \Monolog\Level) {
           return match ($level) {
               \Monolog\Level::Debug => 'DEBUG',
               \Monolog\Level::Info, \Monolog\Level::Notice => 'INFO',
               \Monolog\Level::Warning => 'WARN',
               \Monolog\Level::Error => 'ERROR',
               \Monolog\Level::Critical, \Monolog\Level::Alert, \Monolog\Level::Emergency => 'CRITICAL',
           };
       }

       // Monolog 2: integer constants
       return match ($level) {
           100 => 'DEBUG',
           200, 250 => 'INFO',
           300 => 'WARN',
           400 => 'ERROR',
           500, 550, 600 => 'CRITICAL',
           default => 'INFO',
       };
   }
   ```

3. Update `buildMetadata()` method:
   - Use array access: `$record['context']`, `$record['extra']`

**Verification**: Run PHPStan and existing unit tests.

### Phase 4: Update CI/CD

**File**: `.github/workflows/tests.yml`

Create matrix testing for all Laravel versions:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        include:
          # Laravel 9 (Monolog 2)
          - laravel: 9.*
            testbench: 7.*
            php: '8.0'
          - laravel: 9.*
            testbench: 7.*
            php: '8.1'
          - laravel: 9.*
            testbench: 7.*
            php: '8.2'

          # Laravel 10 (Monolog 3)
          - laravel: 10.*
            testbench: 8.*
            php: '8.1'
          - laravel: 10.*
            testbench: 8.*
            php: '8.2'

          # Laravel 11 (Monolog 3)
          - laravel: 11.*
            testbench: 9.*
            php: '8.2'
          - laravel: 11.*
            testbench: 9.*
            php: '8.3'

          # Laravel 12 (Monolog 3)
          - laravel: 12.*
            testbench: 10.*
            php: '8.2'
          - laravel: 12.*
            testbench: 10.*
            php: '8.3'

    name: L${{ matrix.laravel }} - P${{ matrix.php }}

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, pdo, pdo_sqlite
          coverage: none

      - name: Install dependencies
        run: |
          composer require "laravel/framework:${{ matrix.laravel }}" \
                         "orchestra/testbench:${{ matrix.testbench }}" \
                         --no-interaction --no-update
          composer update --prefer-stable --no-interaction

      - name: Execute tests
        run: vendor/bin/phpunit

      - name: Execute PHPStan
        run: vendor/bin/phpstan analyse
```

**Verification**: Trigger CI pipeline and verify all matrix combinations pass.

### Phase 5: Update Documentation

**File**: `README.md`

Add compatibility section after "Requirements":

```markdown
## Version Compatibility

| Package Version | Laravel | PHP | Monolog |
|----------------|---------|-----|---------|
| 1.3+ | 9.x, 10.x, 11.x, 12.x | ^8.0 | ^2.0\|^3.0 |
| 1.0-1.2 | 10.x, 11.x, 12.x | ^8.1 | ^3.0 |

## Requirements

- PHP 8.0 or higher
- Laravel 9.x, 10.x, 11.x, or 12.x
- Guzzle HTTP Client 7.8+
- Central Logs instance (running at accessible URL)
```

**File**: `CHANGELOG.md`

Add new entry at the top:

```markdown
## [1.3.0] - 2026-01-17

### Added
- Laravel 9 support with backward compatible implementation
- Support for Monolog 2.x and 3.x in single codebase
- PHP 8.0 support

### Changed
- Relaxed Laravel dependency to `^9.0|^10.0|^11.0|^12.0`
- Relaxed PHP dependency to `^8.0`
- Updated Monolog dependency to `^2.0|^3.0`
- Refactored handler to use array access pattern for Monolog compatibility

### Technical
- Implemented ArrayAccess bridge pattern for Monolog 2/3 compatibility
- Added runtime level detection for proper PSR level conversion
- Updated CI/CD matrix to test all Laravel 9-12 combinations
```

**Verification**: Review documentation for clarity.

### Phase 6: Testing & Validation

**Local Testing**:

1. Test with Laravel 9:
   ```bash
   composer require "laravel/framework:^9.0" "orchestra/testbench:^7.0" --no-update
   composer update --prefer-stable
   vendor/bin/phpunit
   vendor/bin/phpstan analyse
   ```

2. Test with Laravel 10:
   ```bash
   composer require "laravel/framework:^10.0" "orchestra/testbench:^8.0" --no-update
   composer update --prefer-stable
   vendor/bin/phpunit
   vendor/bin/phpstan analyse
   ```

3. Manual testing in actual Laravel 9 project

**Verification**: All tests pass, PHPStan passes, manual verification successful.

## Success Criteria

- ✅ All existing unit tests pass on Laravel 9-12
- ✅ All existing feature tests pass on Laravel 9-12
- ✅ PHPStan analysis passes (level 5+)
- ✅ No deprecation warnings in any version
- ✅ Batch, Async, and Sync modes all work
- ✅ CI/CD pipeline green for all matrix combinations
- ✅ Package installs successfully on Laravel 9
- ✅ Logs sent successfully from Laravel 9 project
- ✅ No breaking changes for existing L10+ users

## Timeline Estimate

| Phase | Duration |
|-------|----------|
| Dependencies Update | 0.5 day |
| Code Implementation | 1 day |
| Testing & CI/CD | 1-2 days |
| Documentation | 0.5 day |
| Release | 0.5 day |
| **Total** | **3.5-4.5 days** |

## Risk Assessment

**Low Risk**:
- ✅ No breaking changes to existing API
- ✅ Backward compatible approach
- ✅ Easy rollback (version pinning)
- ✅ Well-tested Monolog compatibility layer

**Mitigations**:
- Comprehensive test matrix catches issues early
- Manual testing in real Laravel 9 project before release
- Gradual rollout capability

## References

- [Monolog UPGRADE.md](https://github.com/Seldaek/monolog/blob/main/UPGRADE.md)
- [Monolog 3.0.0 Release](https://github.com/Seldaek/monolog/releases/tag/3.0.0)
- [Laravel 10 Upgrade Guide](https://laravel.com/docs/10.x/upgrade)
- [Laravel Framework Monolog 3.0 Discussion](https://github.com/laravel/framework/discussions/42753)
