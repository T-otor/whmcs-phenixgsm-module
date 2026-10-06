"""Pydantic models for PHENIX API responses."""
from pydantic import BaseModel
from typing import Any


# ============================================================
# Models - Offers / Catalogue
# ============================================================

class ProductParamModel(BaseModel):
    code: str
    libelle: str
    valueType: str | None = None


class ProductModel(BaseModel):
    operateur: str
    code: str
    libelle: str
    description: str | None = None
    hasParams: bool
    paramList: list[ProductParamModel] = []


class GsmProfileModel(BaseModel):
    id: str
    operateur: str
    code: str
    libelle: str
    produits: list[dict[str, Any]]


# ============================================================
# Models - Line State
# ============================================================

class ProduitActifModel(BaseModel):
    code: str
    produitParams: list[dict[str, Any]] = []


class LineStateModel(BaseModel):
    msisdn: str
    simsn: str | None = None
    imsi: str | None = None
    codeClient: str | None = None
    nomClient: str | None = None
    siteLibelle: str | None = None
    etat: str  # Active, Suspended, Deleted
    operateur: str
    hno: str | None = None
    partenaireId: str | None = None
    dateActivation: str | None = None
    dateResiliation: str | None = None
    gsmProfilId: Any | None = None
    numAbo: str | None = None
    rio: str | None = None
    etatLibelle: str | None = None
    forfaitGsmId: int | None = None
    forfaitGsmCode: str | None = None
    isDataOnly: bool | None = None
    hasIpFixe: bool | None = None
    ipFixe: str | None = None
    radiusLogin: str | None = None
    radiusPassword: str | None = None
    typeSim: str | None = None
    codeTarifAchat: str | None = None
    apn: str | None = None
    codeApn: str | None = None
    dureeEngagement: int | None = None
    dateFinEngagement: str | None = None
    produits: list[ProduitActifModel] = []


class GsmRequestModel(BaseModel):
    id: int
    operation: str
    operateur: str
    partenaireId: str
    msisdn: str
    typeSim: str
    simsn: str | None = None
    imsi: str | None = None
    codeClient: str | None = None
    nomClient: str | None = None
    dateCreation: str | None = None
    dateEnvoi: str | None = None
    siteLibelle: str | None = None
    rio: str | None = None
    portaDate: str | None = None
    gsmProfilId: Any | None = None
    forfaitGsmId: int | None = None
    forfaitGsmCode: str | None = None
    hasMsisdn: bool | None = None
    hasIpFixe: bool | None = None
    isDataOnly: bool | None = None
    codeTarifAchat: str | None = None
    etat: str  # "0"=un-sent, "1"=sent
    statut: str  # OK, NOK, ""=in progress
    portaEtat: Any | None = None
    portaStatut: str | None = None
    produits: list[dict[str, Any]] = []


# ============================================================
# Models - Consumption (SDTR + CDR)
# ============================================================

class SdtrZoneModel(BaseModel):
    msisdn: str
    codeZone: str
    libelleZone: str
    initialValue: int | None = None
    initialValueText: str | None = None
    remainingValue: int | None = None
    remainingValueText: str | None = None
    usedValue: int | None = None
    usedValueText: str | None = None


class SdtrConsoResponse(BaseModel):
    msisdn: str
    zones: list[SdtrZoneModel]
    totalRemaining: str | None = None
    totalRemainingBytes: int | None = None
    totalUsed: str | None = None
    totalUsedBytes: int | None = None
    totalInitial: str | None = None
    totalInitialBytes: int | None = None


class CdrRecordModel(BaseModel):
    msisdn: str
    moisAnnee: str
    type: str
    code: str | int | float
    libelle: str
    conso: str | int | float | None = None
    consoTxt: str | None = None


class CdrDailyModel(CdrRecordModel):
    dateJour: str | None = None


class CdrDetailModel(BaseModel):
    Msisdn: str
    Operateur: str
    RecordDate: str
    Duration: float
    DialedNumber: str | None = None
    CallType: str | None = None
    CallTypeLibelle: str | None = None
    CountryCode: str | None = None
    Location: str | None = None
    CountrySrc: str | None = None
    CountryDes: str | None = None
    Charge: str | None = None
    NumeroImei: str | None = None


# ============================================================
# Models - eSIM QR Code
# ============================================================

class ESimActivationCodeModel(BaseModel):
    simsn: str
    msisdn: str
    activationCode: str
    confirmationCode: str


class ESimDetailsModel(BaseModel):
    codeClient: str | None = None
    puk1: str | None = None
    pin1: str | None = None
    imsi: str | None = None
    etat: str | None = None
    msisdn: str | None = None
    activationCode: str | None = None
    confirmationCode: str | None = None
    nsce_simSn: str | None = None


# ============================================================
# Models - Recharge Data
# ============================================================

class DataRechargeModel(BaseModel):
    operateur: str
    codeZone: str
    codeRecharge: str
    unite: str
    volumeData: int | None = None
    volumeDataEnMo: int | None = None
    volumeDataEnGo: float | None = None
    volumeDataText: str | None = None
    isMontant: bool = False


class DataZoneModel(BaseModel):
    operateur: str
    codeZone: str
    libelle: str
