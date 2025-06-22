#!/bin/bash

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Function to print status messages
print_status() {
    echo -e "${GREEN}==>${NC} $1"
}

# Function to print error messages
print_error() {
    echo -e "${RED}Error:${NC} $1"
}

# Function to print warning messages
print_warning() {
    echo -e "${YELLOW}Warning:${NC} $1"
}

# Check if Docker is installed
check_docker() {
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    if ! command -v docker-compose &> /dev/null; then
        print_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    }
}

# Create necessary directories
create_directories() {
    print_status "Creating necessary directories..."
    mkdir -p storage/cache
    mkdir -p storage/logs
    mkdir -p storage/uploads
    mkdir -p storage/backups
    mkdir -p public/build
    mkdir -p public/uploads
    chmod -R 777 storage
    chmod -R 777 public/uploads
}

# Copy environment file
setup_env() {
    print_status "Setting up environment file..."
    if [ ! -f .env ]; then
        cp .env.example .env
        print_status "Environment file created. Please update the values in .env"
    else
        print_warning ".env file already exists. Skipping..."
    fi
}

# Generate application key
generate_key() {
    print_status "Generating application key..."
    APP_KEY=$(openssl rand -base64 32)
    sed -i "s/APP_KEY=.*/APP_KEY=$APP_KEY/" .env
}

# Start Docker containers
start_containers() {
    print_status "Starting Docker containers..."
    docker-compose up -d
    if [ $? -ne 0 ]; then
        print_error "Failed to start Docker containers"
        exit 1
    fi
}

# Install dependencies
install_dependencies() {
    print_status "Installing PHP dependencies..."
    docker-compose exec app composer install
    if [ $? -ne 0 ]; then
        print_error "Failed to install PHP dependencies"
        exit 1
    fi

    print_status "Installing Node.js dependencies..."
    docker-compose exec node npm install
    if [ $? -ne 0 ]; then
        print_error "Failed to install Node.js dependencies"
        exit 1
    fi
}

# Initialize database
init_database() {
    print_status "Initializing database..."
    sleep 10 # Wait for MySQL to be ready
    docker-compose exec db mysql -u root -p${DB_ROOT_PASSWORD} -e "CREATE DATABASE IF NOT EXISTS ${DB_DATABASE}"
    docker-compose exec db mysql -u root -p${DB_ROOT_PASSWORD} ${DB_DATABASE} < database/schema.sql
    if [ $? -ne 0 ]; then
        print_error "Failed to initialize database"
        exit 1
    fi
}

# Build assets
build_assets() {
    print_status "Building assets..."
    docker-compose exec node npm run production
    if [ $? -ne 0 ]; then
        print_error "Failed to build assets"
        exit 1
    fi
}

# Main setup process
main() {
    echo -e "${GREEN}Starting AWE Academy Management System Setup${NC}"
    echo "=================================================="

    # Check prerequisites
    check_docker

    # Create directories
    create_directories

    # Setup environment
    setup_env

    # Generate key
    generate_key

    # Start containers
    start_containers

    # Install dependencies
    install_dependencies

    # Initialize database
    init_database

    # Build assets
    build_assets

    echo -e "\n${GREEN}Setup completed successfully!${NC}"
    echo -e "You can now access the application at: http://localhost:8000"
    echo -e "Admin credentials:"
    echo -e "Username: ${YELLOW}admin${NC}"
    echo -e "Password: ${YELLOW}admin123${NC}"
    echo -e "\n${YELLOW}Important:${NC} Please change the admin password after first login!"
}

# Run setup
main

exit 0
