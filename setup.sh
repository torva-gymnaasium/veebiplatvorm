#!/bin/bash

# HTM Haridusasutuste Veebiplatvorm (HVP) Setup Script
# Universal setup script for Estonian educational institution websites
# Works for development, staging, and production environments
# Supports any domain

set -e  # Exit on any error

echo "========================================="
echo "HTM Haridusasutuste Veebiplatvorm (HVP)"
echo "Setup Script"
echo "========================================="
echo ""

# Check if running as root
if [ "$EUID" -eq 0 ]; then
   echo "Please do not run this script as root for security reasons"
   exit 1
fi

# Default configuration variables
DEFAULT_DB_HOST="127.0.0.1"
DEFAULT_DB_PORT="3306"
# DEFAULT_DB_NAME and BACKUP_SOURCE will be set based on site selection
DEFAULT_DB_USER="drupal"
DEFAULT_WEB_ROOT="/var/www/html"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[i]${NC} $1"
}

# Load saved configuration if exists (for defaults only)
CONFIG_FILE="./.setup.sh"
if [ -f "$CONFIG_FILE" ]; then
    print_info "Loading previous settings from $CONFIG_FILE"
    source "$CONFIG_FILE"
    echo ""
fi

# Check prerequisites
echo "Checking prerequisites..."

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
if [ $(echo "$PHP_VERSION >= 8.1" | bc) -eq 1 ]; then
    print_status "PHP version $PHP_VERSION is compatible"
else
    print_error "PHP version $PHP_VERSION is too old. PHP 8.1+ required"
    exit 1
fi

# Check Composer
if command -v composer &> /dev/null; then
    print_status "Composer is installed"
else
    print_error "Composer is not installed"
    exit 1
fi

# Check MariaDB/MySQL
if command -v mysql &> /dev/null; then
    print_status "MySQL/MariaDB client is installed"
else
    print_error "MySQL/MariaDB client is not installed"
    exit 1
fi

echo ""
echo "Scanning for Available Backups"
echo "=============================="
echo ""

# Scan backups directory for available sites (check both local and /backups)
BACKUP_DIR=""
if [ -d "./backups" ]; then
    BACKUP_DIR="./backups"
elif [ -d "/backups" ]; then
    BACKUP_DIR="/backups"
fi

