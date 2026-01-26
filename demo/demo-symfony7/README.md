# Performance Bundle Demo - Symfony 7

This demo shows how to use the Performance Bundle with Symfony 7.0, including automatic route performance tracking.

## Features

- **Symfony 7.0** with all necessary dependencies
- **MySQL 8.0** as database
- **Complete CRUD** for Products entity
- **Automatic Performance Tracking** - All routes are automatically tracked
- **Docker Compose** with PHP, MySQL, Nginx, and phpMyAdmin
- **Makefile** with useful commands for development
- **Example fixtures** with test data
- **Web interface** to view performance metrics

## Requirements

- Docker and Docker Compose
- Make (optional, but recommended)

## Quick Start

### 1. Start containers

```bash
make up
```

This will automatically create the `.env` file if it doesn't exist and start all containers (PHP, MySQL, Nginx, phpMyAdmin).

### 2. Setup the demo

```bash
make setup
```

This command:
- Installs Composer dependencies
- Creates database
- Creates schema
- Loads test data (fixtures) using DoctrineFixturesBundle

### 3. Access Web Application

Once containers are up, nginx is automatically running and serving the application.

Access the web application at: **http://localhost:8000**

From there you can:
- View home page with performance metrics
- Manage Products CRUD (`/product`)
- See automatic performance tracking in action

### 4. View Performance Metrics

The bundle automatically tracks:
- Request execution time
- Database query count
- Query execution time

View metrics:
- In the home page (`/`)
- Via command: `make db-view`
- In phpMyAdmin: http://localhost:8080 (check `routes_data` table)

## Available Routes

- **Home**: `/` - Main page with performance metrics overview
- **Products List**: `/product` - List all products
- **Create Product**: `/product/new` - Create new product
- **View Product**: `/product/{id}` - View product details
- **Edit Product**: `/product/{id}/edit` - Edit product
- **Delete Product**: `/product/{id}` - Delete product (POST)

## Docker Services

- **PHP**: PHP 8.4-FPM container
- **Nginx**: Web server (port 8000)
- **MySQL**: Database server (port 3307)
- **phpMyAdmin**: Database management (port 8080)

All services are connected via Docker network `performance-demo-network`.

## Makefile Commands

```bash
make up          # Start containers
make down        # Stop containers
make shell       # Open shell in PHP container
make install     # Install dependencies
make setup       # Complete setup
make db-create   # Create database
make db-drop     # Drop database
make db-reset    # Reset database
make db-fixtures # Load fixtures
make db-view     # View performance metrics
make logs        # Show container logs
make clean       # Clean vendor and cache
```

## Performance Tracking

The bundle automatically tracks all routes (except ignored ones) in `dev` and `test` environments.

### Configuration

See `config/packages/dev/nowo_performance.yaml`:

```yaml
nowo_performance:
    enabled: true
    environments: ['dev', 'test']
    connection: 'default'
    track_queries: true
    track_request_time: true
    ignore_routes:
        - '_wdt'
        - '_profiler'
        - '_error'
```

### Viewing Metrics

1. **Via Web Interface**: Visit `/` to see worst performing routes
2. **Via Database**: Check `routes_data` table in phpMyAdmin
3. **Via Command**: `make db-view`

## Database Access

### phpMyAdmin

Access phpMyAdmin at: **http://localhost:8080**

**Credentials:**
- Username: `demo_user`
- Password: `password`

### Direct MySQL

```bash
docker-compose exec mysql mysql -u demo_user -ppassword performance_demo
```

## Testing Performance Tracking

1. Visit different routes (home, products list, create, edit, etc.)
2. Check performance metrics in home page or database
3. Routes with more queries or slower execution will be tracked
4. Metrics are only updated if they're worse (higher time or more queries)

## Example Usage

```bash
# Start everything
make up
make setup

# Access application
# http://localhost:8000

# View metrics
make db-view

# Or check in phpMyAdmin
# http://localhost:8080 -> routes_data table
```

## Notes

- Performance tracking is automatic - no code changes needed
- Metrics are stored in `routes_data` table
- Only routes in configured environments are tracked
- Ignored routes (profiler, debug toolbar) are not tracked
