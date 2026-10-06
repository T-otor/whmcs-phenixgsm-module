from app.services.phenix_client import PhenixApiError
"""Routes for consumption stats endpoints (SDTR + CDR)."""
from fastapi import APIRouter, HTTPException, Query
import re

router = APIRouter()


@router.get("/consumption/sdtr")
async def get_sdtr_conso(
    msisdn: str = Query(..., description="Numéro de ligne GSM"),
):
    """Consommation data temps réel (SDTR) par zone. Opérateur ORANGE uniquement."""
    from config import settings
    from app.services.phenix_client import phenix_client
    try:
        raw_zones = await phenix_client.post(
            "/GsmApi/V2/SdtrConso",
            json_data={
                "partenaireId": settings.PARTENAIRE_ID,
                "msisdn": msisdn,
            },
        )

        # The v2.9 partner contract documents these values as strings, while
        # examples sometimes return numbers. Some partner responses also wrap
        # the zone list. Normalize both forms before building the response.
        if isinstance(raw_zones, dict):
            for key in ("zones", "data", "items", "result", "results"):
                if isinstance(raw_zones.get(key), list):
                    raw_zones = raw_zones[key]
                    break
            else:
                raw_zones = [raw_zones]
        if not isinstance(raw_zones, list):
            raise ValueError("Unexpected SDTR response format from the partner API")

        def fields_lower(row: dict) -> dict:
            return {str(key).lower(): value for key, value in row.items()}

        def scalar(row: dict, key: str, default=None):
            value = row.get(key.lower(), default)
            return value if value is None or isinstance(value, (str, int, float, bool)) else default

        def amount_mb(value):
            if value is None or isinstance(value, bool):
                return None
            if isinstance(value, (int, float)):
                return int(value)
            raw = str(value).strip().replace(",", ".")
            match = re.fullmatch(r"(-?\d+(?:\.\d+)?)\s*(GO|GB|MO|MB|KO|KB|O|B)?", raw, re.IGNORECASE)
            if not match:
                return None
            number = float(match.group(1))
            unit = (match.group(2) or "MO").upper()
            multiplier = {"GO": 1024, "GB": 1024, "MO": 1, "MB": 1, "KO": 1 / 1024, "KB": 1 / 1024, "O": 1 / (1024 * 1024), "B": 1 / (1024 * 1024)}[unit]
            return int(round(number * multiplier))

        zones = []
        for raw_zone in raw_zones:
            if not isinstance(raw_zone, dict):
                continue
            row = fields_lower(raw_zone)
            code_zone = str(scalar(row, "codeZone", "") or "").strip()
            label = str(scalar(row, "libelleZone", "") or code_zone or "Zone inconnue").strip()
            zones.append({
                "msisdn": str(scalar(row, "msisdn", msisdn) or msisdn).strip(),
                "codeZone": code_zone,
                "libelleZone": label,
                "initialValue": amount_mb(scalar(row, "initialValue")),
                "initialValueText": str(scalar(row, "initialValueText", "") or "").strip(),
                "remainingValue": amount_mb(scalar(row, "remainingValue")),
                "remainingValueText": str(scalar(row, "remainingValueText", "") or "").strip(),
                "usedValue": amount_mb(scalar(row, "usedValue")),
                "usedValueText": str(scalar(row, "usedValueText", "") or "").strip(),
            })

        # Calculate totals
        total_initial = sum(z["initialValue"] or 0 for z in zones)
        total_remaining = sum(z["remainingValue"] or 0 for z in zones)
        total_used = sum(z["usedValue"] or 0 for z in zones)

        return {
            "msisdn": msisdn,
            "zones": zones,
            "totalRemaining": f"{total_remaining / 1024:.1f} Go",
            "totalRemainingBytes": total_remaining,
            "totalUsed": f"{total_used / 1024:.1f} Go",
            "totalUsedBytes": total_used,
            "totalInitial": f"{total_initial / 1024:.1f} Go",
            "totalInitialBytes": total_initial,
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/consumption/cdr")
async def get_monthly_conso(
    msisdn: str = Query(..., description="Numéro de ligne GSM"),
    month: str = Query(..., description="Mois/Au format MMAAAA (ex: 102026)"),
):
    """Consommation mensuelle par type d'appel (CDR - Voice/SMS/Data)."""
    from config import settings
    from app.services.phenix_client import phenix_client

    try:
        response_data = await phenix_client.get(
            "/GsmApi/GetConsoMsisdnFromCDR",
            params={
                "partenaireId": settings.PARTENAIRE_ID,
                "msisdn": msisdn,
                "moisAnnee": month,
            },
        )

        # The partner documentation declares code/conso as strings, while its
        # examples also return numbers. Accept either representation and common
        # collection wrappers rather than rejecting a successful API response.
        records = response_data if isinstance(response_data, list) else None
        if isinstance(response_data, dict):
            for key in ("records", "data", "items", "result", "results"):
                nested = response_data.get(key)
                if isinstance(nested, list):
                    records = nested
                    break
            if records is None and response_data.get("success") is True:
                records = []
            if records is None and any(key in response_data for key in ("msisdn", "Msisdn", "type", "Type")):
                records = [response_data]
        if response_data is None:
            records = []
        if records is None:
            raise ValueError("Unexpected CDR response format from the partner API")

        normalized_records = []
        for record in records:
            if not isinstance(record, dict):
                continue
            fields = {str(key).lower(): value for key, value in record.items()}

            def scalar_field(name: str, default=None):
                value = fields.get(name.lower(), default)
                return value if value is None or isinstance(value, (str, int, float, bool)) else str(value)

            normalized_records.append({
                "msisdn": str(scalar_field("msisdn", msisdn) or msisdn).strip(),
                "moisAnnee": str(scalar_field("moisAnnee", month) or month).strip(),
                "type": str(scalar_field("type", "") or "").strip(),
                "code": scalar_field("code"),
                "libelle": str(scalar_field("libelle", "") or "").strip(),
                "conso": scalar_field("conso"),
                "consoTxt": scalar_field("consoTxt"),
            })

        return {
            "msisdn": msisdn,
            "month": month,
            "type": "CDR",
            "records": normalized_records,
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=501, detail=str(e))


@router.get("/consumption/cdr/daily")
async def get_daily_conso(
    msisdn: str = Query(...),
    month: str = Query(...),
):
    """Consommation journalière par type d'appel (CDR)."""
    from config import settings
    from app.services.phenix_client import phenix_client

    try:
        records = await phenix_client.get(
            "/GsmApi/GetConsoMsisdnByDayFromCDR",
            params={
                "partenaireId": settings.PARTENAIRE_ID,
                "msisdn": msisdn,
                "moisAnnee": month,
            },
        )

        return {
            "msisdn": msisdn,
            "month": month,
            "records": records,
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=501, detail=str(e))


@router.get("/consumption/cdr/details")
async def get_cdr_details(
    msisdn: str = Query(...),
    month: str = Query(...),
):
    """Détail des appels/SMS/Data par CDR (enregistrement par événement)."""
    from config import settings
    from app.services.phenix_client import phenix_client

    try:
        records = await phenix_client.get(
            "/GsmApi/GetMsisdnConsoDetailsByCDR",
            params={
                "partenaireId": settings.PARTENAIRE_ID,
                "msisdn": msisdn,
                "moisAnnee": month,
            },
        )

        return {
            "msisdn": msisdn,
            "month": month,
            "count": len(records),
            "records": records[:100],  # Limit to first 100
        }
    except HTTPException:
        raise
    except PhenixApiError:
        raise
    except Exception as e:
        raise HTTPException(status_code=501, detail=str(e))
