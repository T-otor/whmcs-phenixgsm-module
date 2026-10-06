<div class="phenixgsm-clientarea">
    <header class="phenixgsm-hero">
        <div class="phenixgsm-hero-mark" aria-hidden="true">
            {if $operatorKey == 'ORANGE'}
                <span class="phenixgsm-operator-logo phenixgsm-logo-orange">orange</span>
            {elseif $operatorKey == 'SFR'}
                <span class="phenixgsm-operator-logo phenixgsm-logo-sfr">SFR</span>
            {elseif $operatorKey == 'BTBD'}
                <span class="phenixgsm-operator-logo phenixgsm-logo-btbd">bouygues<small>telecom</small></span>
            {else}
                <span class="phenixgsm-operator-logo phenixgsm-logo-generic">MOBILE</span>
            {/if}
        </div>
        <div class="phenixgsm-hero-copy">
            <div class="phenixgsm-eyebrow">ESPACE CLIENT</div>
            <h2>Ma ligne mobile</h2>
            <p>Retrouvez les informations et le suivi de votre ligne.</p>
        </div>
    </header>

    {if $apiError}
        <div class="phenixgsm-alert phenixgsm-alert-warning" role="alert">{$apiError|escape}</div>
    {elseif !$lineFound}
        <section class="phenixgsm-card phenixgsm-empty-state">
            <div class="phenixgsm-empty-icon" aria-hidden="true">—</div>
            <h3>Aucune ligne associée</h3>
            <p>Aucune ligne mobile n’est encore associée à ce service.</p>
        </section>
    {else}
        <section class="phenixgsm-card">
            <div class="phenixgsm-section-heading">
                <div>
                    <div class="phenixgsm-eyebrow">VOTRE SERVICE</div>
                    <h3>Vue d’ensemble</h3>
                </div>
                <span class="phenixgsm-status">{$lineStatus|escape}</span>
            </div>

            <div class="phenixgsm-details-grid">
                <div class="phenixgsm-detail">
                    <span class="phenixgsm-detail-label">Numéro mobile</span>
                    <strong class="phenixgsm-detail-value phenixgsm-number">{$lineNumber|escape}</strong>
                </div>
                <div class="phenixgsm-detail">
                    <span class="phenixgsm-detail-label">Opérateur</span>
                    <strong class="phenixgsm-detail-value">{$operator|escape}</strong>
                </div>
                <div class="phenixgsm-detail">
                    <span class="phenixgsm-detail-label">Type de carte</span>
                    <strong class="phenixgsm-detail-value">{$simType|escape}</strong>
                </div>
                {if $activationDate}
                    <div class="phenixgsm-detail">
                        <span class="phenixgsm-detail-label">Date d’activation</span>
                        <strong class="phenixgsm-detail-value">{$activationDate|escape}</strong>
                    </div>
                {/if}
            </div>
        </section>

        {if $isEsim}
            <section class="phenixgsm-card phenixgsm-esim-card">
                <div class="phenixgsm-section-heading">
                    <div>
                        <div class="phenixgsm-eyebrow">INSTALLATION</div>
                        <h3>Votre eSIM</h3>
                    </div>
                    <span class="phenixgsm-chip">eSIM</span>
                </div>
                {if !$showEsim}
                    <p class="phenixgsm-section-intro">Affichez votre QR code lorsque vous êtes prêt à installer votre eSIM.</p>
                    <form method="get" action="{$WEB_ROOT}/clientarea.php" class="phenixgsm-esim-action">
                        <input type="hidden" name="action" value="productdetails">
                        <input type="hidden" name="id" value="{$serviceId|escape}">
                        <input type="hidden" name="phenixgsm_month" value="{$selectedMonthValue|escape}">
                        <input type="hidden" name="phenixgsm_show_esim" value="1">
                        <input type="hidden" name="phenixgsm_show_sim_codes" value="1">
                        <button type="submit" class="phenixgsm-button phenixgsm-button-primary">Afficher mon QR code</button>
                        <span class="phenixgsm-privacy-note">Le code d’activation est chargé uniquement à votre demande.</span>
                    </form>
                {elseif $activationCode}
                    <div class="phenixgsm-esim-actions">
                        <p class="phenixgsm-section-intro">Scannez ce code avec l’appareil sur lequel vous souhaitez installer l’eSIM.</p>
                        <button type="button" id="phenixgsm-esim-toggle" class="phenixgsm-button phenixgsm-button-secondary" aria-expanded="true" aria-controls="phenixgsm-esim-content">Masquer le QR code</button>
                    </div>
                    <div id="phenixgsm-esim-content" class="phenixgsm-esim-content" data-activation-code="{$activationCode|escape}" data-confirmation-code="{$confirmationCode|escape}">
                        <div id="phenixgsm-esim-qr" aria-label="QR code d’activation eSIM"></div>
                        <div class="phenixgsm-esim-secret">
                            <span class="phenixgsm-detail-label">Code d’activation</span>
                            <code id="phenixgsm-esim-lpa" class="phenixgsm-lpa"></code>
                            {if $confirmationCode}
                                <span class="phenixgsm-detail-label phenixgsm-confirmation-label">Code de confirmation</span>
                                <code id="phenixgsm-esim-confirmation" class="phenixgsm-lpa"></code>
                            {/if}
                            <span class="phenixgsm-detail-label phenixgsm-confirmation-label">PIN initial</span>
                            <code class="phenixgsm-lpa">{if $pin1}{$pin1|escape}{else}Non communiqué{/if}</code>
                            <span class="phenixgsm-detail-label phenixgsm-confirmation-label">PUK 1</span>
                            <code class="phenixgsm-lpa">{if $puk1}{$puk1|escape}{else}Non communiqué{/if}</code>
                            <span class="phenixgsm-detail-label phenixgsm-confirmation-label">PUK 2</span>
                            <code class="phenixgsm-lpa">{if $puk2}{$puk2|escape}{else}Non communiqué{/if}</code>
                        </div>
                        <div id="phenixgsm-esim-qr-error" class="phenixgsm-alert phenixgsm-alert-warning" hidden>Le QR code n’a pas pu être généré. Utilisez le code d’activation ci-dessus.</div>
                    </div>
                {elseif $esimError}
                    <div class="phenixgsm-alert phenixgsm-alert-info" role="alert">{$esimError|escape}</div>
                    <form method="get" action="{$WEB_ROOT}/clientarea.php" class="phenixgsm-esim-action">
                        <input type="hidden" name="action" value="productdetails">
                        <input type="hidden" name="id" value="{$serviceId|escape}">
                        <input type="hidden" name="phenixgsm_month" value="{$selectedMonthValue|escape}">
                        <input type="hidden" name="phenixgsm_show_esim" value="1">
                        <button type="submit" class="phenixgsm-button phenixgsm-button-secondary">Réessayer</button>
                    </form>
                {/if}
            </section>
        {/if}

        {if !$isEsim}
            <section class="phenixgsm-card phenixgsm-security-card">
                <div class="phenixgsm-section-heading">
                    <div>
                        <div class="phenixgsm-eyebrow">INFORMATIONS SÉCURISÉES</div>
                        <h3>Codes de la carte SIM</h3>
                    </div>
                    <span class="phenixgsm-chip phenixgsm-chip-muted">PIN / PUK</span>
                </div>
                {if !$showSimCodes}
                    <p class="phenixgsm-section-intro">Les codes ne sont récupérés et affichés qu’après votre demande.</p>
                    <form method="get" action="{$WEB_ROOT}/clientarea.php" class="phenixgsm-esim-action">
                        <input type="hidden" name="action" value="productdetails">
                        <input type="hidden" name="id" value="{$serviceId|escape}">
                        <input type="hidden" name="phenixgsm_month" value="{$selectedMonthValue|escape}">
                        <input type="hidden" name="phenixgsm_show_sim_codes" value="1">
                        <button type="submit" class="phenixgsm-button phenixgsm-button-primary">Afficher mes codes SIM</button>
                    </form>
                {elseif $simCodesError}
                    <div class="phenixgsm-alert phenixgsm-alert-info" role="alert">{$simCodesError|escape}</div>
                {else}
                    <button type="button" id="phenixgsm-sim-codes-toggle" class="phenixgsm-button phenixgsm-button-secondary" aria-expanded="true" aria-controls="phenixgsm-sim-codes-content">Masquer mes codes SIM</button>
                    <div id="phenixgsm-sim-codes-content" class="phenixgsm-sim-secret-grid">
                        <div><span class="phenixgsm-detail-label">PIN initial</span><code class="phenixgsm-lpa">{if $pin1}{$pin1|escape}{else}Non communiqué{/if}</code></div>
                        <div><span class="phenixgsm-detail-label">PUK 1</span><code class="phenixgsm-lpa">{if $puk1}{$puk1|escape}{else}Non communiqué{/if}</code></div>
                        <div><span class="phenixgsm-detail-label">PUK 2</span><code class="phenixgsm-lpa">{if $puk2}{$puk2|escape}{else}Non communiqué{/if}</code></div>
                    </div>
                {/if}
            </section>
        {/if}

        {if $consumptionHtml || $consumptionError}
            <section class="phenixgsm-card">
                <div class="phenixgsm-section-heading">
                    <div>
                        <div class="phenixgsm-eyebrow">SUIVI DATA</div>
                        <h3>Consommation en cours</h3>
                    </div>
                    <span class="phenixgsm-chip phenixgsm-chip-muted">Temps réel</span>
                </div>
                {if $consumptionError}<div class="phenixgsm-alert phenixgsm-alert-info" role="status">{$consumptionError|escape}</div>{/if}
                {$consumptionChartHtml nofilter}
                {$consumptionHtml nofilter}
            </section>
        {/if}

        <section class="phenixgsm-card">
            <div class="phenixgsm-section-heading phenixgsm-stats-heading">
                <div>
                    <div class="phenixgsm-eyebrow">SUIVI DE LA LIGNE</div>
                    <h3>Statistiques mensuelles</h3>
                    <p class="phenixgsm-section-intro">Période sélectionnée : <strong>{$monthLabel|escape}</strong></p>
                </div>
            </div>
            <form method="get" action="{$WEB_ROOT}/clientarea.php" class="phenixgsm-month-form">
                <input type="hidden" name="action" value="productdetails">
                <input type="hidden" name="id" value="{$serviceId|escape}">
                <label for="phenixgsm-month">Mois à consulter</label>
                <div class="phenixgsm-month-controls">
                    <select id="phenixgsm-month" name="phenixgsm_month" class="form-control">
                        {$statsMonthOptionsHtml nofilter}
                    </select>
                    <button type="submit" class="phenixgsm-button phenixgsm-button-primary">Afficher</button>
                </div>
            </form>
            <div class="phenixgsm-stats-content">
                {$monthlyStatsHtml nofilter}
            </div>
        </section>
    {/if}
</div>

{if $activationCode || $showSimCodes}
    <script src="{$WEB_ROOT}/modules/servers/phenixgsm/assets/qrcode.min.js"></script>
    <script src="{$WEB_ROOT}/modules/servers/phenixgsm/assets/clientarea.js"></script>
{/if}
<link rel="stylesheet" href="{$WEB_ROOT}/modules/servers/phenixgsm/assets/clientarea.css?v=4">
