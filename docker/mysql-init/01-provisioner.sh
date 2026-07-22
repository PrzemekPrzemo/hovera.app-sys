#!/usr/bin/env bash
set -euo pipefail

# Runs once, automatically, on first MySQL container start (empty data
# volume) via the official mysql image's /docker-entrypoint-initdb.d/
# mechanism. Creates the `hovera_provisioner` user with the broad grants
# the app needs to create/drop per-stable tenant databases and users at
# runtime — mirrors docs/DEPLOY.md §1.5.B (the Plesk phpMyAdmin version
# of this same SQL), adapted for a Docker-network host instead of
# 127.0.0.1 since the app reaches MySQL over the compose network.
#
# Reads DB_PROVISIONER_USERNAME / DB_PROVISIONER_PASSWORD from the
# environment (set on the `mysql` service in docker-compose.yml, sourced
# from Coolify's env vars) — no secrets hardcoded in this file.

: "${DB_PROVISIONER_USERNAME:?DB_PROVISIONER_USERNAME must be set}"
: "${DB_PROVISIONER_PASSWORD:?DB_PROVISIONER_PASSWORD must be set}"

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-SQL
    CREATE USER IF NOT EXISTS '${DB_PROVISIONER_USERNAME}'@'%' IDENTIFIED BY '${DB_PROVISIONER_PASSWORD}';

    GRANT CREATE, DROP, REFERENCES, INDEX, ALTER,
          CREATE TEMPORARY TABLES, LOCK TABLES,
          CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EXECUTE
          ON *.* TO '${DB_PROVISIONER_USERNAME}'@'%';

    GRANT CREATE USER ON *.* TO '${DB_PROVISIONER_USERNAME}'@'%';

    GRANT GRANT OPTION ON *.* TO '${DB_PROVISIONER_USERNAME}'@'%';

    FLUSH PRIVILEGES;
SQL

echo "[mysql-init] hovera_provisioner user ready."
