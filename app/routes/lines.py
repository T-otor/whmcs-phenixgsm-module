from app.services.phenix_client import PhenixApiError
"""Routes for line management endpoints."""
from fastapi import APIRouter, HTTPException, Query, Body
from typing import Any

router = APIRouter()


@router.get("/lines/{msisdn}")
async def get_line_state(
    msisdn: str,
    enrich: bool = Query(True, description="Enrich products with catalog labels"),
):
    """Consulte l'état d'une ligne GSM par MSISDN avec son forfait actif et produits."""
    from config import settings
    from app.services.phenix_client import phenix_client
    from app.models.phenix_models import LineStateModel

    try:
        line = await phenix_client.get(
            "/GsmApi/V2/MsisdnConsult",
            params={"msisdn": msisdn, "partenaireId": settings.PARTENAIRE_ID},
        )

        return {
            "line": LineStateModel(**line),
            "produits_enrichis": line.get("produits", []) if enrich else None,
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/lines")
async def get_all_lines():
    """Liste du parc complet des lignes GSM."""
    from config import settings
    from app.services.phenix_client import phenix_client
    from app.models.phenix_models import LineStateModel

    try:
        lines = await phenix_client.get(
            "/GsmApi/V2/MsisdnConsultAll",
            params={"partenaireId": settings.PARTENAIRE_ID},
        )

        return {
            "count": len(lines),
            "lines": [LineStateModel(**l) for l in lines],
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


async def _gsm_post(endpoint: str, payload: dict[str, Any]):
    from config import settings
    from app.services.phenix_client import phenix_client
    payload = {**payload, "partenaireId": settings.PARTENAIRE_ID}
    return await phenix_client.post(endpoint, json_data=payload)


@router.post("/lines/activate")
async def activate_line(payload: dict[str, Any] = Body(...)):
    """Create a new line (CreateNA) or port in a line (CreateNP)."""
    if payload.get("operation") not in {"CreateNA", "CreateNP"}:
        raise HTTPException(422, "operation must be CreateNA or CreateNP")
    if payload.get("operation") == "CreateNP" and not all(payload.get(k) for k in ("msisdn", "rio", "portaDate")):
        raise HTTPException(422, "CreateNP requires msisdn, rio and portaDate")
    try:
        return await _gsm_post("/GsmApi/V2/MsisdnActivate", payload)
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.post("/lines/{msisdn}/options")
async def modify_line_options(msisdn: str, payload: dict[str, Any] = Body(...)):
    try:
        return await _gsm_post("/GsmApi/V2/MsisdnModifyOptions", {**payload, "msisdn": msisdn})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.post("/lines/{msisdn}/options/changes")
async def add_delete_line_options(msisdn: str, payload: dict[str, Any] = Body(...)):
    try:
        return await _gsm_post("/GsmApi/V2/MsisdnAddDeleteOptions", {**payload, "msisdn": msisdn})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.post("/lines/{msisdn}/suspend")
async def suspend_line(msisdn: str, payload: dict[str, Any] = Body(default={})):
    try:
        return await _gsm_post("/GsmApi/V2/MsisdnSuspend", {**payload, "msisdn": msisdn})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.post("/lines/{msisdn}/resume")
async def resume_line(msisdn: str, payload: dict[str, Any] = Body(default={})):
    try:
        return await _gsm_post("/GsmApi/V2/MsisdnResume", {**payload, "msisdn": msisdn})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.post("/lines/{msisdn}/cancel")
async def cancel_line(msisdn: str, payload: dict[str, Any] = Body(default={})):
    try:
        return await _gsm_post("/GsmApi/V2/MsisdnDelete", {**payload, "msisdn": msisdn})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.post("/lines/{msisdn}/sim-swap")
async def sim_swap(msisdn: str, payload: dict[str, Any] = Body(...)):
    try:
        return await _gsm_post("/GsmApi/V2/SimSwap", {**payload, "msisdn": msisdn})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.get("/lines/{msisdn}/rio")
async def get_line_rio(msisdn: str):
    from app.services.phenix_client import phenix_client
    from config import settings
    try:
        return await phenix_client.get("/GsmApi/V2/MsisdnConsultRio", params={"partenaireId": settings.PARTENAIRE_ID, "msisdn": msisdn})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.get("/portabilities/in")
async def list_porta_in():
    from app.services.phenix_client import phenix_client
    from config import settings
    try:
        return await phenix_client.get("/GsmApi/V2/ConsulterPortaInList", params={"partenaireId": settings.PARTENAIRE_ID})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.get("/portabilities/out")
async def list_porta_out():
    from app.services.phenix_client import phenix_client
    from config import settings
    try:
        return await phenix_client.get("/GsmApi/V2/ConsulterPortaOutList", params={"partenaireId": settings.PARTENAIRE_ID})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.post("/requests/{request_id}/cancel")
async def cancel_pending_request(request_id: int):
    try:
        return await _gsm_post("/GsmApi/DeleteGsmCommande", {"id": request_id})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.post("/portabilities/in/{request_id}/cancel")
async def cancel_porta_in(request_id: int):
    try:
        return await _gsm_post("/GsmApi/CancelPortaIN", {"id": request_id})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.get("/requests/{request_id}")
async def get_gsm_request(request_id: str):
    """Consulte le statut d'une requête GSM (activation/modification) par ID."""
    from config import settings
    from app.services.phenix_client import phenix_client
    from app.models.phenix_models import GsmRequestModel

    try:
        req = await phenix_client.get(
            "/GsmApi/V2/GsmRequestConsult",
            params={"id": request_id, "partenaireId": settings.PARTENAIRE_ID},
        )

        return {
            "request": GsmRequestModel(**req),
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/lines/{msisdn}/switch-operator")
async def switch_operator(msisdn: str, payload: dict[str, Any] = Body(...)):
    if payload.get("hnoNew") not in {"ORANGE", "SFR"}:
        raise HTTPException(422, "hnoNew must be ORANGE or SFR")
    try:
        return await _gsm_post("/GsmApi/V2/SwitchOPE", {**payload, "msisdn": msisdn})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.post("/lines/{msisdn}/cutoff/unlock")
async def unlock_sfr_cutoff(msisdn: str, payload: dict[str, Any] = Body(...)):
    if payload.get("typeOperation") not in {"DeleteCutOffMois", "DeleteCutOffAll"}:
        raise HTTPException(422, "typeOperation must be DeleteCutOffMois or DeleteCutOffAll")
    try:
        return await _gsm_post("/GsmApi/V2/MsisdnDeleteCutOff", {**payload, "msisdn": msisdn})
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.post("/lines/{msisdn}/apn")
async def switch_apn(msisdn: str, payload: dict[str, Any] = Body(...)):
    required = ("typeSimNew", "codeApnNew")
    if not all(payload.get(key) for key in required):
        raise HTTPException(422, "typeSimNew and codeApnNew are required")
    from app.services.phenix_client import phenix_client
    from config import settings
    params = {**payload, "partenaireId": settings.PARTENAIRE_ID, "msisdn": msisdn}
    try:
        return await phenix_client.get("/GsmApi/V2/SwitchApn", params=params)
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(500, str(e))


@router.get("/lines/status/{msisdn}")
async def get_line_status(msisdn: str):
    """Statut simplifié d'une ligne (utile pour le dashboard WHMCS)."""
    from config import settings
    from app.services.phenix_client import phenix_client

    try:
        line = await phenix_client.get(
            "/GsmApi/V2/MsisdnConsult",
            params={"msisdn": msisdn, "partenaireId": settings.PARTENAIRE_ID},
        )

        return {
            "msisdn": msisdn,
            "etat": line.get("etat"),
            "etatLibelle": line.get("etatLibelle"),
            "forfait": line.get("forfaitGsmCode"),
            "actifDepuis": line.get("dateActivation"),
            "operateur": line.get("operateur"),
            "typeSim": line.get("typeSim"),
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
