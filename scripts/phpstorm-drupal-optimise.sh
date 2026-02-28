#!/usr/bin/env bash
# =============================================================================
# phpstorm-drupal-optimise.sh
#
# Applies PHPStorm performance optimisations to a Drupal/DDEV project:
#   1. Adds missing <excludeFolder> entries to the .iml file so PHPStorm
#      stops indexing vendor, contrib, and generated directories.
#   2. Trims the ComposerConfigs block in workspace.xml down to the single
#      root composer.json, eliminating background "checking for updates" CPU
#      load caused by hundreds of nested composer.json references.
#   3. Installs root package.json dependencies via DDEV (suppresses the
#      PHPStorm "Run yarn install" notification) and upgrades npm to latest
#      to clear any bundled npm audit vulnerabilities.
#
# Usage:
#   cd /path/to/your/drupal-project
#   bash scripts/phpstorm-drupal-optimise.sh
#
# Requirements:
#   - Run from the Drupal project root.
#   - DDEV must be running (ddev start) before executing this script.
#   - python3 must be available on the host.
# =============================================================================

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IDEA_DIR="$PROJECT_DIR/.idea"

echo "==> Project root: $PROJECT_DIR"

# -----------------------------------------------------------------------------
# 1. Locate the .iml file
# -----------------------------------------------------------------------------
IML_FILE=$(find "$IDEA_DIR" -maxdepth 1 -name "*.iml" | head -1)
if [[ -z "$IML_FILE" ]]; then
  echo "ERROR: No .iml file found in $IDEA_DIR"
  echo "       Open the project in PHPStorm at least once to generate it, then re-run."
  exit 1
fi
echo "==> Found .iml: $IML_FILE"

# Directories PHPStorm should never index in a Drupal project
EXCLUDE_DIRS=(
  "vendor"
  "web/core"
  "web/modules/contrib"
  "web/themes/contrib"
  "web/profiles/contrib"
  "web/libraries"
  "web/sites/default/files"
  "node_modules"
)

# Insert any missing <excludeFolder> entries just before </content>
for DIR in "${EXCLUDE_DIRS[@]}"; do
  ENTRY="      <excludeFolder url=\"file://\$MODULE_DIR\$/$DIR\" />"
  if grep -qF "$DIR" "$IML_FILE"; then
    echo "    [skip] already excluded: $DIR"
  else
    # Insert before the closing </content> tag
    sed -i "s|    </content>|$ENTRY\n    </content>|" "$IML_FILE"
    echo "    [added] excluded: $DIR"
  fi
done
echo "==> .iml exclusions done."

# -----------------------------------------------------------------------------
# 2. Trim ComposerConfigs in workspace.xml to root composer.json only
# -----------------------------------------------------------------------------
WORKSPACE="$IDEA_DIR/workspace.xml"
if [[ ! -f "$WORKSPACE" ]]; then
  echo "WARN: workspace.xml not found — skipping ComposerConfigs fix."
  echo "      (Open the project in PHPStorm once to generate it, then re-run.)"
else
  COMPOSER_COUNT=$(grep -c "<option value=" "$WORKSPACE" 2>/dev/null || true)
  echo "==> workspace.xml: $COMPOSER_COUNT composer entries found."

  export WORKSPACE
  python3 - <<'PYEOF'
import re, os

path_ws = os.environ.get("WORKSPACE")

with open(path_ws, 'r') as f:
    content = f.read()

replacement = (
    '  <component name="ComposerConfigs">\n'
    '    <option name="configs">\n'
    '      <option value="$PROJECT_DIR$/composer.json" />\n'
    '    </option>\n'
    '  </component>'
)

new_content = re.sub(
    r'  <component name="ComposerConfigs">.*?</component>',
    replacement,
    content,
    flags=re.DOTALL
)

if new_content == content:
    print("    [skip] ComposerConfigs already minimal or not found.")
else:
    with open(path_ws, 'w') as f:
        f.write(new_content)
    print("    [done] ComposerConfigs trimmed to root composer.json only.")
PYEOF
fi

# -----------------------------------------------------------------------------
# 3. Install / update npm dependencies via DDEV
# -----------------------------------------------------------------------------
PACKAGE_JSON="$PROJECT_DIR/package.json"
if [[ ! -f "$PACKAGE_JSON" ]]; then
  echo "==> No package.json at project root — skipping npm step."
else
  echo "==> Installing npm dependencies via DDEV..."
  if ! ddev describe &>/dev/null; then
    echo "WARN: DDEV does not appear to be running. Skipping npm install."
    echo "      Run 'ddev start' then re-run this script."
  else
    ddev exec npm install
    echo "==> Upgrading npm to latest (clears bundled audit vulnerabilities)..."
    ddev exec npm install npm@latest
    echo "==> npm audit:"
    ddev exec npm audit || true
  fi
fi

# -----------------------------------------------------------------------------
echo ""
echo "==================================================================="
echo " PHPStorm Drupal optimisations complete."
echo " Reload the project in PHPStorm (File > Reload All from Disk) or"
echo " simply close and reopen it to apply all changes."
echo "==================================================================="
