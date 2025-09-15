# HTM Haridusasutuste Veebiplatvorm (HVP) - Setup Guide

HTM Educational Institutions Web Platform - Estonian educational institution website deployment system supporting any Estonian educational institution with any domain (.ee, .edu.ee, or custom).

## Overview

This platform provides a standardized Drupal 10 solution for Estonian educational institutions. It includes automated setup scripts, multi-site support, and comprehensive documentation based on production deployments.

## System Requirements

### Minimum Requirements
- **OS**: Linux (Alpine 3.x, Ubuntu 20.04+, or compatible)
- **PHP**: 8.1+ (tested with PHP 8.4)
- **Database**: MariaDB 10.6+ or MySQL 8.0+
- **Web Server**: Apache 2.4+ or Nginx 1.20+
- **Composer**: 2.x
- **Memory**: 512MB minimum (1GB recommended)

### Required PHP Extensions
- `pdo_mysql` or `mysqli` - Database connectivity
- `gd` - Image processing
- `xmlreader` - XML reading
- `xmlwriter` - XML writing
- `mbstring` - Multi-byte string support
- `xml` - XML parsing
- `json` - JSON support
- `opcache` - Performance optimization
- `zip` - Archive handling
- `curl` - HTTP requests (required for GuzzleHttp)
- `session` - Session management
- `simplexml` - SimpleXML parsing
- `dom` - DOM manipulation
- `tokenizer` - PHP tokenizer

## Quick Setup (Automated Script)

### Prerequisites

1. **Obtain Backup Files**
   - Log in as administrator to your existing HTM production site
   - Navigate to: `https://[your-domain]/et/admin/config/development/backup_migrate`
   - Create two backups:
     - **Database backup** → generates `backup-xxx.mysql.gz`
     - **Public files backup** → generates `backup-xxx.tar.gz`

2. **Prepare Backup Directory**
   ```bash
   mkdir -p ./backups/[your-domain]/
   # Place both backup files in this directory
   ```

   The script supports both compressed (`.gz`) and uncompressed backup files.

### Running the Setup Script

```bash
./setup.sh
```

### Setup Examples

#### Development Environment
```
Select site [1]: 1  (selecting sinukool.edu.ee from found backups)
Enter institution name: Tõrva Kool
Choose environment [1]: 1  (Development)
Site domain [sinukool.localhost]: sinukool.localhost
Database host [127.0.0.1]: 127.0.0.1
Database port [3306]: 3306
Database name [sinukool_edu]: sinukool_edu
Database username [drupal]: drupal
Choose option [1]: 1  (Generate secure password)
Choose option [1]: 1  (Create new database)
Private files directory [./private]: ./private
```

**Result:**
- Site created at `web/sites/sinukool.localhost/`
- Imports backups from `./backups/sinukool.edu.ee/`
- CSS/JS aggregation: **DISABLED** (for debugging)
- Error display: **ENABLED**
- Page cache: **0 seconds**

#### Production Environment
```
Select site [1]: 2  (selecting ut.ee from found backups)
Enter institution name: Tartu Ülikool
Choose environment [1]: 3  (Production)
Site domain [ut.ee]: ut.ee
Database host [127.0.0.1]: 127.0.0.1
Database port [3306]: 3306
Database name [ut]: ut
Database username [drupal]: drupal
Choose option [1]: 1  (Generate secure password)
Choose option [1]: 2  (Use existing database)
Private files directory [./private]: ./private
```

**Result:**
- Site created at `web/sites/ut.ee/`
- Imports backups from `./backups/ut.ee/`
- CSS/JS aggregation: **ENABLED**
- Error display: **DISABLED**
- Page cache: **900 seconds** (15 minutes)
- Update.php access: **DISABLED**

#### Multi-Site Configuration
Run the script multiple times for different institutions:
```bash
# First institution
./setup.sh
# Enter: Tallinna Reaalkool, real.edu.ee

# Second institution
./setup.sh
# Enter: Tallinna Tehnikaülikool, ttu.ee

```

Each site maintains independent:
- Database (automatically generated from domain)
- Files directory (`web/sites/[domain]/`)
- Settings (each site has its own settings.php)
- Domain configuration

## Platform-Specific Installation

### Alpine Linux
```bash
# Install required packages
apk add php84 php84-fpm php84-curl php84-gd php84-mbstring php84-mysqli \
        php84-opcache php84-xml php84-zip php84-json php84-session \
        php84-simplexml php84-dom php84-tokenizer php84-pdo_mysql \
        nginx mariadb-client composer git

# Set timezone (optional)
setup-timezone -z Europe/Tallinn
```

