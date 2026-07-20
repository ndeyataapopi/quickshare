#!/usr/bin/env bash
set -e

PROJECT_DIR="/var/www/html/quickshare"
WEB_USER="www-data"

if [ "$EUID" -ne 0 ]; then
    echo "Please run as root (or with sudo)."
    exit 1
fi

# Detect package manager and service system
if command -v systemctl &> /dev/null; then
    USE_SYSTEMD=true
else
    USE_SYSTEMD=false
fi

if command -v apt &> /dev/null; then
    PKG_MANAGER="apt"
elif command -v yum &> /dev/null; then
    PKG_MANAGER="yum"
else
    PKG_MANAGER=""
fi

# Determine which init system is available for Supervisor
if command -v supervisorctl &> /dev/null; then
    SUPERVISOR=true
elif [ -d /etc/supervisor/conf.d ]; then
    SUPERVISOR=true
elif [ -d /etc/supervisord.d ]; then
    SUPERVISOR=true
else
    SUPERVISOR=false
fi

echo "Project dir: $PROJECT_DIR"
echo "Web user:    $WEB_USER"
echo "SystemD:     $USE_SYSTEMD"
echo "Apt/Yum:     $PKG_MANAGER"
echo "Supervisor:  $SUPERVISOR"

# Create log directory if needed
mkdir -p "$PROJECT_DIR/storage/logs"
chown -R "$WEB_USER:$WEB_USER" "$PROJECT_DIR/storage"

if [ "$SUPERVISOR" = true ]; then
    if ! command -v supervisorctl &> /dev/null && [ -n "$PKG_MANAGER" ]; then
        echo "Installing supervisor..."
        if [ "$PKG_MANAGER" = "apt" ]; then
            apt update
            apt install -y supervisor
        else
            yum install -y supervisor
        fi
    fi

    echo "Installing Supervisor config..."
    cp "$PROJECT_DIR/deploy/supervisor/quickshare-worker.conf" /etc/supervisor/conf.d/quickshare-worker.conf
    sed -i "s|/var/www/html/quickshare|$PROJECT_DIR|g" /etc/supervisor/conf.d/quickshare-worker.conf
    sed -i "s|user=www-data|user=$WEB_USER|g" /etc/supervisor/conf.d/quickshare-worker.conf

    supervisorctl reread
    supervisorctl update
    supervisorctl start quickshare-worker:* || true
    supervisorctl status quickshare-worker
elif [ "$USE_SYSTEMD" = true ]; then
    echo "Installing SystemD service..."
    cp "$PROJECT_DIR/deploy/systemd/quickshare-worker.service" /etc/systemd/system/quickshare-worker.service
    sed -i "s|/var/www/html/quickshare|$PROJECT_DIR|g" /etc/systemd/system/quickshare-worker.service
    sed -i "s|User=www-data|User=$WEB_USER|g" /etc/systemd/system/quickshare-worker.service
    sed -i "s|Group=www-data|Group=$WEB_USER|g" /etc/systemd/system/quickshare-worker.service

    systemctl daemon-reload
    systemctl enable quickshare-worker
    systemctl start quickshare-worker
    systemctl status quickshare-worker --no-pager
else
    echo "Could not detect Supervisor or SystemD. Manual worker setup is required."
    exit 1
fi

echo "Done. You can now check the health status at /admin/system-status."
