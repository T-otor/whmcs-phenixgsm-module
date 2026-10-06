"""HTTP client wrapper for PHENIX API."""
import httpx
from config import settings
from app.core.auth import auth_manager
from typing import Optional


class PhenixClient:
    """Centralized HTTP client for all PHENIX API calls with auth injection."""

    def __init__(self):
        self.base_url = settings.PHENIX_BASE_URL
        self.partenaire_id = settings.PARTENAIRE_ID

    async def get(self, endpoint: str, params: Optional[dict] = None) -> dict:
        """Execute GET request with auto auth."""
        url = f"{self.base_url}{endpoint}"
        token = await auth_manager.get_token()
        headers = {
            "Authorization": f"Bearer {token}",
            "Accept": "application/json",
        }
        async with httpx.AsyncClient(timeout=settings.PHENIX_TIMEOUT) as client:
            resp = await client.get(url, headers=headers, params=params)
            if resp.status_code == 401:
                auth_manager._token = None
                headers["Authorization"] = f"Bearer {await auth_manager.get_token()}"
                resp = await client.get(url, headers=headers, params=params)
        return self._decode(resp)

    async def post(self, endpoint: str, json_data: dict) -> dict:
        """Execute POST request with auto auth."""
        url = f"{self.base_url}{endpoint}"
        token = await auth_manager.get_token()
        headers = {
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json",
            "Accept": "application/json",
        }
        async with httpx.AsyncClient(timeout=settings.PHENIX_TIMEOUT) as client:
            resp = await client.post(url, headers=headers, json=json_data)
            if resp.status_code == 401:
                auth_manager._token = None
                headers["Authorization"] = f"Bearer {await auth_manager.get_token()}"
                resp = await client.post(url, headers=headers, json=json_data)
        return self._decode(resp)

    async def download(self, endpoint: str, params: Optional[dict] = None) -> bytes:
        """Download binary file (e.g. eSIM QR code)."""
        url = f"{self.base_url}{endpoint}"
        token = await auth_manager.get_token()
        headers = {
            "Authorization": f"Bearer {token}",
            "Accept": "application/octet-stream",
        }
        async with httpx.AsyncClient(timeout=settings.PHENIX_TIMEOUT) as client:
            resp = await client.get(url, headers=headers, params=params)
            if resp.status_code == 401:
                auth_manager._token = None
                headers["Authorization"] = f"Bearer {await auth_manager.get_token()}"
                resp = await client.get(url, headers=headers, params=params)
        if resp.status_code >= 400:
            raise PhenixApiError(resp.status_code, resp.text)
        return resp.content

    @staticmethod
    def _decode(resp: httpx.Response):
        if resp.status_code >= 400:
            raise PhenixApiError(resp.status_code, resp.text)
        if not resp.content:
            return {"success": True}
        try:
            return resp.json()
        except ValueError:
            return {"content": resp.text}


class PhenixApiError(Exception):
    def __init__(self, status_code: int, body: str):
        self.status_code = status_code
        self.body = body
        super().__init__(f"PHENIX API returned {status_code}")


# Singleton instance
phenix_client = PhenixClient()
