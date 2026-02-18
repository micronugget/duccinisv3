#!/bin/bash
################################################################################
# DDEV Command Template for AI Agents
# Purpose: Demonstrate reliable terminal command patterns for GitHub Copilot
# Usage: Source this file or copy patterns into your commands
################################################################################

# Enable strict error handling
set -e  # Exit on error
set -u  # Exit on undefined variable
set -o pipefail  # Catch errors in pipes

################################################################################
# HELPER FUNCTIONS
################################################################################

# Print a clear separator
print_separator() {
  echo "============================================================"
}

# Print operation header
operation_start() {
  local operation_name="$1"
  print_separator
  echo "OPERATION: $operation_name"
  echo "TIMESTAMP: $(date '+%Y-%m-%d %H:%M:%S')"
  print_separator
}

# Print operation result
operation_end() {
  local exit_code=$1
  local operation_name="$2"
  print_separator
  if [ "$exit_code" -eq 0 ]; then
    echo "✓ SUCCESS: $operation_name"
  else
    echo "✗ FAILED: $operation_name (Exit Code: $exit_code)"
  fi
  echo "COMPLETED: $(date '+%Y-%m-%d %H:%M:%S')"
  print_separator
  return "$exit_code"
}

# Run command with explicit success/failure reporting
run_with_status() {
  local description="$1"
  shift
  local cmd="$*"

  echo "==> $description"
  echo "    Command: $cmd"

  if eval "$cmd" 2>&1; then
    local exit_code=$?
    echo "    Status: ✓ Success (Exit Code: $exit_code)"
    return 0
  else
    local exit_code=$?
    echo "    Status: ✗ Failed (Exit Code: $exit_code)"
    return "$exit_code"
  fi
}

################################################################################
# PATTERN 1: SIMPLE DDEV COMMAND
################################################################################

pattern_simple_ddev_command() {
  echo "=== Starting DDEV ===" && \
  ddev start 2>&1 && \
  echo "=== DDEV Started: Exit Code $? ==="
}

################################################################################
# PATTERN 2: DDEV COMMAND WITH VERIFICATION
################################################################################

pattern_ddev_with_verification() {
  echo "=== Starting DDEV Environment ===" && \
  ddev start 2>&1 && \
  echo "=== Verifying Status ===" && \
  ddev describe | grep -E "NAME|STATUS|PHP" && \
  echo "=== Verification Complete ==="
}

################################################################################
# PATTERN 3: DRUSH COMMAND WITH CONFIG VERIFICATION
################################################################################

pattern_drush_config_import() {
  echo "=== Importing Configuration ===" && \
  ddev drush cim --yes 2>&1 && \
  EXIT_CODE=$? && \
  echo "=== Config Import Exit Code: $EXIT_CODE ===" && \
  if [ $EXIT_CODE -eq 0 ]; then
    echo "=== Verifying Drupal Status ===" && \
    ddev drush status | grep -E "Drupal version|Database"
  fi
}

################################################################################
# PATTERN 4: COMPOSER WITH OUTPUT LOGGING
################################################################################

pattern_composer_with_logging() {
  local package="${1:-drupal/admin_toolbar}"

  echo "=== Installing Composer Package: $package ===" && \
  ddev composer require "$package" 2>&1 | tee /tmp/composer-install.log && \
  EXIT_CODE=$? && \
  echo "=== Composer Exit Code: $EXIT_CODE ===" && \
  if [ $EXIT_CODE -eq 0 ]; then
    echo "=== Package Successfully Installed ==="
    echo "Last 10 lines of output:"
    tail -10 /tmp/composer-install.log
  else
    echo "=== Installation Failed ==="
    echo "Last 20 lines of output:"
    tail -20 /tmp/composer-install.log
  fi
}

################################################################################
# PATTERN 5: RUNNING TESTS WITH RESULT CAPTURE
################################################################################

