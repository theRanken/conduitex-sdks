# Conduitex SDKs

Multi-language SDKs for the Conduitex platform. Kits live in `kits/javascript`, `kits/python`, and `kits/php`.

## Base URL (centralized)
- All SDKs read `CONDUITEX_BASE_URL` (defaults to `https://api.conduitex.com`).
- Optional helper for local runs: copy `.env.example` to `.env` and set `CONDUITEX_BASE_URL` and `CONDUITEX_VAULT_KEY`.
- Each client still accepts an explicit `baseUrl`/`base_url` parameter if you want to override per instance.

## Local setup
- Node (v22): `cd kits/javascript && npm install --no-package-lock`
- Python (3.10): `cd kits/python && python -m venv .venv && .venv/Scripts/activate && pip install -e '.[dev]'`
- PHP (8.2): `cd kits/php && composer install`

## Running tests
- JS: `cd kits/javascript && npm test`
- Python: `cd kits/python && .venv/Scripts/python -m pytest`
- PHP: `cd kits/php && vendor/bin/pest`

## Deployment workflow
- Tags trigger publishing with a test gate:
  - `js-v*`  -> npm (`npm publish --access public`)
  - `py-v*`  -> PyPI (`twine upload dist/*`)
  - `php-v*` -> Packagist API refresh
- Required secrets:
  - `NPM_TOKEN` (npm publish)
  - `PYPI_API_TOKEN` (PyPI upload)
  - `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN` (Packagist update)
- Test gate: publish jobs depend on language-specific test jobs within `.github/workflows/deploy.yml`.

## Versioning and releases
1) Bump the version inside the SDK you are releasing (`package.json`, `pyproject.toml`, or `composer.json`).
2) Commit and tag using the scheme above (e.g., `git tag js-v0.1.0 && git push origin js-v0.1.0`).
3) The deploy workflow runs tests and publishes to the registry if tests pass.

## Notes for contributors
- Shared ignores in `.gitignore` prevent committing build artifacts, virtualenvs, vendors, and environment files.
- Base URL defaults are safe for production; override per environment via `CONDUITEX_BASE_URL`.
