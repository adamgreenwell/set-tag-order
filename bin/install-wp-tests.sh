#!/usr/bin/env bash
# Install the WordPress test suite for Set Tag Order.
# Usage: ./bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]

set -euo pipefail

if [[ $# -lt 3 ]]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR="${TMPDIR%/}"
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

download() {
	if command -v curl >/dev/null 2>&1; then
		curl -fLsS "$1" -o "$2"
	elif command -v wget >/dev/null 2>&1; then
		wget -nv -O "$2" "$1"
	else
		echo "Error: neither curl nor wget is installed."
		exit 1
	fi
}

require_svn() {
	if ! command -v svn >/dev/null 2>&1; then
		echo "Error: svn is required to install the WordPress test suite."
		exit 1
	fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+-(beta|RC)[0-9]+$ ]]; then
	WP_BRANCH=${WP_VERSION%-*}
	WP_TESTS_TAG="branches/$WP_BRANCH"
elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.0$ ]]; then
		WP_TESTS_TAG="tags/${WP_VERSION%??}"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
elif [[ $WP_VERSION == "nightly" || $WP_VERSION == "trunk" ]]; then
	WP_TESTS_TAG="trunk"
else
	download https://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
	LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//' | head -1 || true)
	if [[ -z "${LATEST_VERSION}" ]]; then
		echo "Latest WordPress version could not be found."
		exit 1
	fi
	WP_TESTS_TAG="tags/$LATEST_VERSION"
fi

install_wp() {
	if [[ -d "${WP_CORE_DIR}" ]]; then
		return
	fi

	mkdir -p "${WP_CORE_DIR}"

	if [[ $WP_VERSION == "nightly" || $WP_VERSION == "trunk" ]]; then
		mkdir -p "${TMPDIR}/wordpress-trunk"
		rm -rf "${TMPDIR}/wordpress-trunk"/*
		require_svn
		svn export --quiet https://core.svn.wordpress.org/trunk "${TMPDIR}/wordpress-trunk/wordpress"
		mv "${TMPDIR}/wordpress-trunk/wordpress"/* "${WP_CORE_DIR}"
	else
		if [[ $WP_VERSION == "latest" ]]; then
			ARCHIVE_NAME="latest"
		elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+ ]]; then
			download "https://api.wordpress.org/core/version-check/1.7/?version=$WP_VERSION" "${TMPDIR}/wp-latest.json"
			if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.0$ ]]; then
				LATEST_VERSION=${WP_VERSION%??}
			else
				LATEST_VERSION=$(
					while IFS= read -r VERSION_OFFER; do
						if [[ $VERSION_OFFER == "$WP_VERSION" || $VERSION_OFFER == "$WP_VERSION".* ]]; then
							printf '%s\n' "$VERSION_OFFER"
							break
						fi
					done < <(grep -o '"version":"[^"]*' "${TMPDIR}/wp-latest.json" | sed 's/"version":"//')
				)
			fi

			if [[ -z "${LATEST_VERSION:-}" ]]; then
				ARCHIVE_NAME="wordpress-$WP_VERSION"
			else
				ARCHIVE_NAME="wordpress-$LATEST_VERSION"
			fi
		else
			ARCHIVE_NAME="wordpress-$WP_VERSION"
		fi

		download "https://wordpress.org/${ARCHIVE_NAME}.tar.gz" "${TMPDIR}/wordpress.tar.gz"
		tar --strip-components=1 -zxmf "${TMPDIR}/wordpress.tar.gz" -C "${WP_CORE_DIR}"
	fi

	download https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php "${WP_CORE_DIR}/wp-content/db.php"
}

install_test_suite() {
	if [[ $(uname -s) == "Darwin" ]]; then
		IOPTION='-i.bak'
	else
		IOPTION='-i'
	fi

	if [[ ! -f "${WP_TESTS_DIR}/includes/functions.php" || ! -d "${WP_TESTS_DIR}/data" ]]; then
		mkdir -p "${WP_TESTS_DIR}"
		rm -rf "${WP_TESTS_DIR}/includes" "${WP_TESTS_DIR}/data"
		require_svn
		svn export --quiet --ignore-externals "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "${WP_TESTS_DIR}/includes"
		svn export --quiet --ignore-externals "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "${WP_TESTS_DIR}/data"
	fi

	if [[ ! -f "${WP_TESTS_DIR}/wp-tests-config.php" ]]; then
		download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "${WP_TESTS_DIR}/wp-tests-config.php"
		WP_CORE_DIR="${WP_CORE_DIR%/}"
		sed ${IOPTION} "s:dirname( __FILE__ ) . '/src/':'${WP_CORE_DIR}/':" "${WP_TESTS_DIR}/wp-tests-config.php"
		sed ${IOPTION} "s:__DIR__ . '/src/':'${WP_CORE_DIR}/':" "${WP_TESTS_DIR}/wp-tests-config.php"
		sed ${IOPTION} "s/youremptytestdbnamehere/${DB_NAME}/" "${WP_TESTS_DIR}/wp-tests-config.php"
		sed ${IOPTION} "s/yourusernamehere/${DB_USER}/" "${WP_TESTS_DIR}/wp-tests-config.php"
		sed ${IOPTION} "s/yourpasswordhere/${DB_PASS}/" "${WP_TESTS_DIR}/wp-tests-config.php"
		sed ${IOPTION} "s|localhost|${DB_HOST}|" "${WP_TESTS_DIR}/wp-tests-config.php"
	fi
}

install_db() {
	local parts
	local db_hostname
	local db_sock_or_port
	local extra

	if [[ ${SKIP_DB_CREATE} == "true" ]]; then
		return 0
	fi

	if ! command -v mysql >/dev/null 2>&1; then
		echo "Error: mysql client not found."
		echo "Run 'composer test:setup' to use Docker fallback, or install a local mysql client."
		exit 1
	fi

	parts=(${DB_HOST//:/ })
	db_hostname=${parts[0]:-}
	db_sock_or_port=${parts[1]:-}
	extra=""

	if [[ -n "${db_hostname}" ]]; then
		if [[ "${db_sock_or_port}" =~ ^[0-9]+$ ]]; then
			extra=" --host=${db_hostname} --port=${db_sock_or_port} --protocol=tcp"
		elif [[ -n "${db_sock_or_port}" ]]; then
			extra=" --socket=${db_sock_or_port}"
		else
			extra=" --host=${db_hostname} --protocol=tcp"
		fi
	fi

	mysql --user="${DB_USER}" --password="${DB_PASS}"${extra} -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`"
}

install_wp
install_test_suite
install_db
