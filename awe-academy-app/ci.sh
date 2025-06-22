#!/bin/bash

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuration
TEST_DB_NAME="awe_academy_test"
COVERAGE_THRESHOLD=80
QUALITY_THRESHOLD=85
REPORT_DIR="reports"
COVERAGE_DIR="$REPORT_DIR/coverage"
QUALITY_DIR="$REPORT_DIR/quality"

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

# Create directories
create_dirs() {
    mkdir -p "$REPORT_DIR"
    mkdir -p "$COVERAGE_DIR"
    mkdir -p "$QUALITY_DIR"
}

# Check code style
check_code_style() {
    print_status "Checking code style..."
    
    # PHP_CodeSniffer
    ./vendor/bin/phpcs --standard=PSR12 src tests modules || {
        print_error "Code style check failed"
    }
    
    # ESLint
    npm run lint || {
        print_error "JavaScript lint failed"
    }
}

# Static analysis
static_analysis() {
    print_status "Running static analysis..."
    
    # PHPStan
    ./vendor/bin/phpstan analyse src tests modules --level=max || {
        print_error "Static analysis failed"
    }
}

# Security check
security_check() {
    print_status "Running security checks..."
    
    # Composer security check
    composer audit || {
        print_error "Security vulnerabilities found in dependencies"
    }
    
    # npm audit
    npm audit || {
        print_warning "Security vulnerabilities found in npm packages"
    }
}

# Setup test environment
setup_test_env() {
    print_status "Setting up test environment..."
    
    # Create test database
    docker-compose exec -T db mysql -u root -p"${DB_ROOT_PASSWORD}" -e "DROP DATABASE IF EXISTS ${TEST_DB_NAME}; CREATE DATABASE ${TEST_DB_NAME};"
    
    # Run migrations for test database
    DB_DATABASE="${TEST_DB_NAME}" php artisan migrate:fresh --env=testing || {
        print_error "Test database setup failed"
    }
}

# Run tests
run_tests() {
    print_status "Running tests..."
    
    # PHPUnit tests with coverage
    ./vendor/bin/phpunit --coverage-html="$COVERAGE_DIR" \
                        --coverage-clover="$COVERAGE_DIR/clover.xml" \
                        --log-junit="$COVERAGE_DIR/junit.xml" || {
        print_error "Tests failed"
    }
    
    # JavaScript tests
    npm test || {
        print_error "JavaScript tests failed"
    }
}

# Check test coverage
check_coverage() {
    print_status "Checking test coverage..."
    
    coverage=$(php coverage-checker.php "$COVERAGE_DIR/clover.xml" "$COVERAGE_THRESHOLD")
    if [ "$coverage" -lt "$COVERAGE_THRESHOLD" ]; then
        print_error "Test coverage ($coverage%) is below threshold ($COVERAGE_THRESHOLD%)"
    fi
}

# Build assets
build_assets() {
    print_status "Building assets..."
    
    npm run production || {
        print_error "Asset build failed"
    }
}

# Check build size
check_build_size() {
    print_status "Checking build size..."
    
    # Check JavaScript bundle size
    js_size=$(find public/js -type f -name "*.js" -exec du -ch {} + | grep total$ | cut -f1)
    if [[ ${js_size%M} -gt 2 ]]; then
        print_warning "JavaScript bundle size ($js_size) exceeds 2M"
    fi
    
    # Check CSS bundle size
    css_size=$(find public/css -type f -name "*.css" -exec du -ch {} + | grep total$ | cut -f1)
    if [[ ${css_size%M} -gt 1 ]]; then
        print_warning "CSS bundle size ($css_size) exceeds 1M"
    fi
}

# Generate documentation
generate_docs() {
    print_status "Generating documentation..."
    
    # PHP documentation
    ./vendor/bin/phpDocumentor -d src,modules -t "$REPORT_DIR/docs/php"
    
    # JavaScript documentation
    npm run docs
}

# Cleanup
cleanup() {
    print_status "Cleaning up..."
    
    # Remove test database
    docker-compose exec -T db mysql -u root -p"${DB_ROOT_PASSWORD}" -e "DROP DATABASE IF EXISTS ${TEST_DB_NAME};"
    
    # Clean temporary files
    rm -rf .phpunit.result.cache
    rm -rf .phpunit.cache
}

# Main CI process
main() {
    echo -e "${GREEN}Starting CI process${NC}"
    echo "===================="
    
    # Create directories
    create_dirs
    
    # Code quality checks
    check_code_style
    static_analysis
    security_check
    
    # Testing
    setup_test_env
    run_tests
    check_coverage
    
    # Build and size checks
    build_assets
    check_build_size
    
    # Documentation
    generate_docs
    
    # Cleanup
    cleanup
    
    echo -e "\n${GREEN}CI process completed successfully!${NC}"
    echo "Check $REPORT_DIR for detailed reports"
}

# Run CI
main

exit 0
