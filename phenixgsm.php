<?php
if (!defined("WHMCS")) die("Direct access forbidden");
require_once __DIR__ . "/phenixgsm_config.php";
require_once __DIR__ . "/phenixgsm_api.php";

function phenixgsm_TestConnection(array $params): array {
    $api = new PhenixGsmApi($params["username"], $params["username"], $params["password"]);
    if ($api->testConnection()) {
        return ["success" => true, "error" => null];
    }
    return ["success" => false, "error" => $api->getLastError()];
}

function phenixgsm_CreateAccount(array $params): string {
    $api = new PhenixGsmApi($params["username"], $params["username"], $params["password"]);
    $activationData = [
        "partenaireId" => $params["username"],
        "operateur" => $params["configoption1"] ?? "ORANGE",
        "hno" => $params["configoption1"] ?? "ORANGE",
        "operation" => $params["configoption2"] ?? "CreateNA",
        "siteLibelle" => "WHMCS Client",
        "typeSim" => $params["configoption3"] ?? "SIM",
        "codeTarifAchat" => $params["configoption4"] ?? "",
        "nomClient" => "Client WHMCS",
        "codeClient" => "WHMCS-" . $params["serviceid"],
        "hasMsisdn" => false,
        "hasIpFixe" => isset($params["configoption5"]) && $params["configoption5"] == "on",
        "isDataOnly" => isset($params["configoption6"]) && $params["configoption6"] == "on",
        "produits" => []
    ];
    if (($activationData["operation"] ?? "") === "CreateNP") {
        $activationData["hasMsisdn"] = true;
        $activationData["msisdn"] = $params["configoption7"] ?? "";
        $activationData["rio"] = $params["configoption8"] ?? "";
        $activationData["portaDate"] = $params["configoption9"] ?? "";
    }
    if (!in_array($activationData["typeSim"], ["ESIM","ESIM15D","ESIM15D_DataOnly"])) {
        $activationData["simsn"] = $params["configoption10"] ?? "";
        $activationData["imsi"] = $params["configoption11"] ?? "";
    }
    if (isset($params["configoption12"])) {
        $activationData["forfaitGsmCode"] = $params["configoption12"];
    }
    $result = $api->msisdnActivate($activationData);
    if (!$result) return "Erreur: " . $api->getLastError();
    $msg = "Activation demandee avec succes";
    if (isset($result["msisdn"])) {
        $msg .= " (MSISDN: " . $result["msisdn"] . ")";
        localAPI("UpdateClientService", [
            "serviceid" => $params["serviceid"],
            "dedicatedip" => $result["msisdn"]
        ]);
    }
    if (isset($result["id"])) $msg .= " (ID: " . $result["id"] . ")";
    return $msg;
}

function phenixgsm_SuspendAccount(array $params): string {
    $api = new PhenixGsmApi($params["username"], $params["username"], $params["password"]);
    $service = localAPI("GetClientsServices", ["serviceid" => $params["serviceid"]]);
    $msisdn = $service["services"]["service"][0]["dedicatedip"] ?? null;
    if (!$msisdn) return "Erreur: MSISDN non trouve";
    $result = $api->msisdnSuspend($params["username"], $msisdn);
    return $result ? "Suspension demandee" : "Erreur: " . $api->getLastError();
}

function phenixgsm_UnsuspendAccount(array $params): string {
    $api = new PhenixGsmApi($params["username"], $params["username"], $params["password"]);
    $service = localAPI("GetClientsServices", ["serviceid" => $params["serviceid"]]);
    $msisdn = $service["services"]["service"][0]["dedicatedip"] ?? null;
    if (!$msisdn) return "Erreur: MSISDN non trouve";
    $result = $api->msisdnResume($params["username"], $msisdn);
    return $result ? "Reactivation demandee" : "Erreur: " . $api->getLastError();
}

function phenixgsm_TerminateAccount(array $params): string {
    $api = new PhenixGsmApi($params["username"], $params["username"], $params["password"]);
    $service = localAPI("GetClientsServices", ["serviceid" => $params["serviceid"]]);
    $msisdn = $service["services"]["service"][0]["dedicatedip"] ?? null;
    if (!$msisdn) return "Erreur: MSISDN non trouve";
    $result = $api->msisdnDelete($params["username"], $msisdn);
    return $result ? "Resiliation demandee" : "Erreur: " . $api->getLastError();
}

function phenixgsm_ChangePackage(array $params): string {
    return "Changement de forfait a implementer";
}

function phenixgsm_ChangePassword(array $params): string {
    return "Non applicable pour GSM";
}

