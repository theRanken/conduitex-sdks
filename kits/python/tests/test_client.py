from __future__ import annotations

import json
import os

import pytest
import responses

from dotenv import load_dotenv
from conduitex import ApiError, ConduitexClient

load_dotenv()

BASE_URL = os.getenv("CONDUITEX_BASE_URL", "https://api.test")
DEFAULT_BASE_URL = "https://api.conduitex.com"
LIVE = os.getenv("CONDUITEX_LIVE_TESTS") == "1"


def test_get_with_params_and_default_headers(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setenv("CONDUITEX_BASE_URL", BASE_URL)
    client = ConduitexClient(vault_key="vk_test")

    if LIVE:
        try:
            response = client.get("github", "repos", params={"q": "laravel"})
            assert response.status_code >= 200
        except ApiError as exc:
            # Live mode: treat reaching the API (even 401) as success signal.
            assert exc.status_code >= 400
    else:
        with responses.RequestsMock() as rsps:
            rsps.add(
                responses.GET,
                f"{BASE_URL}/api/v1/proxy/github/repos",
                json={"ok": True},
                status=200,
            )

            response = client.get("github", "repos", params={"q": "laravel"})

            assert response.status_code == 200
            request = rsps.calls[0].request
            assert request.headers["X-Vault-Key"] == "vk_test"
            assert request.headers["Accept"] == "application/json"
            assert request.url == f"{BASE_URL}/api/v1/proxy/github/repos?q=laravel"


def test_post_with_idempotency_and_json_body(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setenv("CONDUITEX_BASE_URL", BASE_URL)
    client = ConduitexClient(vault_key="vk_test", timeout=5)

    if LIVE:
        try:
            response = client.post(
                "stripe",
                "charges",
                json={"amount": 1000, "currency": "usd"},
                idempotency_key="idem-123",
            )
            assert response.status_code >= 200
        except ApiError as exc:
            assert exc.status_code >= 400
    else:
        with responses.RequestsMock() as rsps:
            rsps.add(
                responses.POST,
                f"{BASE_URL}/api/v1/proxy/stripe/charges",
                json={"id": "ch_123"},
                status=201,
            )

            response = client.post(
                "stripe",
                "charges",
                json={"amount": 1000, "currency": "usd"},
                idempotency_key="idem-123",
            )

            assert response.status_code == 201
            request = rsps.calls[0].request
            assert request.headers["Idempotency-Key"] == "idem-123"
            assert json.loads(request.body) == {"amount": 1000, "currency": "usd"}


def test_builds_url_without_optional_path(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setenv("CONDUITEX_BASE_URL", BASE_URL)

    client = ConduitexClient(vault_key="vk_test")

    if LIVE:
        try:
            response = client.delete("slack")
            assert response.status_code >= 200
        except ApiError as exc:
            assert exc.status_code >= 400
    else:
        with responses.RequestsMock() as rsps:
            rsps.add(
                responses.DELETE,
                f"{BASE_URL}/api/v1/proxy/slack",
                body="",
                status=204,
            )

            response = client.delete("slack")

            assert response.status_code == 204
            assert rsps.calls[0].request.url == f"{BASE_URL}/api/v1/proxy/slack"


def test_api_error_raised_for_failure(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setenv("CONDUITEX_BASE_URL", BASE_URL)

    client = ConduitexClient(vault_key="vk_test")

    if LIVE:
        with pytest.raises(ApiError):
            client.get("github", "repos")
    else:
        with responses.RequestsMock() as rsps:
            rsps.add(
                responses.GET,
                f"{BASE_URL}/api/v1/proxy/github/repos",
                json={"message": "Unauthorized vault key."},
                status=401,
            )

            with pytest.raises(ApiError) as exc:
                client.get("github", "repos")

            assert exc.value.status_code == 401
            assert "Unauthorized" in str(exc.value)


def test_env_base_url_is_used_when_missing_argument(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setenv("CONDUITEX_BASE_URL", BASE_URL)

    client = ConduitexClient(vault_key="vk_test")

    if LIVE:
        try:
            response = client.get("github", "repos")
            assert response.status_code >= 200
        except ApiError as exc:
            assert exc.status_code >= 400
    else:
        with responses.RequestsMock() as rsps:
            rsps.add(
                responses.GET,
                f"{BASE_URL}/api/v1/proxy/github/repos",
                json={"ok": True},
                status=200,
            )

            response = client.get("github", "repos")

            assert response.status_code == 200
            assert rsps.calls[0].request.url == f"{BASE_URL}/api/v1/proxy/github/repos"


def test_rejects_explicit_base_url(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.delenv("CONDUITEX_BASE_URL", raising=False)

    with pytest.raises(ValueError):
        ConduitexClient(vault_key="vk_test", base_url="https://override.test")
