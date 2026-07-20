# QuickShare Queue Worker Deployment Guide

## Overview

QuickShare queues emails and other background jobs. A persistent worker process is required or emails will sit in the queue and never be sent.

## Queue Configuration

- `QUEUE_CONNECTION` in `.env` is `redis` by default.
- Notification jobs are pushed to the `notifications` queue.
- Generic jobs use the `default` queue.
- The worker must listen to both:

```bash
php artisan queue:work --queue=notifications,default --sleep=3 --tries=3
```

## Option A: Supervisor (Recommended for Ubuntu/Debian)

### 1. Install Supervisor

```bash
sudo apt update
sudo apt install supervisor
```

### 2. Copy the config file

```bash
sudo cp deploy/supervisor/quickshare-worker.conf /etc/supervisor/conf.d/quickshare-worker.conf
```

Edit the file and confirm:

- `command` path matches your Laravel install directory.
- `user` matches the web server user (often `www-data` on Ubuntu / Apache/Nginx).
- `stdout_logfile` path exists and is writable.

### 3. Update Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start quickshare-worker:*
```

### 4. Verify

```bash
sudo supervisorctl status quickshare-worker
```

Expected output:

```
quickshare-worker_00   RUNNING   pid 1234, uptime 0:00:05
```

### 5. Auto-start

Supervisor is normally started by SystemD automatically. If not:

```bash
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

## Option B: SystemD Service

Use this if Supervisor is unavailable.

### 1. Copy the service file

```bash
sudo cp deploy/systemd/quickshare-worker.service /etc/systemd/system/quickshare-worker.service
```

Edit the file and confirm `User`, `Group`, `WorkingDirectory`, and `ExecStart` match your server.

### 2. Enable and start

```bash
sudo systemctl daemon-reload
sudo systemctl enable quickshare-worker
sudo systemctl start quickshare-worker
```

### 3. Verify

```bash
sudo systemctl status quickshare-worker
```

## Useful Commands

Restart the worker after deploying new code:

```bash
php artisan queue:restart
```

Or restart via Supervisor/SystemD:

```bash
# Supervisor
sudo supervisorctl restart quickshare-worker:*

# SystemD
sudo systemctl restart quickshare-worker
```

View logs:

```bash
tail -f /var/www/html/quickshare/storage/logs/worker.log
tail -f /var/www/html/quickshare/storage/logs/laravel.log
```

Check pending jobs:

```bash
php artisan queue:monitor notifications,default
```

Inspect failed jobs:

```bash
php artisan queue:failed
```

Retry all failed jobs:

```bash
php artisan queue:retry all
```

Clear failed jobs:

```bash
php artisan queue:flush
```

## Admin Health Check

Visit `/admin/system-status` in the admin panel to see:

- Queue connection and driver
- Whether a worker is running
- Pending job counts
- Failed job count
- Worker uptime
- Buttons to restart the worker, retry failed jobs, or clear failed jobs

## Deployment Checklist

- [ ] `.env` mail variables are set to a real SMTP driver/credentials
- [ ] `php artisan config:clear` has been run after changing `.env`
- [ ] Redis is running and reachable
- [ ] Supervisor or SystemD worker is running
- [ ] Worker auto-starts on server reboot
- [ ] `storage/logs/worker.log` is writable
- [ ] `php artisan email:test your@email.com` succeeds and delivers
