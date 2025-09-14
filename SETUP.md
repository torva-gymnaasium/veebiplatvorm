# HTM HVP Deployment Guide

HTM Haridusasutuste Veebiplatvorm (HVP) - Estonian Educational Institutions Web Platform

This platform supports any Estonian educational institution website with any domain (.ee, .edu.ee, or custom).

## Quick Setup (Universal Script)

The setup script works for any site and environment:

```bash
git clone [repository-url]
cd veebiplatvorm-backend
./setup.sh
```

### Example Usage:

#### Setting up a School Website for Development:
```
Institution name: Tõrva Kool
Production domain: torvakool.edu.ee
Environment: Development
Site domain: torvakool.localhost
Database host: 127.0.0.1
→ Creates site at web/sites/torvakool.localhost/
→ Looks for real.edu.ee backups
→ Disables caching and aggregation
→ Enables error display
```

#### Setting up for Production:
```
Institution name: Tartu Ülikool
Production domain: ut.ee
Environment: Production
Site domain: ut.ee
Database host: 127.0.0.1
→ Creates site at web/sites/ut.ee/
→ Looks for ut.ee backups
→ Enables full performance optimizations
→ Hardens security settings
```

#### Setting up Multiple Sites on Same Server:
You can run the script multiple times to set up multiple institutions on the same server:
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

Each site will have separate:
- Database (automatically generated from domain)
- Files directory (web/sites/[domain]/)
- Settings (each site has its own settings.php)
- Domain configuration

## Manual Setup Instructions

### Prerequisites
- PHP 8.1+ with required extensions
- MariaDB 11.4.x or MySQL 8.0+
- Composer 2.x
- Apache/Nginx with mod_rewrite enabled

## Deployment Steps

### 1. Clone Repository
```bash
git clone [repository-url]
cd veebiplatvorm-backend
```

### 2. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Database Setup
```sql
CREATE DATABASE torvakooleduee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'torvakooleduee'@'localhost' IDENTIFIED BY '[SECURE_PASSWORD]';
GRANT ALL PRIVILEGES ON torvakooleduee.* TO 'torvakooleduee'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Import Database
```bash
mysql -u torvakooleduee -p torvakooleduee < backup/backup-2025-09-07T10-56-51.sql
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
  'host' => '127.0.0.1',  // Use 127.0.0.1 instead of localhost
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

$settings['trusted_host_patterns'] = [
  '^torvakool\.edu\.ee$',
  '^www\.torvakool\.edu\.ee$',
];

$settings['hash_salt'] = '[GENERATE_UNIQUE_SALT]';  // Use: openssl rand -base64 32
$settings['config_sync_directory'] = '../config/sync';
```

### 6. Create Required Directories
```bash
mkdir -p config/sync
cp -r backup/sites/torvakool.edu.ee web/sites/
chmod -R 755 web/sites/torvakool.edu.ee/files
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

### 9. Clear Cache
```bash
vendor/bin/drush cache:rebuild --uri=torvakool.edu.ee
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

### If composer install hangs
Increase timeout:
```bash
COMPOSER_PROCESS_TIMEOUT=0 composer install
```

### If site redirects to install.php
1. Check database connection in settings.php
2. Verify sites.php configuration
3. Ensure hash_salt is set
4. Check config_sync_directory is defined

### If JavaScript/CSS aggregation fails
Increase PHP memory limit in settings.php or disable aggregation for debugging:
```php
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
```