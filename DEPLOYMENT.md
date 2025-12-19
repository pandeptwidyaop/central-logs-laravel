# Deployment Guide

Panduan lengkap untuk publish package ke GitHub dan Packagist dengan semantic versioning otomatis.

## Prerequisites

1. GitHub account
2. Packagist account
3. Git configured dengan SSH atau HTTPS

## Step 1: Push ke GitHub

```bash
cd /Users/pande/Projects/central-logs-laravel

# Add remote (sudah ada)
git remote add origin git@github.com:pandeptwidyaop/central-logs-laravel.git

# Push semua commits
git push -u origin main

# Push semua tags (jika ada)
git push --tags
```

## Step 2: Setup GitHub Secrets

Buka: https://github.com/pandeptwidyaop/central-logs-laravel/settings/secrets/actions

Tambahkan secrets berikut:

### 1. GH_TOKEN (Required untuk semantic-release)
- Name: `GH_TOKEN`
- Value: Personal Access Token dengan scope:
  - `repo` (full control)
  - `workflow`
  
Buat di: https://github.com/settings/tokens/new

### 2. PACKAGIST_USERNAME (Optional, untuk auto-update)
- Name: `PACKAGIST_USERNAME`
- Value: Username Packagist Anda

### 3. PACKAGIST_TOKEN (Optional, untuk auto-update)  
- Name: `PACKAGIST_TOKEN`
- Value: API Token dari Packagist

Buat di: https://packagist.org/profile/

## Step 3: Publish ke Packagist

### Manual Submit (First Time)

1. Login ke Packagist: https://packagist.org/login
2. Submit package: https://packagist.org/packages/submit
3. Masukkan URL: `https://github.com/pandeptwidyaop/central-logs-laravel`
4. Klik "Check"
5. Packagist akan validate dan publish

### Auto-Update Setup

#### Option A: GitHub Webhook (Recommended)

1. Buka: https://github.com/pandeptwidyaop/central-logs-laravel/settings/hooks
2. Add webhook
3. **Payload URL**: `https://packagist.org/api/github?username=PACKAGIST_USERNAME`
4. **Content type**: `application/json`
5. **Events**: Just the push event
6. **Active**: ✓
7. Add webhook

#### Option B: GitHub Actions (Sudah tersetup)

Workflow akan otomatis notify Packagist setiap ada release baru.
Pastikan secrets `PACKAGIST_USERNAME` dan `PACKAGIST_TOKEN` sudah di-set.

## Step 4: Cara Kerja Semantic Versioning

Package ini menggunakan **Conventional Commits** untuk automatic versioning:

### Commit Message Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Version Bump Rules

| Commit Type | Example | Version Bump |
|-------------|---------|--------------|
| `fix:` | `fix: resolve memory leak` | PATCH (1.0.0 → 1.0.1) |
| `feat:` | `feat: add new logger` | MINOR (1.0.0 → 1.1.0) |
| `BREAKING CHANGE:` | Footer berisi `BREAKING CHANGE:` | MAJOR (1.0.0 → 2.0.0) |
| `perf:` | `perf: optimize batch` | PATCH (1.0.0 → 1.0.1) |
| `docs:` | `docs: update README` | No release |
| `chore:` | `chore: update deps` | No release |

### Contoh Commits

**PATCH (Bug Fix):**
```bash
git commit -m "fix: resolve connection timeout issue

- Increase default timeout to 10s
- Add retry logic for transient errors
- Update error messages

Closes #123"
```

**MINOR (New Feature):**
```bash
git commit -m "feat: add support for custom formatters

- Allow users to define custom log formatters
- Add formatter interface
- Update documentation

Closes #124"
```

**MAJOR (Breaking Change):**
```bash
git commit -m "feat: redesign configuration structure

BREAKING CHANGE: Config structure has changed.
Migration required from old config format.

- Rename 'batch.size' to 'batch.max_size'
- Remove deprecated 'async.delay' option
- Add new 'batch.flush_interval' setting

See UPGRADE.md for migration guide"
```

## Step 5: Release Process (Otomatis)

1. **Buat perubahan** dan commit dengan conventional commit format
2. **Push ke main branch**:
   ```bash
   git push origin main
   ```
3. **GitHub Actions** akan otomatis:
   - Analyze commits
   - Determine version bump
   - Generate CHANGELOG.md
   - Create git tag
   - Create GitHub Release
   - Notify Packagist

## Step 6: Verifikasi

### Check GitHub Release
- Buka: https://github.com/pandeptwidyaop/central-logs-laravel/releases
- Pastikan release baru muncul

### Check Packagist
- Buka: https://packagist.org/packages/central-logs/laravel
- Pastikan version terbaru terdeteksi
- Pastikan "Auto-updated" badge hijau

### Test Installation
```bash
composer require central-logs/laravel
```

## Manual Release (Emergency)

Jika perlu release manual:

```bash
# Create tag manually
git tag -a v1.0.1 -m "Release v1.0.1"

# Push tag
git push origin v1.0.1

# GitHub Actions "release" workflow akan auto-trigger
```

## Troubleshooting

### GitHub Actions Failed

1. Check workflow run: https://github.com/pandeptwidyaop/central-logs-laravel/actions
2. Verify secrets are set correctly
3. Check commit message format

### Packagist Not Updating

1. Manually trigger update: https://packagist.org/packages/central-logs/laravel
2. Click "Force Update"
3. Check webhook configuration

### Semantic Release Not Working

1. Verify `GH_TOKEN` secret has correct permissions
2. Check commit follows conventional commits format
3. Ensure pushing to `main` branch

## Maintenance

### Update Dependencies

```bash
composer update
git commit -m "chore: update dependencies"
git push
```

### Hotfix

```bash
# Create fix
git commit -m "fix: critical security issue"

# Push immediately
git push origin main

# Semantic release akan otomatis:
# - Bump patch version
# - Create release
# - Update Packagist
```

## Resources

- **Repository**: https://github.com/pandeptwidyaop/central-logs-laravel
- **Packagist**: https://packagist.org/packages/central-logs/laravel
- **Conventional Commits**: https://www.conventionalcommits.org/
- **Semantic Release**: https://semantic-release.gitbook.io/

## Support

Jika ada masalah:
1. Check GitHub Actions logs
2. Check Packagist webhook logs
3. Email: dev@pande.id
4. Issues: https://github.com/pandeptwidyaop/central-logs-laravel/issues
