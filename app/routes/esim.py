from app.services.phenix_client import PhenixApiError
"""Routes for eSIM QR Code endpoints."""
from fastapi import APIRouter, HTTPException, Query
from fastapi.responses import Response

router = APIRouter()


@router.get("/esim/qrcode/{msisdn}")
async def get_esim_qr_code(msisdn: str):
    """Télécharge le QR code d'activation eSIM (format byte) pour un MSISDN."""
    from config import settings
    from app.services.phenix_client import phenix_client

    try:
        qr_bytes = await phenix_client.download(
            "/GsmApi/V2/DownloadEsimQRCodeByMsisdn",
            params={
                "msisdn": msisdn,
                "partenaireId": settings.PARTENAIRE_ID,
            },
        )

        return Response(content=qr_bytes, media_type="application/pdf", headers={"Content-Disposition": f'attachment; filename="esim-{msisdn}.pdf"'})
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/esim/qrcode/text")
async def get_esim_qr_text(
    msisdn: str = Query(...),
):
    """Récupère le code d'activation eSIM au format texte (pour générer son propre QR)."""
    from config import settings
    from app.services.phenix_client import phenix_client
    from app.models.phenix_models import ESimActivationCodeModel

    try:
        data = await phenix_client.get(
            "/GsmApi/GetEsimActivationCodeByMsisdn",
            params={
                "msisdn": msisdn,
                "partenaireId": settings.PARTENAIRE_ID,
            },
        )

        return {
            "activationCode": ESimActivationCodeModel(**data).model_dump(),
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/esim/qrcode-by-sim/{sim_sn}")
async def get_esim_qr_by_sim(sim_sn: str):
    from config import settings
    from app.services.phenix_client import phenix_client
    try:
        content = await phenix_client.download(
            "/GsmApi/V2/DownloadEsimQRCodeBySimSN",
            params={"simSn": sim_sn, "partenaireId": settings.PARTENAIRE_ID},
        )
        return Response(content=content, media_type="application/pdf", headers={"Content-Disposition": f'attachment; filename="esim-{sim_sn}.pdf"'})
    except PhenixApiError:
        raise
    except Exception as exc:
        raise HTTPException(status_code=502, detail="Unable to retrieve eSIM QR code") from exc


@router.get("/esim/details/{simsn}")
async def get_esim_details(simsn: str, operator: str = Query("ORANGE", description="ORANGE or SFR")):
    """Détails complets d'une eSIM par SIM SN."""
    from config import settings
    from app.services.phenix_client import phenix_client

    try:
        # Get details
        data = await phenix_client.get(
            "/GsmApi/GetStockESim",
            params={
                "partenaireId": settings.PARTENAIRE_ID,
                "simsn": simsn,
                "operateur": operator,
            },
        )

        return {
            "esim": data,
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/esim/recharge")
async def recharge_esim_data(
    msisdn: str = Query(...),
    volume: str = Query("1024", description="Volume data en MO"),
    zone: str = Query("ZoneA", description="Code zone (ex: ZoneC)"),
    code_recharge: str | None = Query(None, description="Code fourni par le catalogue de recharges"),
):
    """Recharge un volume data pour une ligne GSM."""
    from config import settings
    from app.services.phenix_client import phenix_client

    try:
        result = await phenix_client.post(
            "/GsmApi/V2/MsisdnAddDataRecharge",
            json_data={
                "partenaireId": settings.PARTENAIRE_ID,
                "msisdn": msisdn,
                "volumeDataEnMo": volume,
                "codeZone": zone,
                **({"codeRecharge": code_recharge} if code_recharge else {}),
            },
        )

        return {
            "success": True,
            "recharge": result,
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/esim/recharges/{msisdn}")
async def get_line_recharges(msisdn: str, zone: str | None = Query(None)):
    from app.services.phenix_client import phenix_client
    params = {"msisdn": msisdn}
    if zone:
        params["codeZone"] = zone
    try:
        return await phenix_client.get("/GsmApi/V2/GetDataRechargesByMsisdn", params=params)
    except PhenixApiError:
        raise
    except Exception as exc:
        raise HTTPException(status_code=502, detail="Unable to retrieve recharge options") from exc
