<?php
/** PHENIX GSM fleet dashboard for WHMCS administrators. */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function phenixgsmadmin_config()
{
    return [
        'name' => 'PHENIX GSM Dashboard',
        'description' => 'Vue d’ensemble du parc GSM PHENIX et des portabilités.',
        'version' => '1.1.0',
        'author' => 'PHENIX Centralizer',
        'language' => 'french',
        'fields' => [
            'api_url' => [
                'FriendlyName' => 'URL API Centralizer',
                'Type' => 'text',
                'Size' => '60',
                'Default' => 'http://127.0.0.1:8000/api',
                'Description' => 'URL complète de l’API, suffixe /api inclus. Source commune pour le dashboard et le module serveur.',
            ],
            'api_key' => [
                'FriendlyName' => 'Clé API',
                'Type' => 'password',
                'Size' => '60',
                'Description' => 'Même clé que API_KEY dans le .env du Centralizer. Partagée avec le module de provisionnement.',
            ],
        ],
    ];
}

function phenixgsmadmin_activate()
{
    return ['status' => 'success', 'description' => 'PHENIX GSM Dashboard est activé.'];
}

function phenixgsmadmin_deactivate()
{
    return ['status' => 'success', 'description' => 'PHENIX GSM Dashboard est désactivé.'];
}

