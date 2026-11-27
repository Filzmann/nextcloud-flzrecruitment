<?php

declare(strict_types=1);

use OCP\Util;

Util::addStyle('flzrecruitment', 'style');
Util::addScript('flzrecruitment', 'modules/api');
Util::addScript('flzrecruitment', 'shared/overlay');
Util::addScript('flzrecruitment', 'admin-access');
if ($_['hasRecruitmentAccess'] ?? false) {
    Util::addScript('flzrecruitment', 'modules/bubble-text');
    Util::addScript('flzrecruitment', 'modules/ui-data');
    Util::addScript('flzrecruitment', 'modules/contact-links');
    Util::addScript('flzrecruitment', 'modules/application-workbench');
    Util::addScript('flzrecruitment', 'modules/settings-navigation');
    Util::addScript('flzrecruitment', 'modules/dialog-overlay');
    Util::addScript('flzrecruitment', 'modules/pdf-lightbox');
    Util::addScript('flzrecruitment', 'modules/rich-text-editor');
    Util::addScript('flzrecruitment', 'main');
}
?>
<main id="flzrecruitment-app" class="flzrecruitment-app" aria-labelledby="flzrecruitment-title">
    <div class="orgsuite-host" data-orgsuite data-suite="flz" data-current-app="flzrecruitment"></div>
    <header class="flzrecruitment-header">
        <div>
            <p class="flzrecruitment-eyebrow">Bewerbungsmanagement</p>
            <div class="recr-title-row"><h1 id="flzrecruitment-title">Filzmann Recruitment</h1><?php if ($_['showMissingAdminGrant'] ?? false): ?><button type="button" id="recr-admin-grant-warning" class="flz-shared-warn-trigger" aria-haspopup="dialog" aria-expanded="false" aria-controls="recr-admin-access-overlay" aria-label="Fehlender fachlicher Admin-Vollzugriff: Freigabesteuerung im Overlay öffnen"><span aria-hidden="true">⚠</span></button><?php endif; ?></div>
        </div>
        <p id="flzrecruitment-status" class="flzrecruitment-status" role="status"><?php p(($_['hasRecruitmentAccess'] ?? false) ? 'Daten werden geladen …' : 'Freigabesteuerung'); ?></p>
    </header>

    <?php if ($_['showMissingAdminGrant'] ?? false): ?>
        <aside hidden class="flzrecruitment-access-notice" role="status">
            <strong>Für dieses Administrationskonto ist kein zeitlich begrenzter fachlicher Vollzugriff aktiv.</strong>
            <?php if ($_['showAdminAccessLink'] ?? false): ?>
                <button type="button" data-open-admin-access>Freigabesteuerung öffnen</button>
            <?php endif; ?>
        </aside>
    <?php endif; ?>

    <?php if ($_['hasRecruitmentAccess'] ?? false): ?>
        <div id="flzrecruitment-error" class="flzrecruitment-error" role="alert" hidden></div>
        <nav id="flzrecruitment-tabs" class="flzrecruitment-tabs" aria-label="Filzmann-Recruitment-Bereiche"></nav>
        <section id="flzrecruitment-content" class="flzrecruitment-content" aria-live="polite">
            <p>Filzmann Recruitment wird geladen …</p>
        </section>
    <?php endif; ?>

    <?php if (($_['showMissingAdminGrant'] ?? false) || ($_['canManageAdminAccess'] ?? false)): ?>
    <div id="recr-admin-access-overlay" class="flz-shared-overlay" hidden>
        <div class="flz-shared-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="recr-admin-access-overlay-title">
            <div class="flz-shared-overlay__head">
                <h2 id="recr-admin-access-overlay-title">Zeitlich begrenzter Admin-Vollzugriff</h2>
                <button type="button" id="recr-admin-access-overlay-close" aria-label="Schließen" title="Schließen">×</button>
            </div>
            <div class="flz-shared-overlay__content">
                <?php if ($_['showMissingAdminGrant'] ?? false): ?>
                    <div class="flz-shared-overlay__warning">
                        <p><strong>Kein fachlicher Admin-Vollzugriff.</strong></p>
                        <p>Native Nextcloud-Administration erteilt keinen fachlichen Vollzugriff. Es fehlt eine aktive app-lokale Freigabe.</p>
                        <p>Freigaben können Mitglieder des Personalreferats oder der Gruppe Datenschutzbeauftragte erteilen oder widerrufen, höchstens für 24 Stunden.</p>
                    </div>
                <?php endif; ?>
                <?php if ($_['canManageAdminAccess'] ?? false): ?>
                    <section id="recr-full-access" class="flzrecruitment-admin-card flzrecruitment-access-card" aria-labelledby="recr-full-access-heading">
                        <h2 id="recr-full-access-heading">Freigabesteuerung</h2>
                        <p>Mitglieder des Personalreferats oder der Gruppe Datenschutzbeauftragte dürfen aktuellen Nextcloud-Administrationskonten einen fachlichen Vollzugriff erteilen. Maximal 24 Stunden sind zulässig.</p>
                        <form id="recr-full-access-form"><label>Admin-Benutzerkennung <input name="targetUid" required maxlength="64" autocomplete="off"></label><label>Dauer <select name="durationMinutes" required><option value="60">1 Stunde</option><option value="240">4 Stunden</option><option value="480">8 Stunden</option><option value="1440">24 Stunden</option></select></label><label><input id="recr-full-access-enabled" name="enabled" type="checkbox" required> Vollzugriff für diesen Zeitraum aktivieren</label><button type="submit" class="primary">Freigabe aktivieren</button></form>
                        <p id="recr-full-access-status" class="flzrecruitment-admin-status" role="status" aria-live="polite"></p>
                        <div class="flzrecruitment-table-scroll" tabindex="0" role="region" aria-label="Protokollierte Admin-Vollzugriffszeiträume"><table><caption>Protokollierte Admin-Vollzugriffszeiträume</caption><thead><tr><th>Ziel-Admin</th><th>Freigegeben von</th><th>Von</th><th>Geplant bis</th><th>Tatsächlich bis / Status</th><th>Aktion</th></tr></thead><tbody id="recr-full-access-history"><tr><td colspan="6">Freigaben werden geladen.</td></tr></tbody></table></div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>
