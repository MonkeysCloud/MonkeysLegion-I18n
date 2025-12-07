#!/bin/bash

# Package Setup and Test Verification Script
# Run this if you're having issues with tests not running

set +e  # Don't exit on error

echo "======================================================"
echo "MonkeysLegion I18n - Package Setup & Test Verification"
echo "======================================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}Step 1: Checking prerequisites...${NC}"
echo ""

# Check composer
if ! command -v composer &> /dev/null; then
    echo -e "${RED}✗ Composer not found${NC}"
    echo "  Install from: https://getcomposer.org/"
    exit 1
fi
echo -e "${GREEN}✓ Composer found${NC}"

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo -e "${GREEN}✓ PHP version: ${PHP_VERSION}${NC}"

if [[ $(php -r "echo version_compare(PHP_VERSION, '8.4.0', '>=') ? 1 : 0;") != "1" ]]; then
    echo -e "${YELLOW}⚠ Warning: PHP 8.4+ recommended${NC}"
fi

# Check required extensions
echo ""
echo -e "${BLUE}Step 2: Checking PHP extensions...${NC}"
echo ""

if php -m | grep -q "json"; then
    echo -e "${GREEN}✓ ext-json${NC}"
else
    echo -e "${RED}✗ ext-json (required)${NC}"
fi

if php -m | grep -q "mbstring"; then
    echo -e "${GREEN}✓ ext-mbstring${NC}"
else
    echo -e "${RED}✗ ext-mbstring (required)${NC}"
fi

# Check if we're in the right directory
echo ""
echo -e "${BLUE}Step 3: Verifying directory structure...${NC}"
echo ""

if [ ! -f "composer.json" ]; then
    echo -e "${RED}✗ composer.json not found${NC}"
    echo "  Are you in the package root directory?"
    exit 1
fi
echo -e "${GREEN}✓ composer.json found${NC}"

if [ ! -f "phpunit.xml.dist" ]; then
    echo -e "${RED}✗ phpunit.xml.dist not found${NC}"
    exit 1
fi
echo -e "${GREEN}✓ phpunit.xml.dist found${NC}"

if [ ! -d "tests" ]; then
    echo -e "${RED}✗ tests/ directory not found${NC}"
    exit 1
fi
echo -e "${GREEN}✓ tests/ directory found${NC}"

# Check for test files
TEST_COUNT=$(find tests -name "*Test.php" -type f | wc -l | tr -d ' ')
echo -e "${GREEN}✓ Found ${TEST_COUNT} test files${NC}"

# Clean and install
echo ""
echo -e "${BLUE}Step 4: Installing dependencies...${NC}"
echo ""

if [ -d "vendor" ]; then
    echo "  Removing old vendor directory..."
    rm -rf vendor
fi

if [ -f "composer.lock" ]; then
    echo "  Removing composer.lock..."
    rm -f composer.lock
fi

if [ -d ".phpunit.cache" ]; then
    echo "  Removing PHPUnit cache..."
    rm -rf .phpunit.cache
fi

echo "  Running composer install..."
composer install --quiet --no-interaction

if [ ! -f "vendor/autoload.php" ]; then
    echo -e "${RED}✗ vendor/autoload.php not created${NC}"
    echo "  Composer install failed"
    exit 1
fi
echo -e "${GREEN}✓ Dependencies installed${NC}"

# Verify PHPUnit
echo ""
echo -e "${BLUE}Step 5: Verifying PHPUnit...${NC}"
echo ""

if [ ! -f "vendor/bin/phpunit" ]; then
    echo -e "${RED}✗ PHPUnit not found in vendor/bin/${NC}"
    exit 1
fi

PHPUNIT_VERSION=$(vendor/bin/phpunit --version | head -n 1)
echo -e "${GREEN}✓ ${PHPUNIT_VERSION}${NC}"

# List tests
echo ""
echo -e "${BLUE}Step 6: Discovering tests...${NC}"
echo ""

echo "  Listing tests..."
TEST_LIST=$(vendor/bin/phpunit --list-tests 2>&1)

if echo "$TEST_LIST" | grep -q "No tests executed"; then
    echo -e "${RED}✗ No tests discovered${NC}"
    echo ""
    echo "Debugging information:"
    echo "  - Test files found: ${TEST_COUNT}"
    echo "  - Autoload test namespace defined: $(grep -c 'MonkeysLegion.*Tests' composer.json)"
    echo ""
    echo "Trying to regenerate autoload..."
    composer dump-autoload -o
    
    echo "Trying again..."
    TEST_LIST=$(vendor/bin/phpunit --list-tests 2>&1)
fi

DISCOVERED_TESTS=$(echo "$TEST_LIST" | grep -c "Test '" || echo "0")

if [ "$DISCOVERED_TESTS" -gt "0" ]; then
    echo -e "${GREEN}✓ Discovered ${DISCOVERED_TESTS} tests${NC}"
else
    echo -e "${YELLOW}⚠ Could not determine test count${NC}"
fi

# Run tests
echo ""
echo -e "${BLUE}Step 7: Running tests...${NC}"
echo ""

vendor/bin/phpunit --colors=always

TEST_RESULT=$?

echo ""
echo "======================================================"

if [ $TEST_RESULT -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed!${NC}"
    echo "======================================================"
    echo ""
    echo "Available commands:"
    echo "  composer test          - Run all tests"
    echo "  composer test-debug    - Run with debug output"
    echo "  composer test-coverage - Generate coverage report"
    echo "  composer phpstan       - Run static analysis"
    echo "  composer quality       - Run both PHPStan and tests"
    echo "  composer check-tests   - List all tests"
else
    echo -e "${RED}❌ Tests failed or could not run${NC}"
    echo "======================================================"
    echo ""
    echo "Troubleshooting:"
    echo "  1. Check TROUBLESHOOTING_TESTS.md"
    echo "  2. Run: composer test-debug"
    echo "  3. Run: composer check-tests"
    echo ""
    echo "Common fixes:"
    echo "  - rm -rf vendor .phpunit.cache && composer install"
    echo "  - composer dump-autoload -o"
    echo "  - vendor/bin/phpunit --debug tests/"
fi

echo ""
exit $TEST_RESULT
