#!/bin/bash
set -e

# Restore vendor dependencies if shadowed by a host volume mount
if [ ! -f "/var/www/html/vendor/autoload.php" ] && [ -d "/opt/vendor" ]; then
    echo "Restoring vendor dependencies from /opt/vendor into /var/www/html/vendor..."
    mkdir -p /var/www/html/vendor
    cp -a /opt/vendor/. /var/www/html/vendor/
fi

# Ensure writable directories exist with proper permissions
mkdir -p /var/www/html/writable/cache /var/www/html/writable/session /var/www/html/writable/logs /var/www/html/writable/uploads
chown -R www-data:www-data /var/www/html/writable 2>/dev/null || true
chmod -R 775 /var/www/html/writable 2>/dev/null || true

# Wait for MySQL to be fully ready (beyond just ping)
echo "Waiting for MySQL to accept connections..."
max_retries=30
count=0
while ! php -r "
\$url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
if (\$url && (\$p = parse_url(\$url))) {
    \$h = \$p['host'] ?? 'localhost';
    \$P = \$p['port'] ?? 3306;
    \$u = urldecode(\$p['user'] ?? 'root');
    \$pw = urldecode(\$p['pass'] ?? '');
    \$db = ltrim(\$p['path'] ?? '', '/');
} else {
    \$h = getenv('database_default_hostname') ?: getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: 'localhost';
    \$P = getenv('database_default_port') ?: getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: 3306;
    \$u = getenv('database_default_username') ?: getenv('DB_USER') ?: getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: 'root';
    \$pw = getenv('database_default_password') ?: getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';
    \$db = getenv('database_default_database') ?: getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: '';
}
try {
    \$pdo = new PDO(\"mysql:host={\$h};port={\$P}\", \$u, \$pw, [PDO::ATTR_TIMEOUT => 3, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if (!empty(\$db)) {
        \$pdo->exec(\"CREATE DATABASE IF NOT EXISTS \`{\$db}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\");
    }
    exit(0);
} catch (Throwable \$e) {
    exit(1);
}
"; do
    count=$((count + 1))
    if [ $count -ge $max_retries ]; then
        echo "ERROR: MySQL did not become ready in time. Starting Apache without migrations."
        exec apache2-foreground
    fi
    echo "MySQL not ready yet... retrying ($count/$max_retries)"
    sleep 2
done

echo "MySQL is ready!"

# Run migrations (safe to run multiple times — only applies pending migrations)
echo "Running database migrations..."
cd /var/www/html
php spark migrate --all 2>&1 || echo "WARNING: Migration encountered an issue. Check logs."

echo "Migrations complete."

# Seed required default data (all seeders are idempotent — safe on every restart)
echo "Running database seeders..."

# 1. Superadmin account (reads SUPERADMIN_EMAIL / _PASSWORD / _USERNAME from env)
php spark db:seed SuperAdminSeeder || echo "Notice: SuperAdminSeeder completed with notice."

# 2. Global app settings (app name, email, retention, maintenance, registration, etc.)
php spark db:seed AppSettingsSeeder || echo "Notice: AppSettingsSeeder completed with notice."

# 3. Cron job configurations (schedules + types read by the cron daemon)
php spark db:seed CronSettingsSeeder || echo "Notice: CronSettingsSeeder completed with notice."

# 4. Finance sender allowlist (ML backend reads this to classify finance SMS)
php spark db:seed AllowedSendersSeeder || echo "Notice: AllowedSendersSeeder completed with notice."

# 5. M-Pesa keyword → category correction rules (used by AnalysisCallbackController)
php spark db:seed CategoryRulesSeeder || echo "Notice: CategoryRulesSeeder completed with notice."

# 6. ML backend control flags (tuning defaults — overridden via admin panel)
php spark db:seed MLControlsSeeder || echo "Notice: MLControlsSeeder completed with notice."

echo "Seeders complete."

# Export container environment variables for cron scheduler
echo "Exporting environment variables for cron scheduler..."
mkdir -p /var/www/html/writable
printenv | grep -E '^(DB_|MYSQL|DATABASE_|CI_|REDIS_|ML_|SUPERADMIN_|APP_|MAIL_|EMAIL_)' | sed 's/^\(.*\)$/export \1/g' > /var/www/html/writable/cron_env.sh
chmod 644 /var/www/html/writable/cron_env.sh
printenv | grep -E '^(DB_|MYSQL|DATABASE_|CI_|REDIS_|ML_|SUPERADMIN_|APP_|MAIL_|EMAIL_)' > /etc/environment 2>/dev/null || true

# Ensure cron daemon and log file are ready
touch /var/log/mpesa-cron.log
chmod 666 /var/log/mpesa-cron.log

# Ensure /etc/cron.d/mpesa-analyzer exists and has correct permissions
if [ ! -f "/etc/cron.d/mpesa-analyzer" ]; then
    printf 'SHELL=/bin/bash\nPATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\n* * * * * root [ -f /var/www/html/writable/cron_env.sh ] && . /var/www/html/writable/cron_env.sh; cd /var/www/html && /usr/local/bin/php spark cron:run >> /var/log/mpesa-cron.log 2>&1\n' > /etc/cron.d/mpesa-analyzer
    chmod 644 /etc/cron.d/mpesa-analyzer
    chown root:root /etc/cron.d/mpesa-analyzer
fi

# Start the cron daemon so scheduled jobs run inside the container
cron -L 15 || service cron status || true

echo "Fixing Apache MPM conflicts..."
a2dismod mpm_event mpm_worker || true
a2enmod mpm_prefork || true

echo "Starting Apache..."
exec apache2-foreground
