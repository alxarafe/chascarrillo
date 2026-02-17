#!/bin/bash
# Description: Orchestrates the execution of all quality assurance scripts for Chascarrillo.

set -e

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

echo "=== 1. Auto-fixing Standards (PHPCBF) ==="
bash "$SCRIPT_DIR/fix_standards.sh" || true

echo "=== 2. Checking Standards (PHPCS) ==="
bash "$SCRIPT_DIR/check_standards.sh"

echo "=== 3. Static Analysis (PHPStan & Psalm) ==="
bash "$SCRIPT_DIR/static_analysis.sh"

echo "=== 4. Running Tests (PHPUnit) ==="
bash "$SCRIPT_DIR/run_tests.sh"

echo ""
echo "✅ All local CI checks passed successfully for Chascarrillo!"
