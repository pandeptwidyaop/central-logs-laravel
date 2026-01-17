# Laravel 9 Support Design

## Overview

Menambahkan dukungan Laravel 9 ke Central Logs Laravel package dengan pendekatan **maintenance branch** untuk menghindari kompleksitas dual-version support dalam satu codebase.

## Motivation

Klien masih menggunakan Laravel 9 dan membutuhkan package ini untuk tetap berfungsi.

## Technical Approach

### Dua Codebase Terpisah

#### 1. Branch `1.x-laravel9` (Maintenance Mode)
- **Target**: Laravel 9.x
- **PHP**: 8.0-8.2
- **Monolog**: ^2.0
- **Maintenance**: Bug fixes dan security patches only
- **Features**: Tidak ada fitur baru
- **Version**: 1.x (continue from current)

#### 2. Branch `main` Version 2.0.0 (Development)
- **Target**: Laravel 10.x, 11.x, 12.x
- **PHP**: ^8.1
- **Monolog**: ^3.0
- **Maintenance**: Full development dengan fitur baru
- **Version**: 2.0.0+

## Implementation Steps

### Phase 1: Create Laravel 9 Branch

```bash
# From current main branch
git checkout -b 1.x-laravel9

# Update composer.json constraints
# - "php": "^8.0"
# - "laravel/framework": "^9.0"
# - "monolog/monolog": "^2.0"

# Test in Laravel 9 environment
php vendor/bin/phpunit

# Tag and push
git tag v1.2.4-l9
git push origin 1.x-laravel9 --tags
```

### Phase 2: Bump main to 2.0.0

```bash
git checkout main

# Update version in composer.json
# "version": "2.0.0"

# Update composer.json constraints (explicit)
# - "php": "^8.1"
# - "laravel/framework": "^10.0|^11.0|^12.0"
# - "monolog/monolog": "^3.0"

# Update CHANGELOG.md
git tag v2.0.0
git push origin main --tags
```

### Phase 3: Documentation Updates

#### README.md
```markdown
## Version Compatibility

- **Laravel 9**: Use version `1.x` (branch `1.x-laravel9`)
- **Laravel 10, 11, 12**: Use version `2.x` (branch `main`)

### Installation

#### For Laravel 9
```bash
composer require pandeptwidyaop/central-logs-laravel:^1.0
```

#### For Laravel 10+
```bash
composer require pandeptwidyaop/central-logs-laravel:^2.0
```
```

#### Installation Guide
- Separate sections for L9 vs L10+
- Version-specific notes
- Migration guide from 1.x to 2.0.0

## Maintenance Workflow

### Bug Fix Process

1. **Laravel 9 Specific Bug**
   - Fix di branch `1.x-laravel9`
   - Test di L9 environment
   - Tag as `v1.x.x`
   - Tidak perlu cherry-pick ke main

2. **Laravel 10+ Bug**
   - Fix di branch `main`
   - Test di L10/11/12 environments
   - Tag as `v2.x.x`
   - Tidak perlu cherry-pick ke L9 branch

3. **Universal Bug** (applies to both)
   - Fix di main branch dulu
   - Cherry-pick commit ke `1.x-laravel9`
   - Tag kedua version
   - Document di changelog keduanya

4. **Security Fix**
   - Fix di main branch
   - Assess jika applies ke L9
   - Cherry-pick jika applicable
   - Coordinated release

### Feature Development

- **Hanya di main branch** (Laravel 10+)
- Tidak ada porting ke L9 branch
- L9 users tetap di versi 1.x terakhir

## Testing Strategy

### Laravel 9 Branch (1.x-laravel9)
- Full test suite harus pass
- CI/CD untuk Laravel 9.x
- PHP 8.0, 8.1, 8.2 matrix testing

### Main Branch (2.0.0)
- Test suite untuk Laravel 10.x, 11.x, 12.x
- PHP 8.1, 8.2, 8.3 matrix testing
- Existing tests tetap valid

## Release Management

### Version Numbers
- `1.x-laravel9`: 1.2.4, 1.2.5, etc. (bug fixes)
- `main`: 2.0.0, 2.0.1, 2.1.0, etc. (full development)

### Changelog
- Separate CHANGELOG for each branch
- Reference cross-branch fixes when applicable

### Packagist
- Both branches published to same package
- Users select version via composer constraint

## Benefits

1. **Simplicity**: Tidak perlu conditional code untuk Monolog 2 vs 3
2. **Clarity**: Clear separation of maintenance vs development
3. **Performance**: Tidak ada overhead version checks di runtime
4. **Testing**: More reliable dengan environment-specific tests
5. **Documentation**: Clearer installation instructions

## Trade-offs

1. **Maintenance Overhead**: Dua branch untuk di-maintain
2. **Bug Duplication**: Bug fix mungkin perlu di dua branch
3. **Feature Gap**: L9 users tidak dapat fitur baru

## Timeline

1. Create `1.x-laravel9` branch - 1 hari
2. Test dan validate L9 compatibility - 1-2 hari
3. Bump main to 2.0.0 - 0.5 hari
4. Update documentation - 0.5 hari
5. Release both versions - 0.5 hari

**Total Estimated**: 3-4 hari

## Success Criteria

- [ ] Laravel 9 branch passes full test suite
- [ ] Main branch bumped to 2.0.0
- [ ] Documentation updated with clear version selection guide
- [ ] Both versions published to Packagist
- [ ] CI/CD running for both branches
- [ ] Existing L10+ users can upgrade to 2.0.0 tanpa breaking changes
