#!/bin/bash

# Development helper script for Central Logs Laravel Package

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

function print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

function print_error() {
    echo -e "${RED}✗ $1${NC}"
}

function print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

# Commands
case "$1" in
    up)
        print_info "Starting Docker containers..."
        docker-compose up -d
        print_success "Containers started"
        docker-compose ps
        ;;

    down)
        print_info "Stopping Docker containers..."
        docker-compose down
        print_success "Containers stopped"
        ;;

    restart)
        print_info "Restarting Docker containers..."
        docker-compose restart
        print_success "Containers restarted"
        ;;

    logs)
        if [ -z "$2" ]; then
            docker-compose logs -f
        else
            docker-compose logs -f "$2"
        fi
        ;;

    shell)
        print_info "Entering PHP container shell..."
        docker-compose exec php sh
        ;;

    test)
        print_info "Running PHPUnit tests..."
        docker-compose exec php vendor/bin/phpunit
        ;;

    phpstan)
        print_info "Running PHPStan..."
        docker-compose exec php vendor/bin/phpstan analyse
        ;;

    format)
        print_info "Running Laravel Pint (code formatting)..."
        docker-compose exec php vendor/bin/pint
        ;;

    composer)
        print_info "Running composer command..."
        docker-compose exec php composer "${@:2}"
        ;;

    setup-test-app)
        print_info "Setting up test Laravel application..."
        docker-compose exec php sh /var/www/test-app/setup.sh
        print_success "Test app setup complete"
        ;;

    test-connection)
        print_info "Testing connection to Central Logs..."
        docker-compose exec php sh -c "cd /var/www/test-app && php artisan central-logs:test"
        ;;

    queue)
        print_info "Starting queue worker..."
        docker-compose exec php sh -c "cd /var/www/test-app && php artisan queue:work --queue=central-logs"
        ;;

    redis-cli)
        print_info "Connecting to Redis CLI..."
        docker-compose exec redis redis-cli
        ;;

    fresh)
        print_info "Fresh start (rebuild everything)..."
        docker-compose down -v
        docker-compose build --no-cache
        docker-compose up -d
        docker-compose exec php composer install
        print_success "Fresh build complete!"
        ;;

    status)
        print_info "Container status:"
        docker-compose ps
        echo ""
        print_info "Package info:"
        docker-compose exec php composer show | head -5
        ;;

    *)
        echo "Central Logs Laravel - Development Helper"
        echo ""
        echo "Usage: ./dev.sh [command]"
        echo ""
        echo "Available commands:"
        echo "  up                  Start Docker containers"
        echo "  down                Stop Docker containers"
        echo "  restart             Restart Docker containers"
        echo "  logs [service]      View logs (optionally for specific service)"
        echo "  shell               Enter PHP container shell"
        echo "  test                Run PHPUnit tests"
        echo "  phpstan             Run PHPStan static analysis"
        echo "  format              Run Laravel Pint code formatter"
        echo "  composer [args]     Run composer command"
        echo "  setup-test-app      Setup test Laravel application"
        echo "  test-connection     Test connection to Central Logs"
        echo "  queue               Start queue worker"
        echo "  redis-cli           Connect to Redis CLI"
        echo "  fresh               Fresh rebuild (warning: removes all data)"
        echo "  status              Show container and package status"
        echo ""
        echo "Examples:"
        echo "  ./dev.sh up"
        echo "  ./dev.sh logs php"
        echo "  ./dev.sh composer install"
        echo "  ./dev.sh test"
        ;;
esac

