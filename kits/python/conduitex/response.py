from __future__ import annotations

from typing import Any, Mapping

import requests


class ConduitexResponse:
    """Wrapper around a `requests.Response` with convenience helpers."""

    def __init__(self, response: requests.Response) -> None:
        self._response = response

    @property
    def status_code(self) -> int:
        return self._response.status_code

    @property
    def headers(self) -> Mapping[str, str]:
        return self._response.headers

    @property
    def text(self) -> str:
        return self._response.text

    def json(self) -> Any:
        return self._response.json()

    def raw(self) -> bytes:
        return self._response.content

    def __repr__(self) -> str:
        return f"<ConduitexResponse [{self.status_code}]>"
