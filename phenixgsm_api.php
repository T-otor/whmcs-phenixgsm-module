<?php
if (!defined("WHMCS")) die("Direct access forbidden");
require_once __DIR__ . "/phenixgsm_config.php";

class PhenixGsmApi {
    private $partenaireId, $username, $password, $accessToken, $tokenExpiresAt, $lastError;

    public function __construct($partenaireId=null, $username=null, $password=null) {
        $this->partenaireId = $partenaireId;
        $this->username = $username;
        $this->password = $password;
        $this->loadTokenFromCache();
    }

    public function setCredentials($partenaireId, $username, $password) {
        $this->partenaireId = $partenaireId;
        $this->username = $username;
        $this->password = $password;
        $this->accessToken = null;
        $this->tokenExpiresAt = null;
    }

    public function getLastError() { return $this->lastError; }

    public function hasValidToken() {
        if (empty($this->accessToken) || empty($this->tokenExpiresAt)) return false;
        return $this->tokenExpiresAt > (time() + 60);
    }

    public function authenticate() {
        if ($this->hasValidToken()) return true;
        if (empty($this->username) || empty($this->password)) {
            $this->lastError = "Identifiants manquants";
            return false;
        }
        $response = $this->postRequest(
            PHENIXGSM_API_BASE_URL . "/Auth/authenticate",
            ["username" => $this->username, "password" => $this->password]
        );
        if (!$response || !isset($response["access_token"])) {
            $this->lastError = "Auth failed: " . ($response["message"] ?? $response["errorDescription"] ?? "Unknown error");
            return false;
        }
        $this->accessToken = $response["access_token"];
        $this->tokenExpiresAt = time() + ($response["expires_in"] ?? PHENIXGSM_TOKEN_CACHE_TTL);
        $this->saveTokenToCache();
        return true;
    }

    private function loadTokenFromCache() {
        if (empty($this->partenaireId)) return;
        try {
            $token = Capsule::table(PHENIXGSM_CACHE_TABLE)
                ->where("partenaireId", $this->partenaireId)
                ->where("expires_at", ">", date("Y-m-d H:i:s"))
                ->orderBy("created_at", "desc")->first();
            if ($token) {
                $this->accessToken = $token->access_token;
                $this->tokenExpiresAt = strtotime($token->expires_at);
            }
        } catch (Exception $e) {}
    }

    private function saveTokenToCache() {
        if (empty($this->partenaireId) || empty($this->accessToken)) return;
        try {
            Capsule::table(PHENIXGSM_CACHE_TABLE)->insert([
                "partenaireId" => $this->partenaireId,
                "access_token" => $this->accessToken,
                "working_token" => "test",
                "expires_in" => PHENIXGSM_TOKEN_CACHE_TTL,
                "token_type" => "Bearer",
                "expires_at" => date("Y-m-d H:i:s", $this->tokenExpiresAt)
            ]);
        } catch (Exception $e) {}
    }

