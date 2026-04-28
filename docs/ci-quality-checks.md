# CI Quality Checks

The GitHub Actions workflow validates the code that belongs to this portfolio project without requiring Magento Marketplace credentials.

## Workflow

```text
.github/workflows/ci.yml
```

The workflow runs on pull requests to `develop` and `master`, plus pushes to `develop`, `master`, and `feature/**` branches.

## Checks

- Validates `src/composer.json` metadata without installing Magento dependencies.
- Runs PHP syntax checks for custom modules under `src/app/code/Portfolio`.
- Validates custom Magento XML configuration files.
- Checks the mock ERP API JavaScript syntax.
- Installs and builds the React search widget with `npm ci` and `npm run build`.
- Fails if built frontend assets differ from committed files.
- Validates `docker-compose.yml` with `.env.example`.

## Local Commands

PHP syntax:

```bash
bash scripts/ci/lint-php.sh
```

XML validation:

```bash
python3 scripts/ci/validate-xml.py
```

Mock ERP API syntax:

```bash
bash scripts/ci/check-mock-api.sh
```

React build:

```bash
make search-widget-install
make search-widget-build
```

If your host machine has an older Node.js version, run the build with the same Node major version used by CI:

```bash
make search-widget-build-docker
```

Docker Compose validation:

```bash
docker compose config --quiet
```

## Notes

The workflow intentionally does not run `composer install` or `bin/magento setup:install` because Magento Open Source installation requires valid `repo.magento.com` Marketplace credentials.