AVAILABLE_SITES=()
if [ -n "$BACKUP_DIR" ] && [ -d "$BACKUP_DIR" ]; then
    for dir in "$BACKUP_DIR"/*; do
        if [ -d "$dir" ]; then
            domain=$(basename "$dir")
            # Check if there are actual backup files
            if ls "$dir"/*.mysql 2>/dev/null 1>&2 || ls "$dir"/*.tar 2>/dev/null 1>&2; then
                AVAILABLE_SITES+=("$domain")
            fi
        fi
    done
fi

echo ""
echo "Site Configuration"
echo "=================="
echo ""

# If backups found, offer them as options
if [ ${#AVAILABLE_SITES[@]} -gt 0 ]; then
    echo "Found backups for the following sites:"
    echo ""
    for i in "${!AVAILABLE_SITES[@]}"; do
        echo "$((i+1))) ${AVAILABLE_SITES[$i]}"
        # Show latest backup dates
        latest_db=$(ls -t "$BACKUP_DIR/${AVAILABLE_SITES[$i]}"/*.mysql 2>/dev/null | head -1)
        latest_files=$(ls -t "$BACKUP_DIR/${AVAILABLE_SITES[$i]}"/*.tar 2>/dev/null | head -1)
        if [ -n "$latest_db" ]; then
            db_date=$(basename "$latest_db" | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}-[0-9]{2}-[0-9]{2}')
            echo "   Database: $db_date"
        fi
        if [ -n "$latest_files" ]; then
            files_date=$(basename "$latest_files" | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}-[0-9]{2}-[0-9]{2}')
            echo "   Files: $files_date"
        fi
        echo ""
    done
    echo "$((${#AVAILABLE_SITES[@]}+1))) Enter custom domain"
    echo ""

    # Use saved default if available
    DEFAULT_SITE_SELECTION="${SAVED_SITE_SELECTION:-1}"
    read -p "Select site [$DEFAULT_SITE_SELECTION]: " SITE_SELECTION
    SITE_SELECTION=${SITE_SELECTION:-$DEFAULT_SITE_SELECTION}

    if [ "$SITE_SELECTION" -le "${#AVAILABLE_SITES[@]}" ] 2>/dev/null; then
        # User selected an existing backup
        BASE_DOMAIN="${AVAILABLE_SITES[$((SITE_SELECTION-1))]}"
        BACKUP_AVAILABLE=true
        print_status "Selected: $BASE_DOMAIN"
    else
        # User wants custom domain
        read -p "Enter production domain (e.g., 'ut.ee', 'torvakool.edu.ee'): " BASE_DOMAIN
        while [ -z "$BASE_DOMAIN" ]; do
            echo "Domain is required!"
            read -p "Enter production domain: " BASE_DOMAIN
        done
        BACKUP_AVAILABLE=false
    fi
else
    # No backups found, ask for domain
    read -p "Enter production domain (e.g., 'ut.ee', 'torvakool.edu.ee'): " BASE_DOMAIN
    while [ -z "$BASE_DOMAIN" ]; do
        echo "Domain is required!"
        read -p "Enter production domain: " BASE_DOMAIN
    done
    BACKUP_AVAILABLE=false
fi

# Ask for institution name
DEFAULT_SITE_NAME="${SAVED_SITE_NAME:-Educational Institution}"
read -p "Enter institution name (e.g., 'Tartu Ülikool') [$DEFAULT_SITE_NAME]: " SITE_NAME
SITE_NAME=${SITE_NAME:-$DEFAULT_SITE_NAME}

# Generate default database name from domain
# Remove TLD and replace dots/special chars with underscores
DEFAULT_DB_NAME=$(echo $BASE_DOMAIN | sed 's/\.[^.]*$//' | sed 's/[^a-zA-Z0-9]/_/g')
BACKUP_SOURCE="$BASE_DOMAIN"

print_status "Setting up ${SITE_NAME} (${BASE_DOMAIN})"

echo ""
echo "Environment Configuration"
echo "========================"
echo ""

# Ask for environment type
echo "Select environment type:"
echo "1) Development"
echo "2) Staging"
echo "3) Production"
DEFAULT_ENV_TYPE="${SAVED_ENV_TYPE:-1}"
read -p "Choose environment [$DEFAULT_ENV_TYPE]: " ENV_TYPE
ENV_TYPE=${ENV_TYPE:-$DEFAULT_ENV_TYPE}

case $ENV_TYPE in
    1)
        ENVIRONMENT="development"
        # For development, suggest .localhost or .local domain
        BASE_NAME=$(echo $BASE_DOMAIN | sed 's/\.[^.]*$//')
        DEFAULT_DOMAIN="${BASE_NAME}.localhost"
        ENABLE_AGGREGATION="FALSE"
        ERROR_DISPLAY="TRUE"
        CACHE_MAX_AGE="0"
        ;;
    2)
        ENVIRONMENT="staging"
        DEFAULT_DOMAIN="staging.${BASE_DOMAIN}"
        ENABLE_AGGREGATION="TRUE"
        ERROR_DISPLAY="FALSE"
        CACHE_MAX_AGE="300"
        ;;
    3)
        ENVIRONMENT="production"
        DEFAULT_DOMAIN="${BASE_DOMAIN}"
        ENABLE_AGGREGATION="TRUE"
        ERROR_DISPLAY="FALSE"
        CACHE_MAX_AGE="900"
        ;;
    *)
        print_error "Invalid environment selection"
        exit 1
        ;;
esac

print_status "Setting up for ${ENVIRONMENT} environment"

# Ask for site domain
echo ""
DEFAULT_SITE_DOMAIN="${SAVED_SITE_DOMAIN:-$DEFAULT_DOMAIN}"
read -p "Site domain [${DEFAULT_SITE_DOMAIN}]: " SITE_DOMAIN
SITE_DOMAIN=${SITE_DOMAIN:-$DEFAULT_SITE_DOMAIN}

# Extract base domain for sites directory
SITE_DIR=$(echo $SITE_DOMAIN | sed 's/^www\.//')

echo ""
echo "Database Configuration"
echo "======================"
echo ""

# Interactive prompts for database configuration
DEFAULT_DB_HOST="${SAVED_DB_HOST:-$DEFAULT_DB_HOST}"
read -p "Database host [${DEFAULT_DB_HOST}]: " DB_HOST
DB_HOST=${DB_HOST:-$DEFAULT_DB_HOST}

DEFAULT_DB_PORT="${SAVED_DB_PORT:-$DEFAULT_DB_PORT}"
read -p "Database port [${DEFAULT_DB_PORT}]: " DB_PORT
DB_PORT=${DB_PORT:-$DEFAULT_DB_PORT}

DEFAULT_DB_NAME="${SAVED_DB_NAME:-$DEFAULT_DB_NAME}"
read -p "Database name [${DEFAULT_DB_NAME}]: " DB_NAME
DB_NAME=${DB_NAME:-$DEFAULT_DB_NAME}

DEFAULT_DB_USER="${SAVED_DB_USER:-$DEFAULT_DB_USER}"
read -p "Database username [${DEFAULT_DB_USER}]: " DB_USER
DB_USER=${DB_USER:-$DEFAULT_DB_USER}

# Ask if user wants to provide password or generate one
echo ""
echo "Database Password Options:"
echo "1) Generate a secure random password (recommended)"
echo "2) Enter your own password"
DEFAULT_PASSWORD_OPTION="${SAVED_PASSWORD_OPTION:-1}"
read -p "Choose option [$DEFAULT_PASSWORD_OPTION]: " PASSWORD_OPTION
PASSWORD_OPTION=${PASSWORD_OPTION:-$DEFAULT_PASSWORD_OPTION}

if [ "$PASSWORD_OPTION" = "2" ]; then
    # If we have a saved password and user just pressed enter, use it
    if [ -n "$SAVED_DB_PASSWORD" ]; then
        read -s -p "Enter database password [saved]: " DB_PASSWORD
        echo ""
        if [ -z "$DB_PASSWORD" ]; then
            DB_PASSWORD="$SAVED_DB_PASSWORD"
            print_status "Using saved password"
        else
            read -s -p "Confirm database password: " DB_PASSWORD_CONFIRM
            echo ""
            if [ "$DB_PASSWORD" != "$DB_PASSWORD_CONFIRM" ]; then
                print_error "Passwords do not match!"
                exit 1
            fi
        fi
    else
        read -s -p "Enter database password: " DB_PASSWORD
        echo ""
        read -s -p "Confirm database password: " DB_PASSWORD_CONFIRM
        echo ""
        if [ "$DB_PASSWORD" != "$DB_PASSWORD_CONFIRM" ]; then
            print_error "Passwords do not match!"
            exit 1
        fi
    fi
else
    print_status "Generating secure password..."
    DB_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)
fi

# Ask about creating new database or using existing
echo ""
echo "Database Setup Options:"
echo "1) Create new database and user (requires MySQL root access)"
echo "2) Use existing database and user"
DEFAULT_DB_SETUP_OPTION="${SAVED_DB_SETUP_OPTION:-1}"
read -p "Choose option [$DEFAULT_DB_SETUP_OPTION]: " DB_SETUP_OPTION
DB_SETUP_OPTION=${DB_SETUP_OPTION:-$DEFAULT_DB_SETUP_OPTION}

echo ""
echo "File Storage Configuration"
echo "=========================="
echo ""

# Use ./private as default - outside web root but in project directory
DEFAULT_PRIVATE_FILES="./private"
DEFAULT_PRIVATE_FILES_DIR="${SAVED_PRIVATE_FILES:-$DEFAULT_PRIVATE_FILES}"

# Loop until we get a valid private files directory
while true; do
    read -p "Private files directory [${DEFAULT_PRIVATE_FILES_DIR}]: " PRIVATE_FILES
    PRIVATE_FILES=${PRIVATE_FILES:-$DEFAULT_PRIVATE_FILES_DIR}

    # Try to create the directory if it doesn't exist
    if [ ! -d "$PRIVATE_FILES" ]; then
        print_status "Creating private files directory: $PRIVATE_FILES"
        if mkdir -p "$PRIVATE_FILES" 2>/dev/null; then
            print_status "Private files directory created successfully"
            break
        else
            print_error "Failed to create directory: $PRIVATE_FILES"
            echo "Please enter a different path or create the directory manually"
            DEFAULT_PRIVATE_FILES_DIR="$PRIVATE_FILES"  # Keep their last attempt as default
            continue
        fi
    fi

    # Check if directory is writable
    if [ -w "$PRIVATE_FILES" ]; then
        print_status "Private files directory is writable"
        break
    else
        print_error "Directory exists but is not writable: $PRIVATE_FILES"
        echo "Please fix permissions or choose a different directory"
        DEFAULT_PRIVATE_FILES_DIR="$PRIVATE_FILES"
    fi
done

# Generate hash salt
print_status "Generating secure hash salt..."
HASH_SALT=$(openssl rand -base64 32)

echo ""
echo "Generated credentials:"
echo "Database Password: $DB_PASSWORD"
echo "Hash Salt: $HASH_SALT"
echo ""
print_warning "Please save these credentials securely!"
echo ""

read -p "Press Enter to continue with setup..."

# 2. Install Composer dependencies
print_status "Installing Composer dependencies..."
# Suppress PHP 8.4 deprecation warnings while keeping other output
COMPOSER_PROCESS_TIMEOUT=600 composer install --no-dev --optimize-autoloader 2>&1 | \
    grep -v "Deprecation Notice:" | \
    grep -v "Implicitly marking parameter" | \
    grep -v "Constant E_STRICT is deprecated"

# 3. Create database and user (if requested)
if [ "$DB_SETUP_OPTION" = "1" ]; then
    echo ""
    echo "Creating database and user..."
    echo "Please enter MySQL root password when prompted:"

    # Handle both localhost and IP connections
    if [ "$DB_HOST" = "localhost" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
        USER_HOST="127.0.0.1"
    else
        USER_HOST="%"  # Allow from any host for remote databases
    fi

    mysql -u root -p -h ${DB_HOST} -P ${DB_PORT} <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'${USER_HOST}' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'${USER_HOST}';
FLUSH PRIVILEGES;
EOF

    if [ $? -eq 0 ]; then
        print_status "Database and user created successfully"
    else
        print_error "Database setup failed"
        exit 1
    fi
else
    print_status "Using existing database and user"
    # Test connection
    echo ""
    echo "Testing database connection..."
    mysql -u ${DB_USER} -p${DB_PASSWORD} -h ${DB_HOST} -P ${DB_PORT} -e "SELECT 1" ${DB_NAME} > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        print_status "Database connection successful"
    else
        print_error "Cannot connect to database. Please check your credentials."
        exit 1
    fi
fi

# 4. Import database backup (if available)
# Look for backup files in backups/[domain]/ structure
BACKUP_DB_FILE=""
if [ "$BACKUP_AVAILABLE" = "true" ] && [ -n "${BACKUP_SOURCE}" ] && [ -n "$BACKUP_DIR" ]; then
    # Look for MySQL backup file in backups/[domain]/
    BACKUP_DB_DIR="${BACKUP_DIR}/${BACKUP_SOURCE}"
    if [ -d "$BACKUP_DB_DIR" ]; then
        # Find the most recent .mysql file
        BACKUP_DB_FILE=$(ls -t "$BACKUP_DB_DIR"/*.mysql 2>/dev/null | head -n 1)
    fi
fi

# Fallback to old backup structure if new structure not found
if [ -z "$BACKUP_DB_FILE" ] && [ -n "${BACKUP_SOURCE}" ]; then
    # Check for site-specific backups in old location
    for pattern in "backup/*${BACKUP_SOURCE}*.sql" "backup/*${BASE_DOMAIN}*.sql" "backup/backup*.sql"; do
        for file in $pattern; do
            if [ -f "$file" ]; then
                BACKUP_DB_FILE="$file"
                break 2
            fi
        done
    done
fi

if [ -n "$BACKUP_DB_FILE" ] && [ -f "$BACKUP_DB_FILE" ]; then
    echo ""
    echo "Found database backup: $BACKUP_DB_FILE"
    DEFAULT_IMPORT_BACKUP="${SAVED_IMPORT_BACKUP:-Y}"
    read -p "Import this backup? [$DEFAULT_IMPORT_BACKUP]: " IMPORT_BACKUP
    IMPORT_BACKUP=${IMPORT_BACKUP:-$DEFAULT_IMPORT_BACKUP}

    if [[ "$IMPORT_BACKUP" =~ ^[Yy]$ ]]; then
        print_status "Importing database backup..."
        mysql -u ${DB_USER} -p${DB_PASSWORD} -h ${DB_HOST} -P ${DB_PORT} ${DB_NAME} < ${BACKUP_DB_FILE}
        if [ $? -eq 0 ]; then
            print_status "Database imported successfully"
        else
            print_error "Database import failed"
            exit 1
        fi
    else
        print_warning "Skipping database import - starting with empty database"
    fi
else
    print_warning "No database backup found - starting with empty database"
    echo "You can import a database later using:"
    echo "  mysql -u ${DB_USER} -p ${DB_NAME} < your-backup.mysql"
fi

# 5. Create required directories
print_status "Creating required directories..."
mkdir -p config/sync
mkdir -p ${PRIVATE_FILES}
mkdir -p web/sites/${SITE_DIR}/files/{css,js,php}

# 6. Copy sites directory from backup or create new
BACKUP_FILES_TAR=""
if [ "$BACKUP_AVAILABLE" = "true" ] && [ -n "${BACKUP_SOURCE}" ] && [ -n "$BACKUP_DIR" ]; then
    # Look for tar backup file in backups/[domain]/
    BACKUP_TAR_DIR="${BACKUP_DIR}/${BACKUP_SOURCE}"
    if [ -d "$BACKUP_TAR_DIR" ]; then
        # Find the most recent .tar file
        BACKUP_FILES_TAR=$(ls -t "$BACKUP_TAR_DIR"/*.tar 2>/dev/null | head -n 1)
    fi
fi

# Fallback to old backup structure if new structure not found
BACKUP_FILES_DIR=""
if [ -z "$BACKUP_FILES_TAR" ] && [ -n "${BACKUP_SOURCE}" ]; then
    # Look for backup files directory in old structure
    for dir in "backup/sites/${BACKUP_SOURCE}" "backup/sites/${BASE_DOMAIN}" "backup/sites/torvakool.edu.ee" "backup/sites/torva.edu.ee"; do
        if [ -d "$dir" ]; then
            BACKUP_FILES_DIR="$dir"
            break
        fi
    done
fi

if [ -n "$BACKUP_FILES_TAR" ] && [ -f "$BACKUP_FILES_TAR" ]; then
    echo ""
    echo "Found files backup: $BACKUP_FILES_TAR"
    DEFAULT_RESTORE_FILES="${SAVED_RESTORE_FILES:-Y}"
    read -p "Extract and restore files? [$DEFAULT_RESTORE_FILES]: " RESTORE_FILES
    RESTORE_FILES=${RESTORE_FILES:-$DEFAULT_RESTORE_FILES}

    if [[ "$RESTORE_FILES" =~ ^[Yy]$ ]]; then
        print_status "Extracting files from backup..."
        # The tar contains the files directory contents directly (no 'files' prefix)
        mkdir -p web/sites/${SITE_DIR}/files
        tar -xf "$BACKUP_FILES_TAR" -C web/sites/${SITE_DIR}/files/
        if [ $? -eq 0 ]; then
            print_status "Files restored successfully"
        else
            print_warning "Failed to extract some files - continuing anyway"
        fi
    else
        print_warning "Skipping files restoration"
    fi
elif [ -n "$BACKUP_FILES_DIR" ] && [ -d "$BACKUP_FILES_DIR/files" ]; then
    # Use old backup structure
    print_status "Restoring files from backup: $BACKUP_FILES_DIR"
    if [ "${SITE_DIR}" != "${BACKUP_SOURCE}" ]; then
        # If using different domain, copy backup to new location
        mkdir -p web/sites/${SITE_DIR}
        cp -r ${BACKUP_FILES_DIR}/files web/sites/${SITE_DIR}/
    else
        cp -r ${BACKUP_FILES_DIR}/files/* web/sites/${SITE_DIR}/files/
    fi
else
    print_warning "No files backup found - creating empty files directory"
fi

# 7. Create settings.php from template
print_status "Creating settings.php..."
# First check if we need to create the site directory
if [ ! -d "web/sites/${SITE_DIR}" ]; then
    mkdir -p web/sites/${SITE_DIR}
fi

# Copy template - use production template if it exists, otherwise use default
if [ -f "web/sites/torvakool.edu.ee/settings.production.php" ]; then
    cp web/sites/torvakool.edu.ee/settings.production.php web/sites/${SITE_DIR}/settings.php
else
    cp web/sites/default/default.settings.php web/sites/${SITE_DIR}/settings.php
    # Append database configuration to default settings
    cat >> web/sites/${SITE_DIR}/settings.php <<'SETTINGS_EOF'

/**
 * Database settings
 */
