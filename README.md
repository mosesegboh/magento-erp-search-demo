# Magento Product Discovery + ERP Integration Platform

This repository is prepared for a Magento 2 portfolio project using Magento Open Source `2.4.8-p4`, Docker, OpenSearch, RabbitMQ, Redis, MySQL, and nginx.

## Current Status

Magento Open Source `2.4.8-p4` is installed through Docker with OpenSearch, RabbitMQ, Valkey, MySQL, MailHog, nginx, and PHP-FPM.

## Prerequisites

- Docker Engine
- Docker Compose plugin
- Magento Marketplace authentication keys for `repo.magento.com`

Configure Composer authentication before running the Magento install:

```bash
docker compose exec --user www-data php composer config --global http-basic.repo.magento.com PUBLIC_KEY PRIVATE_KEY
```

## Setup

```bash
make init
make build
make up
make install
```

Magento will be available at:

```text
http://localhost:8080/
```

Admin defaults are defined in `.env.example`.

## Local Hostname

If you want to use the configured base URL, add this to your hosts file:

```text
127.0.0.1 magento.test
```

Then visit:

```text
http://magento.test:8080/
```

## Services

- Magento/PHP-FPM: `php:8.3-fpm`
- Web server: `nginx:1.28-alpine`
- Database: `mysql:8.4`
- Search: `opensearchproject/opensearch:3`
- Queue: `rabbitmq:4.1-management`
- Cache/session: `valkey/valkey:8-alpine`
- Mail testing: `mailhog/mailhog`

## Useful Commands

```bash
make shell
make magento
make composer
make reindex
make clean-cache
make logs
```

## Next Build Phases

After Magento is installed:

1. Add a mock ERP/PIM API service.
2. Build `Portfolio_ErpSync`.
3. Build `Portfolio_OrderExport`.
4. Build `Portfolio_SearchEnhancer`.
5. Add tests, CI, and architecture documentation.
