#!/bin/bash

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuration
LOG_FILE="storage/logs/monitor.log"
ALERT_EMAIL="admin@aweacademy.com"
DISK_THRESHOLD=90
MEMORY_THRESHOLD=90
LOAD_THRESHOLD=5
DB_CONNECTIONS_THRESHOLD=100
PHP_PROCESSES_THRESHOLD=50
NGINX_CONNECTIONS_THRESHOLD=1000

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# Function to send email alerts
send_alert() {
    local subject="$1"
    local message="$2"
    echo "$message" | mail -s "AWE Academy Alert: $subject" "$ALERT_EMAIL"
}

# Check Docker containers status
check_containers() {
    echo -e "${GREEN}Checking Docker containers...${NC}"
    
    docker-compose ps | while read line; do
        if [[ $line == *"Exit"* ]] || [[ $line == *"Restarting"* ]]; then
            message="Container issue detected: $line"
            log_message "$message"
            send_alert "Container Issue" "$message"
        fi
    done
    
    # Check container resource usage
    docker stats --no-stream | while read line; do
        if [[ $line == *"90.00%"* ]]; then
            message="High resource usage detected: $line"
            log_message "$message"
            send_alert "Resource Usage Alert" "$message"
        fi
    done
}

# Check disk space
check_disk_space() {
    echo -e "${GREEN}Checking disk space...${NC}"
    
    df -h | grep -vE '^Filesystem|tmpfs|cdrom' | awk '{ print $5 " " $1 }' | while read output; do
        usage=$(echo "$output" | awk '{ print $1}' | cut -d'%' -f1)
        partition=$(echo "$output" | awk '{ print $2 }')
        
        if [ "$usage" -ge "$DISK_THRESHOLD" ]; then
            message="Disk space alert: $partition ($usage%)"
            log_message "$message"
            send_alert "Disk Space Alert" "$message"
        fi
    done
}

# Check memory usage
check_memory() {
    echo -e "${GREEN}Checking memory usage...${NC}"
    
    memory_usage=$(free | grep Mem | awk '{print $3/$2 * 100.0}')
    if [ "$(echo "$memory_usage > $MEMORY_THRESHOLD" | bc)" -eq 1 ]; then
        message="High memory usage: ${memory_usage}%"
        log_message "$message"
        send_alert "Memory Usage Alert" "$message"
    fi
}

# Check system load
check_system_load() {
    echo -e "${GREEN}Checking system load...${NC}"
    
    load=$(uptime | awk -F'load average:' '{ print $2 }' | cut -d, -f1)
    if [ "$(echo "$load > $LOAD_THRESHOLD" | bc)" -eq 1 ]; then
        message="High system load: $load"
        log_message "$message"
        send_alert "System Load Alert" "$message"
    fi
}

# Check MySQL connections
check_mysql() {
    echo -e "${GREEN}Checking MySQL connections...${NC}"
    
    connections=$(docker-compose exec -T db mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SHOW STATUS WHERE Variable_name = 'Threads_connected';" | awk 'NR==2{print $2}')
    if [ "$connections" -gt "$DB_CONNECTIONS_THRESHOLD" ]; then
        message="High number of database connections: $connections"
        log_message "$message"
        send_alert "Database Connections Alert" "$message"
    fi
}

# Check PHP-FPM processes
check_php_fpm() {
    echo -e "${GREEN}Checking PHP-FPM processes...${NC}"
    
    processes=$(docker-compose exec -T app ps aux | grep php-fpm | wc -l)
    if [ "$processes" -gt "$PHP_PROCESSES_THRESHOLD" ]; then
        message="High number of PHP-FPM processes: $processes"
        log_message "$message"
        send_alert "PHP-FPM Alert" "$message"
    fi
}

# Check Nginx connections
check_nginx() {
    echo -e "${GREEN}Checking Nginx connections...${NC}"
    
    connections=$(docker-compose exec -T app nginx -v 2>&1 | grep connections)
    if [ "$connections" -gt "$NGINX_CONNECTIONS_THRESHOLD" ]; then
        message="High number of Nginx connections: $connections"
        log_message "$message"
        send_alert "Nginx Connections Alert" "$message"
    fi
}

# Check application logs for errors
check_error_logs() {
    echo -e "${GREEN}Checking application logs...${NC}"
    
    errors=$(tail -n 1000 storage/logs/error.log | grep -i "error\|exception\|fatal" | wc -l)
    if [ "$errors" -gt 0 ]; then
        message="Found $errors errors in application logs"
        log_message "$message"
        send_alert "Application Error Alert" "$message"
    fi
}

# Check SSL certificate expiry
check_ssl() {
    echo -e "${GREEN}Checking SSL certificate...${NC}"
    
    if [ -f "/etc/ssl/certs/aweacademy.crt" ]; then
        expiry_date=$(openssl x509 -enddate -noout -in /etc/ssl/certs/aweacademy.crt | cut -d= -f2)
        expiry_epoch=$(date -d "$expiry_date" +%s)
        current_epoch=$(date +%s)
        days_left=$(( ($expiry_epoch - $current_epoch) / 86400 ))
        
        if [ "$days_left" -lt 30 ]; then
            message="SSL certificate will expire in $days_left days"
            log_message "$message"
            send_alert "SSL Certificate Alert" "$message"
        fi
    fi
}

# Check backup status
check_backups() {
    echo -e "${GREEN}Checking backup status...${NC}"
    
    latest_backup=$(ls -t backups/database | head -1)
    if [ -n "$latest_backup" ]; then
        backup_time=$(stat -c %Y "backups/database/$latest_backup")
        current_time=$(date +%s)
        hours_old=$(( ($current_time - $backup_time) / 3600 ))
        
        if [ "$hours_old" -gt 24 ]; then
            message="No recent backup found. Latest backup is $hours_old hours old"
            log_message "$message"
            send_alert "Backup Alert" "$message"
        fi
    else
        message="No backups found"
        log_message "$message"
        send_alert "Backup Alert" "$message"
    fi
}

# Main monitoring process
main() {
    echo -e "${GREEN}Starting system monitoring${NC}"
    echo "=========================="
    
    # Create log file if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    touch "$LOG_FILE"
    
    # Run checks
    check_containers
    check_disk_space
    check_memory
    check_system_load
    check_mysql
    check_php_fpm
    check_nginx
    check_error_logs
    check_ssl
    check_backups
    
    echo -e "\n${GREEN}Monitoring completed successfully!${NC}"
    echo "Check $LOG_FILE for details"
}

# Run monitoring
main

exit 0
