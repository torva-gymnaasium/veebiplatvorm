#!/bin/bash

# HTM HVP Setup Test Script
# This script safely tests the setup.sh script by cleaning and re-running it

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
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

echo "========================================="
echo "HTM HVP Setup Test Script"
echo "========================================="
echo ""
print_warning "This script will:"
echo "  1. Save your backups and configuration"
echo "  2. Clean the entire repository (git clean -fxd)"
echo "  3. Restore your backups and configuration"
echo "  4. Run the setup.sh script"
echo ""
print_warning "This will DELETE all uncommitted changes and generated files!"
echo ""

# Safety confirmation
read -p "Are you sure you want to continue? Type 'yes' to proceed: " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    print_error "Aborted. No changes were made."
    exit 1
fi

# Double confirmation for safety
echo ""
print_warning "FINAL WARNING: This will destroy all local data except git commits!"
read -p "Type 'DELETE' to confirm: " FINAL_CONFIRM
if [ "$FINAL_CONFIRM" != "DELETE" ]; then
    print_error "Aborted. No changes were made."
    exit 1
fi

echo ""
print_status "Starting test process..."

# Check for uncommitted changes
if ! git diff --quiet || ! git diff --cached --quiet; then
    print_warning "You have uncommitted changes:"
    git status --short
    echo ""
    read -p "Do you want to commit them first? [y/N]: " COMMIT_FIRST
    if [[ "$COMMIT_FIRST" =~ ^[Yy]$ ]]; then
        read -p "Enter commit message: " COMMIT_MSG
        git add -A
        git commit -m "$COMMIT_MSG"
        print_status "Changes committed"
    else
        print_warning "Proceeding without committing changes (they will be lost)"
    fi
fi

# Save important files
TEMP_DIR="/tmp/hvp-test-$$"
mkdir -p "$TEMP_DIR"

print_status "Saving important files to $TEMP_DIR..."

# Save backups if they exist
if [ -d "backups" ]; then
    print_info "Saving backups directory..."
    cp -r backups "$TEMP_DIR/"
fi

# Save .setup.sh configuration if it exists
if [ -f ".setup.sh" ]; then
    print_info "Saving .setup.sh configuration..."
    cp .setup.sh "$TEMP_DIR/"
fi

# Save private directory if it exists
if [ -d "private" ]; then
    print_info "Saving private directory..."
    cp -r private "$TEMP_DIR/"
fi

# Clean the repository
print_status "Cleaning repository..."
git clean -fxd

# Remove any remaining directories
if [ -d "web/sites" ]; then
    rm -rf web/sites
fi

if [ -d "vendor" ]; then
    rm -rf vendor
fi

if [ -d "private" ]; then
    rm -rf private
fi

print_status "Repository cleaned"

# Restore saved files
print_status "Restoring saved files..."

if [ -d "$TEMP_DIR/backups" ]; then
    print_info "Restoring backups..."
    cp -r "$TEMP_DIR/backups" .
fi

if [ -f "$TEMP_DIR/.setup.sh" ]; then
    print_info "Restoring .setup.sh configuration..."
    cp "$TEMP_DIR/.setup.sh" .
fi

# Note: Not restoring private directory as setup.sh should create it fresh

# Clean up temp directory
rm -rf "$TEMP_DIR"

print_status "Files restored"

# Check if setup.sh exists
if [ ! -f "setup.sh" ]; then
    print_error "setup.sh not found!"
    exit 1
fi

# Make setup.sh executable
chmod +x setup.sh

echo ""
echo "========================================="
echo "Ready to run setup.sh"
echo "========================================="
echo ""
print_info "Your previous settings are loaded from .setup.sh"
print_info "You can press Enter to accept defaults for most prompts"
echo ""

# Run setup.sh
./setup.sh

echo ""
print_status "Test completed!"
echo ""
echo "If everything worked correctly, you should now have:"
echo "  - Drupal installed and configured"
echo "  - Database imported from backup"
echo "  - Files restored from backup"
echo "  - Site accessible at your configured domain"