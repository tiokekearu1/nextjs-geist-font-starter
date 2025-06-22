#!/bin/bash

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuration
DEPLOY_USER="deploy"
DEPLOY_PATH="/var/www/awe-academy"
REPOSITORY="git@github.com:awe-academy/management-system.git"
BRANCH="main"
KEEP_RELEASES=5

# Timestamp for release directory
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RELEASE_PATH="$DEPLOY_PATH/releases/$TIMESTAMP"

# Function to print status messages
print_status() {
    echo -e "${GREEN}==>${NC} $1"
}

# Function to print error messages
print_error() {
    echo -e "${RED}Error:${NC} $1"
    exit 1
}

# Function to print warning messages
print_warning() {
    echo -e "${YELLOW}Warning:${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Please run as root"
fi

# Create deployment structure
create_structure() {
    print_status "Creating deployment structure..."
    mkdir -p "$DEPLOY_PATH/releases"
    mkdir -p "$DEPLOY_PATH/shared/storage"
    mkdir -p "$DEPLOY_PATH/shared/uploads"
    mkdir -p "$DEPLOY_PATH/shared/.env"
    mkdir -p "$DEPLOY_PATH/backup"
}

# Backup current version
backup_current() {
    if [ -d "$DEPLOY_PATH/current" ]; then
        print_status "Backing up current version..."
        cp -r "$DEPLOY_PATH/current" "$DEPLOY_PATH/backup/backup_$(date +%Y%m%d_%H%M%S)"
    fi
}

# Clone new release
clone_release() {
    print_status "Cloning repository..."
    git clone -b "$BRANCH" "$REPOSITORY" "$RELEASE_PATH"
    if [ $? -ne 0 ]; then
        print_error "Failed to clone repository"
    fi
}

# Install dependencies
install_dependencies() {
    print_status "Installing dependencies..."
    cd "$RELEASE_PATH" || exit

    print_status "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    if [ $? -ne 0 ]; then
        print_error "Failed to install Composer dependencies"
    fi

    print_status "Installing NPM dependencies..."
    npm ci
    if [ $? -ne 0 ]; then
        print_error "Failed to install NPM dependencies"
    fi

    print_status "Building assets..."
    npm run production
    if [ $? -ne 0 ]; then
        print_error "Failed to build assets"
    fi
}

# Create symlinks
create_symlinks() {
    print_status "Creating symlinks..."
    ln -nfs "$DEPLOY_PATH/shared/storage" "$RELEASE_PATH/storage"
    ln -nfs "$DEPLOY_PATH/shared/uploads" "$RELEASE_PATH/public/uploads"
    ln -nfs "$DEPLOY_PATH/shared/.env" "$RELEASE_PATH/.env"
}

# Update permissions
update_permissions() {
    print_status "Updating permissions..."
    chown -R $DEPLOY_USER:www-data "$RELEASE_PATH"
    chmod -R 755 "$RELEASE_PATH"
    chmod -R 777 "$DEPLOY_PATH/shared/storage"
    chmod -R 777 "$DEPLOY_PATH/shared/uploads"
}

# Activate new release
activate_release() {
    print_status "Activating new release..."
    ln -nfs "$RELEASE_PATH" "$DEPLOY_PATH/current"
}

# Clear cache
clear_cache() {
    print_status "Clearing cache..."
    rm -rf "$DEPLOY_PATH/shared/storage/cache/*"
    rm -rf "$DEPLOY_PATH/shared/storage/views/*"
}

# Clean old releases
clean_old_releases() {
    print_status "Cleaning old releases..."
    cd "$DEPLOY_PATH/releases" || exit
    ls -t | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf
}

# Maintenance mode
maintenance_mode() {
    if [ "$1" = "on" ]; then
        print_status "Enabling maintenance mode..."
        touch "$DEPLOY_PATH/current/storage/framework/down"
    else
        print_status "Disabling maintenance mode..."
        rm -f "$DEPLOY_PATH/current/storage/framework/down"
    fi
}

# Database migrations
run_migrations() {
    print_status "Running database migrations..."
    cd "$RELEASE_PATH" || exit
    php artisan migrate --force
    if [ $? -ne 0 ]; then
        print_error "Failed to run migrations"
    fi
}

# Main deployment process
main() {
    echo -e "${GREEN}Starting deployment process${NC}"
    echo "================================"

    # Enable maintenance mode
    maintenance_mode "on"

    # Create deployment structure
    create_structure

    # Backup current version
    backup_current

    # Clone new release
    clone_release

    # Install dependencies
    install_dependencies

    # Create symlinks
    create_symlinks

    # Update permissions
    update_permissions

    # Run migrations
    run_migrations

    # Clear cache
    clear_cache

    # Activate new release
    activate_release

    # Clean old releases
    clean_old_releases

    # Disable maintenance mode
    maintenance_mode "off"

    echo -e "\n${GREEN}Deployment completed successfully!${NC}"
}

# Run deployment
main

exit 0