function phenixgsm_GetUsage(array $params): array {
    $api = new PhenixGsmApi($params["username"], $params["username"], $params["password"]);
    $service = localAPI("GetClientsServices", ["serviceid" => $params["serviceid"]]);
    $msisdn = $service["services"]["service"][0]["dedicatedip"] ?? null;
    if (!$msisdn) return ["bandwidth" => ["used" => 0, "total" => 0], "disk" => ["used" => 0, "total" => 0]];
    $consumption = $api->sdtrConso($params["username"], $msisdn);
    $usage = ["bandwidth" => ["used" => 0, "total" => 0], "disk" => ["used" => 0, "total" => 0]];
    if ($consumption && isset($consumption["usedValue"], $consumption["initialValue"])) {
        $usage["bandwidth"]["used"] = (int)$consumption["usedValue"];
        $usage["bandwidth"]["total"] = (int)$consumption["initialValue"];
    }
    return $usage;
}

function phenixgsm_ClientArea(array $params): string {
    $api = new PhenixGsmApi($params["username"], $params["username"], $params["password"]);
    $service = localAPI("GetClientsServices", ["serviceid" => $params["serviceid"]]);
    $msisdn = $service["services"]["service"][0]["dedicatedip"] ?? null;
    if (!$msisdn) return '<div class="alert alert-info">Ligne en cours d activation...</div>';
    $line = $api->msisdnConsult($msisdn, $params["username"]);
    $consumption = $api->sdtrConso($params["username"], $msisdn);
    $zones = $api->getZonesByOperator($line["operateur"] ?? "ORANGE");
    $html = '<div class="panel panel-default">';
    $html .= '<div class="panel-heading"><h3>Informations de la ligne</h3></div>';
    $html .= '<div class="panel-body">';
    $html .= '<p><strong>MSISDN:</strong> ' . ($line["msisdn"] ?? "N/A") . '</p>';
    $html .= '<p><strong>Operateur:</strong> ' . ($line["operateur"] ?? "N/A") . '</p>';
    $html .= '<p><strong>Etat:</strong> ' . ($line["etat"] ?? "N/A") . '</p>';
    $html .= '<p><strong>Forfait:</strong> ' . ($line["forfaitGsmCode"] ?? "N/A") . '</p>';
    $html .= '</div></div>';
    $html .= '<div class="panel panel-default">';
    $html .= '<div class="panel-heading"><h3>Consommation Data</h3></div>';
    $html .= '<div class="panel-body">';
    if ($consumption && isset($consumption["usedValue"], $consumption["initialValue"])) {
        $used = (int)$consumption["usedValue"];
        $total = (int)$consumption["initialValue"];
        $percent = $total > 0 ? min(100, ($used / $total) * 100) : 0;
        $barClass = $percent > 90 ? "danger" : ($percent > 75 ? "warning" : "success");
        $html .= '<div class="progress"><div class="progress-bar progress-bar-' . $barClass . '" style="width: ' . $percent . '%;">' . round($percent, 1) . '%</div></div>';
        $html .= '<p>Utilise: ' . $used . ' Mo / Total: ' . $total . ' Mo</p>';
    } else {
        $html .= '<p>Aucune donnee de consommation disponible</p>';
    }
    $html .= '</div></div>';
    $html .= '<div class="panel panel-default">';
    $html .= '<div class="panel-heading"><h3>Recharge Data</h3></div>';
    $html .= '<div class="panel-body">';
    $html .= '<form method="post">';
    $html .= '<input type="hidden" name="action" value="recharge">';
    $html .= '<input type="hidden" name="serviceid" value="' . $params["serviceid"] . '">';
    $html .= '<div class="form-group"><label>Volume (Mo):</label>';
    $html .= '<input type="number" name="volume" class="form-control" value="1024" min="1" required></div>';
    $html .= '<div class="form-group"><label>Zone:</label><select name="zone" class="form-control" required>';
    foreach ($zones as $code => $libelle) {
        $html .= '<option value="' . $code . '">' . $libelle . '</option>';
    }
    $html .= '</select></div>';
    $html .= '<button type="submit" class="btn btn-primary">Recharger</button></form>';
    if (isset($_POST["action"]) && $_POST["action"] === "recharge") {
        $result = $api->msisdnAddDataRecharge([
            "partenaireId" => $params["username"],
            "msisdn" => $msisdn,
            "volumeDataEnMo" => $_POST["volume"],
            "codeZone" => $_POST["zone"]
        ]);
        $html .= $result ? '<div class="alert alert-success">Recharge effectuee!</div>' : '<div class="alert alert-danger">Erreur</div>';
    }
    $html .= '</div></div>';
    return $html;
}

function phenixgsm_AdminArea(array $params): string {
    return phenixgsm_ClientArea($params);
}
