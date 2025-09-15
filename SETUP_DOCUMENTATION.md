# HTM Haridusasutuste Veebiplatvorm - Setup Documentation

## Overview
This document provides comprehensive instructions for setting up the Drupal 10 educational institution website platform. It includes all lessons learned from production deployments and solutions to common issues.

## Prerequisites

### System Requirements
- **OS**: Alpine Linux 3.x or compatible Linux distribution
- **PHP**: 8.1+ (tested with PHP 8.4)
- **Database**: MariaDB 10.6+ or MySQL 8.0+
- **Web Server**: Nginx 1.20+
- **PHP-FPM**: Configured and running
- **Composer**: 2.x
- **Git**: For version control
- **Drush**: Included via Composer

### Required PHP Extensions
- pdo_mysql
- gd
- mbstring
- xml
- json
- opcache
- zip
- curl

## Quick Start

1. Clone the repository to your site directory:
```bash
cd /sites/yourdomain.com
git clone [repository-url] veebiplatvorm
cd veebiplatvorm
```

2. Run the setup script:
```bash
./setup.sh
```

3. Follow the interactive prompts to configure your site.

## Detailed Setup Process

### 1. Database Backup Preparation

Place your backups in the `backups/DOMAIN/` directory:
```
backups/
└── torva.edu.ee/
    ├── backup-2025-09-07T10-53-08.mysql.gz  # Gzipped database dump
    └── backup-2025-09-07T10-53-34.tar.gz    # Gzipped public files
```

**Important**: The setup script supports both gzipped (`.gz`) and uncompressed backup files.

### 2. Running the Setup Script

The setup script will:
- Check prerequisites
- Scan for available backups
- Configure database settings
- Import database and files
- Set proper permissions
- Clear caches and run updates

### 3. Critical Server Configuration

#### PHP-FPM Configuration (`/etc/php84/php-fpm.d/yourdomain.conf`)

```ini
[yourdomain.com]
user = yourusername
group = yourusername
listen = /run/php-fpm84/yourdomain.com.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660

pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

; PHP settings
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 300
php_admin_value[post_max_size] = 64M
php_admin_value[upload_max_filesize] = 64M

; CRITICAL: Must include veebiplatvorm directory
php_admin_value[open_basedir] = /sites/yourdomain.com/veebiplatvorm:/sites/yourdomain.com/tmp:/usr/share/php84

; IMPORTANT: These functions MUST NOT be disabled for Drupal 10
; Safe disable_functions for Drupal:
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,popen,curl_multi_exec,show_source,highlight_file,phpinfo

; DO NOT DISABLE THESE (Required by Drupal):
; - ini_set, ini_restore (Drupal core session handling)
; - proc_open (Symfony Process component)
; - escapeshellarg, escapeshellcmd (security functions)
; - parse_ini_file (configuration parsing)
```

#### Nginx Configuration

Use the provided `nginx-drupal.conf.example` as a template. Key points:

1. **Document Root**: Must point to `/sites/yourdomain.com/veebiplatvorm/web`
2. **@drupal Location**: MUST use `fastcgi_params` directly, NOT `rewrite`
3. **PHP-FPM Socket**: Match the socket path in PHP-FPM configuration

Critical configuration snippet:
```nginx
# CORRECT - Use this:
location @drupal {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_param SCRIPT_NAME /index.php;
    fastcgi_param QUERY_STRING $query_string;
    fastcgi_pass unix:/run/php-fpm84/yourdomain.com.sock;
}

# WRONG - Do NOT use this (causes redirect loops):
location @drupal {
    rewrite ^/(.*)$ /index.php?q=$1 last;
}
```

### 4. Post-Installation Steps

1. **Restart services**:
```bash
rc-service php-fpm84 restart
rc-service nginx restart
```

2. **Test the installation**:
```bash
curl -I https://yourdomain.com
```

3. **Check logs for errors**:
```bash
tail -f /sites/yourdomain.com/logs/php/error.log
tail -f /sites/yourdomain.com/logs/nginx/error.log
```