### Ubuntu/Debian
```bash
# Install required packages
apt-get update
apt-get install php8.1-fpm php8.1-curl php8.1-gd php8.1-mbstring \
                php8.1-mysql php8.1-opcache php8.1-xml php8.1-zip \
                nginx mariadb-client composer git
```

## Manual Setup Instructions

For manual configuration, follow these detailed steps. Example uses `sinukool.edu.ee` - replace with your institution's domain.

### 1. Clone Repository
```bash
git clone https://github.com/torva-gymnaasium/veebiplatvorm.git
cd veebiplatvorm
```

### 2. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Database Setup
```sql
CREATE DATABASE sinukooleduee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sinukooleduee'@'127.0.0.1' IDENTIFIED BY '[SECURE_PASSWORD]';
GRANT ALL PRIVILEGES ON sinukooleduee.* TO 'sinukooleduee'@'127.0.0.1';
FLUSH PRIVILEGES;
```

**Note:** Use `127.0.0.1` instead of `localhost` to avoid socket connection issues.

### 4. Import Database
```bash
# For compressed backup
gunzip -c backups/sinukool.edu.ee/backup-xxx.mysql.gz | mysql -u sinukooleduee -p sinukooleduee

# For uncompressed backup
mysql -u sinukooleduee -p sinukooleduee < backups/sinukool.edu.ee/backup-xxx.mysql
```

### 5. Configure Settings
Create `web/sites/sinukool.edu.ee/settings.php`:
```php
<?php
// Copy from web/sites/default/default.settings.php
// Then add at the end:

$databases['default']['default'] = [
  'database' => 'sinukooleduee',
  'username' => 'sinukooleduee',
  'password' => '[SECURE_PASSWORD]',
  'host' => '127.0.0.1',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

$settings['trusted_host_patterns'] = [
  '^sinukool\.edu\.ee$',
  '^www\.sinukool\.edu\.ee$',
  '^localhost$',
  '^127\.0\.0\.1$',
];

$settings['hash_salt'] = '[GENERATE_UNIQUE_SALT]';  // Use: openssl rand -base64 32
$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = './private';

// Environment-specific settings
ini_set('memory_limit', '512M');

// For production environments:
$config['system.performance']['css']['preprocess'] = TRUE;
$config['system.performance']['js']['preprocess'] = TRUE;
$config['system.performance']['cache']['page']['max_age'] = 900;

// Suppress PHP 8.3+ deprecation warnings
if (PHP_VERSION_ID >= 80300) {
  @ini_set('zend.assertions', -1);
}
error_reporting(E_ALL & ~E_DEPRECATED);
```

### 6. Create Required Directories
```bash
mkdir -p config/sync
mkdir -p ./private
mkdir -p web/sites/sinukool.edu.ee/files/{css,js,php}

# Extract files from backup
# For compressed backup
tar -xzf backups/sinukool.edu.ee/backup-xxx.tar.gz -C web/sites/sinukool.edu.ee/files/

# For uncompressed backup
tar -xf backups/sinukool.edu.ee/backup-xxx.tar -C web/sites/sinukool.edu.ee/files/

# Set permissions
chmod -R 775 web/sites/sinukool.edu.ee/files
chmod -R 775 ./private
```

### 7. Configure Multi-site
Create `web/sites/sites.php`:
```php
<?php
$sites['sinukool.edu.ee'] = 'sinukool.edu.ee';
$sites['www.sinukool.edu.ee'] = 'sinukool.edu.ee';
```

### 8. Web Server Configuration

#### Apache Configuration
Ensure `.htaccess` exists:
```bash
cp web/core/assets/scaffold/files/htaccess web/.htaccess
```

Virtual host configuration:
```apache
<VirtualHost *:80>
    ServerName sinukool.edu.ee
    ServerAlias www.sinukool.edu.ee
    DocumentRoot /path/to/veebiplatvorm/web

    <Directory /path/to/veebiplatvorm/web>
        AllowOverride All
        Require all granted
    </Directory>

    # PHP settings (if using mod_php)
    php_value memory_limit 512M
    php_value max_execution_time 300
    php_value post_max_size 64M
    php_value upload_max_filesize 64M
</VirtualHost>
```

#### Nginx Configuration

**Complete production-ready configuration** (see also `nginx-drupal.example.conf`):

