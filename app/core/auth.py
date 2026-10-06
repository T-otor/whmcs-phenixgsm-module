"""OAuth2 token management for PHENIX API with auto-refresh."""
import time
import asyncio
import hmac
from fastapi import HTTPException, Security
from fastapi.security import APIKeyHeader
import httpx
from config import settings


class PhenixAuth:
    """Manages OAuth2 token lifecycle with automatic caching and refresh."""

    def __init__(self):
        self._token: str | None = None
        self._expires_at: float = 0.0
        self._lock = asyncio.Lock()

    @property
    def token(self) -> str | None:
        return self._token

    @property
    def is_expired(self) -> bool:
        if not self._token:
            return True
        return time.time() >= (self._expires_at - settings.TOKEN_TTL_BUFFER)

    async def refresh_token(self) -> str:
        """Fetch a new OAuth access token from PHENIX Auth API."""
        url = f"{settings.PHENIX_BASE_URL}/Auth/authenticate"
        payload = {
            "username": settings.PHENIX_USERNAME,
            "password": settings.PHENIX_PASSWORD,
        }
        async with httpx.AsyncClient(timeout=30) as client:
            resp = await client.post(url, json=payload)
            resp.raise_for_status()
            data = resp.json()

        self._token = data["access_token"]
        self._expires_at = time.time() + int(data["expires_in"])
        return self._token

    async def get_token(self) -> str:
        """Get current token, refreshing if needed."""
        if self.is_expired:
            async with self._lock:
                if self.is_expired:
                    return await self.refresh_token()
        return self._token


auth_manager = PhenixAuth()

_api_key_header = APIKeyHeader(name="X-API-Key", auto_error=False)


async def require_api_key(api_key: str | None = Security(_api_key_header)):
    """Authenticate WHMCS/integrators before allowing any GSM operation."""
    if not api_key or not hmac.compare_digest(api_key, settings.API_KEY):
        raise HTTPException(status_code=401, detail="Invalid or missing API key")
