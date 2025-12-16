# Getting Started with Central Logs Laravel Package

Quick guide to get the package up and running in development.

## Prerequisites

- Docker and Docker Compose installed
- Central Logs system running (from /Users/pande/Projects/central-logs)
- Basic understanding of Laravel

## Quick Start (5 minutes)

### 1. Start Central Logs System

First, make sure your Central Logs system is running:

```bash
cd /Users/pande/Projects/central-logs
make dev
# Central Logs should be running at http://localhost:8080
```

Get an API key from Central Logs dashboard.

### 2. Start Development Environment

```bash
cd /Users/pande/Projects/central-logs-laravel

# Start Docker containers
docker-compose up -d

# Install package dependencies
docker-compose exec php composer install
```

### 3. Set Up Test Laravel Application

```bash
# Enter PHP container
docker-compose exec php sh

# Navigate to test app directory
cd /var/www/test-app

# Run setup script
chmod +x setup.sh
./setup.sh

# Exit container
exit
```

### 4. Configure API Key

Edit the test app's `.env` file:

```bash
# Open in your editor
# File: docker/test-app/.env

# Update this line with your actual API key
CENTRAL_LOGS_API_KEY=your_actual_api_key_from_central_logs
```

### 5. Test the Connection

```bash
docker-compose exec php sh
cd /var/www/test-app
php artisan central-logs:test
```

You should see: `✅ Connection successful!`

### 6. Test Logging

Open your browser and visit:
- http://localhost:8000/test-log
- http://localhost:8000/test-log-error
- http://localhost:8000/test-log-batch

Check your Central Logs dashboard to see the logs appearing!

## Development Workflow

### Making Changes to the Package

1. Edit files in `src/`
2. Changes are immediately available (composer local path)
3. Test in the test app
4. Run tests: `docker-compose exec php composer test`

### Monitoring Queue

View queue worker logs:
```bash
docker-compose logs -f queue-worker
```

### Running Tests

```bash
# All tests
docker-compose exec php composer test

# Specific test
docker-compose exec php vendor/bin/phpunit tests/Unit/ClientTest.php

# With coverage
docker-compose exec php composer test-coverage
```

### Checking Code Quality

```bash
# PHPStan
docker-compose exec php composer phpstan

# Code formatting
docker-compose exec php composer format
```

## Project Structure

```
central-logs-laravel/
├── src/                    # Package source code
│   ├── Client/            # HTTP client for API
│   ├── Handler/           # Monolog handlers
│   ├── Jobs/              # Queue jobs
│   ├── Support/           # Helper classes
│   ├── Commands/          # Artisan commands
│   └── Exceptions/        # Custom exceptions
├── config/                # Configuration files
├── tests/                 # Test suite
├── docker/                # Docker setup
│   ├── php/              # PHP container
│   ├── nginx/            # Nginx config
│   └── test-app/         # Test Laravel app
└── docker-compose.yml    # Docker services
```

## Common Tasks

### Restart Services

```bash
docker-compose restart
docker-compose restart queue-worker
```

### Clear Caches

```bash
docker-compose exec php sh
cd /var/www/test-app
php artisan cache:clear
php artisan config:clear
```

### View Logs

```bash
# Laravel logs
docker-compose exec php tail -f /var/www/test-app/storage/logs/laravel.log

# Queue worker logs
docker-compose logs -f queue-worker

# All logs
docker-compose logs -f
```

### Stop Everything

```bash
docker-compose down
```

## Next Steps

1. Read the [README.md](README.md) for full documentation
2. Check [CONTRIBUTING.md](CONTRIBUTING.md) if you want to contribute
3. Explore the test app at `docker/test-app/`
4. Write your own tests in `tests/`

## Troubleshooting

### "Connection refused" error
- Make sure Central Logs is running
- Check `CENTRAL_LOGS_URL` in test app's `.env`
- Use `http://host.docker.internal:8080` instead of `localhost` in Docker

### "Authentication failed" error
- Check your API key is correct
- Verify the API key in Central Logs dashboard

### Logs not appearing
- Check queue worker is running: `docker-compose ps`
- View queue worker logs: `docker-compose logs queue-worker`
- Try sync mode: Set `CENTRAL_LOGS_MODE=sync` in test app's `.env`

### Permission errors
- Make sure user ID in docker-compose.yml matches your local user
- Run: `id -u` to get your user ID

## Getting Help

- Check [README.md](README.md) for detailed documentation
- Open an issue on GitHub
- Check Central Logs documentation

Happy coding! 🚀
