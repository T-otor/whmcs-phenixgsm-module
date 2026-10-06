<?php
/**
 * PHENIX GSM provisioning module for WHMCS.
 * Install in modules/servers/phenixgsm/ and select "PHENIX GSM" on the product.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function phenixgsm_MetaData()
{
    return [
        'DisplayName' => 'PHENIX GSM',
        'APIVersion' => '1.1',
        'RequiresServer' => false,
        'DefaultNonSSLPort' => '8000',
        'DefaultSSLPort' => '443',
    ];
}

function phenixgsm_ConfigOptions()
{
    return [
        'Centralizer API URL' => [
            'Type' => 'text',
            'Size' => '60',
            'Default' => 'http://127.0.0.1:8000/api',
            'Description' => 'Valeur de repli. L’URL et la clé enregistrées dans PHENIX GSM Dashboard sont utilisées en priorité.',
        ],
        'Opérateur' => [
            'Type' => 'dropdown',
            'Options' => 'ORANGE,SFR,BTBD,PHENIX',
            'Default' => 'ORANGE',
        ],
        'Opération d’activation' => [
            'Type' => 'dropdown',
            'Options' => 'CreateNA,CreateNP',
            'Default' => 'CreateNA',
            'Description' => 'CreateNA = nouveau numéro; CreateNP = portabilité entrante.',
        ],
        'Type SIM' => [
            'Type' => 'dropdown',
            'Options' => 'SIM,ESIM,SIM15D,SIM15D_IPFIXE,SIM15D_M2M,ESIM15D,ESIM15D_DataOnly',
            'Default' => 'SIM',
        ],
        'Code tarif d’achat' => [
            'Type' => 'text',
            'Size' => '35',
            'Description' => 'CodeTarifAchat fourni par PHENIX.',
        ],
        'Site / libellé client' => [
            'Type' => 'text',
            'Size' => '40',
            'Description' => 'Optionnel; sinon le nom du client WHMCS est utilisé.',
        ],
        'Ligne Data Only' => [
            'Type' => 'yesno',
            'Description' => 'Activer isDataOnly.',
        ],
        'IP fixe' => [
            'Type' => 'yesno',
            'Description' => 'Activer hasIpFixe.',
        ],
        'Profil technique GSM ID' => ['Type' => 'text', 'Size' => '20'],
        'Code forfait commercial' => ['Type' => 'text', 'Size' => '35'],
        'Supprimer le forfait existant lors d’une modification' => [
            'Type' => 'yesno',
            'Description' => 'Utilisé lors de ChangePackage.',
        ],
        'Produits PHENIX (JSON)' => [
            'Type' => 'textarea',
            'Rows' => '8',
            'Cols' => '60',
            'Description' => 'Tableau JSON de produits/options selon la documentation GSM v2.9.',
        ],
        'HNO (réseau PHENIX)' => [
            'Type' => 'dropdown',
            'Options' => 'ORANGE,SFR',
            'Description' => 'Seulement pour l’opérateur PHENIX.',
        ],
        'Durée engagement (mois)' => ['Type' => 'text', 'Size' => '8'],
        'Code APN' => ['Type' => 'text', 'Size' => '35'],
    ];
}

/** Resolve an order/service value from configurable options or product custom fields. */
function phenixgsm_Field(array $params, string $name, string $default = ''): string
{
    foreach (['configoptions', 'customfields'] as $source) {
        if (isset($params[$source][$name]) && is_scalar($params[$source][$name])) {
            return trim((string) $params[$source][$name]);
        }
    }
    return $default;
}

function phenixgsm_Setting(array $params, int $index, string $default = ''): string
{
    $key = 'configoption' . $index;
    return isset($params[$key]) && is_scalar($params[$key]) ? trim((string) $params[$key]) : $default;
}

function phenixgsm_FieldAny(array $params, array $names, string $default = ''): string
{
    foreach ($names as $name) {
        $value = phenixgsm_Field($params, $name);
        if ($value !== '') {
            return $value;
        }
    }
    return $default;
}

function phenixgsm_IsTrue(string $value): bool
{
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on', 'oui'], true);
}

