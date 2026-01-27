from __future__ import annotations

from typing import Any, Optional


class ConduitexError(Exception):
    """Base exception for Conduitex SDK errors."""


class ApiError(ConduitexError):
    """Raised when the Conduitex API returns an error response."""

    def __init__(self, status_code: int, message: str, data: Optional[Any] = None) -> None:
        super().__init__(message)
        self.status_code = status_code
        self.data = data

    def __repr__(self) -> str:
        return f"<ApiError status={self.status_code} message={self.args[0]!r}>"
