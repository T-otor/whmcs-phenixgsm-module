from app.services.phenix_client import PhenixApiError
"""Routes for catalogue/offers endpoints."""
from fastapi import APIRouter, HTTPException, Query
from typing import Optional

router = APIRouter()


@router.get("/offers")
async def get_offers(
    operator: str = Query("ORANGE", description="Opérateur : ORANGE, SFR, BTBD"),
):
    """Récupère le catalogue des produits/offres d'un opérateur avec les profils techniques."""
    from config import settings
    from app.services.phenix_client import phenix_client
    from app.models.phenix_models import ProductModel

    try:
        # Load product catalog for this operator
        produits = await phenix_client.get(
            "/GsmApi/V2/GetGsmProduitsByOperator",
            params={"partenaireId": settings.PARTENAIRE_ID, "operateur": operator},
        )

        return {
            "operator": operator,
            "products": produits,
        }
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/offers/profiles/{profile_id}")
async def get_profile_offers(profile_id: str):
    """Récupère les détails d'un profil technique (offre pré-configurée) avec ses produits."""
    from config import settings
    from app.services.phenix_client import phenix_client
    from app.models.phenix_models import GsmProfileModel

    try:
        profile = await phenix_client.get(
            "/GsmApi/GetGsmProfilById",
            params={"id": profile_id},
        )

        return {
            "profile": GsmProfileModel(**profile),
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/offers/zones/{operator}")
async def get_data_zones(operator: str):
    """Liste des zones de recharge data par opérateur."""
    from app.services.phenix_client import phenix_client

    try:
        zones = await phenix_client.get(
            "/GsmApi/V2/GetZonesByOperator",
            params={"operateur": operator},
        )

        return {
            "operator": operator,
            "zones": zones,
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/offers/recharges/{operator}")
async def get_data_recharges(
    operator: str,
    zone: Optional[str] = Query(None, description="Code zone (ex: ZoneA)"),
):
    """Liste des codes de recharge data par opérateur."""
    from app.services.phenix_client import phenix_client

    try:
        params = {"operateur": operator}
        if zone:
            params["codeZone"] = zone

        recharges = await phenix_client.get(
            "/GsmApi/V2/GetDataRechargesByOperator",
            params=params,
        )

        return {
            "operator": operator,
            "zone": zone or "toutes",
            "recharges": recharges,
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