```nginx
# HTTP to HTTPS redirect (except ACME challenges)
server {
    listen 80;
    listen [::]:80;
    server_name sinukool.edu.ee www.sinukool.edu.ee;

    # Allow ACME challenges for SSL certificate renewal
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        root /path/to/veebiplatvorm/web;
    }

    # Redirect everything else to HTTPS
    location / {
        return 301 https://sinukool.edu.ee$request_uri;
    }
}

# HTTPS redirect for www
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name www.sinukool.edu.ee;

    ssl_certificate /etc/nginx/ssl/sinukool.edu.ee.crt;
    ssl_certificate_key /etc/nginx/ssl/sinukool.edu.ee.key;

    return 301 https://sinukool.edu.ee$request_uri;
}

# Main HTTPS server block
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name sinukool.edu.ee;

    # SSL Configuration
    ssl_certificate /etc/nginx/ssl/sinukool.edu.ee.crt;
    ssl_certificate_key /etc/nginx/ssl/sinukool.edu.ee.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Document root - MUST point to web directory
    root /path/to/veebiplatvorm/web;
    index index.php;

    # Logging
    access_log /var/log/nginx/sinukool.edu.ee-access.log;
    error_log /var/log/nginx/sinukool.edu.ee-error.log;

    # Client body size for file uploads
    client_max_body_size 100M;

    # Security - Block hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Block access to private files
    location ~ ^/sites/.*/private/ {
        deny all;
    }

    # Block PHP in files directory
    location ~ ^/sites/[^/]+/files/.*\.php$ {
        deny all;
    }

    # Block vendor and config directories
    location ^~ /vendor/ {
        deny all;
    }
    location ^~ /config/ {
        deny all;
    }

    # Block Drupal configuration files
    location ~ \.(engine|inc|install|make|module|profile|po|sh|.*sql|theme|twig|tpl(\.php)?|xtmpl|yml)(~|\.sw[op]|\.bak|\.orig|\.save)?$|composer\.(lock|json)$|web\.config$|^(\..*|Entries.*|Repository|Root|Tag|Template)$|^#.*#$|\.php(~|\.sw[op]|\.bak|\.orig|\.save)$ {
        deny all;
        return 404;
    }

    # Drupal private files
    location ~ ^/system/files/ {
        try_files $uri /index.php?$query_string;
    }

    # XML sitemap
    location ~ ^/sitemap\.xml {
        try_files $uri @drupal;
    }

    # Robots.txt
    location = /robots.txt {
        allow all;
        log_not_found off;
        access_log off;
        try_files $uri @drupal;
    }

    # Favicon
    location = /favicon.ico {
        log_not_found off;
        access_log off;
        try_files $uri =204;
    }

    # Image styles
    location ~ ^/sites/.*/files/styles/ {
        try_files $uri @drupal;
    }

    # Aggregated CSS/JS with caching
    location ~ ^/sites/.*/files/(css|js)/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        add_header Vary "Accept-Encoding";
        gzip_static on;
        try_files $uri @drupal;
    }

    # Rate limiting for login/admin pages (requires rate limit zones in nginx.conf)
    location ~ ^/(user/login|admin) {
        # limit_req zone=login burst=5 nodelay;
        try_files $uri @drupal;
    }

    # Main location
    location / {
        # limit_req zone=general burst=20 nodelay;
        try_files $uri @drupal;
    }

    # CRITICAL: Named location for Drupal - MUST use fastcgi_params directly!
    # DO NOT use rewrite here - it causes redirect loops
    location @drupal {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param QUERY_STRING $query_string;
        fastcgi_pass unix:/var/run/php/php8.1-fpm-sinukool.sock;
    }

    # PHP handling
    location ~ \.php$ {
        fastcgi_split_path_info ^(.+?\.php)(|/.*)$;
        try_files $fastcgi_script_name =404;
        include fastcgi_params;
        fastcgi_param HTTP_PROXY "";
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param QUERY_STRING $query_string;
        fastcgi_intercept_errors on;
        fastcgi_pass unix:/var/run/php/php8.1-fpm-sinukool.sock;

        # PHP-FPM tuning
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
        fastcgi_read_timeout 240s;
    }

    # Static file caching
    location ~* \.(css|js)$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
        add_header Vary "Accept-Encoding";
        gzip_vary on;
    }

    location ~* \.(jpg|jpeg|gif|png|webp|avif|svg|ico|pdf|mp4|webm|mov|avi)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Font files with CORS
    location ~* \.(woff|woff2|ttf|eot|otf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Access-Control-Allow-Origin "*";
    }

    # Enable gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml+rss
        application/rss+xml
        application/atom+xml
        image/svg+xml;
}

# Rate limiting zones (add to main nginx.conf)
# limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
# limit_req_zone $binary_remote_addr zone=general:10m rate=10r/s;
```

