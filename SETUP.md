# HTM HVP Deployment Guide

HTM Haridusasutuste Veebiplatvorm (HVP) - Estonian Educational Institutions Web Platform

This platform supports any Estonian educational institution website with any domain (.ee, .edu.ee, or custom).

## Quick Setup (Automated Script)

### Prerequisites
1. Log in as administrator to your existing HTM production site
2. Navigate to: `https://[your-domain]/et/admin/config/development/backup_migrate`
3. Create two backups:
   - **Database backup** → generates `backup-xxx.mysql`
   - **Public files backup** → generates `backup-xxx.tar`

4. Create backup directory: `mkdir -p ./backups/[your-domain]/`
5. Place both backup files in this directory

Note: The script will automatically scan the `./backups/` directory for available site backups

### Running the Setup Script

```bash
./setup.sh
```

The script will:
1. Check prerequisites (PHP, Composer, MySQL client)
2. Scan for available backups in `./backups/`
3. Prompt for site configuration (institution name, domain)
4. Ask for environment type (Development/Staging/Production)
5. Configure database settings (with option to generate secure password)
6. Create database and user (optional, requires MySQL root access)
7. Import database and files from backup (if available)
8. Generate secure hash salt automatically
9. Configure environment-specific settings
10. Set proper file permissions
11. Clear cache and run database updates

### Setup Examples

#### Development Environment Setup:
```
Select site [1]: 1  (selecting torvakool.edu.ee from found backups)
Enter institution name: Tõrva Kool
Choose environment [1]: 1  (Development)
Site domain [torvakool.localhost]: torvakool.localhost
Database host [127.0.0.1]: 127.0.0.1
Database port [3306]: 3306
Database name [torvakool_edu]: torvakool_edu
Database username [drupal]: drupal
Choose option [1]: 1  (Generate secure password)
Choose option [1]: 1  (Create new database)
Private files directory [./private]: ./private

→ Creates site at web/sites/torvakool.localhost/
→ Imports backups from ./backups/torvakool.edu.ee/
→ Sets CSS/JS aggregation to FALSE
→ Sets error display to TRUE
→ Sets page cache to 0 seconds
```

