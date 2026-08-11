# Conduitex SDKs

Multi-language gateway runtime SDKs for the Conduitex platform. Kits live in `kits/javascript`, `kits/python`, and `kits/php`.

## Base URL
- All SDKs read `CONDUITEX_BASE_URL`.
- Point `CONDUITEX_BASE_URL` at your deployed gateway, for example `https://gateway.example.com`.
- The legacy fallback default remains `https://api.conduitex.com`, but gateway deployments should override it explicitly.
- Optional helper for local runs: copy `.env.example` to `.env` and set `CONDUITEX_BASE_URL` and `CONDUITEX_VAULT_KEY`.

## Scope
- These SDKs are for customer-to-gateway runtime traffic only.
- They wrap vault-key-authenticated proxy calls under `/api/v1/proxy/...`.
- They do not expose gateway registration, bootstrap, sync, billing, org management, or other hosted control-plane flows.

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
- Keep the public SDK surface runtime-only; gateway control-plane plumbing belongs inside the gateway application.
- Configure runtime targets per environment via `CONDUITEX_BASE_URL`.
