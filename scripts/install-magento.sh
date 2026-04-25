#!/usr/bin/env bash
set -euo pipefail

: "${MAGENTO_VERSION:=2.4.8-p4}"
: "${MAGENTO_BASE_URL:=http://magento.test/}"
: "${MAGENTO_BACKEND_FRONTNAME:=admin}"
: "${MAGENTO_ADMIN_FIRSTNAME:=Senior}"
: "${MAGENTO_ADMIN_LASTNAME:=Developer}"
: "${MAGENTO_ADMIN_EMAIL:=admin@example.com}"
: "${MAGENTO_ADMIN_USER:=admin}"
: "${MAGENTO_ADMIN_PASSWORD:=Admin123456!}"
: "${MYSQL_DATABASE:=magento}"
: "${MYSQL_USER:=magento}"
: "${MYSQL_PASSWORD:=magento}"
: "${OPENSEARCH_HOST:=opensearch}"
: "${OPENSEARCH_PORT:=9200}"
: "${RABBITMQ_DEFAULT_USER:=magento}"
: "${RABBITMQ_DEFAULT_PASS:=magento}"
: "${REDIS_HOST:=redis}"

echo "Waiting for OpenSearch at ${OPENSEARCH_HOST}:${OPENSEARCH_PORT}..."
for attempt in $(seq 1 60); do
    if curl -fsS "http://${OPENSEARCH_HOST}:${OPENSEARCH_PORT}" >/dev/null; then
        echo "OpenSearch is reachable."
        break
    fi

    if [ "${attempt}" -eq 60 ]; then
        echo "OpenSearch did not become reachable after 60 attempts." >&2
        exit 1
    fi

    sleep 5
done

if [ ! -f composer.json ]; then
    rm -rf /tmp/magento-project

    composer create-project \
        --repository-url=https://repo.magento.com/ \
        "magento/project-community-edition=${MAGENTO_VERSION}" \
        /tmp/magento-project \
        --no-interaction

    shopt -s dotglob
    mv /tmp/magento-project/* /var/www/html/
    shopt -u dotglob
fi

composer install --no-interaction --prefer-dist

if [ ! -f app/etc/env.php ]; then
    bin/magento setup:install \
        --base-url="${MAGENTO_BASE_URL}" \
        --db-host=mysql \
        --db-name="${MYSQL_DATABASE}" \
        --db-user="${MYSQL_USER}" \
        --db-password="${MYSQL_PASSWORD}" \
        --backend-frontname="${MAGENTO_BACKEND_FRONTNAME}" \
        --admin-firstname="${MAGENTO_ADMIN_FIRSTNAME}" \
        --admin-lastname="${MAGENTO_ADMIN_LASTNAME}" \
        --admin-email="${MAGENTO_ADMIN_EMAIL}" \
        --admin-user="${MAGENTO_ADMIN_USER}" \
        --admin-password="${MAGENTO_ADMIN_PASSWORD}" \
        --language=en_US \
        --currency=USD \
        --timezone=UTC \
        --use-rewrites=1 \
        --search-engine=opensearch \
        --opensearch-host="${OPENSEARCH_HOST}" \
        --opensearch-port="${OPENSEARCH_PORT}" \
        --opensearch-index-prefix=magento2 \
        --opensearch-timeout=15 \
        --amqp-host=rabbitmq \
        --amqp-port=5672 \
        --amqp-user="${RABBITMQ_DEFAULT_USER}" \
        --amqp-password="${RABBITMQ_DEFAULT_PASS}" \
        --cache-backend=redis \
        --cache-backend-redis-server="${REDIS_HOST}" \
        --cache-backend-redis-db=0 \
        --page-cache=redis \
        --page-cache-redis-server="${REDIS_HOST}" \
        --page-cache-redis-db=1 \
        --session-save=redis \
        --session-save-redis-host="${REDIS_HOST}" \
        --session-save-redis-log-level=3 \
        --session-save-redis-db=2
fi

bin/magento deploy:mode:set developer
bin/magento cache:flush
bin/magento indexer:reindex
