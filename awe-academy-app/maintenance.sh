#!/bin/bash

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuration
BACKUP_DIR="backups/database"
LOG_DIR="storage/logs"
DAYS_TO_KEEP_BACKUPS=30
DAYS_TO_KEEP_LOGS=7
MAX_LOG_SIZE_MB=100

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

# Create backup directory if it doesn't exist
create_dirs() {
    mkdir -p "$BACKUP_DIR"
    mkdir -p "$LOG_DIR"
    chmod 755 "$BACKUP_DIR"
    chmod 755 "$LOG_DIR"
}

# Database backup
backup_database() {
    print_status "Creating database backup..."
    
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_FILE="$BACKUP_DIR/backup_$TIMESTAMP.sql.gz"
    
    # Get database credentials from .env
    if [ -f .env ]; then
        source .env
    else
        print_error ".env file not found"
    fi

    # Create backup
    docker-compose exec -T db mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > "$BACKUP_FILE"
    
    if [ $? -eq 0 ]; then
        print_status "Backup created: $BACKUP_FILE"
    else
        print_error "Backup failed"
    fi
}

# Clean old backups
clean_old_backups() {
    print_status "Cleaning old backups..."
    find "$BACKUP_DIR" -type f -name "backup_*.sql.gz" -mtime +$DAYS_TO_KEEP_BACKUPS -delete
}

# Clean old logs
clean_old_logs() {
    print_status "Cleaning old logs..."
    find "$LOG_DIR" -type f -name "*.log" -mtime +$DAYS_TO_KEEP_LOGS -delete
}

# Rotate large log files
rotate_logs() {
    print_status "Checking log sizes..."
    
    find "$LOG_DIR" -type f -name "*.log" | while read -r log_file; do
        size_mb=$(du -m "$log_file" | cut -f1)
        if [ "$size_mb" -gt "$MAX_LOG_SIZE_MB" ]; then
            print_warning "Rotating large log file: $log_file"
            mv "$log_file" "${log_file}.1"
            touch "$log_file"
            chmod 644 "$log_file"
        fi
    done
}

# Check disk space
check_disk_space() {
    print_status "Checking disk space..."
    
    DISK_USAGE=$(df -h / | tail -1 | awk '{print $5}' | sed 's/%//')
    if [ "$DISK_USAGE" -gt 90 ]; then
        print_warning "Disk usage is at ${DISK_USAGE}%"
    fi
}

# Check system resources
check_resources() {
    print_status "Checking system resources..."
    
    # Check memory usage
    FREE_MEM=$(free | grep Mem | awk '{print $4/$2 * 100.0}')
    if [ "$(echo "$FREE_MEM < 20" | bc)" -eq 1 ]; then
        print_warning "Low memory: ${FREE_MEM}% free"
    fi
    
    # Check CPU load
    LOAD=$(uptime | awk -F'load average:' '{ print $2 }' | cut -d, -f1)
    if [ "$(echo "$LOAD > 2" | bc)" -eq 1 ]; then
        print_warning "High CPU load: $LOAD"
    fi
}

# Optimize database
optimize_database() {
    print_status "Optimizing database..."
    
    docker-compose exec -T db mysqlcheck -u "$DB_USERNAME" -p"$DB_PASSWORD" --optimize "$DB_DATABASE"
    if [ $? -ne 0 ]; then
        print_warning "Database optimization failed"
    fi
}

# Clear application cache
clear_cache() {
    print_status "Clearing application cache..."
    
    rm -rf storage/cache/*
    rm -rf storage/views/*
    
    if [ -f storage/framework/down ]; then
        print_warning "Application is in maintenance mode"
    fi
}

# Check file permissions
check_permissions() {
    print_status "Checking file permissions..."
    
    # Check storage directory permissions
    if [ ! -w "storage" ]; then
        print_warning "Storage directory is not writable"
        chmod -R 755 storage
    fi
    
    # Check uploads directory permissions
    if [ ! -w "public/uploads" ]; then
        print_warning "Uploads directory is not writable"
        chmod -R 755 public/uploads
    fi
}

# Main maintenance process
main() {
    echo -e "${GREEN}Starting maintenance tasks${NC}"
    echo "=========================="
    
    # Create necessary directories
    create_dirs
    
    # Backup database
    backup_database
    
    # Clean old files
    clean_old_backups
    clean_old_logs
    
    # Rotate logs
    rotate_logs
    
    # System checks
    check_disk_space
    check_resources
    check_permissions
    
    # Database maintenance
    optimize_database
    
    # Application maintenance
    clear_cache
    
    echo -e "\n${GREEN}Maintenance completed successfully!${NC}"
}

# Run maintenance
main

exit 0
