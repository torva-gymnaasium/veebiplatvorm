#!/bin/bash
# HTM HVP Setup Configuration File
# This file stores your previous choices for quick re-run
# Delete or comment out any line to be prompted for that value

# Site Configuration
SAVED_SITE_SELECTION="1"                    # Which backup to use (1 for first found, or domain name)
SAVED_SITE_NAME="Tõrva Kool"               # Institution name
SAVED_BASE_DOMAIN="torvakool.edu.ee"       # Production domain

# Environment Configuration
SAVED_ENV_TYPE="1"                         # 1=Development, 2=Staging, 3=Production
SAVED_SITE_DOMAIN=""                        # Leave empty to use default based on environment

# Database Configuration
SAVED_DB_HOST="127.0.0.1"                  # Database host
SAVED_DB_PORT="3306"                       # Database port
SAVED_DB_NAME="torvakooleduee"             # Database name
SAVED_DB_USER="torvakooleduee"             # Database username
SAVED_PASSWORD_OPTION="2"                  # 1=Generate password, 2=Enter own password
SAVED_DB_PASSWORD="torvakooleduee"         # Database password (only if option 2)
SAVED_DB_SETUP_OPTION="2"                  # 1=Create new database, 2=Use existing

# File Storage Configuration
SAVED_PRIVATE_FILES="/tmp/private"         # Private files directory

# Import Options
SAVED_IMPORT_BACKUP="Y"                    # Import database backup? (Y/n)
SAVED_RESTORE_FILES="Y"                    # Restore files from backup? (Y/n)