pattern_run_tests() {
  operation_start "PHPUnit Test Suite"

  echo "=== Running PHPUnit Tests ===" && \
  ddev phpunit --testdox 2>&1 | tee /tmp/phpunit-results.log

  EXIT_CODE=$?
  echo "=== Test Suite Exit Code: $EXIT_CODE ==="

  if [ $EXIT_CODE -eq 0 ]; then
    echo "✓ All tests passed"
  else
    echo "✗ Some tests failed"
    echo "=== Showing Failed Tests ==="
    grep -i "fail\|error" /tmp/phpunit-results.log || echo "No specific failures found in output"
  fi

  operation_end $EXIT_CODE "PHPUnit Test Suite"
  return $EXIT_CODE
}

################################################################################
# PATTERN 6: COMPLEX OPERATION WITH MULTIPLE STEPS
################################################################################

pattern_full_deployment_prep() {
  operation_start "Deployment Preparation"

  # Step 1: Ensure DDEV is running
  run_with_status "Step 1/5: Starting DDEV" "ddev start"

  # Step 2: Install dependencies
  run_with_status "Step 2/5: Installing Composer dependencies" "ddev composer install"

  # Step 3: Import configuration
  run_with_status "Step 3/5: Importing configuration" "ddev drush cim --yes"

  # Step 4: Clear cache
  run_with_status "Step 4/5: Clearing Drupal cache" "ddev drush cr"

  # Step 5: Verify status
  echo "=== Step 5/5: Verifying Final Status ==="
  ddev drush status | grep -E "Drupal version|Database|Drupal bootstrap"

  EXIT_CODE=$?
  operation_end $EXIT_CODE "Deployment Preparation"
  return $EXIT_CODE
}

################################################################################
# PATTERN 7: DATABASE OPERATIONS
################################################################################

pattern_database_backup() {
  local backup_file="/tmp/drupal-backup-$(date +%Y%m%d-%H%M%S).sql.gz"

  operation_start "Database Backup"

  echo "=== Exporting Database ===" && \
  ddev export-db --gzip --file="$backup_file" 2>&1 && \
  echo "=== Verifying Backup File ===" && \
  ls -lh "$backup_file" && \
  echo "=== Backup Location: $backup_file ==="

  EXIT_CODE=$?
  operation_end $EXIT_CODE "Database Backup"
  return $EXIT_CODE
}

################################################################################
# PATTERN 8: ENVIRONMENT HEALTH CHECK
################################################################################

pattern_health_check() {
  operation_start "Environment Health Check"

  echo "=== DDEV Status ===" && \
  ddev describe | head -15 && \
  echo "" && \

  echo "=== PHP Version ===" && \
  ddev exec php -v | head -1 && \
  echo "" && \

  echo "=== MySQL Version ===" && \
  ddev exec mysql --version && \
  echo "" && \

  echo "=== Drupal Status ===" && \
  ddev drush status | grep -E "Drupal|Database|PHP|Files" && \
  echo "" && \

  echo "=== Disk Usage ===" && \
  ddev exec df -h | grep -E "Filesystem|/var/www/html" && \
  echo ""

  EXIT_CODE=$?
  operation_end $EXIT_CODE "Environment Health Check"
  return $EXIT_CODE
}

################################################################################
# MAIN EXECUTION (if run directly)
################################################################################

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
  echo "DDEV Command Pattern Demonstrations"
  echo "===================================="
  echo ""
  echo "Available patterns:"
  echo "  1. Simple DDEV command"
  echo "  2. DDEV with verification"
  echo "  3. Drush config import"
  echo "  4. Composer with logging"
  echo "  5. Run tests"
  echo "  6. Full deployment prep"
  echo "  7. Database backup"
  echo "  8. Health check"
  echo ""
  echo "Run a specific pattern by calling its function:"
  echo "  source $0"
  echo "  pattern_health_check"
  echo ""
  echo "Or run the health check now:"
  read -p "Run health check? (y/N): " -n 1 -r
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]; then
    pattern_health_check
  fi
fi