**Post-Setup Steps:**
1. Configure your web server document root to point to `./web` (see [Web Server Configuration](#10-web-server-and-cron-setup) for examples)
2. Access the site at http://torvakool.localhost

#### Production Environment Setup:
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

→ Creates site at web/sites/ut.ee/
→ Imports backups from ./backups/ut.ee/
→ Sets CSS/JS aggregation to TRUE
→ Sets error display to FALSE
→ Sets page cache to 900 seconds (15 minutes)
→ Disables update.php access
```

**Post-Setup Steps:**
1. Configure web server document root to point to `./web` (see [Web Server Configuration](#10-web-server-and-cron-setup) for full Apache/Nginx examples)
2. Ensure mod_rewrite (Apache) or URL rewriting (Nginx) is enabled
3. Set up SSL certificate for HTTPS
4. Configure Drupal cron job:
   ```bash
   */15 * * * * cd /path/to/veebiplatvorm && vendor/bin/drush cron --uri=ut.ee
   ```
5. Access the site at https://ut.ee

#### Multi-Site Configuration:
Run the script multiple times to configure multiple institutions on the same server:
```bash
# First institution
./setup.sh
# Enter: Tallinna Reaalkool, real.edu.ee

# Second institution
./setup.sh
# Enter: Tallinna Tehnikaülikool, ttu.ee

# Third institution
./setup.sh
# Enter: Tartu Kutsehariduskeskus, khk.ee
```

Each site maintains independent:
- Database (automatically generated from domain)
- Files directory (web/sites/[domain]/)
- Settings (each site has its own settings.php)
- Domain configuration

## Manual Setup Instructions

Follow these steps if you prefer manual configuration over the automated script. Example uses `torvakool.edu.ee` domain - replace with your institution's domain.

### System Requirements
- **PHP** 8.1+ with extensions: `mbstring`, `xml`, `gd`, `json`, `pdo_mysql`
- **Database**: MariaDB 11.4+ or MySQL 8.0+
- **Composer** 2.x
- **Web Server**: Apache/Nginx with `mod_rewrite`
- **OpenSSL**: For generating secure passwords and hash salts

### Deployment Steps

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
CREATE DATABASE torvakooleduee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'torvakooleduee'@'127.0.0.1' IDENTIFIED BY '[SECURE_PASSWORD]';
GRANT ALL PRIVILEGES ON torvakooleduee.* TO 'torvakooleduee'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### 4. Import Database
```bash
# Replace with your actual backup filename
mysql -u torvakooleduee -p torvakooleduee < backups/torvakool.edu.ee/backup-xxx.mysql
```

### 5. Configure Settings
Create `web/sites/torvakool.edu.ee/settings.php`:
```php
<?php
// Copy from web/sites/default/default.settings.php
// Then add at the end:

$databases['default']['default'] = [
  'database' => 'torvakooleduee',
  'username' => 'torvakooleduee',
  'password' => '[SECURE_PASSWORD]',
  'host' => '127.0.0.1',  // Always use 127.0.0.1 to avoid socket issues
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

$settings['trusted_host_patterns'] = [
  '^torvakool\.edu\.ee$',
  '^www\.torvakool\.edu\.ee$',
  '^localhost$',
  '^127\.0\.0\.1$',
];

$settings['hash_salt'] = '[GENERATE_UNIQUE_SALT]';  // Use: openssl rand -base64 32
$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = './private';  // Private files directory

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
mkdir -p ./private  # Private files directory
mkdir -p web/sites/torvakool.edu.ee/files/{css,js,php}

# Extract files from backup if available
tar -xf backups/torvakool.edu.ee/backup-xxx.tar -C web/sites/torvakool.edu.ee/files/
chmod -R 775 web/sites/torvakool.edu.ee/files
chmod -R 775 ./private
```

### 7. Configure Multi-site
Create `web/sites/sites.php`:
```php
<?php
$sites['torvakool.edu.ee'] = 'torvakool.edu.ee';
$sites['www.torvakool.edu.ee'] = 'torvakool.edu.ee';
```

### 8. Web Server Configuration
Ensure `.htaccess` exists:
```bash
cp web/core/assets/scaffold/files/htaccess web/.htaccess
```

### 9. Clear Cache and Run Updates
```bash
# Clear cache
vendor/bin/drush cache:rebuild --uri=torvakool.edu.ee

# Run database updates
vendor/bin/drush updatedb --uri=torvakool.edu.ee -y

# For production: disable update.php access
vendor/bin/drush state:set system.update_free_access 0 --uri=torvakool.edu.ee
```

### 10. Web Server and Cron Setup
Configure your web server to point to the `web` directory:

**Apache Configuration:**
```apache
DocumentRoot /path/to/veebiplatvorm/web
<Directory /path/to/veebiplatvorm/web>
    AllowOverride All
    Require all granted
</Directory>
```

**Nginx Configuration:**
```nginx
server {
    listen 80;
    server_name torvakool.edu.ee www.torvakool.edu.ee;
    root /path/to/veebiplatvorm/web;
    index index.php;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ '\.php$|^/update.php' {
        fastcgi_split_path_info ^(.+?\.php)(|/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_intercept_errors on;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;  # Adjust PHP version
    }

    # Protect files and directories from prying eyes
    location ~* \.(engine|inc|install|make|module|profile|po|sh|.*sql|theme|twig|tpl(\.php)?|xtmpl|yml)(~|\.sw[op]|\.bak|\.orig|\.save)?$|/(\.(?!well-known).*|Entries.*|Repository|Root|Tag|Template|composer\.(json|lock)|web\.config)$|/#.*#$|\.php(~|\.sw[op]|\.bak|\.orig|\.save)$ {
        deny all;
        return 404;
    }

    # Allow "Well-Known URIs" as per RFC 5785
    location ~* ^/.well-known/ {
        allow all;
    }

    # Block access to scripts in site files directory
    location ~ ^/sites/.*/files/.*\.php$ {
        deny all;
    }

    # Block access to "hidden" files and directories
    location ~ (^|/)\. {
        return 403;
    }

    location @rewrite {
        rewrite ^ /index.php;
    }

    # Don't allow direct access to PHP files in the vendor directory
    location ~ /vendor/.*\.php$ {
        deny all;
        return 404;
    }

    # Protect private files
    location ~ ^/sites/.*/private/ {
        return 403;
    }

    # Allow access to theme and module assets
    location ~ ^/sites/.*/files/(css|js|images|fonts)/ {
        try_files $uri @rewrite;
    }

    # Handle private file transfers
    location ~ ^/system/files/ {
        try_files $uri /index.php?$query_string;
    }

    # Serve static files directly
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        try_files $uri @rewrite;
        expires max;
        log_not_found off;
    }
}
```

**Cron Job for Drupal (run every 15 minutes):**
```bash
*/15 * * * * cd /path/to/veebiplatvorm && vendor/bin/drush cron --uri=torvakool.edu.ee
```

## Important Notes

### Removed Patches
The following Drupal core patches were removed as they caused installation issues and aren't critical for Drupal 10.4.6:
- Menu translation patch (2466553)
- ChangedItem migration patch (2329253)
- Help text override patch
- DateTimePlus validation patch

These functionalities work correctly in Drupal 10.4.6 without patches.

### Database Connection
Always use `127.0.0.1` instead of `localhost` in database configuration to avoid socket connection issues.

### PHP Memory Limit
Ensure PHP memory limit is at least 256M (512M recommended):
```php
ini_set('memory_limit', '512M');
```

## Troubleshooting

### Composer Installation Timeout
Extend the timeout period:
```bash
COMPOSER_PROCESS_TIMEOUT=0 composer install
```

### Installation Page Redirect Issues
1. Verify database connection in settings.php
2. Confirm sites.php configuration
3. Ensure hash_salt is properly set
4. Verify config_sync_directory is defined
5. Check that private files directory exists and is writable

### JavaScript/CSS Aggregation Failures
Increase PHP memory limit or disable aggregation for debugging:
```php
ini_set('memory_limit', '512M');
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
```

### PHP 8.3+ Deprecation Warnings
The setup script automatically suppresses PHP 8.3+ deprecation warnings. If you see them, add to settings.php:
```php
if (PHP_VERSION_ID >= 80300) {
  @ini_set('zend.assertions', -1);
}
error_reporting(E_ALL & ~E_DEPRECATED);
```