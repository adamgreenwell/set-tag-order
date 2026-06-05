#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
WP_ROOT_DEFAULT="$(cd "${PLUGIN_DIR}/../../.." && pwd)"

WP_ROOT="${WP_ROOT:-${WP_CORE_DIR:-$WP_ROOT_DEFAULT}}"
PHPUNIT_ARGS=()

if [[ ! -x "${PLUGIN_DIR}/vendor/bin/phpunit" ]]; then
	echo "PHPUnit binary not found at ${PLUGIN_DIR}/vendor/bin/phpunit"
	echo "Run:"
	echo "  cd ${PLUGIN_DIR}"
	echo "  composer install"
	exit 1
fi

if [[ ! -f "${PLUGIN_DIR}/phpunit.xml" ]]; then
	echo "Missing PHPUnit config at ${PLUGIN_DIR}/phpunit.xml"
	exit 1
fi

resolve_tests_dir() {
	local candidate

	if [[ -n "${WP_TESTS_DIR:-}" && -f "${WP_TESTS_DIR}/includes/functions.php" ]]; then
		echo "${WP_TESTS_DIR}"
		return 0
	fi

	candidate="${WP_ROOT}/tests/phpunit"
	if [[ -f "${candidate}/includes/functions.php" ]]; then
		echo "${candidate}"
		return 0
	fi

	for candidate in "/tmp/wordpress-tests-lib" "${TMPDIR:-}/wordpress-tests-lib"; do
		if [[ -n "${candidate}" && -f "${candidate}/includes/functions.php" ]]; then
			echo "${candidate}"
			return 0
		fi
	done

	return 1
}

if ! RESOLVED_WP_TESTS_DIR="$(resolve_tests_dir)"; then
	echo "WordPress test suite not found."
	echo "Checked: WP_TESTS_DIR, ${WP_ROOT}/tests/phpunit, /tmp/wordpress-tests-lib, \${TMPDIR}/wordpress-tests-lib"
	echo
	echo "Set it up from the plugin directory:"
	echo "  cd ${PLUGIN_DIR}"
	echo "  composer test:setup"
	echo
	echo "Then run:"
	echo "  composer test"
	exit 1
fi

for arg in "$@"; do
	if [[ "${arg}" == *.php && "${arg}" != /* && -f "${PLUGIN_DIR}/${arg}" ]]; then
		PHPUNIT_ARGS+=("${PLUGIN_DIR}/${arg}")
	else
		PHPUNIT_ARGS+=("${arg}")
	fi
done

RUN_DIR="${PLUGIN_DIR}"
if [[ -f "${WP_ROOT}/wp-includes/version.php" ]]; then
	RUN_DIR="${WP_ROOT}"
fi

cd "${RUN_DIR}"

echo "Running Set Tag Order tests"
echo "  Run dir: ${RUN_DIR}"
echo "  Plugin dir: ${PLUGIN_DIR}"
echo "  WP_TESTS_DIR: ${RESOLVED_WP_TESTS_DIR}"

WP_TESTS_DIR="${RESOLVED_WP_TESTS_DIR}" "${PLUGIN_DIR}/vendor/bin/phpunit" -c "${PLUGIN_DIR}/phpunit.xml" "${PHPUNIT_ARGS[@]}"
