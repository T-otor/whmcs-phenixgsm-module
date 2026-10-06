<?php
/**
 * Hook WHMCS - Dashboard PHENIX Centralizer
 * Ce fichier injecte le dashboard dans l'interface client WHMCS
 */

if (!defined("WHMCS")) {
    die("Ce fichier ne peut pas être appelé directement.");
}

/**
 * Hook clientareastepsa - Injecte le contenu du dashboard dans la page client
 */
function phenix_centralizer_dashboard(&$vars)
{
    // Configuration de l'API Phenix
    $api_url = rtrim(getenv('PHENIX_CENTRALIZER_URL') ?: 'http://127.0.0.1:8000/api', '/');
    $api_key = getenv('PHENIX_CENTRALIZER_API_KEY') ?: '';

    // Récupérer le MSISDN du produit client (exemple)
    $client_id = $_SESSION['uid'] ?? null;
    $products = getShoppingCartContent($vars);

    $active_products = [];
    foreach ($products as &$product) {
        if ($product['type'] === 'HostingAccount' && !empty($product['domain'])) {
            // Récupérer MSISDN depuis le champ personnalisé du produit
            $serviceid = $product['relid'];
            $custom_fields = CustomFieldsGetValues(16, $serviceid);  // Custom Field ID pour MSISDN
            if (!empty($custom_fields['values'])) {
                foreach ($custom_fields['values'] as $cf) {
                    if (!empty($cf['value'])) {
                        $product['msisdn'] = $cf['value'];
                        $active_products[] = $product;
                        break;
                    }
                }
            }
        }
    }

    // Charger les données du dashboard
    $dashboard_data = phenix_fetch_dashboard_data($api_url, $api_key, $active_products);

    // Injecter le contenu HTML dans le template client
    global $smarty;
    
    // Assigner les variables au template
    if (isset($vars['templatefile'])) {
        $smarty->assign('phenix_api_url', $api_url);
        $smarty->assign('active_products', $active_products);
        $smarty->assign('dashboard_data', $dashboard_data);
        
        // Inclure le template custom
        return "inclues/phenix-dashboard.tpl";
    }

    return null;
}

/**
 * Fetch dashboard data from PHENIX Centralizer API
 */
function phenix_fetch_dashboard_data($api_url, $api_key, $products)
{
    $data = [];
    
    foreach ($products as $product) {
        if (!empty($product['msisdn'])) {
            // Fetch line state
            $line_state = phenix_api_get($api_url . "/lines/" . urlencode($product['msisdn']), $api_key);
            if ($line_state) {
                $data[$product['domain']]['state'] = $line_state['line'] ?? $line_state;
            }
            
            // Fetch consumption (SDTR for real-time data usage)
            $consumption = phenix_api_get($api_url . "/consumption/sdtr?msisdn=" . urlencode($product['msisdn']), $api_key);
            if ($consumption) {
                $data[$product['domain']]['consumption'] = $consumption;
            }
            
            // Check for eSIM QR code
            $line = $line_state['line'] ?? $line_state ?? [];
            if (($line['typeSim'] ?? '') === 'ESIM') {
                $qr_available = true;
            }
            
            $data[$product['domain']]['qr_available'] = $qr_available ?? false;
        }
    }
    
    return $data;
}

/**
 * Generic API call to PHENIX Centralizer
 */
function phenix_api_get($url, $api_key)
{
    if ($api_key === '') {
        logActivity("Clé PHENIX Centralizer absente (variable PHENIX_CENTRALIZER_API_KEY)");
        return null;
    }
    if (!function_exists('curl_init')) {
        logActivity("CURL n'est pas disponible pour l'appel à PHENIX API");
        return null;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['X-API-Key: ' . $api_key, 'Accept: application/json'],
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        logActivity("Erreur API PHENIX (" . $url . "): " . $error);
        return null;
    }
    
    if ($http_code !== 200) {
        logActivity("Erreur HTTP API PHENIX (code: $http_code pour " . $url . ")");
        return null;
    }
    
    $decoded = json_decode($response, true);
    return $decoded ?: null;
}

// Enregistrer le hook (à appeler depuis hooks/any.php ou via un autre système)
add_hook("ClientAreaPage", 1, "phenix_centralizer_dashboard");
