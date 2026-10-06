"""SIM/eSIM inventory and customer lookup routes from PHENIX GSM API v2.9."""
from fastapi import APIRouter, HTTPException, Query, Body
from app.services.phenix_client import PhenixApiError, phenix_client
from config import settings

router = APIRouter()


@router.post("/stock/orders/sims")
async def order_sims(order: dict = Body(...)):
    payload = {**order, "partenaireId": settings.PARTENAIRE_ID}
    try:
        return await phenix_client.post("/GsmApi/SaveCommandeSim", json_data=payload)
    except PhenixApiError:
        raise
    except Exception as exc:
        raise HTTPException(502, "Unable to place SIM order") from exc


@router.post("/stock/orders/esims")
async def order_esims(order: dict = Body(...)):
    payload = {**order, "partenaireId": settings.PARTENAIRE_ID}
    try:
        return await phenix_client.post("/GsmApi/SaveCommandeEsim", json_data=payload)
    except PhenixApiError:
        raise
    except Exception as exc:
        raise HTTPException(502, "Unable to place eSIM order") from exc


@router.delete("/stock/orders/sims/{order_id}")
async def delete_sim_order(order_id: int):
    try:
        return await phenix_client.get("/GsmApi/DeleteCommandeSim", params={"partenaireId": settings.PARTENAIRE_ID, "id": order_id})
    except PhenixApiError:
        raise
    except Exception as exc:
        raise HTTPException(502, "Unable to delete SIM order") from exc


@router.get("/stock/sims")
async def list_sims(sim_sn: str | None = Query(None, alias="simSN"), imsi: str | None = None):
    params = {"partenaireId": settings.PARTENAIRE_ID}
    if sim_sn:
        params["simSN"] = sim_sn
    if imsi:
        params["imsi"] = imsi
    try:
        return await phenix_client.get("/GsmApi/V2/GetInfoSimList", params=params)
    except PhenixApiError:
        raise
    except Exception as exc:
        raise HTTPException(502, "Unable to retrieve SIM inventory") from exc


@router.get("/stock/sims/{sim_sn}")
async def get_sim(sim_sn: str):
    try:
        return await phenix_client.get("/GsmApi/V2/GetInfoSim", params={"partenaireId": settings.PARTENAIRE_ID, "simSN": sim_sn})
    except PhenixApiError:
        raise
    except Exception as exc:
        raise HTTPException(502, "Unable to retrieve SIM details") from exc


@router.get("/stock/orders/{order_id}")
async def get_sim_order(order_id: int):
    try:
        return await phenix_client.get("/GsmApi/GetCommandeSimDetails", params={"partenaireId": settings.PARTENAIRE_ID, "commandeSimId": order_id})
    except PhenixApiError:
        raise
    except Exception as exc:
        raise HTTPException(502, "Unable to retrieve SIM order") from exc


@router.get("/stock/orders/{order_id}/sims")
async def get_order_sims(order_id: int):
    try:
        return await phenix_client.get("/GsmApi/GetStockSimByCommandeSim", params={"partenaireId": settings.PARTENAIRE_ID, "commandeSimId": order_id})
    except PhenixApiError:
        raise
    except Exception as exc:
        raise HTTPException(502, "Unable to retrieve SIM order stock") from exc


@router.get("/stock/esims/{sim_sn}")
async def get_esim(sim_sn: str, operator: str = Query(..., description="ORANGE or SFR")):
    try:
        return await phenix_client.get("/GsmApi/GetStockESim", params={"partenaireId": settings.PARTENAIRE_ID, "simsn": sim_sn, "operateur": operator})
    except PhenixApiError:
        raise
    except Exception as exc:
        raise HTTPException(502, "Unable to retrieve eSIM details") from exc


@router.post("/customers/search")
async def search_customers(filters: dict):
    try:
        return await phenix_client.post("/GsmApi/SearchCustomer", json_data=filters)
    except PhenixApiError:
        raise
    except Exception as exc:
        raise HTTPException(502, "Unable to search customers") from exc
