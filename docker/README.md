# Docker Development Environment

This Docker setup provides a complete development environment for the Central Logs Laravel package.

## Services

- **php**: PHP 8.2 FPM with all required extensions
- **nginx**: Web server for serving the test Laravel application
- **redis**: Redis server for queue processing
- **queue-worker**: Laravel queue worker for async log processing

## Getting Started

1. Build and start the containers:
```bash
docker-compose up -d
```

2. Install package dependencies:
```bash
docker-compose exec php composer install
```

3. Set up the test Laravel application (see test-app/README.md)

4. Access the test application:
- Web: http://localhost:8000
- API: http://localhost:8000/api

## Useful Commands

### Run composer commands
```bash
docker-compose exec php composer install
docker-compose exec php composer test
```

### Run PHPUnit tests
```bash
docker-compose exec php vendor/bin/phpunit
```

### Run PHPStan
```bash
docker-compose exec php vendor/bin/phpstan analyse
```

### Access PHP container shell
```bash
docker-compose exec php sh
```

### View logs
```bash
docker-compose logs -f
docker-compose logs -f queue-worker
```

### Restart services
```bash
docker-compose restart
docker-compose restart queue-worker
```

### Stop and remove containers
```bash
docker-compose down
```

## Troubleshooting

### Permission issues
If you encounter permission issues, ensure the user ID in docker-compose.yml matches your local user ID:
```bash
id -u  # Get your user ID
```

### Redis connection issues
Ensure Redis is running:
```bash
docker-compose exec redis redis-cli ping
```

### Queue worker not processing jobs
Check queue worker logs:
```bash
docker-compose logs -f queue-worker
```

Restart the queue worker:
```bash
docker-compose restart queue-worker
```