function phenixgsmadmin_e($value): string
{
    if (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function phenixgsmadmin_api(array $vars, string $path): array
{
    $base = trim((string) ($vars['api_url'] ?? '')) ?: (getenv('PHENIX_CENTRALIZER_URL') ?: 'http://127.0.0.1:8000/api');
    $key = trim((string) ($vars['api_key'] ?? '')) ?: (getenv('PHENIX_CENTRALIZER_API_KEY') ?: '');
    if ($key === '') {
        throw new RuntimeException('Clé API absente. Définissez PHENIX_CENTRALIZER_API_KEY dans l’environnement PHP ou dans la configuration du module.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('L’extension PHP cURL est requise.');
    }

    $url = rtrim($base, '/') . '/' . ltrim($path, '/');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $key, 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error !== '') {
        throw new RuntimeException('Connexion au Centralizer impossible : ' . $error);
    }
    $data = is_string($body) ? json_decode($body, true) : null;
    if ($status < 200 || $status >= 300) {
        $detail = is_array($data) ? ($data['detail'] ?? 'Erreur HTTP ' . $status) : 'Erreur HTTP ' . $status;
        throw new RuntimeException(is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE));
    }
    if (!is_array($data)) {
        throw new RuntimeException('Réponse JSON invalide du Centralizer.');
    }
    return $data;
}

function phenixgsmadmin_rows($response, string $listKey): array
{
    if (!is_array($response)) {
        return [];
    }
    if (isset($response[$listKey]) && is_array($response[$listKey])) {
        return $response[$listKey];
    }
    if (!$response) {
        return [];
    }
    $keys = array_keys($response);
    return $keys === range(0, count($keys) - 1) ? $response : [];
}

function phenixgsmadmin_table(array $rows, string $kind): string
{
    $columns = $kind === 'lines'
        ? [
            'msisdn' => 'MSISDN', 'etatLibelle' => 'État', 'operateur' => 'Opérateur',
            'forfaitGsmCode' => 'Forfait', 'typeSim' => 'SIM', 'codeClient' => 'Code client',
            'dateActivation' => 'Activation',
        ]
        : [
            'id' => 'Requête', 'msisdn' => 'MSISDN', 'operateur' => 'Opérateur',
            'operation' => 'Opération', 'portaEtat' => 'Étape', 'portaEtatEtape' => 'Avancement',
            'portaStatut' => 'Statut porta', 'statut' => 'Statut commande', 'portaDate' => 'Date prévue',
        ];

    $tableId = $kind === 'lines' ? ' id="phx-lines-table"' : ' id="phx-' . phenixgsmadmin_e($kind) . '-table"';
    $html = '<div class="table-responsive"><table' . $tableId . ' class="table table-striped table-hover phx-table"><thead><tr>';
    foreach ($columns as $label) {
        $html .= '<th>' . phenixgsmadmin_e($label) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rowSearch = implode(' ', array_map(static function ($value): string {
            return is_scalar($value) ? (string) $value : '';
        }, $row));
        $rowState = strtolower((string) ($row['etat'] ?? $row['etatLibelle'] ?? ''));
        $rowOperator = strtolower((string) ($row['operateur'] ?? ''));
        $rowSimType = strtolower((string) ($row['typeSim'] ?? ''));
        $html .= '<tr data-search="' . phenixgsmadmin_e($rowSearch) . '" data-state="' . phenixgsmadmin_e($rowState) . '" data-operator="' . phenixgsmadmin_e($rowOperator) . '" data-sim="' . phenixgsmadmin_e($rowSimType) . '">';
        foreach ($columns as $key => $label) {
            $value = $row[$key] ?? '';
            if ($key === 'etatLibelle' && $value === '') {
                $value = $row['etat'] ?? '';
            }
            if ($key === 'portaStatut' && $value === '') {
                $value = $row['portaStatus'] ?? '';
            }
            $html .= '<td>' . phenixgsmadmin_e($value ?: '—') . '</td>';
        }
        $html .= '</tr>';
    }
    if (!$rows) {
        $html .= '<tr><td colspan="' . count($columns) . '" class="text-muted">Aucune donnée retournée.</td></tr>';
    }
    return $html . '</tbody></table></div>';
}

function phenixgsmadmin_output(array $vars)
{
    $moduleLink = $vars['modulelink'] ?? 'addonmodules.php?module=phenixgsmadmin';
    $error = '';
    $lines = $portaIn = $portaOut = [];
    try {
        $linesResponse = phenixgsmadmin_api($vars, '/lines');
        $lines = phenixgsmadmin_rows($linesResponse, 'lines');
        $portaIn = phenixgsmadmin_rows(phenixgsmadmin_api($vars, '/portabilities/in'), 'portabilities');
        $portaOut = phenixgsmadmin_rows(phenixgsmadmin_api($vars, '/portabilities/out'), 'portabilities');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    $active = $suspended = $deleted = 0;
    $operators = $states = $simTypes = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $state = strtolower((string) ($line['etat'] ?? ''));
        if ($state === 'active') {
            $active++;
        } elseif ($state === 'suspended') {
            $suspended++;
        } elseif (in_array($state, ['deleted', 'inactive', 'résiliée', 'resiliee'], true)) {
            $deleted++;
        }
        $operator = (string) ($line['operateur'] ?? 'Inconnu');
        $operators[$operator] = ($operators[$operator] ?? 0) + 1;
        $stateLabel = (string) ($line['etatLibelle'] ?? $line['etat'] ?? 'Inconnu');
        $states[$stateLabel] = ($states[$stateLabel] ?? 0) + 1;
        $simType = (string) ($line['typeSim'] ?? 'Inconnu');
        $simTypes[$simType] = ($simTypes[$simType] ?? 0) + 1;
    }

    echo '<style>
        .phx-wrap{--phx-ink:#1c2940;--phx-muted:#718096;--phx-border:#e5eaf1;--phx-accent:#3158c9;color:var(--phx-ink);margin:20px 0 30px;font-family:inherit}
        .phx-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:20px;padding:23px 25px;border:1px solid #e1e8f7;border-radius:14px;background:linear-gradient(115deg,#f3f6ff,#fbfcff 58%,#eff8ff)}
        .phx-hero h2{margin:0;color:var(--phx-ink);font-size:23px;font-weight:700;letter-spacing:-.02em}.phx-hero p{margin:5px 0 0;color:var(--phx-muted)}
        .phx-eyebrow{margin-bottom:5px;color:#7182a1;font-size:10px;font-weight:700;letter-spacing:.11em}.phx-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .phx-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:12px;margin:0 0 20px}.phx-card{padding:17px 18px;border:1px solid var(--phx-border);border-radius:12px;background:#fff;box-shadow:0 5px 18px rgba(25,42,70,.045)}
        .phx-card small{display:block;margin-bottom:7px;color:var(--phx-muted);font-size:11px;font-weight:650;letter-spacing:.045em;text-transform:uppercase}.phx-card strong{color:var(--phx-ink);font-size:25px;font-weight:750}
        .phx-section{margin-top:20px;padding:20px;border:1px solid var(--phx-border);border-radius:13px;background:#fff;box-shadow:0 5px 18px rgba(25,42,70,.04)}.phx-section h3{margin:0;color:var(--phx-ink);font-size:17px;font-weight:700}
        .phx-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.phx-section-head p{margin:5px 0 0;color:var(--phx-muted)}
        .phx-toolbar{display:flex;gap:9px;margin:16px 0;flex-wrap:wrap}.phx-toolbar input,.phx-toolbar select{max-width:260px;min-width:150px;border-color:#dce3ed;border-radius:8px;box-shadow:none}
        .phx-table{margin:0!important;font-size:13px}.phx-table th{white-space:nowrap;background:#f6f8fc;color:#596982;font-size:11px;letter-spacing:.035em;text-transform:uppercase;cursor:pointer}.phx-table td,.phx-table th{padding:11px 12px!important;vertical-align:middle!important}.phx-table tbody tr:hover{background:#f7f9fd!important}
        .phx-table-wrap{overflow:hidden;border:1px solid var(--phx-border);border-radius:10px}.phx-meta{margin-top:13px;color:var(--phx-muted);font-size:12px}.phx-operator-summary{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px}.phx-tag{display:inline-flex;padding:5px 10px;border:1px solid #e2e8f4;border-radius:99px;background:#f7f9fd;color:#52627a;font-size:11px;font-weight:650}
        .phx-btn{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:7px 13px;border:1px solid #dce3ed;border-radius:8px;background:#fff;color:#34445e;font-weight:650;text-decoration:none;cursor:pointer}.phx-btn:hover{background:#f7f9fc;color:var(--phx-ink);text-decoration:none}.phx-btn-primary{border-color:var(--phx-accent);background:var(--phx-accent);color:#fff}.phx-btn-primary:hover{background:#2446aa;color:#fff}
        .phx-porta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:16px}.phx-empty{padding:20px;color:var(--phx-muted);text-align:center}
        @media(max-width:700px){.phx-hero,.phx-section-head{align-items:flex-start;flex-direction:column}.phx-hero{padding:18px}.phx-section{padding:15px}.phx-porta-grid{grid-template-columns:1fr}.phx-toolbar input,.phx-toolbar select{max-width:none;width:100%}}
    </style><div class="phx-wrap">';
    echo '<header class="phx-hero"><div><div class="phx-eyebrow">ESPACE ADMINISTRATEUR</div><h2>Parc mobile</h2><p>Lignes et portabilités centralisées dans votre espace WHMCS.</p></div><div class="phx-actions"><span class="text-muted">Actualisé le ' . phenixgsmadmin_e(date('d/m/Y à H:i')) . '</span><a class="phx-btn phx-btn-primary" href="' . phenixgsmadmin_e($moduleLink) . '">Actualiser</a></div></header>';
    if ($error !== '') {
        echo '<div class="alert alert-danger"><strong>Lecture API impossible :</strong> ' . phenixgsmadmin_e($error) . '</div>';
    } else {
        echo '<div class="phx-cards">';
        foreach ([['Lignes', count($lines)], ['Actives', $active], ['Suspendues', $suspended], ['Résiliées', $deleted], ['Portabilités IN', count($portaIn)], ['Portabilités OUT', count($portaOut)]] as $card) {
            echo '<div class="phx-card"><small>' . phenixgsmadmin_e($card[0]) . '</small><strong>' . (int) $card[1] . '</strong></div>';
        }
        echo '</div>';
        echo '<div class="phx-operator-summary">';
        foreach ($operators as $operator => $count) {
            echo '<span class="phx-tag">' . phenixgsmadmin_e($operator) . ' · ' . (int) $count . '</span>';
        }
        foreach ($simTypes as $simType => $count) {
            echo '<span class="phx-tag">' . phenixgsmadmin_e($simType) . ' · ' . (int) $count . '</span>';
        }
        echo '</div>';

        echo '<div class="phx-section"><div class="phx-section-head"><div><h3>Parc des lignes</h3><p id="phx-lines-count">' . count($lines) . ' ligne(s)</p></div><button type="button" id="phx-export" class="phx-btn">Exporter les résultats (CSV)</button></div><div class="phx-toolbar">';
        echo '<input id="phx-search" type="search" class="form-control" placeholder="Rechercher MSISDN, forfait, client…">';
        echo '<select id="phx-state" class="form-control"><option value="">Tous les états</option>';
        foreach (array_keys($states) as $state) {
            echo '<option value="' . phenixgsmadmin_e(strtolower($state)) . '">' . phenixgsmadmin_e($state) . '</option>';
        }
        echo '</select>';
        echo '<select id="phx-operator" class="form-control"><option value="">Tous les opérateurs</option>';
        foreach (array_keys($operators) as $operator) {
            echo '<option value="' . phenixgsmadmin_e($operator) . '">' . phenixgsmadmin_e($operator) . '</option>';
        }
        echo '</select><select id="phx-sim" class="form-control"><option value="">SIM et eSIM</option>';
        foreach (array_keys($simTypes) as $simType) {
            echo '<option value="' . phenixgsmadmin_e(strtolower($simType)) . '">' . phenixgsmadmin_e($simType) . '</option>';
        }
        echo '</select></div><div class="phx-table-wrap">' . phenixgsmadmin_table($lines, 'lines') . '</div><p class="phx-meta">Cliquez sur un titre de colonne pour trier. L’export CSV reprend uniquement les lignes visibles après filtrage.</p></div>';
        echo '<div class="phx-porta-grid"><div class="phx-section"><h3>Portabilités entrantes <span class="phx-tag">' . count($portaIn) . '</span></h3><div class="phx-toolbar"><input type="search" class="form-control phx-table-search" data-table="phx-porta-in-table" placeholder="Filtrer les portabilités…"></div><div class="phx-table-wrap">' . phenixgsmadmin_table($portaIn, 'porta-in') . '</div></div>';
        echo '<div class="phx-section"><h3>Portabilités sortantes <span class="phx-tag">' . count($portaOut) . '</span></h3><div class="phx-toolbar"><input type="search" class="form-control phx-table-search" data-table="phx-porta-out-table" placeholder="Filtrer les portabilités…"></div><div class="phx-table-wrap">' . phenixgsmadmin_table($portaOut, 'porta-out') . '</div></div></div>';
        echo '<p class="text-muted">' . count($lines) . ' ligne(s) chargée(s). Les secrets SIM/eSIM et identifiants RADIUS ne sont pas affichés.</p>';
    }
    echo '</div><script>
    (function(){
      var search=document.getElementById("phx-search"), state=document.getElementById("phx-state"), operator=document.getElementById("phx-operator"), sim=document.getElementById("phx-sim");
      if(!search||!state||!operator||!sim)return;
      function filter(){var q=search.value.toLowerCase(),s=state.value.toLowerCase(),o=operator.value.toLowerCase(),m=sim.value.toLowerCase();
        var visible=0;document.querySelectorAll("#phx-lines-table tbody tr").forEach(function(row){var t=(row.innerText||"").toLowerCase();var data=(row.getAttribute("data-search")||"").toLowerCase();
          var rowState=(row.getAttribute("data-state")||"").toLowerCase(),rowOperator=(row.getAttribute("data-operator")||"").toLowerCase(),rowSim=(row.getAttribute("data-sim")||"").toLowerCase();
          var stateMatch=!s||rowState===s||t.indexOf(s)>=0;var operatorMatch=!o||rowOperator===o;var simMatch=!m||rowSim===m;var searchMatch=!q||t.indexOf(q)>=0||data.indexOf(q)>=0;
          row.style.display=(stateMatch&&operatorMatch&&simMatch&&searchMatch)?"":"none";
          if(stateMatch&&operatorMatch&&simMatch&&searchMatch)visible++;
        });var count=document.getElementById("phx-lines-count");if(count)count.textContent=visible+" ligne(s) affichée(s)";}
      search.addEventListener("input",filter);state.addEventListener("change",filter);operator.addEventListener("change",filter);sim.addEventListener("change",filter);
      document.querySelectorAll(".phx-table-search").forEach(function(input){input.addEventListener("input",function(){var table=document.getElementById(input.getAttribute("data-table"));if(!table)return;var q=input.value.toLowerCase();table.querySelectorAll("tbody tr").forEach(function(row){row.style.display=(row.innerText||"").toLowerCase().indexOf(q)>=0?"":"none";});});});
      document.querySelectorAll(".phx-table th").forEach(function(th){th.addEventListener("click",function(){var table=th.closest("table"),body=table&&table.tBodies[0];if(!body)return;var col=Array.prototype.indexOf.call(th.parentNode.children,th),rows=Array.prototype.slice.call(body.rows);var asc=th.getAttribute("data-sort")!=="asc";table.querySelectorAll("th").forEach(function(h){h.removeAttribute("data-sort");});th.setAttribute("data-sort",asc?"asc":"desc");rows.sort(function(a,b){var x=(a.cells[col].innerText||"").trim(),y=(b.cells[col].innerText||"").trim(),sx=x.replace(/[^0-9,.+-]/g,"").replace(",","."),sy=y.replace(/[^0-9,.+-]/g,"").replace(",","."),numericX=/^[+-]?(?:\d+\.?\d*|\.\d+)$/.test(sx),numericY=/^[+-]?(?:\d+\.?\d*|\.\d+)$/.test(sy),cmp=numericX&&numericY?Number(sx)-Number(sy):x.localeCompare(y,"fr",{numeric:true,sensitivity:"base"});return asc?cmp:-cmp;});rows.forEach(function(row){body.appendChild(row);});});});
      var exportButton=document.getElementById("phx-export");if(exportButton)exportButton.addEventListener("click",function(){var table=document.getElementById("phx-lines-table");if(!table)return;var rows=Array.prototype.slice.call(table.querySelectorAll("tr")).filter(function(row){return row.style.display!=="none";});var csv=rows.map(function(row){return Array.prototype.map.call(row.cells,function(cell){return \'"\'+(cell.innerText||"").replace(/"/g,\'""\').trim()+\'"\';}).join(";");}).join("\r\n");var blob=new Blob(["\uFEFF"+csv],{type:"text/csv;charset=utf-8"}),link=document.createElement("a");link.href=URL.createObjectURL(blob);link.download="parc-mobile.csv";link.click();URL.revokeObjectURL(link.href);});
    })();</script>';
}