    private function sendRequest($method, $url, $data=[]) {
        $ch = curl_init();
        $headers = ["Content-Type: application/json", "Accept: application/json"];
        if ($this->hasValidToken()) $headers[] = "Authorization: Bearer " . $this->accessToken;
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ];
        if (!empty($data) && in_array($method, ["POST","PUT","DELETE"])) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            $this->lastError = "cURL Error: " . $error;
            return false;
        }
        if ($httpCode >= 400) {
            $this->lastError = "HTTP Error " . $httpCode;
            return false;
        }
        $decoded = json_decode($response, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $response;
    }

    private function postRequest($url, $data=[]) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Accept: application/json"],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            $this->lastError = "cURL Error: " . $error;
            return false;
        }
        if ($httpCode >= 400) {
            $this->lastError = "HTTP Error " . $httpCode;
            return false;
        }
        $decoded = json_decode($response, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $response;
    }

    public function testConnection() { return $this->authenticate(); }
    public function get($endpoint, $params=[], $useAuth=true) {
        if ($useAuth && !$this->authenticate()) return false;
        $url = PHENIXGSM_API_BASE_URL . $endpoint;
        if (!empty($params)) $url .= "?" . http_build_query($params);
        return $this->sendRequest("GET", $url);
    }
    public function post($endpoint, $data=[], $useAuth=true) {
        if ($useAuth && !$this->authenticate()) return false;
        return $this->sendRequest("POST", PHENIXGSM_API_BASE_URL . $endpoint, $data);
    }

    public function msisdnActivate($data) {
        $required = ["partenaireId","operateur","operation","typeSim","codeTarifAchat"];
        foreach ($required as $field) if (!isset($data[$field])) {
            $this->lastError = "Missing field: $field"; return false;
        }
        if (($data["operation"] ?? "") === "CreateNP" && (!isset($data["msisdn"]) || !isset($data["rio"]))) {
            $this->lastError = "msisdn and rio required for portability"; return false;
        }
        if (!in_array($data["typeSim"], ["ESIM","ESIM15D","ESIM15D_DataOnly"]) && (!isset($data["simsn"]) || !isset($data["imsi"]))) {
            $this->lastError = "simsn and imsi required for physical SIM"; return false;
        }
        return $this->post("/GsmApi/V2/MsisdnActivate", $data);
    }

    public function msisdnConsult($msisdn, $partenaireId) {
        return $this->get("/GsmApi/V2/MsisdnConsult", ["msisdn" => $msisdn, "partenaireId" => $partenaireId]);
    }

    public function msisdnConsultAll($partenaireId) {
        return $this->get("/GsmApi/V2/MsisdnConsultAll", ["partenaireId" => $partenaireId]);
    }

    public function msisdnSuspend($partenaireId, $msisdn) {
        return $this->post("/GsmApi/V2/MsisdnSuspend", ["partenaireId" => $partenaireId, "msisdn" => $msisdn]);
    }

    public function msisdnResume($partenaireId, $msisdn) {
        return $this->post("/GsmApi/V2/MsisdnResume", ["partenaireId" => $partenaireId, "msisdn" => $msisdn]);
    }

    public function msisdnDelete($partenaireId, $msisdn) {
        return $this->post("/GsmApi/V2/MsisdnDelete", ["partenaireId" => $partenaireId, "msisdn" => $msisdn]);
    }

    public function simSwap($data) {
        $required = ["partenaireId","msisdn","typeSimNew"];
        foreach ($required as $field) if (!isset($data[$field])) {
            $this->lastError = "Missing field: $field"; return false;
        }
        if ($data["typeSimNew"] !== "ESIM" && (!isset($data["simSnNew"]) || !isset($data["imsiNew"]))) {
            $this->lastError = "simSnNew and imsiNew required for physical SIM"; return false;
        }
        return $this->post("/GsmApi/V2/SimSwap", $data);
    }

    public function sdtrConso($partenaireId, $msisdn) {
        return $this->post("/GsmApi/V2/SdtrConso", ["partenaireId" => $partenaireId, "msisdn" => $msisdn]);
    }

    public function getConsoMsisdnFromCDR($partenaireId, $msisdn, $moisAnnee) {
        return $this->get("/GsmApi/GetConsoMsisdnFromCDR", [
            "partenaireId" => $partenaireId, "msisdn" => $msisdn, "moisAnnee" => $moisAnnee
        ]);
    }

    public function msisdnAddDataRecharge($data) {
        $required = ["partenaireId","msisdn","volumeDataEnMo","codeZone"];
        foreach ($required as $field) if (!isset($data[$field])) {
            $this->lastError = "Missing field: $field"; return false;
        }
        return $this->post("/GsmApi/V2/MsisdnAddDataRecharge", $data);
    }

    public function getZonesByOperator($operateur) {
        return $this->get("/GsmApi/V2/GetZonesByOperator", ["operateur" => $operateur]);
    }

    public function getDataRechargesByOperator($operateur, $codeZone=null) {
        $params = ["operateur" => $operateur];
        if ($codeZone) $params["codeZone"] = $codeZone;
        return $this->get("/GsmApi/V2/GetDataRechargesByOperator", $params);
    }

    public function msisdnConsultRio($partenaireId, $msisdn) {
        return $this->get("/GsmApi/V2/MsisdnConsultRio", ["partenaireId" => $partenaireId, "msisdn" => $msisdn]);
    }

    public function saveCommandeSim($data) {
        $required = ["partenaireId","operateur","nombreSim"];
        foreach ($required as $field) if (!isset($data[$field])) {
            $this->lastError = "Missing field: $field"; return false;
        }
        return $this->post("/GsmApi/SaveCommandeSim", $data);
    }

    public function saveCommandeEsim($data) {
        $required = ["partenaireId","operateur","nombreEsim"];
        foreach ($required as $field) if (!isset($data[$field])) {
            $this->lastError = "Missing field: $field"; return false;
        }
        return $this->post("/GsmApi/SaveCommandeEsim", $data);
    }
}
