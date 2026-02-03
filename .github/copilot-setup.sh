#!/bin/bash
# Copilot Setup Script for Duccini's
# This script automates the setup steps defined in copilot-setup-steps.yml
# Usage: bash .github/copilot-setup.sh

set -e  # Exit on error

echo "🚀 Starting Duccini's DDEV Setup..."
echo "================================================"

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

# Check if running in CI/automated environment
if [ -z "$CI" ]; then
    echo "⚠️  Warning: This appears to be a local environment."
    echo "   This script is designed for automated/CI environments."
    read -p "   Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Step 1: Install DDEV
echo ""
echo "📦 Step 1: Installing DDEV..."
if command -v ddev &> /dev/null; then
    echo "✅ DDEV already installed: $(ddev --version)"
else
    echo "   Downloading DDEV installer..."
    curl -LO https://raw.githubusercontent.com/ddev/ddev/master/scripts/install_ddev.sh
    echo "   Running DDEV installer..."
    bash install_ddev.sh
    rm -f install_ddev.sh
    echo "✅ DDEV installed: $(ddev --version)"
fi

# Step 2: Configure DDEV project
echo ""
echo "⚙️  Step 2: Configuring DDEV project..."
if [ -d .ddev ]; then
    echo "✅ DDEV already configured"
else
    ddev config --project-type=drupal11 --docroot=web --project-name=duccinis
    echo "✅ DDEV project configured"
fi

# Step 3: Start DDEV
echo ""
echo "🚀 Step 3: Starting DDEV..."
ddev start
echo "✅ DDEV started successfully"

# Step 4: Install Composer dependencies
echo ""
echo "📚 Step 4: Installing Composer dependencies..."
ddev composer install --no-interaction --prefer-dist
echo "✅ Composer dependencies installed"

# Step 5: Unpack Drupal recipes
echo ""
echo "📦 Step 5: Unpacking Drupal recipes..."
ddev composer drupal:recipe-unpack --no-interaction || echo "⚠️  Recipe unpack skipped (may not be needed)"
echo "✅ Drupal recipes processed"

# Step 6: Install Drupal
echo ""
echo "🔧 Step 6: Installing Drupal..."
if ! ddev drush status --fields=bootstrap 2>/dev/null | grep -q "Successful"; then
    echo "   Installing Drupal with minimal profile..."
    ddev drush site:install minimal --account-name=admin --account-pass=admin -y
    echo "✅ Drupal installed"
else
    echo "✅ Drupal already installed"
fi

# Step 7: Enable required modules
echo ""
echo "🔌 Step 7: Enabling required modules..."
ddev drush en \
    announcements_feed automated_cron big_pipe block breakpoint ckeditor5 config \
    content_moderation contextual datetime dblog dynamic_page_cache editor field \
    field_ui file filter image inline_form_errors layout_builder layout_discovery \
    link media media_library menu_link_content menu_ui mysql navigation node \
    options package_manager page_cache path path_alias responsive_image system \
    taxonomy text update user views views_ui workflows \
    automatic_updates autosave_form bpmn_io captcha coffee crop dashboard \
    drupal_cms_helper easy_breadcrumb easy_email easy_email_override eca eca_base \
    eca_config eca_content eca_form eca_misc eca_modeller_bpmn eca_render eca_ui \
    eca_user focal_point friendlycaptcha geofield gin_toolbar honeypot jquery_ui \
    jquery_ui_resizable klaro linkit login_emailusername mailsystem \
    menu_link_attributes pathauto project_browser redirect_404 redirect sam \
    scheduler scheduler_content_moderation_integration svg_image \
    symfony_mailer_lite tagify_user_list tagify token trash fns_archive \
    claro olivero stark drupal_cms_olivero easy_email_theme gin -y
echo "✅ Required modules enabled"

# Step 8: Clear cache
echo ""
echo "🧹 Step 8: Clearing Drupal cache..."
ddev drush cr
echo "✅ Cache cleared"

# Step 9: Check configuration status
echo ""
echo "🔍 Step 9: Checking configuration status..."
ddev drush cst || echo "⚠️  Configuration status check completed with warnings"

# Step 10: Verify module
echo ""
echo "✓ Step 10: Verifying fns_archive module..."
ddev drush pml --filter=fns_archive --format=yaml
echo "✅ Module verification complete"

# Step 11: Show project info
echo ""
echo "📊 Final: Project information..."
ddev describe

echo ""
echo "================================================"
echo "✨ Setup Complete! DDEV environment is ready."
echo ""
echo "Quick reference:"
echo "  • Site URL: Run 'ddev launch' to open in browser"
echo "  • Admin login: admin / admin"
echo "  • Drush: ddev drush [command]"
echo "  • Stop: ddev stop"
echo "  • Restart: ddev restart"
echo ""
echo "Next steps:"
echo "  1. Run: ddev drush uli  # Get one-time login link"
echo "  2. Run: ddev launch     # Open site in browser"
echo "================================================"