#### PHP-FPM Configuration (Critical for Production)
Create `/etc/php8/php-fpm.d/sinukool.conf`:
```ini
[sinukool.edu.ee]
user = www-data
group = www-data
listen = /var/run/php/php8.1-fpm-sinukool.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Process management
pm = dynamic
pm.max_children = 10
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
pm.max_requests = 500

; PHP settings
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 300
php_admin_value[post_max_size] = 64M
php_admin_value[upload_max_filesize] = 64M

; Security: open_basedir restriction
php_admin_value[open_basedir] = /path/to/veebiplatvorm:/tmp:/usr/share/php

; CRITICAL: Required functions for Drupal 10 - DO NOT DISABLE THESE:
; - ini_set, ini_restore (session and cache management)
; - proc_open (Symfony Process component)
; - escapeshellarg, escapeshellcmd (security functions)
; - parse_ini_file (configuration parsing)
; - curl_exec (required for GuzzleHttp)

; Safe to disable (but NOT curl_exec):
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,popen,show_source,highlight_file,phpinfo
; Note: curl_multi_exec can be disabled, but curl_exec must remain enabled
```

### 9. Clear Cache and Run Updates
```bash
# Clear cache
vendor/bin/drush cache:rebuild --uri=sinukool.edu.ee

# Run database updates
vendor/bin/drush updatedb --uri=sinukool.edu.ee -y

# For production: disable update.php access
vendor/bin/drush state:set system.update_free_access 0 --uri=sinukool.edu.ee
```

### 10. Configure Cron
Add to crontab for regular maintenance:
```bash
# Run Drupal cron every 15 minutes
*/15 * * * * cd /path/to/veebiplatvorm && vendor/bin/drush cron --uri=sinukool.edu.ee
```

## Troubleshooting

### Common Issues and Solutions

#### "500 Error - Call to undefined function ini_set()"
**Cause:** `ini_set` is in the disabled_functions list.
**Solution:** Remove `ini_set` and `ini_restore` from `disable_functions` in PHP-FPM config. These are required by Drupal core.

#### "No input file specified"
**Causes & Solutions:**
1. **Wrong open_basedir**: Ensure PHP-FPM config includes your veebiplatvorm path
2. **Wrong document root**: Web server must point to `/path/to/veebiplatvorm/web`
3. **Permission issues**: Check file ownership matches PHP-FPM user

#### "Redirect loop with ?q= parameter"
**Cause:** Incorrect @drupal location configuration in Nginx.
**Solution:** Use `fastcgi_params` directly in @drupal location, not `rewrite`.

#### Installation Page Redirect Issues
1. Verify database connection in settings.php
2. Confirm sites.php configuration
3. Ensure hash_salt is properly set
4. Verify config_sync_directory is defined
5. Check that private files directory exists and is writable

#### JavaScript/CSS Aggregation Failures
Increase PHP memory limit or disable aggregation for debugging:
```php
ini_set('memory_limit', '512M');
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
```

#### PHP 8.3+ Deprecation Warnings
Add to settings.php:
```php
if (PHP_VERSION_ID >= 80300) {
  @ini_set('zend.assertions', -1);
}
error_reporting(E_ALL & ~E_DEPRECATED);
```

#### Composer Installation Timeout
```bash
COMPOSER_PROCESS_TIMEOUT=0 composer install
```

#### GuzzleHttp cURL Error
**Cause:** Missing PHP curl extension or curl functions disabled.
**Solution:**
1. Install curl extension (e.g., `php84-curl` on Alpine)
2. Remove `curl_exec` and `curl_multi_exec` from PHP-FPM `disable_functions`
3. Restart PHP-FPM service

### Module Compatibility Issues

#### Date Week Range (dwr)
Edit `/web/modules/contrib/dwr/dwr.info.yml`:
- Change: `core_version_requirement: ^8 || ^9 || ^10`
- Remove: `core: 8.x` line

#### Applying Patches Manually
If Composer patches fail:
```bash
cd web/modules/contrib/MODULE_NAME
curl -s PATCH_URL | patch -p1
```

## Security Best Practices

### Required PHP Functions for Drupal 10
These functions MUST remain enabled:
- `ini_set`, `ini_restore` - Session and cache management
- `proc_open` - Symfony Process component
- `escapeshellarg`, `escapeshellcmd` - Shell command security
- `parse_ini_file` - Configuration file parsing

### Functions Safe to Disable
- `exec`, `passthru`, `shell_exec`, `system` - Shell execution
- `popen` - Process execution
- `curl_multi_exec` - Not used by Drupal core
- `show_source`, `highlight_file` - Code display
- `phpinfo` - System information disclosure