## Troubleshooting Guide

### Issue: "500 Error - Call to undefined function ini_set()"

**Cause**: `ini_set` is in the disabled_functions list.

**Solution**: Remove `ini_set` and `ini_restore` from `disable_functions` in PHP-FPM config. These are required by Drupal core.

### Issue: "No input file specified"

**Causes & Solutions**:
1. **Wrong open_basedir**: Ensure PHP-FPM config includes `/sites/yourdomain.com/veebiplatvorm`
2. **Wrong document root**: Nginx must point to `/sites/yourdomain.com/veebiplatvorm/web`
3. **Permission issues**: Check file ownership matches PHP-FPM user

### Issue: "Redirect loop with ?q= parameter"

**Cause**: Incorrect @drupal location configuration in Nginx.

**Solution**: Use `fastcgi_params` directly in @drupal location, not `rewrite`. See nginx configuration section above.

### Issue: "Incompatible module" warnings

**Common incompatible modules and fixes**:

1. **Date Week Range (dwr)**:
   - Edit `/web/modules/contrib/dwr/dwr.info.yml`
   - Change: `core_version_requirement: ^8 || ^9 || ^10`
   - Remove: `core: 8.x` line

2. **Apply patches manually if Composer fails**:
```bash
cd web/modules/contrib/MODULE_NAME
curl -s PATCH_URL | patch -p1
```

### Issue: "Permission denied" when creating settings.php

**Cause**: Existing read-only settings.php file.

**Solution**: The setup script now handles this automatically, but if manual intervention is needed:
```bash
chmod 644 web/sites/yourdomain.com/settings.php
```

## Security Considerations

### Required PHP Functions for Drupal 10
These functions MUST remain enabled:
- `ini_set`, `ini_restore` - Session and cache management
- `proc_open` - Symfony Process component
- `escapeshellarg`, `escapeshellcmd` - Shell command security
- `parse_ini_file` - Configuration file parsing

### Safe to Disable
These functions can be safely disabled:
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

## Module Compatibility

### Known Issues with Drupal 10.4

1. **Date Week Range**: Requires manual patch for D10 compatibility
2. **Genpass**: Remove obsolete update hooks
3. **Dropzonejs/Media Bulk Upload**: Alt text patches may fail with newer versions

### Applying Patches

If Composer patches fail, apply manually:
```bash
# Example for Date Week Range
cd web/modules/contrib/dwr
curl -s https://www.drupal.org/files/issues/[patch-file].patch | patch -p1
```

## Performance Optimization

### PHP-FPM Tuning
```ini
pm = dynamic
pm.max_children = 10
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
pm.max_requests = 500
```

### Nginx Buffer Settings
```nginx
fastcgi_buffer_size 128k;
fastcgi_buffers 256 16k;
fastcgi_busy_buffers_size 256k;
fastcgi_temp_file_write_size 256k;
fastcgi_read_timeout 240s;
```

## Backup and Maintenance

### Creating Backups
```bash
# Database backup
drush sql:dump --gzip > backups/$(date +%Y-%m-%d).mysql.gz

# Files backup
tar -czf backups/files-$(date +%Y-%m-%d).tar.gz web/sites/*/files/
```

### Regular Maintenance
```bash
# Clear caches
drush cache:rebuild

# Run database updates
drush updatedb

# Check security updates
drush pm:security
```

## Support and Resources

- **Drupal Documentation**: https://www.drupal.org/docs/10
- **Nginx with Drupal**: https://www.nginx.com/resources/wiki/start/topics/recipes/drupal/
- **PHP-FPM Configuration**: https://www.php.net/manual/en/install.fpm.configuration.php

## Version History

- **v1.1** (2025-09-15): Added gzip support, fixed redirect loops, improved error handling
- **v1.0** (2025-09-01): Initial release

## License

[Your License Here]

---

*Last updated: 2025-09-15*