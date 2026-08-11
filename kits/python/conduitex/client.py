from __future__ import annotations

import os
from typing import Any, Mapping, MutableMapping, Optional

import requests

from .errors import ApiError, ConduitexError
from .response import ConduitexResponse


class ConduitexClient:
    """Client for interacting with the Conduitex gateway runtime proxy API."""

    def __init__(
        self,
        vault_key: Optional[str] = None,
        api_version: str = "v1",
        timeout: float = 30.0,
        session: Optional[requests.Session] = None,
        base_url: Optional[str] = None,
    ) -> None:
        if base_url:
            raise ValueError("Base URL must be configured via CONDUITEX_BASE_URL.")

        resolved_base = (
            os.environ.get("CONDUITEX_BASE_URL")
            or "https://api.conduitex.com"
        )

        if not resolved_base:
            raise ValueError("CONDUITEX_BASE_URL is required.")

        if not vault_key:
            raise ValueError("vault_key is required")

        self.base_url = resolved_base.rstrip("/")
        self.api_version = api_version.strip("/ ")
        self.timeout = timeout
        self.session = session or requests.Session()
        self.session.headers.update(
            {
                "X-Vault-Key": vault_key,
                "Accept": "application/json",
            },
        )

    def request(
        self,
        service_slug: str,
        path: Optional[str] = None,
        *,
        method: str = "GET",
        params: Optional[Mapping[str, Any]] = None,
        json: Any = None,
        data: Any = None,
        headers: Optional[Mapping[str, str]] = None,
        idempotency_key: Optional[str] = None,
        timeout: Optional[float] = None,
    ) -> ConduitexResponse:
        url = self._build_url(service_slug, path)
        merged_headers: MutableMapping[str, str] = {**self.session.headers, **(headers or {})}

        if idempotency_key:
            merged_headers["Idempotency-Key"] = idempotency_key

        try:
            response = self.session.request(
                method=method.upper(),
                url=url,
                params=params or None,
                json=json if json is not None else None,
                data=data if json is None else None,
                headers=merged_headers,
                timeout=timeout or self.timeout,
            )
        except requests.RequestException as exc:
            raise ConduitexError(str(exc)) from exc

        conduit_response = ConduitexResponse(response)

        if response.status_code >= 400:
            message = self._extract_error_message(response)
            raise ApiError(response.status_code, message, data=conduit_response)

        return conduit_response

    def get(
        self,
        service_slug: str,
        path: Optional[str] = None,
        *,
        params: Optional[Mapping[str, Any]] = None,
        headers: Optional[Mapping[str, str]] = None,
        idempotency_key: Optional[str] = None,
        timeout: Optional[float] = None,
    ) -> ConduitexResponse:
        return self.request(
            service_slug,
            path,
            method="GET",
            params=params,
            headers=headers,
            idempotency_key=idempotency_key,
            timeout=timeout,
        )

    def post(
        self,
        service_slug: str,
        path: Optional[str] = None,
        *,
        json: Any = None,
        data: Any = None,
        params: Optional[Mapping[str, Any]] = None,
        headers: Optional[Mapping[str, str]] = None,
        idempotency_key: Optional[str] = None,
        timeout: Optional[float] = None,
    ) -> ConduitexResponse:
        return self.request(
            service_slug,
            path,
            method="POST",
            params=params,
            json=json,
            data=data,
            headers=headers,
            idempotency_key=idempotency_key,
            timeout=timeout,
        )

    def put(
        self,
        service_slug: str,
        path: Optional[str] = None,
        *,
        json: Any = None,
        data: Any = None,
        params: Optional[Mapping[str, Any]] = None,
        headers: Optional[Mapping[str, str]] = None,
        idempotency_key: Optional[str] = None,
        timeout: Optional[float] = None,
    ) -> ConduitexResponse:
        return self.request(
            service_slug,
            path,
            method="PUT",
            params=params,
            json=json,
            data=data,
            headers=headers,
            idempotency_key=idempotency_key,
            timeout=timeout,
        )

    def patch(
        self,
        service_slug: str,
        path: Optional[str] = None,
        *,
        json: Any = None,
        data: Any = None,
        params: Optional[Mapping[str, Any]] = None,
        headers: Optional[Mapping[str, str]] = None,
        idempotency_key: Optional[str] = None,
        timeout: Optional[float] = None,
    ) -> ConduitexResponse:
        return self.request(
            service_slug,
            path,
            method="PATCH",
            params=params,
            json=json,
            data=data,
            headers=headers,
            idempotency_key=idempotency_key,
            timeout=timeout,
        )

    def delete(
        self,
        service_slug: str,
        path: Optional[str] = None,
        *,
        params: Optional[Mapping[str, Any]] = None,
        headers: Optional[Mapping[str, str]] = None,
        idempotency_key: Optional[str] = None,
        timeout: Optional[float] = None,
    ) -> ConduitexResponse:
        return self.request(
            service_slug,
            path,
            method="DELETE",
            params=params,
            headers=headers,
            idempotency_key=idempotency_key,
            timeout=timeout,
        )

    def _build_url(self, service_slug: str, path: Optional[str]) -> str:
        trimmed_service = service_slug.strip("/ ")
        clean_path = (path or "").strip("/ ")
        base = f"{self.base_url}/api/{self.api_version}/proxy/{trimmed_service}"

        return f"{base}/{clean_path}" if clean_path else base

    def _extract_error_message(self, response: requests.Response) -> str:
        try:
            payload = response.json()
        except ValueError:
            return f"Request failed with status {response.status_code}"

        if isinstance(payload, dict):
            message = payload.get("message")
            if isinstance(message, str) and message.strip():
                return message

        return f"Request failed with status {response.status_code}"
