# QuickShare Production Deployment Guide

This guide covers deploying QuickShare to production using Docker, Nginx, PHP-FPM, Redis, and MySQL.

## Prerequisites

- Docker 20.10+
- Docker Compose 2.0+
- SSL certificate (for HTTPS)
- Domain name configured

## Quick Start

1. **Clone and configure:**
```bash
git clone <repository-url> quickshare
cd quickshare
cp .env.production.example .env
```

2. **Generate application key:**
```bash
docker-compose run --rm php php artisan key:generate
```

3. **Set environment variables:**
Edit `.env` and configure:
- `APP_KEY` (generated above)
- `DB_PASSWORD` and `DB_ROOT_PASSWORD`
- `APP_URL` (your production domain)
- Redis and other service configurations

4. **Build and start services:**
```bash
docker-compose build
docker-compose up -d
```

5. **Run migrations:**
```bash
docker-compose exec php php artisan migrate --force
```

6. **Seed database (optional):**
```bash
docker-compose exec php php artisan db:seed --force
```

7. **Create storage link:**
```bash
docker-compose exec php php artisan storage:link
```

8. **Cache configuration:**
```bash
docker-compose exec php php artisan config:cache
docker-compose exec php php artisan route:cache
docker-compose exec php php artisan view:cache
```

## Services

### PHP-FPM
- **Port:** 9000
- **Workers:** 4 default queue workers, 2 high-priority workers
- **Scheduler:** Automatic task scheduling
- **Configuration:** `docker/php/php.ini`

### Nginx
- **Ports:** 80 (HTTP), 443 (HTTPS)
- **Configuration:** `docker/nginx/nginx.conf`
- **Health Check:** `/health`
- **Monitoring:** `/monitoring` (requires authentication)

### MySQL
- **Port:** 3306
- **Version:** 8.0
- **Configuration:** `docker/mysql/my.cnf`
- **Backups:** Automated daily backups (7-day retention)

### Redis
- **Port:** 6379
- **Configuration:** `docker/redis/redis.conf`
- **Persistence:** AOF enabled
- **Memory:** 256MB max

## Security

### SSL/TLS Setup

1. Place SSL certificates in `docker/nginx/ssl/`:
```
docker/nginx/ssl/
├── fullchain.pem
└── privkey.pem
```

2. Update Nginx configuration for HTTPS (modify `docker/nginx/nginx.conf`)

### Environment Variables

Critical security settings in `.env`:
```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=<random-32-char-key>
DB_PASSWORD=<strong-password>
DB_ROOT_PASSWORD=<strong-password>
REDIS_PASSWORD=<redis-password>
```

### Firewall Rules

Allow only necessary ports:
- 80 (HTTP)
- 443 (HTTPS)
- 22 (SSH, if needed)

## Monitoring

### Health Check
```bash
curl https://yourdomain.com/health
```

Response:
```json
{
  "status": "healthy",
  "timestamp": "2026-05-21T12:00:00Z",
  "checks": {
    "database": {"status": "ok", "message": "Database connection successful"},
    "redis": {"status": "ok", "message": "Redis connection successful"},
    "storage": {"status": "ok", "message": "Storage is writable"}
  }
}
```

### Monitoring Endpoint
```bash
curl -u username:password https://yourdomain.com/monitoring
```

Response includes:
- System metrics (PHP version, memory, etc.)
- Database metrics (connection status, version)
- Cache metrics (Redis stats)
- Queue metrics (pending jobs, failed jobs)
- Security metrics (active sessions, login attempts)
- Storage metrics (disk status)

### Logs

View logs:
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f php
docker-compose logs -f nginx
docker-compose logs -f mysql
docker-compose logs -f redis
docker-compose logs -f supervisor
```

## Backups

### Database Backups

Automatic daily backups are configured in docker-compose. Backups are stored in `docker/backup/` with 7-day retention.

Manual backup:
```bash
docker-compose exec mysql mysqldump -u root -p quickshare > backup.sql
```

Restore backup:
```bash
docker-compose exec -T mysql mysql -u root -p quickshare < backup.sql
```

### File Backups

Backup storage directory:
```bash
tar -czf storage-backup.tar.gz storage/
```

## Scaling

### Horizontal Scaling

To scale PHP workers:
```yaml
# In docker-compose.yml
services:
  supervisor:
    deploy:
      replicas: 3
```

### Database Scaling

For high traffic, consider:
- Read replicas
- Connection pooling
- Query optimization

## Performance Optimization

### OPcache

OPcache is enabled in `docker/php/php.ini`:
- Memory: 256MB
- Max files: 10,000
- Revalidate: 60 seconds

### Redis Caching

Cache configuration in `.env`:
```env
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### Queue Workers

Worker configuration in `docker/supervisor/supervisord.conf`:
- 4 default workers
- 2 high-priority workers
- Auto-restart on failure

## Troubleshooting

### Container Won't Start

```bash
docker-compose logs <service-name>
docker-compose ps
```

### Database Connection Issues

```bash
docker-compose exec php php artisan tinker
>>> DB::connection()->getPdo()
```

### Queue Jobs Not Processing

```bash
docker-compose logs supervisor
docker-compose exec php php artisan queue:failed
```

### High Memory Usage

Check PHP-FPM memory:
```bash
docker-compose exec php php -r "echo memory_get_peak_usage(true) / 1024 / 1024 . ' MB';"
```

## Maintenance

### Clear Caches

```bash
docker-compose exec php php artisan cache:clear
docker-compose exec php php artisan config:clear
docker-compose exec php php artisan route:clear
docker-compose exec php php artisan view:clear
```

### Update Application

```bash
git pull
docker-compose build php
docker-compose up -d php
docker-compose exec php php artisan migrate --force
docker-compose exec php php artisan config:cache
docker-compose exec php php artisan route:cache
docker-compose exec php php artisan view:cache
```

### Restart Services

```bash
docker-compose restart
```

## Production Checklist

- [ ] Set strong passwords for all services
- [ ] Configure SSL/TLS certificates
- [ ] Set `APP_DEBUG=false` and `APP_ENV=production`
- [ ] Generate and set secure `APP_KEY`
- [ ] Configure firewall rules
- [ ] Set up monitoring and alerts
- [ ] Configure backup retention policy
- [ ] Test health check endpoint
- [ ] Test monitoring endpoint
- [ ] Verify queue workers are running
- [ ] Test database backups
- [ ] Configure rate limiting
- [ ] Set up log rotation
- [ ] Review and adjust resource limits
- [ ] Configure email notifications
- [ ] Test disaster recovery procedure

## Support

For issues or questions:
- Check logs: `docker-compose logs -f`
- Health check: `/health`
- Monitoring: `/monitoring`
