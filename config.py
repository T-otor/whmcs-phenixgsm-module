#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Config singleton using pydantic settings, loads from .env automatically."""
from pydantic_settings import BaseSettings
from pydantic import Field
from pydantic import model_validator


class Settings(BaseSettings):
    PHENIX_BASE_URL: str = "https://XXXX"
    PHENIX_USERNAME: str
    PHENIX_PASSWORD: str
    PARTENAIRE_ID: str
    API_KEY: str

    SERVER_HOST: str = "0.0.0.0"
    SERVER_PORT: int = 8000

    # Cache token TTL (seconds) - refresh 1 minute before expiry
    TOKEN_TTL_BUFFER: int = Field(default=60)
    PHENIX_TIMEOUT: float = 30.0

    @model_validator(mode="after")
    def validate_secrets(self):
        if len(self.API_KEY) < 32 or any(marker in self.API_KEY.lower() for marker in ("change_me", "changeme", "your_api_key", "generer-une-cle", "replace_with")):
            raise ValueError("API_KEY must be a unique secret of at least 32 characters")
        return self

    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"


settings = Settings()