/** Shared connection settings saved by the PHENIX GSM Dashboard addon. */
function phenixgsm_SharedApiSettings(array $params): array
{
    $shared = [];
    if (class_exists('\\WHMCS\\Database\\Capsule')) {
        try {
            $shared = \WHMCS\Database\Capsule::table('tbladdonmodules')
                ->where('module', 'phenixgsmadmin')
                ->pluck('value', 'setting')
                ->all();
        } catch (Throwable $e) {
            $shared = [];
        }
    }

    $apiUrl = trim((string) ($shared['api_url'] ?? ''));
    if ($apiUrl === '') {
        $apiUrl = getenv('PHENIX_CENTRALIZER_URL') ?: phenixgsm_Setting($params, 1, 'http://127.0.0.1:8000/api');
    }
    $apiKey = trim((string) ($shared['api_key'] ?? ''));
    if ($apiKey === '') {
        $apiKey = getenv('PHENIX_CENTRALIZER_API_KEY') ?: '';
    }
    return ['api_url' => $apiUrl, 'api_key' => $apiKey];
}

function phenixgsm_Request(array $params, string $method, string $path, ?array $payload = null): array
{
    $apiSettings = phenixgsm_SharedApiSettings($params);
    $apiKey = $apiSettings['api_key'];
    if (!$apiKey) {
        throw new RuntimeException('PHENIX_CENTRALIZER_API_KEY is not configured in the WHMCS PHP environment.');
    }

    $baseUrl = $apiSettings['api_url'];
    $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    $headers = ['X-API-Key: ' . $apiKey, 'Accept: application/json'];
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($payload !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $decoded = is_string($body) ? json_decode($body, true) : null;
    if ($curlError !== '') {
        throw new RuntimeException('Could not connect to PHENIX Centralizer: ' . $curlError);
    }
    if ($status < 200 || $status >= 300) {
        $detail = is_array($decoded) ? ($decoded['detail'] ?? $decoded['message'] ?? '') : '';
        throw new RuntimeException('PHENIX Centralizer returned HTTP ' . $status . ($detail !== '' ? ': ' . (is_string($detail) ? $detail : json_encode($detail)) : '.'));
    }
    if (!is_array($decoded)) {
        throw new RuntimeException('PHENIX Centralizer returned an invalid JSON response.');
    }

    if (function_exists('logModuleCall')) {
        logModuleCall('phenixgsm', strtoupper($method) . ' ' . $path, $payload ?? [], $decoded, $decoded, [$apiKey]);
    }
    return $decoded;
}

function phenixgsm_SaveRequestId(array $params, array $result): void
{
    if (!isset($result['id']) || empty($params['model']->serviceProperties)) {
        return;
    }
    try {
        $params['model']->serviceProperties->save(['Identifiant de demande opérateur' => (string) $result['id']]);
    } catch (Throwable $e) {
        // The API request is already accepted; logging must not cause WHMCS to retry it.
        if (function_exists('logActivity')) {
            logActivity('PHENIX GSM: request accepted but request ID could not be saved for service ' . (int) ($params['serviceid'] ?? 0));
        }
    }
}

function phenixgsm_Msisdn(array $params): string
{
    $value = phenixgsm_Field($params, 'MSISDN');
    if ($value === '') {
        // Many WHMCS GSM products store the mobile number in the Domain field.
        $value = trim((string) ($params['domain'] ?? ''));
    }
    $normalized = preg_replace('/[\s().-]+/u', '', $value);
    return is_string($normalized) && preg_match('/^\+?[0-9]{8,15}$/', $normalized) ? $normalized : '';
}

function phenixgsm_CreateAccount(array $params)
{
    try {
        $operation = phenixgsm_FieldAny($params, ['Opération GSM', 'Operation', 'Opération'], phenixgsm_Setting($params, 3, 'CreateNA'));
        $operator = strtoupper(phenixgsm_FieldAny($params, ['Opérateur', 'Operateur', 'Operator'], phenixgsm_Setting($params, 2, 'ORANGE')));
        $typeSimInput = phenixgsm_FieldAny($params, ['Type SIM', 'Type de SIM', 'SIM Type'], phenixgsm_Setting($params, 4, 'SIM'));
        $typeSimMap = [
            'SIM' => 'SIM', 'ESIM' => 'ESIM', 'SIM15D' => 'SIM15D',
            'SIM15D_IPFIXE' => 'SIM15D_IPFIXE', 'SIM15D_M2M' => 'SIM15D_M2M',
            'ESIM15D' => 'ESIM15D', 'ESIM15D_DATAONLY' => 'ESIM15D_DataOnly',
        ];
        $typeSim = $typeSimMap[strtoupper($typeSimInput)] ?? '';
        $siteLabel = phenixgsm_Setting($params, 6);
        if ($siteLabel === '') {
            $siteLabel = trim(($params['clientsdetails']['companyname'] ?? '') ?: (($params['clientsdetails']['firstname'] ?? '') . ' ' . ($params['clientsdetails']['lastname'] ?? '')));
        }
        $payload = [
            'operateur' => $operator,
            'operation' => $operation,
            'typeSim' => $typeSim,
            'codeTarifAchat' => phenixgsm_FieldAny($params, ['Code tarif d’achat', 'CodeTarifAchat'], phenixgsm_Setting($params, 5)),
            'siteLibelle' => $siteLabel,
            'nomClient' => trim(($params['clientsdetails']['firstname'] ?? '') . ' ' . ($params['clientsdetails']['lastname'] ?? '')),
            'hasIpFixe' => phenixgsm_IsTrue(phenixgsm_FieldAny($params, ['IP fixe', 'Has IP fixe'], phenixgsm_Setting($params, 8))),
            'isDataOnly' => phenixgsm_IsTrue(phenixgsm_FieldAny($params, ['Ligne Data Only', 'Data Only'], phenixgsm_Setting($params, 7))),
        ];
        $hno = strtoupper(phenixgsm_FieldAny($params, ['HNO', 'Réseau HNO'], phenixgsm_Setting($params, 13)));
        if ($hno !== '') {
            $payload['hno'] = $hno;
        }

        $msisdn = phenixgsm_Msisdn($params);
        $simSn = phenixgsm_FieldAny($params, ['SIM SN', 'SIM SN / ICCID', 'Numéro SIM']);
        $imsi = phenixgsm_FieldAny($params, ['IMSI']);
        if ($msisdn !== '') {
            $payload['msisdn'] = $msisdn;
            $payload['hasMsisdn'] = $operation === 'CreateNA';
        }
        if ($simSn !== '') {
            $payload['simsn'] = $simSn;
        }
        if ($imsi !== '') {
            $payload['imsi'] = $imsi;
        }
        $clientCode = phenixgsm_FieldAny($params, ['Code client', 'Code client PHENIX']);
        if ($clientCode !== '') {
            $payload['codeClient'] = $clientCode;
            unset($payload['siteLibelle']);
        }
        if ($operation === 'CreateNP') {
            $payload['rio'] = phenixgsm_Field($params, 'RIO');
            $payload['portaDate'] = phenixgsm_FieldAny($params, ['Date de portabilité', 'Porta Date']);
        }
        $profileId = phenixgsm_FieldAny($params, ['Profil technique GSM ID', 'GSM Profile ID'], phenixgsm_Setting($params, 9));
        if ($profileId !== '') {
            $payload['gsmProfilId'] = (int) $profileId;
        }
        $planCode = phenixgsm_FieldAny($params, ['Code forfait commercial', 'Forfait GSM Code'], phenixgsm_Setting($params, 10));
        if ($planCode !== '') {
            $payload['forfaitGsmCode'] = $planCode;
        }
        $duration = phenixgsm_FieldAny($params, ['Durée engagement', 'Duree engagement'], phenixgsm_Setting($params, 14));
        if ($duration !== '') {
            $payload['dureeEngagement'] = (int) $duration;
        }
        $apnCode = phenixgsm_FieldAny($params, ['Code APN', 'APN'], phenixgsm_Setting($params, 15));
        if ($apnCode !== '') {
            $payload['codeApn'] = $apnCode;
        }
        $productsJson = phenixgsm_FieldAny($params, ['Produits PHENIX (JSON)', 'Options PHENIX (JSON)'], phenixgsm_Setting($params, 12));
        if ($productsJson !== '') {
            $products = json_decode($productsJson, true);
            if (!is_array($products)) {
                return 'Produits PHENIX (JSON) must contain a valid JSON array.';
            }
            $payload['produits'] = $products;
        } else {
            $payload['produits'] = [];
        }

        if ($payload['codeTarifAchat'] === '') {
            return 'Configure the PHENIX purchase tariff code on this WHMCS product.';
        }
        if ($typeSim === '') {
            return 'Type SIM invalide. Choisir un type présent dans la liste PHENIX.';
        }
        if (!in_array(strtoupper($typeSim), ['ESIM', 'ESIM15D', 'ESIM15D_DATAONLY'], true) && ($simSn === '' || $imsi === '')) {
            return 'Une SIM physique nécessite les options configurables SIM SN et IMSI.';
        }
        if ($operation === 'CreateNP' && ($payload['msisdn'] ?? '') === '') {
            return 'CreateNP requires a service custom field or configurable option named MSISDN.';
        }
        if ($operation === 'CreateNP' && ($payload['rio'] === '' || $payload['portaDate'] === '')) {
            return 'CreateNP requires service fields named RIO and Date de portabilité.';
        }

        $result = phenixgsm_Request($params, 'POST', '/lines/activate', $payload);
        phenixgsm_SaveRequestId($params, $result);
        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function phenixgsm_SuspendAccount(array $params)
{
    return phenixgsm_LineAction($params, 'suspend');
}

function phenixgsm_UnsuspendAccount(array $params)
{
    return phenixgsm_LineAction($params, 'resume');
}

function phenixgsm_TerminateAccount(array $params)
{
    return phenixgsm_LineAction($params, 'cancel');
}

function phenixgsm_LineAction(array $params, string $action)
{
    $msisdn = phenixgsm_Msisdn($params);
    if ($msisdn === '') {
        return 'The service has no MSISDN. Add a service custom field or configurable option named MSISDN.';
    }
    try {
        $result = phenixgsm_Request($params, 'POST', '/lines/' . rawurlencode($msisdn) . '/' . $action);
        phenixgsm_SaveRequestId($params, $result);
        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function phenixgsm_ChangePackage(array $params)
{
    $msisdn = phenixgsm_Msisdn($params);
    if ($msisdn === '') {
        return 'The service has no MSISDN. Add a service custom field or configurable option named MSISDN.';
    }
    $productsJson = phenixgsm_FieldAny($params, ['Produits PHENIX (JSON)', 'Options PHENIX (JSON)'], phenixgsm_Setting($params, 12));
    $products = $productsJson !== '' ? json_decode($productsJson, true) : [];
    if (!is_array($products)) {
        return 'Produits PHENIX (JSON) must contain a valid JSON array.';
    }
    $payload = [
        'forfaitGsmCode' => phenixgsm_FieldAny($params, ['Code forfait commercial', 'Forfait GSM Code'], phenixgsm_Setting($params, 10)),
        'deleteForfait' => phenixgsm_IsTrue(phenixgsm_FieldAny($params, ['Supprimer le forfait existant'], phenixgsm_Setting($params, 11))),
        'produits' => $products,
    ];
    $profileId = phenixgsm_FieldAny($params, ['Profil technique GSM ID', 'GSM Profile ID'], phenixgsm_Setting($params, 9));
    if ($profileId !== '') {
        $payload['gsmProfilId'] = (int) $profileId;
    }
    try {
        $result = phenixgsm_Request($params, 'POST', '/lines/' . rawurlencode($msisdn) . '/options', $payload);
        phenixgsm_SaveRequestId($params, $result);
        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function phenixgsm_ClientArea(array $params)
{
    $msisdn = phenixgsm_Msisdn($params);
    $showEsim = isset($_GET['phenixgsm_show_esim']) && is_scalar($_GET['phenixgsm_show_esim'])
        && (string) $_GET['phenixgsm_show_esim'] === '1';
    $showSimCodes = isset($_GET['phenixgsm_show_sim_codes']) && is_scalar($_GET['phenixgsm_show_sim_codes'])
        && (string) $_GET['phenixgsm_show_sim_codes'] === '1';
    $currentMonth = (new DateTimeImmutable('first day of this month'))->setTime(0, 0);
    $serviceStartMonth = phenixgsm_ServiceStartMonth($params, $currentMonth);
    if ($serviceStartMonth > $currentMonth) {
        $serviceStartMonth = $currentMonth;
    }
    $requestedMonth = isset($_GET['phenixgsm_month']) && is_scalar($_GET['phenixgsm_month'])
        ? trim((string) $_GET['phenixgsm_month'])
        : '';
    $selectedMonth = $currentMonth;
    if (preg_match('/^\d{4}-\d{2}$/', $requestedMonth)) {
        $parsedMonth = DateTimeImmutable::createFromFormat('!Y-m', $requestedMonth);
        if ($parsedMonth instanceof DateTimeImmutable && $parsedMonth->format('Y-m') === $requestedMonth && $parsedMonth >= $serviceStartMonth && $parsedMonth <= $currentMonth) {
            $selectedMonth = $parsedMonth;
        }
    }
    $selectedMonthValue = $selectedMonth->format('Y-m');
    $statsMonthOptionsHtml = '';
    for ($monthOption = $currentMonth; $monthOption >= $serviceStartMonth; $monthOption = $monthOption->modify('-1 month')) {
        $monthValue = $monthOption->format('Y-m');
        $selectedAttribute = $monthValue === $selectedMonthValue ? ' selected' : '';
        $statsMonthOptionsHtml .= '<option value="' . $monthValue . '"' . $selectedAttribute . '>' . $monthOption->format('m/Y') . '</option>';
    }
    $line = null;
    $consumption = null;
    $consumptionError = '';
    $esimActivation = null;
    $esimError = '';
    $simCodesError = '';
    $pin1 = '';
    $puk1 = '';
    $puk2 = '';
    $error = '';
    $monthlyStats = null;
    if ($msisdn !== '') {
        try {
            $lineResponse = phenixgsm_Request($params, 'GET', '/lines/' . rawurlencode($msisdn));
            $lineData = $lineResponse['line'] ?? $lineResponse;
            // Keep Smarty's dotted property access safe even if an upstream
            // response unexpectedly contains a plain string.
            $line = is_array($lineData) ? $lineData : null;
            if ($line === null) {
                throw new RuntimeException('The line API returned an invalid response.');
            }
            if (($line['operateur'] ?? '') === 'ORANGE') {
                try {
                    $consoResponse = phenixgsm_Request($params, 'GET', '/consumption/sdtr?msisdn=' . rawurlencode($msisdn));
                    if (is_array($consoResponse['zones'] ?? null)) {
                        $consoResponse['zones'] = array_values(array_filter(
                            $consoResponse['zones'],
                            static function ($zone): bool { return is_array($zone); }
                        ));
                        $consumption = $consoResponse;
                    }
                } catch (Throwable $e) {
                    // A provider-side SDTR issue must not hide valid line and
                    // monthly CDR details from the customer.
                    $consumptionError = 'La consommation data en temps réel est momentanément indisponible.';
                }
            }
            $isEsimLine = in_array(strtoupper((string) ($line['typeSim'] ?? '')), ['ESIM', 'ESIM15D', 'ESIM15D_DATAONLY'], true);
            if (($isEsimLine && $showEsim) || (!$isEsimLine && $showSimCodes)) {
                $simSerial = phenixgsm_ClientAreaScalar($line['simsn'] ?? '');
                if ($simSerial !== '') {
                    try {
                        // Load PIN/PUK and eSIM data only after an explicit
                        // client action. Never call the QR endpoint here.
                        $detailsPath = $isEsimLine
                            ? '/esim/details/' . rawurlencode($simSerial) . '?operator=' . rawurlencode(phenixgsm_ClientAreaScalar($line['operateur'] ?? 'ORANGE'))
                            : '/stock/sims/' . rawurlencode($simSerial);
                        $detailsResponse = phenixgsm_Request($params, 'GET', $detailsPath);
                        $details = $isEsimLine ? ($detailsResponse['esim'] ?? null) : $detailsResponse;
                        if (is_array($details)) {
                            $pin1 = phenixgsm_FindScalarKey($details, 'pin1');
                            $puk1 = phenixgsm_FindScalarKey($details, 'puk1');
                            $puk2 = phenixgsm_FindScalarKey($details, 'puk2');
                            if ($puk2 === '') {
                                // Some versions of the eSIM API document this key as Puk32.
                                $puk2 = phenixgsm_FindScalarKey($details, 'puk32');
                            }
                            if ($isEsimLine) {
                                $esimActivation = [
                                    'activationCode' => phenixgsm_FindScalarKey($details, 'activationCode'),
                                    'confirmationCode' => phenixgsm_FindScalarKey($details, 'confirmationCode'),
                                ];
                            }
                        }
                    } catch (Throwable $e) {
                        $esimActivation = null;
                        $simCodesError = 'Les codes de cette carte SIM ne sont pas disponibles pour le moment.';
                    }
                }
                if ($simSerial === '') {
                    $simCodesError = 'Le numéro de série de cette carte SIM est absent de la réponse de la ligne.';
                }
                if ($isEsimLine && empty($esimActivation['activationCode'])) {
                    $esimError = $simCodesError !== ''
                        ? $simCodesError
                        : 'Le code d’activation eSIM n’est pas disponible dans les détails de cette SIM.';
                }
                if (!$isEsimLine && $pin1 === '' && $puk1 === '' && $puk2 === '' && $simCodesError === '') {
                    $simCodesError = 'Aucun code PIN ou PUK n’est disponible pour cette carte SIM.';
                }
            }
        } catch (Throwable $e) {
            $error = 'Les informations de cette ligne sont momentanément indisponibles. Réessaie un peu plus tard.';
        }

        // CDR month parameter uses MMAAAA (for example 102026).
        try {
            $month = $selectedMonth->format('mY');
            $monthlyStats = phenixgsm_Request($params, 'GET', '/consumption/cdr?msisdn=' . rawurlencode($msisdn) . '&month=' . rawurlencode($month));
        } catch (Throwable $e) {
            $monthlyStats = null;
        }
    }
    $lineFound = is_array($line);
    $lineNumber = $msisdn;
    $operator = '-';
    $status = '-';
    $simType = '';
    $activationDate = '';
    $activationCode = '';
    $confirmationCode = '';
    $consumptionHtml = '';
    $consumptionChartHtml = '';
    $monthlyStatsHtml = '';
    $operatorKey = 'OTHER';
    if ($lineFound) {
        $lineNumber = phenixgsm_ClientAreaScalar($line['msisdn'] ?? '') ?: $msisdn;
        $operatorKey = strtoupper(phenixgsm_ClientAreaScalar($line['operateur'] ?? ''));
        // Keep the supplier's name out of the client-facing page.
        $operator = $operatorKey === 'PHENIX'
            ? 'Réseau mobile'
            : (phenixgsm_ClientAreaScalar($line['operateur'] ?? '') ?: '-');
        $status = phenixgsm_ClientAreaScalar($line['etatLibelle'] ?? $line['etat'] ?? '') ?: '-';
        $simType = phenixgsm_ClientAreaScalar($line['typeSim'] ?? '');
        $activationDate = phenixgsm_ClientAreaScalar($line['dateActivation'] ?? '');
    }
    if (is_array($esimActivation)) {
        $activationCode = phenixgsm_ClientAreaScalar($esimActivation['activationCode'] ?? '');
        $confirmationCode = phenixgsm_ClientAreaScalar($esimActivation['confirmationCode'] ?? '');
    }
    if (is_array($consumption) && is_array($consumption['zones'] ?? null)) {
        $rows = '';
        $validZones = [];
        $dataRegions = [
            'france' => ['label' => 'Data France', 'zone' => null],
            'ue' => ['label' => 'Data UE', 'zone' => null],
        ];
        foreach ($consumption['zones'] as $zone) {
            if (!is_array($zone)) {
                continue;
            }
            $zoneName = phenixgsm_ClientAreaScalar($zone['libelleZone'] ?? $zone['codeZone'] ?? '') ?: '-';
            $initial = phenixgsm_ClientAreaScalar($zone['initialValueText'] ?? '') ?: '-';
            $used = phenixgsm_ClientAreaScalar($zone['usedValueText'] ?? '') ?: '-';
            $remaining = phenixgsm_ClientAreaScalar($zone['remainingValueText'] ?? '') ?: '-';
            $rows .= '<tr><td>' . phenixgsm_ClientAreaEscape($zoneName) . '</td><td>' . phenixgsm_ClientAreaEscape($initial) . '</td><td>' . phenixgsm_ClientAreaEscape($used) . '</td><td>' . phenixgsm_ClientAreaEscape($remaining) . '</td></tr>';
            $validZones[] = $zone;
            $zoneIdentifier = strtolower(phenixgsm_ClientAreaScalar($zone['codeZone'] ?? '') . ' ' . phenixgsm_ClientAreaScalar($zone['libelleZone'] ?? ''));
            if (preg_match('/france|national|zone\s*a\b|zonea/ui', $zoneIdentifier)) {
                $dataRegions['france']['zone'] = $zone;
            } elseif (preg_match('/\b(ue|eu|europe|européen|européenne)\b|zone\s*b\b|zoneb/ui', $zoneIdentifier)) {
                $dataRegions['ue']['zone'] = $zone;
            }
        }
        // PHENIX commonly returns ZoneA/ZoneB codes without human-readable
        // France/UE labels. On a two-zone SDTR response these are the ordered
        // national and European envelopes, respectively.
        if (count($validZones) <= 2 && $dataRegions['france']['zone'] === null) {
            foreach ($validZones as $candidateZone) {
                if ($candidateZone !== $dataRegions['ue']['zone']) {
                    $dataRegions['france']['zone'] = $candidateZone;
                    break;
                }
            }
        }
        if (count($validZones) <= 2 && $dataRegions['ue']['zone'] === null) {
            foreach ($validZones as $candidateZone) {
                if ($candidateZone !== $dataRegions['france']['zone']) {
                    $dataRegions['ue']['zone'] = $candidateZone;
                    break;
                }
            }
        }
        if ($rows !== '') {
            $consumptionHtml = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Zone</th><th>Inclus</th><th>Utilisé</th><th>Restant</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
        }
        $chartRows = '';
        foreach ($dataRegions as $region) {
            $zone = $region['zone'];
            if (!is_array($zone)) {
                continue;
            }
            // The SDTR initialValue is the PHENIX-reported allowance attached
            // to this active line/product; never infer a quota from usage rows.
            $maximum = phenixgsm_DataAmount($zone['initialValue'] ?? null, $zone['initialValueText'] ?? null);
            $usedValue = phenixgsm_DataAmount($zone['usedValue'] ?? null, $zone['usedValueText'] ?? null);
            if ($maximum === null || $usedValue === null || $maximum <= 0) {
                continue;
            }
            $usedValue = max(0, $usedValue);
            $percent = (int) round(min(100, ($usedValue / $maximum) * 100));
            $usedText = phenixgsm_ClientAreaScalar($zone['usedValueText'] ?? '') ?: (string) $zone['usedValue'];
            $maxText = phenixgsm_ClientAreaScalar($zone['initialValueText'] ?? '') ?: (string) $zone['initialValue'];
            $remainingText = phenixgsm_ClientAreaScalar($zone['remainingValueText'] ?? '');
            $chartRows .= '<div class="phenixgsm-chart-row"><div class="phenixgsm-chart-label">' . phenixgsm_ClientAreaEscape($region['label']) . '</div><div class="phenixgsm-chart-track" role="progressbar" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100" aria-label="' . phenixgsm_ClientAreaEscape($region['label']) . '"><span class="phenixgsm-chart-fill" style="width:' . $percent . '%"></span></div><div class="phenixgsm-chart-percent">' . $percent . '%</div><div class="phenixgsm-chart-caption">' . phenixgsm_ClientAreaEscape($usedText) . ' utilisés sur ' . phenixgsm_ClientAreaEscape($maxText) . ($remainingText !== '' ? ' · ' . phenixgsm_ClientAreaEscape($remainingText) . ' restants' : '') . '</div></div>';
        }
        if ($chartRows !== '') {
            $consumptionChartHtml = '<div class="phenixgsm-chart"><div class="phenixgsm-chart-title">Enveloppes data</div>' . $chartRows . '</div>';
        }
    }
    if (is_array($monthlyStats)) {
        $rows = '';
        $records = is_array($monthlyStats['records'] ?? null) ? $monthlyStats['records'] : [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $type = phenixgsm_ClientAreaScalar($record['type'] ?? '') ?: '-';
            $label = phenixgsm_ClientAreaScalar($record['libelle'] ?? '') ?: '-';
            $usage = phenixgsm_ClientAreaScalar($record['consoTxt'] ?? $record['conso'] ?? '') ?: '-';
            $rows .= '<tr><td>' . phenixgsm_ClientAreaEscape($type) . '</td><td>' . phenixgsm_ClientAreaEscape($label) . '</td><td>' . phenixgsm_ClientAreaEscape($usage) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="3">Aucune consommation enregistrée pour ce mois.</td></tr>';
        }
        $monthlyStatsHtml = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Type</th><th>Détail</th><th>Consommation</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    } else {
        $monthlyStatsHtml = '<div class="alert alert-info">Les statistiques mensuelles ne sont pas disponibles pour le moment.</div>';
    }

    return [
        'templatefile' => 'clientarea',
        'vars' => [
            'lineFound' => $lineFound,
            'lineNumber' => $lineNumber,
            'operator' => $operator,
            'operatorKey' => $operatorKey,
            'isEsim' => in_array(strtoupper($simType), ['ESIM', 'ESIM15D', 'ESIM15D_DATAONLY'], true),
            'lineStatus' => $status,
            'simType' => $simType,
            'activationDate' => $activationDate,
            'activationCode' => $activationCode,
            'confirmationCode' => $confirmationCode,
            'consumptionHtml' => $consumptionHtml,
            'consumptionChartHtml' => $consumptionChartHtml,
            'consumptionError' => $consumptionError,
            'monthLabel' => $selectedMonth->format('m/Y'),
            'selectedMonthValue' => $selectedMonthValue,
            'statsMonthOptionsHtml' => $statsMonthOptionsHtml,
            'serviceId' => (int) ($params['serviceid'] ?? 0),
            'monthlyStatsHtml' => $monthlyStatsHtml,
            'esimError' => $esimError,
            'simCodesError' => $simCodesError,
            'pin1' => $pin1,
            'puk1' => $puk1,
            'puk2' => $puk2,
            'apiError' => $error,
            'showEsim' => $showEsim,
            'showSimCodes' => $showSimCodes,
        ],
    ];
}

function phenixgsm_ServiceStartMonth(array $params, DateTimeImmutable $fallback): DateTimeImmutable
{
    $registeredAt = '';
    $serviceId = (int) ($params['serviceid'] ?? 0);
    $userId = (int) ($params['userid'] ?? 0);
    if ($serviceId > 0 && class_exists('\\WHMCS\\Database\\Capsule')) {
        try {
            $query = \WHMCS\Database\Capsule::table('tblhosting')->where('id', $serviceId);
            if ($userId > 0) {
                $query->where('userid', $userId);
            }
            $registeredAt = (string) ($query->value('regdate') ?? '');
        } catch (Throwable $e) {
            $registeredAt = '';
        }
    }
    if ($registeredAt !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $registeredAt)) {
        $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', substr($registeredAt, 0, 10));
        if ($startDate instanceof DateTimeImmutable && $startDate->format('Y-m-d') === substr($registeredAt, 0, 10)) {
            return $startDate->modify('first day of this month')->setTime(0, 0);
        }
    }
    return $fallback;
}

function phenixgsm_FindScalarKey(array $data, string $wantedKey): string
{
    foreach ($data as $key => $value) {
        if (is_string($key) && strtolower($key) === strtolower($wantedKey) && is_scalar($value)) {
            return trim((string) $value);
        }
        if (is_array($value)) {
            $found = phenixgsm_FindScalarKey($value, $wantedKey);
            if ($found !== '') {
                return $found;
            }
        }
    }
    return '';
}

function phenixgsm_ClientAreaScalar($value): string
{
    return is_scalar($value) ? trim((string) $value) : '';
}

function phenixgsm_ClientAreaEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Normalize PHENIX SDTR amounts to the same unit for accurate graph ratios. */
function phenixgsm_DataAmount($rawValue, $textValue): ?float
{
    if (is_numeric($rawValue)) {
        return (float) $rawValue;
    }
    $text = phenixgsm_ClientAreaScalar($textValue);
    if ($text === '' || !preg_match('/(-?\d+(?:[,.]\d+)?)\s*(Go|GB|Mo|MB|Ko|KB|o|B)/iu', $text, $matches)) {
        return null;
    }
    $amount = (float) str_replace(',', '.', $matches[1]);
    $unit = strtolower($matches[2]);
    $powers = ['go' => 3, 'gb' => 3, 'mo' => 2, 'mb' => 2, 'ko' => 1, 'kb' => 1, 'o' => 0, 'b' => 0];
    return $amount * (1024 ** ($powers[$unit] ?? 0));
}
