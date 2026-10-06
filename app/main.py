#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
PHENIX Centralizer API - Entrée principale
API REST pour centraliser les visualisations des forfaits GSM PHENIX.
Intégration WHMCS via module hook + dashboard.

Usage: uvicorn main:app --host 0.0.0.0 --port 8000 --reload
"""

from fastapi import FastAPI
from contextlib import asynccontextmanager
import logging
import httpx

from config import settings
from app.routes import offers, lines, consumption, esim, stock
from app.core.auth import require_api_key
from app.services.phenix_client import PhenixApiError
from fastapi import Depends, Request
from fastapi.responses import JSONResponse

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("phenix-centralizer")


@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("PHENIX Centralizer API starting...")
    logger.info(f"Base URL: {settings.PHENIX_BASE_URL}")
    yield
    logger.info("PHENIX Centralizer API stopping...")


app = FastAPI(
    title="PHENIX Centralizer API",
    description="API REST pour centraliser la visualisation des forfaits GSM PHENIX. Intégration WHMCS.",
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
    lifespan=lifespan,
)

@app.exception_handler(PhenixApiError)
async def phenix_api_error_handler(request: Request, exc: PhenixApiError):
    status = exc.status_code if exc.status_code in {400, 401, 403, 404, 409, 422, 429} else 502
    try:
        detail = exc.body and __import__("json").loads(exc.body) or "PHENIX API request failed"
    except (ValueError, TypeError):
        detail = exc.body or "PHENIX API request failed"
    return JSONResponse(status_code=status, content={"detail": detail})


@app.exception_handler(httpx.TimeoutException)
async def phenix_timeout_handler(request: Request, exc: Exception):
    return JSONResponse(status_code=504, content={"detail": "PHENIX API timeout"})

app.include_router(offers.router, prefix="/api", tags=["Catalogue forfaits"], dependencies=[Depends(require_api_key)])
app.include_router(lines.router, prefix="/api", tags=["Gestion des lignes"], dependencies=[Depends(require_api_key)])
app.include_router(consumption.router, prefix="/api", tags=["Consommation & Stats"], dependencies=[Depends(require_api_key)])
app.include_router(esim.router, prefix="/api", tags=["eSIM QR Codes"], dependencies=[Depends(require_api_key)])
app.include_router(stock.router, prefix="/api", tags=["Stocks SIM/eSIM et clients"], dependencies=[Depends(require_api_key)])


@app.get("/health")
async def health_check():
    return {
        "status": "ok",
        "version": app.version,
    }
