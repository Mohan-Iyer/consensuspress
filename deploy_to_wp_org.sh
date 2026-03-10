#!/bin/bash
# =============================================================================
# DNA Header
# File:    deploy_to_wp_org.sh
# Version: 1.0.1
# Purpose: ConsensusPress WordPress.org deploy gate script
# Author:  C-C (Session 12, Sprint 8) | Patched: D-C (Session 13, Sprint 8)
# Spec:    docs/sprint_8_d1_d7_instructions.yaml D4 deploy_script (rev)
# Gates:   PHPUnit → WP compliance → Version consistency → HAL-001 → SVN
# Change:  v1.0.0→v1.0.1: Gate reorder per locked spec. WP compliance (incl.
#          readme.txt validation) moved to Gate 2. Version consistency to
#          Gate 3. HAL-001 to Gate 4. Standalone Gate 5 removed (folded).
# Usage:   ./deploy_to_wp_org.sh           # full run (all gates + SVN steps)
#          ./deploy_to_wp_org.sh --dry-run  # all gates, stops before SVN
# =============================================================================

set -e

DRY_RUN=false
if [ "${1}" = "--dry-run" ]; then
  DRY_RUN=true
  echo "🔍 DRY RUN MODE — all gates will run, SVN step skipped"
  echo ""
fi

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="${REPO_ROOT}/plugin/consensuspress"
TESTS_DIR="${REPO_ROOT}/tests"

# =============================================================================
# GATE 1: PHPUnit
# All tests must be green. Zero regressions permitted.
# =============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Gate 1: PHPUnit test suite"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ ! -f "${PLUGIN_DIR}/vendor/bin/phpunit" ]; then
  echo "❌ GATE 1 FAILED: vendor/bin/phpunit not found."
  echo "   Run: cd ${PLUGIN_DIR} && composer install"
  exit 1
fi

cd "${PLUGIN_DIR}"
vendor/bin/phpunit --configuration "${TESTS_DIR}/phpunit.xml"
PHPUNIT_EXIT=$?

if [ ${PHPUNIT_EXIT} -ne 0 ]; then
  echo ""
  echo "❌ GATE 1 FAILED: PHPUnit tests not green. Aborting."
  exit 1
fi
echo "✅ Gate 1 passed: PHPUnit"
echo ""

# =============================================================================
# GATE 2: WordPress.org compliance
# Structural gate. Validates readme.txt presence and required sections,
# then PHP escaping. Must pass before version check is meaningful.
# =============================================================================
cd "${REPO_ROOT}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Gate 2: WordPress.org compliance"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 2a: readme.txt present
if [ ! -f "${PLUGIN_DIR}/readme.txt" ]; then
  echo "❌ GATE 2 FAILED: readme.txt missing at ${PLUGIN_DIR}/readme.txt"
  exit 1
fi

# 2b: Required readme sections
MISSING_SECTIONS=""
for section in "== Description ==" "== Installation ==" "== Frequently Asked Questions ==" "== Screenshots ==" "== Changelog =="; do
  if ! grep -q "${section}" "${PLUGIN_DIR}/readme.txt"; then
    MISSING_SECTIONS="${MISSING_SECTIONS}\n  Missing: ${section}"
  fi
done
if [ -n "${MISSING_SECTIONS}" ]; then
  echo "❌ GATE 2 FAILED: readme.txt missing required sections:"
  echo -e "${MISSING_SECTIONS}"
  exit 1
fi

# 2c: PHP compliance scan
if [ -f "${REPO_ROOT}/docs/tooling/php-afd/wp_compliance_scanner.php" ]; then
  php "${REPO_ROOT}/docs/tooling/php-afd/wp_compliance_scanner.php" "${PLUGIN_DIR}/"
  WP_EXIT=$?
  if [ ${WP_EXIT} -ne 0 ]; then
    echo ""
    echo "❌ GATE 2 FAILED: WordPress.org PHP compliance violations. Aborting."
    exit 1
  fi
