# Server Requirements for HTM Veebiplatvorm

## PHP Configuration

### Required PHP Version
- PHP 8.3+ (tested with 8.3.25)

### Required PHP Extensions
- php84-curl (for GuzzleHttp)
- php84-gd
- php84-mbstring
- php84-mysqli or php84-pdo_mysql
- php84-opcache
- php84-xml
- php84-zip
- php84-json
- php84-session
- php84-simplexml
- php84-dom
- php84-tokenizer

### PHP-FPM Configuration
When setting up PHP-FPM pool configuration, ensure:

1. **open_basedir** includes all necessary paths:
   ```
   php_admin_value[open_basedir] = /path/to/veebiplatvorm:/path/to/veebiplatvorm/tmp:/path/to/veebiplatvorm/logs:/path/to/veebiplatvorm/private:/tmp:/usr/share/php84
   ```

2. **Do NOT disable curl functions** in disable_functions:
   ```
   # Remove curl_exec and curl_multi_exec from this list
   php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,parse_ini_file,show_source
   ```

3. **Memory and upload limits**:
   ```
   php_admin_value[memory_limit] = 256M
   php_admin_value[max_execution_time] = 300
   php_admin_value[post_max_size] = 100M
   php_admin_value[upload_max_filesize] = 100M
   ```

## Web Server (Nginx)

### Document Root
- Set to: `/path/to/veebiplatvorm/web`

### Important Nginx Configuration
- Adapt the nginx configuration for your specific domain
- Ensure proper Drupal 10 clean URL support with @drupal location
- PHP-FPM socket should match your pool configuration (e.g., `/run/php-fpm84/YOUR_DOMAIN.sock` or use TCP like `127.0.0.1:9000`)

## Database
- MariaDB/MySQL 5.7.8+ or MariaDB 10.3.7+
- Database collation: utf8mb4_general_ci

## File System
Create these directories with proper permissions:
```bash
/path/to/veebiplatvorm/web/sites/YOUR_DOMAIN/files (775)
/path/to/veebiplatvorm/private (775)
/path/to/veebiplatvorm/tmp (775)
/path/to/veebiplatvorm/logs/php (775)
/path/to/veebiplatvorm/logs/nginx (775)
```

## System Tools
- Composer 2.x
- Git
- Drush (included via Composer)

## Known Issues and Solutions

### Date Week Range Module
The Date Week Range (dwr) module is not compatible with Drupal 10. It has been removed from the configuration.

### GuzzleHttp cURL Error
If you see "GuzzleHttp requires cURL" error:
1. Install php84-curl package
2. Remove curl_exec and curl_multi_exec from PHP-FPM disable_functions
3. Restart PHP-FPM

### Setup Script
The setup.sh script now saves configuration choices in real-time to .setup.sh file. This allows resuming setup if interrupted.

## Alpine Linux Specific
For Alpine Linux systems:
```bash
# Install required packages
apk add php84 php84-fpm php84-curl php84-gd php84-mbstring php84-mysqli \
        php84-opcache php84-xml php84-zip php84-json php84-session \
        php84-simplexml php84-dom php84-tokenizer nginx mariadb-client composer git

# Set timezone (optional)
setup-timezone -z Europe/Tallinn
```