### File Permissions
- Web files: 644 (rw-r--r--)
- Directories: 755 (rwxr-xr-x)
- Private files: 775 (rwxrwxr-x)
- settings.php: 444 (r--r--r--) after installation

## Performance Optimization

### PHP-FPM Tuning for Production
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 1000
```

### Nginx Performance Settings
```nginx
# Enable gzip compression
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml application/atom+xml image/svg+xml text/x-js text/x-cross-domain-policy application/x-font-ttf application/x-font-opentype application/vnd.ms-fontobject image/x-icon;

# Buffer settings
client_body_buffer_size 128k;
client_max_body_size 64m;
client_header_buffer_size 1k;
large_client_header_buffers 4 8k;
```

### Drupal Performance Settings
For production in settings.php:
```php
// Cache settings
$config['system.performance']['cache']['page']['max_age'] = 900;  // 15 minutes
$config['system.performance']['css']['preprocess'] = TRUE;
$config['system.performance']['js']['preprocess'] = TRUE;

// Fast 404 settings
$config['system.performance']['fast_404']['enabled'] = TRUE;
$config['system.performance']['fast_404']['paths'] = '/\.(?:txt|png|gif|jpe?g|css|js|ico|swf|flv|cgi|bat|pl|dll|exe|asp)$/i';
$config['system.performance']['fast_404']['html'] = '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1></body></html>';
```

## Backup and Maintenance

### Creating Backups
```bash
# Database backup (with compression)
vendor/bin/drush sql:dump --gzip > backups/$(date +%Y-%m-%d).mysql.gz

# Files backup (with compression)
tar -czf backups/files-$(date +%Y-%m-%d).tar.gz web/sites/*/files/

# Complete site backup script
#!/bin/bash
BACKUP_DIR="backups/$(date +%Y-%m-%d)"
mkdir -p $BACKUP_DIR
vendor/bin/drush sql:dump --gzip > $BACKUP_DIR/database.mysql.gz
tar -czf $BACKUP_DIR/files.tar.gz web/sites/*/files/
echo "Backup completed in $BACKUP_DIR"
```

### Regular Maintenance Tasks
```bash
# Clear all caches
vendor/bin/drush cache:rebuild

# Run database updates
vendor/bin/drush updatedb

# Check security updates
vendor/bin/drush pm:security

# Entity updates
vendor/bin/drush entity:updates

# Cron run
vendor/bin/drush cron

# Check status
vendor/bin/drush status
```

### Automated Maintenance Script
Create `maintenance.sh`:
```bash
#!/bin/bash
SITE_URI="sinukool.edu.ee"
LOG_FILE="/var/log/drupal-maintenance.log"

echo "Maintenance started at $(date)" >> $LOG_FILE

# Clear cache
vendor/bin/drush cache:rebuild --uri=$SITE_URI >> $LOG_FILE 2>&1

# Run updates
vendor/bin/drush updatedb --uri=$SITE_URI -y >> $LOG_FILE 2>&1

# Run cron
vendor/bin/drush cron --uri=$SITE_URI >> $LOG_FILE 2>&1

echo "Maintenance completed at $(date)" >> $LOG_FILE
```

Add to crontab:
```bash
# Daily maintenance at 3 AM
0 3 * * * /path/to/veebiplatvorm/maintenance.sh
```

## Important Notes

### Database Connection
Always use `127.0.0.1` instead of `localhost` in database configuration to avoid socket connection issues.

### Removed Patches
The following Drupal core patches were removed as they caused installation issues and aren't critical for Drupal 10.4.6:
- Menu translation patch (2466553)
- ChangedItem migration patch (2329253)
- Help text override patch
- DateTimePlus validation patch

These functionalities work correctly in Drupal 10.4.6 without patches.

### PHP Memory Requirements
- Minimum: 256MB
- Recommended: 512MB
- For large sites: 1GB+

## Support and Resources

- **Drupal Documentation**: https://www.drupal.org/docs/10
- **Nginx with Drupal**: https://www.nginx.com/resources/wiki/start/topics/recipes/drupal/
- **Apache with Drupal**: https://www.drupal.org/docs/7/system-requirements/apache
- **PHP-FPM Configuration**: https://www.php.net/manual/en/install.fpm.configuration.php
- **Drush Commands**: https://www.drush.org/latest/commands/

## Version History

- **v1.2** (2025-09-15): Unified documentation, improved troubleshooting guide
- **v1.1** (2025-09-15): Added gzip support, fixed redirect loops, improved error handling
- **v1.0** (2025-09-01): Initial release

---

*Last updated: 2025-09-15*