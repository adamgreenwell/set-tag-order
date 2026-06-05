#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
WP_ROOT_DEFAULT="$(cd "${PLUGIN_DIR}/../../.." && pwd)"

WP_ROOT="${WP_ROOT:-$WP_ROOT_DEFAULT}"
ENV_FILE="${ENV_FILE:-${WP_ROOT}/.env}"
WP_VERSION="${WP_VERSION:-latest}"
WP_TEST_DB_NAME="${WP_TEST_DB_NAME:-wordpress_test}"
WP_TEST_DB_HOST="${WP_TEST_DB_HOST:-127.0.0.1:3309}"
MYSQL_CONTAINER_NAME="${MYSQL_CONTAINER_NAME:-settagorder_mysql}"
TMP_BASE="${TMPDIR:-/tmp}"
RESOLVED_WP_TESTS_DIR="${WP_TESTS_DIR:-${TMP_BASE%/}/wordpress-tests-lib}"

load_env_file() {
	if [[ ! -f "${ENV_FILE}" ]]; then
		return 0
	fi

	set -a
	# shellcheck disable=SC1090
	source "${ENV_FILE}"
	set +a
}

docker_available() {
	command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1
}

is_mysql_running() {
	docker_available && docker ps --format '{{.Names}}' | grep -qx "${MYSQL_CONTAINER_NAME}"
}

maybe_start_mysql_container() {
	if is_mysql_running; then
		return 0
	fi

	if ! docker_available; then
		return 0
	fi

	if [[ -f "${WP_ROOT}/docker-compose.yml" ]]; then
		echo "Starting ${MYSQL_CONTAINER_NAME} via docker compose..."
		(
			cd "${WP_ROOT}"
			docker compose up -d "${MYSQL_CONTAINER_NAME}" >/dev/null
		)
	fi
}

create_test_database_via_docker() {
	local ready=false
	local root_password="${MYSQL_ROOT_PASSWORD:-}"

	if ! docker_available; then
		return 1
	fi

	if ! is_mysql_running; then
		return 1
	fi

	for _ in {1..30}; do
		if docker exec "${MYSQL_CONTAINER_NAME}" mariadb \
			--host=127.0.0.1 \
			-u"${DB_USER}" \
			-p"${DB_PASSWORD}" \
			-e "SELECT 1" >/dev/null 2>&1; then
			ready=true
			break
		fi

		sleep 2
	done

	if [[ "${ready}" != "true" ]]; then
		return 1
	fi

	if [[ -z "${root_password}" ]]; then
		echo "MYSQL_ROOT_PASSWORD is required in ${ENV_FILE} to create the test database through Docker."
		return 1
	fi

	echo "Creating ${WP_TEST_DB_NAME} through the ${MYSQL_CONTAINER_NAME} container..."
	docker exec "${MYSQL_CONTAINER_NAME}" mariadb \
		--host=127.0.0.1 \
		-uroot \
		-p"${root_password}" \
		-e "CREATE DATABASE IF NOT EXISTS \`${WP_TEST_DB_NAME}\`; GRANT ALL PRIVILEGES ON \`${WP_TEST_DB_NAME}\`.* TO '${DB_USER}'@'%'; FLUSH PRIVILEGES;"
}

load_env_file

DB_USER="${WP_TEST_DB_USER:-${DB_USER:-}}"
DB_PASSWORD="${WP_TEST_DB_PASSWORD:-${DB_PASSWORD:-}}"

if [[ -z "${DB_USER}" || -z "${DB_PASSWORD}" ]]; then
	echo "Unable to resolve WordPress test database credentials."
	echo "Expected DB_USER and DB_PASSWORD in ${ENV_FILE}, or WP_TEST_DB_USER/WP_TEST_DB_PASSWORD in the shell."
	exit 1
fi

maybe_start_mysql_container

SKIP_DB_CREATE="false"
if ! command -v mysql >/dev/null 2>&1; then
	if create_test_database_via_docker; then
		SKIP_DB_CREATE="true"
	else
		echo "No local mysql client was found, and Docker fallback was unavailable."
		echo "Start Docker and ${MYSQL_CONTAINER_NAME}, or install a mysql client, then run this command again."
		exit 1
	fi
fi

echo "Preparing the Set Tag Order WordPress test suite..."
echo "  WP root: ${WP_ROOT}"
echo "  WP_TESTS_DIR: ${RESOLVED_WP_TESTS_DIR}"
echo "  Test DB: ${WP_TEST_DB_NAME} @ ${WP_TEST_DB_HOST}"

WP_TESTS_DIR="${RESOLVED_WP_TESTS_DIR}" \
	bash "${PLUGIN_DIR}/bin/install-wp-tests.sh" \
	"${WP_TEST_DB_NAME}" \
	"${DB_USER}" \
	"${DB_PASSWORD}" \
	"${WP_TEST_DB_HOST}" \
	"${WP_VERSION}" \
	"${SKIP_DB_CREATE}"

echo
echo "Set Tag Order test setup is ready."
if [[ ! -x "${PLUGIN_DIR}/vendor/bin/phpunit" ]]; then
	echo "Next: cd ${PLUGIN_DIR} && composer install"
fi
echo "Run tests with:"
echo "  cd ${PLUGIN_DIR}"
echo "  composer test"