$databases['default']['default'] = [
  'database' => '[CHANGE_ME_DB_NAME]',
  'username' => '[CHANGE_ME_DB_USER]',
  'password' => '[CHANGE_ME_DB_PASSWORD]',
  'host' => '[CHANGE_ME_DB_HOST]',
  'port' => '[CHANGE_ME_DB_PORT]',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

$settings['hash_salt'] = '[CHANGE_ME_GENERATE_HASH_SALT]';
$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = '/var/www/private/torvakool';
SETTINGS_EOF
fi

# Replace placeholders in settings.php using PHP for better handling of special characters
php -r "
\$file = 'web/sites/${SITE_DIR}/settings.php';
\$content = file_get_contents(\$file);
\$content = str_replace('[CHANGE_ME_DB_HOST]', '${DB_HOST}', \$content);
\$content = str_replace('[CHANGE_ME_DB_PORT]', '${DB_PORT}', \$content);
\$content = str_replace('[CHANGE_ME_DB_NAME]', '${DB_NAME}', \$content);
\$content = str_replace('[CHANGE_ME_DB_USER]', '${DB_USER}', \$content);
\$content = str_replace('[CHANGE_ME_DB_PASSWORD]', '${DB_PASSWORD}', \$content);
\$content = str_replace('[CHANGE_ME_GENERATE_HASH_SALT]', addslashes('${HASH_SALT}'), \$content);
\$content = str_replace('/var/www/private/torvakool', '${PRIVATE_FILES}', \$content);
file_put_contents(\$file, \$content);
"

# Add environment-specific settings
cat >> web/sites/${SITE_DIR}/settings.php <<EOF

/**
 * Environment: ${ENVIRONMENT}
 */

// Trusted host patterns
\$settings['trusted_host_patterns'] = [
  '^${SITE_DOMAIN//./\\.}\$',
  '^www\\.${SITE_DOMAIN//./\\.}\$',
  '^localhost\$',
  '^127\\.0\\.0\\.1\$',
];

// Environment-specific performance settings
\$config['system.performance']['css']['preprocess'] = ${ENABLE_AGGREGATION};
\$config['system.performance']['js']['preprocess'] = ${ENABLE_AGGREGATION};
\$config['system.performance']['cache']['page']['max_age'] = ${CACHE_MAX_AGE};

// Error display settings
if ('${ENVIRONMENT}' === 'development') {
  \$config['system.logging']['error_level'] = 'verbose';
  error_reporting(E_ALL);
  ini_set('display_errors', TRUE);
  ini_set('display_startup_errors', TRUE);

  // Disable caching for development
  \$settings['cache']['bins']['render'] = 'cache.backend.null';
  \$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
  \$settings['cache']['bins']['page'] = 'cache.backend.null';
} else {
  \$config['system.logging']['error_level'] = 'hide';
  error_reporting(0);
  ini_set('display_errors', FALSE);
  ini_set('display_startup_errors', FALSE);
}

// Environment indicator
\$config['environment_indicator.indicator']['name'] = '${ENVIRONMENT}';
\$config['environment_indicator.indicator']['bg_color'] = ('${ENVIRONMENT}' === 'production') ? '#d40000' : (('${ENVIRONMENT}' === 'staging') ? '#ffa500' : '#5cb85c');
\$config['environment_indicator.indicator']['fg_color'] = '#ffffff';
EOF

# 8. Create sites.php for multi-site
print_status "Configuring multi-site..."
cat > web/sites/sites.php <<EOF
<?php
/**
 * Multi-site directory aliasing
 * Environment: ${ENVIRONMENT}
 * Primary domain: ${SITE_DOMAIN}
 */

// Main site configuration
\$sites['${SITE_DOMAIN}'] = '${SITE_DIR}';
\$sites['www.${SITE_DOMAIN}'] = '${SITE_DIR}';

// Local development aliases
if ('${ENVIRONMENT}' === 'development') {
  \$sites['localhost.${SITE_DIR}'] = '${SITE_DIR}';
  \$sites['127.0.0.1.${SITE_DIR}'] = '${SITE_DIR}';
  \$sites['${SITE_DIR}.localhost'] = '${SITE_DIR}';
  \$sites['${SITE_DIR}.local'] = '${SITE_DIR}';
  \$sites['${SITE_DIR}.lndo.site'] = '${SITE_DIR}';  // Lando
  \$sites['${SITE_DIR}.ddev.site'] = '${SITE_DIR}';  // DDEV
}
EOF

# 9. Copy .htaccess if it doesn't exist
if [ ! -f "web/.htaccess" ]; then
    print_status "Creating .htaccess..."
    cp web/core/assets/scaffold/files/htaccess web/.htaccess
fi

# 10. Set proper permissions
print_status "Setting file permissions..."

# Detect web server user
if [ "${ENVIRONMENT}" = "development" ]; then
    # For development, use current user
    WEB_USER=$(whoami)
else
    # Try to detect web server user
    if id -u www-data > /dev/null 2>&1; then
        WEB_USER="www-data"
    elif id -u apache > /dev/null 2>&1; then
        WEB_USER="apache"
    elif id -u nginx > /dev/null 2>&1; then
        WEB_USER="nginx"
    else
        # Detect OS and suggest appropriate default
        if [[ "$OSTYPE" == "darwin"* ]]; then
            DEFAULT_WEB_USER="_www"  # macOS default
        else
            DEFAULT_WEB_USER="www-data"  # Linux default
        fi
        read -p "Web server user [$DEFAULT_WEB_USER]: " WEB_USER
        WEB_USER=${WEB_USER:-$DEFAULT_WEB_USER}
    fi
fi

# Set ownership (only use sudo if not in development)
if [ "${ENVIRONMENT}" != "development" ]; then
    # Skip ownership changes if we're the web user (common in local dev)
    if [ "$(whoami)" != "${WEB_USER}" ]; then
        # Get the primary group of the web user
        if id -g ${WEB_USER} > /dev/null 2>&1; then
            WEB_GROUP=$(id -gn ${WEB_USER})
        else
            # If web user doesn't exist, use current user's group
            WEB_GROUP=$(id -gn)
        fi
        print_status "Setting ownership to $(whoami):${WEB_GROUP}..."
        sudo chown -R $(whoami):${WEB_GROUP} .
    else
        print_status "Skipping ownership change (already running as web user)"
    fi
    # Files directory needs to be writable by web server
    chmod -R 775 web/sites/${SITE_DIR}/files
    chmod -R 775 ${PRIVATE_FILES} 2>/dev/null || true
    # Protect settings file
    chmod 444 web/sites/${SITE_DIR}/settings.php
else
    # For development, just ensure directories are writable
    chmod -R 775 web/sites/${SITE_DIR}/files
    chmod -R 775 ${PRIVATE_FILES} 2>/dev/null || true
fi

# 11. Clear Drupal cache
print_status "Clearing Drupal cache..."
vendor/bin/drush cache:rebuild --uri=${SITE_DOMAIN} 2>&1 | grep -v "Deprecated:" | grep -v "Implicitly marking parameter"

# 12. Run database updates
print_status "Running database updates..."
vendor/bin/drush updatedb --uri=${SITE_DOMAIN} -y 2>&1 | grep -v "Deprecated:" | grep -v "Implicitly marking parameter"

# 13. Final security check (only for non-development)
if [ "${ENVIRONMENT}" != "development" ]; then
    print_status "Running security checks..."
    # Ensure update.php is not accessible
    echo "Disabling update.php access..."
    vendor/bin/drush state:set system.update_free_access 0 --uri=${SITE_DOMAIN} 2>&1 | grep -v "Deprecated:" | grep -v "Implicitly marking parameter"
fi

echo ""
echo "========================================="
# Capitalize environment name in a portable way
ENV_DISPLAY=$(echo "$ENVIRONMENT" | awk '{ print toupper(substr($0, 1, 1)) substr($0, 2) }')
echo -e "${GREEN}${ENV_DISPLAY} setup completed successfully!${NC}"
echo "========================================="
echo ""
echo "Configuration Summary:"
echo "====================="
echo "Site: ${SITE_NAME}"
echo "Environment: ${ENVIRONMENT}"
echo "Site Domain: ${SITE_DOMAIN}"
echo "Site Directory: web/sites/${SITE_DIR}"
echo ""
echo "Database Configuration:"
echo "----------------------"
echo "Host: ${DB_HOST}"
echo "Port: ${DB_PORT}"
echo "Name: ${DB_NAME}"
echo "User: ${DB_USER}"
echo "Password: ${DB_PASSWORD}"
echo ""
echo "Security:"
echo "---------"
echo "Hash Salt: ${HASH_SALT}"
echo "Private Files: ${PRIVATE_FILES}"
echo ""
echo "Performance Settings:"
echo "--------------------"
echo "CSS/JS Aggregation: ${ENABLE_AGGREGATION}"
echo "Page Cache Max Age: ${CACHE_MAX_AGE} seconds"
echo "Error Display: ${ERROR_DISPLAY}"
echo ""
echo "Next steps:"
echo "-----------"
if [ "${ENVIRONMENT}" = "development" ]; then
    echo "1. Start your local web server"
    echo "2. Access the site at: http://${SITE_DOMAIN}"
    echo "3. If using MAMP/XAMPP, ensure document root points to: $(pwd)/web"
else
    echo "1. Configure your web server (Apache/Nginx) to point to: $(pwd)/web"
    echo "2. Ensure mod_rewrite (Apache) or equivalent (Nginx) is enabled"
    echo "3. Set up SSL certificate for HTTPS"
    echo "4. Configure cron for Drupal: "
    echo "   */15 * * * * cd $(pwd) && vendor/bin/drush cron --uri=${SITE_DOMAIN}"
fi
echo ""
echo "To test the installation:"
echo "   curl -I http://${SITE_DOMAIN}"
echo ""
print_warning "Remember to save the database password and hash salt securely!"
echo ""