else
  # Inline fallback: unescaped superglobal output
  UNESCAPED=$(grep -rn --include="*.php" \
    -e "echo \$_GET\|echo \$_POST\|echo \$_REQUEST\|echo \$_COOKIE\|echo \$_SERVER" \
    "${PLUGIN_DIR}/includes/" 2>/dev/null || true)
  if [ -n "${UNESCAPED}" ]; then
    echo "❌ GATE 2 FAILED: Unescaped output detected:"
    echo "${UNESCAPED}"
    exit 1
  fi
  echo "  (wp_compliance_scanner.php not found — grep fallback used)"
fi
echo "✅ Gate 2 passed: WordPress.org compliance"
echo ""

# =============================================================================
# GATE 3: Version consistency
# Stable tag in readme.txt must match Version in plugin header.
# Single most common WP.org first-submission rejection reason. Hard exit.
# =============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Gate 3: Version consistency"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

STABLE_TAG=$(grep "^Stable tag:" "${PLUGIN_DIR}/readme.txt" | sed 's/Stable tag: *//' | tr -d '[:space:]')
PLUGIN_VER=$(grep "^ \* Version:" "${PLUGIN_DIR}/consensuspress.php" | head -1 | sed 's/.*Version: *//' | tr -d '[:space:]')

echo "  readme.txt  Stable tag : ${STABLE_TAG}"
echo "  plugin.php  Version    : ${PLUGIN_VER}"

if [ "${STABLE_TAG}" != "${PLUGIN_VER}" ]; then
  echo ""
  echo "❌ GATE 3 FAILED: Version mismatch."
  echo "   readme.txt Stable tag (${STABLE_TAG}) != consensuspress.php Version (${PLUGIN_VER})"
  echo "   WordPress.org will reject the submission. Align both values before deploying."
  exit 1
fi
echo "✅ Gate 3 passed: Version consistency (${STABLE_TAG})"
echo ""

# =============================================================================
# GATE 4: HAL-001 scanner — Fracture-Free gate
# Zero undeferred hardcoded secrets or API key violations permitted.
# =============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Gate 4: HAL-001 scanner (Fracture-Free — hardcoded secrets)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "${REPO_ROOT}/docs/tooling/php-afd/hal001_php_scanner.php" ]; then
  php "${REPO_ROOT}/docs/tooling/php-afd/hal001_php_scanner.php" "${PLUGIN_DIR}/"
  HAL_EXIT=$?
  if [ ${HAL_EXIT} -ne 0 ]; then
    echo ""
    echo "❌ GATE 4 FAILED: HAL-001 violations found. Aborting."
    exit 1
  fi
  echo "✅ Gate 4 passed: HAL-001 scanner"
else
  # Inline fallback: common hardcoded secret patterns
  SECRETS=$(grep -rn --include="*.php" \
    -e "sk-[a-zA-Z0-9]\{20,\}" \
    -e "AIza[a-zA-Z0-9]\{20,\}" \
    -e "api_key.*=.*['\"][a-zA-Z0-9_\-]\{20,\}['\"]" \
    "${PLUGIN_DIR}/" 2>/dev/null | grep -v "get_option\|wp_options\|sanitize\|test-\|// " || true)
  if [ -n "${SECRETS}" ]; then
    echo "❌ GATE 4 FAILED: Potential hardcoded secrets detected:"
    echo "${SECRETS}"
    exit 1
  fi
  echo "✅ Gate 4 passed: HAL-001 inline check (scanner not found — grep fallback used)"
fi
echo ""

# =============================================================================
# --dry-run exits here — all gates passed, SVN not touched
# =============================================================================
if [ "${DRY_RUN}" = "true" ]; then
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "✅ DRY RUN COMPLETE — All 4 gates passed."
  echo "   Run without --dry-run to proceed to SVN submission."
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  exit 0
fi

# =============================================================================
# ALL GATES PASSED — SVN steps (manual execution required)
# =============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ ALL GATES PASSED. Plugin ready for WordPress.org SVN submission."
echo ""
echo "Next steps (manual):"
echo "  1. svn co https://plugins.svn.wordpress.org/consensuspress/ svn-consensuspress"
echo "  2. cp -r ${PLUGIN_DIR}/* svn-consensuspress/trunk/"
echo "  3. cp ${PLUGIN_DIR}/assets/* svn-consensuspress/assets/"
echo "  4. cd svn-consensuspress"
echo "  5. svn add --force ."
echo "  6. svn ci -m \"Release ${STABLE_TAG}\""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"