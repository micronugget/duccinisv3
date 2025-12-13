#!/usr/bin/env bash
set -euo pipefail

# Import all configuration from the sync directory and rebuild caches.
# Can be run either inside the DDEV web container OR from the host machine.
# When run from host, it will automatically execute Drush via `ddev exec`.
# Usage (either works):
#   - ddev ssh -s web && scripts/import-config.sh
#   - bash scripts/import-config.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

has_drush() { command -v drush >/dev/null 2>&1; }
has_ddev() { command -v ddev >/dev/null 2>&1; }

run_drush() {
  echo "Importing configuration (drush cim -y) ..."
  drush cim -y
  echo "Rebuilding caches (drush cr) ..."
  drush cr
}

run_ddev_drush() {
  echo "Importing configuration inside DDEV (ddev exec -s web drush cim -y) ..."
  ddev exec -s web drush cim -y
  echo "Rebuilding caches inside DDEV (ddev exec -s web drush cr) ..."
  ddev exec -s web drush cr
}

if has_drush; then
  run_drush
elif has_ddev; then
  run_ddev_drush
else
  echo "Error: Neither drush nor ddev is available in PATH."
  echo "Install DDEV and start the project (ddev start), then re-run: bash scripts/import-config.sh"
  exit 1
fi

echo "Done. Configuration imported and caches rebuilt."
