{* Template dashboard phenix - à inclure dans le template client WHMCS *}
{if !empty($active_products)}
<div class="phenix-dashboard">
    <style>
        .phenix-dashboard { max-width: 900px; margin: 2rem auto; font-family: system-ui, sans-serif; }
        .phenix-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); padding: 1.5rem; margin-bottom: 1.5rem; }
        .phenix-card h3 { margin-top: 0; color: #2c3034; border-bottom: 1px solid #e5e7eb; padding-bottom: .75rem; }
        .phenix-status { display: inline-block; padding: .25rem .75rem; border-radius: 4px; font-weight: 600; font-size: .85rem; }
        .phenix-status.active { background: #dcfce7; color: #16a34a; }
        .phenix-status.suspended { background: #fef3c7; color: #92400e; }
        .phenix-status.deleted, .phenix-status.inactive. { background: #fee2e2; color: #dc2626; }
        .phenix-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .phenix-cell { padding: .75rem; background: #f8f9fa; border-radius: 6px; }
        .phenix-cell label { display: block; font-size: .8rem; color: #6b7280; margin-bottom: .25rem; }
        .phenix-cell span { font-weight: 500; color: #1f2937; }
        .phenix-progress { height: 8px; background: #e5e7eb; border-radius: 4px; margin-top: .5rem; overflow: hidden; }
        .phenix-progress-bar { height: 100%; border-radius: 4px; transition: width .3s ease; }
        .phenix-progress-bar.green { background: #22c55e; }
        .phenix-progress-bar.yellow { background: #f59e0b; }
        .phenix-progress-bar.red { background: #ef4444; }
        .phenix-btn { display: inline-block; padding: .5rem 1.25rem; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; }
        .phenix-btn-primary { background: #3b82f6; color: #fff; }
        .phenix-btn-primary:hover { background: #2563eb; }
        .phenix-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .phenix-table th, .phenix-table td { padding: .75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .phenix-table th { background: #f8f9fa; font-weight: 600; color: #374151; }
    </style>

    {* Boucle sur chaque produit actif *}
    {foreach $active_products as $product}
    <div class="phenix-card">
        <h3>{$product['domain']}</h3>
        
        <div class="phenix-grid">
            <div class="phenix-cell">
                <label>Opérateur</label>
                <span>{if !empty($dashboard_data[$product['domain']]['state'].operateur)}{$dashboard_data[$product['domain']]['state'].operateur}{else}-{/if}</span>
            </div>
            
            <div class="phenix-cell">
                <label>État de la ligne</label>
                {setvar 'etat' value=$dashboard_data[$product['domain']].state.etat|default:'unknown'}
                {if $etat == 'Active'}
                    <span class="phenix-status active">Actif</span>
                {elseif $etat == 'Suspended'}
                    <span class="phenix-status suspended">Suspendu</span>
                {else}
                    <span class="phenix-status deleted">Inactif</span>
                {/if}
            </div>

            <div class="phenix-cell">
                <label>Forfait actuel</label>
                <span>{if !empty($dashboard_data[$product['domain']].state.forfaitGsmCode)}{$dashboard_data[$product['domain']].state.forfaitGsmCode}{else}-{/if}</span>
            </div>

            {if !empty($dashboard_data[$product['domain']].state.dateActivation)}
            <div class="phenix-cell">
                <label>Actif depuis</label>
                <span>{$dashboard_data[$product['domain']].state.dateActivation|date_format:"%d/%m/%Y"}</span>
            </div>
            {/if}
        </div>

        {* Consommation Data temps réel (SDTR) *}
        {if !empty($dashboard_data[$product['domain']].consumption)}
        <h3 style="margin-top: 1.5rem;">Consommation Data</h3>
        <table class="phenix-table">
            <thead>
                <tr>
                    <th>Zone</th>
                    <th>Total</th>
                    <th>Consommé</th>
                    <th>Reste</th>
                    <th>% Utilisé</th>
                </tr>
            </thead>
            <tbody>
                {foreach $dashboard_data[$product['domain']].consumption.zones as $zone}
                <tr>
                    <td>{$zone.libelleZone} ({$zone.codeZone})</td>
                    <td>{$zone.initialValueText}</td>
                    <td>{$zone.usedValueText}</td>
                    <td>{$zone.remainingValueText}</td>
                    <td>
                        {setvar 'pct' value=0}
                        {if !empty($zone.initialValue) && $zone.initialValue > 0}
                            {setvar 'pct' value=round(($zone.usedValue / $zone.initialValue) * 100)}
                        {/if}
                        {$pct}%
                        <div class="phenix-progress">
                            <div class="phenix-progress-bar {if $pct < 50}green{elseif $pct < 80}yellow{else}red{/if}" style="width: {$pct}%"></div>
                        </div>
                    </td>
                </tr>
                {/foreach}
            </tbody>
        </table>

        {* Total en bas *}
        <div class="phenix-grid" style="margin-top: 1rem;">
            <div class="phenix-cell">
                <label>Total consommé</label>
                <span>{$dashboard_data[$product['domain']].consumption.totalUsed}</span>
            </div>
            <div class="phenix-cell">
                <label>Total restant</label>
                <span style="color: {if $dashboard_data[$product['domain']].consumption.totalRemainingBytes > 5120}#16a34a{else}#dc2626{/if}; font-weight: 600;">{$dashboard_data[$product['domain']].consumption.totalRemaining}</span>
            </div>
        </div>
        {/if}

        {* QR Code eSIM *}
        {if !empty($dashboard_data[$product['domain']].qr_available)}
        <h3 style="margin-top: 1.5rem;">eSIM - QR Code d'activation</h3>
        <p>Vous pouvez télécharger le QR code pour activer votre eSIM sur votre appareil.</p>
        <a class="phenix-btn phenix-btn-primary" href="{$smarty.const.HTTP_REQUEST|replace:'https':'http'}://{$smarty.server.SERVER_NAME}/inclues/phenix-download-qr.php?msisdn={$product['msisdn']}">Télécharger le QR Code</a>
        {elseif !empty($dashboard_data[$product['domain']].state)}
            <h3 style="margin-top: 1.5rem;">eSIM</h3>
            <p>QR code disponible uniquement pour les ligne avec type SIM = ESIM.</p>
        {/if}
    </div>

    {* Tableau d'état de la requête GSM en cours (si applicable) *}
    {if !empty($dashboard_data[$product['domain']].pending_request)}
    <div class="phenix-card">
        <h3>Demande en cours</h3>
        <p>Opération: <strong>{$dashboard_data[$product['domain']].pending_request.operation}</strong></p>
        <p>Statut: 
            {if $dashboard_data[$product['domain']].pending_request.statut == 'OK'}
                <span class="phenix-status active">Terminée avec succès</span>
            {elseif $dashboard_data[$product['domain']].pending_request.statut == 'NOK'}
                <span class="phenix-status deleted">Echec</span>
            {else}
                <span class="phenix-status suspended">En cours...</span>
            {/if}
        </p>
    </div>
    {/if}
    {/foreach}
    
    {$dashboard_debug|@print_r}

</div>
{else}
<div class="phenix-card" style="text-align: center; padding: 3rem;">
    <p>Aucune ligne mobile associée à votre compte.</p>
    <p style="color: #6b7280; font-size: .9rem;">Contactez le support si vous pensez qu'il s'agit d'une erreur.</p>
</div>
{/if}
