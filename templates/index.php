<?php

declare(strict_types=1);

use OCP\Util;

Util::addStyle('adrecruitment', 'style');
Util::addScript('adrecruitment', 'modules/api');
Util::addScript('adrecruitment', 'admin-access');
if ($_['hasRecruitmentAccess'] ?? false) {
    Util::addScript('adrecruitment', 'modules/bubble-text');
    Util::addScript('adrecruitment', 'modules/ui-data');
    Util::addScript('adrecruitment', 'modules/contact-links');
    Util::addScript('adrecruitment', 'modules/application-workbench');
    Util::addScript('adrecruitment', 'modules/settings-navigation');
    Util::addScript('adrecruitment', 'modules/dialog-overlay');
    Util::addScript('adrecruitment', 'modules/pdf-lightbox');
    Util::addScript('adrecruitment', 'modules/rich-text-editor');
    Util::addScript('adrecruitment', 'main');
}
?>
<main id="adrecruitment-app" class="adrecruitment-app" aria-labelledby="adrecruitment-title">
    <div class="orgsuite-host" data-orgsuite data-suite="ad" data-current-app="adrecruitment"></div>
    <header class="adrecruitment-header">
        <div>
            <p class="adrecruitment-eyebrow">Bewerbungsmanagement</p>
            <h1 id="adrecruitment-title">AD Recruitment</h1>
        </div>
        <p id="adrecruitment-status" class="adrecruitment-status" role="status"><?php p(($_['hasRecruitmentAccess'] ?? false) ? 'Daten werden geladen …' : 'Freigabesteuerung'); ?></p>
    </header>

    <?php if ($_['showMissingAdminGrant'] ?? false): ?>
        <aside class="adrecruitment-access-notice" role="status">
            <strong>Für dieses Administrationskonto ist kein zeitlich begrenzter fachlicher Vollzugriff aktiv.</strong>
            <?php if ($_['showAdminAccessLink'] ?? false): ?>
                <a href="#recr-full-access-heading">Freigabesteuerung öffnen</a>
            <?php endif; ?>
        </aside>
    <?php endif; ?>

    <?php if ($_['canManageAdminAccess'] ?? false): ?>
        <section id="recr-full-access" class="adrecruitment-admin-card adrecruitment-access-card" aria-labelledby="recr-full-access-heading">
            <h2 id="recr-full-access-heading" tabindex="-1">Zeitlich begrenzter Admin-Vollzugriff</h2>
            <p>Ausschließlich Mitglieder der Gruppe Datenschutzbeauftragte dürfen aktuellen Nextcloud-Administrationskonten einen fachlichen Vollzugriff erteilen. Maximal 24 Stunden sind zulässig.</p>
            <form id="recr-full-access-form"><label>Admin-Benutzerkennung <input name="targetUid" required maxlength="64" autocomplete="off"></label><label>Dauer <select name="durationMinutes" required><option value="60">1 Stunde</option><option value="240">4 Stunden</option><option value="480">8 Stunden</option><option value="1440">24 Stunden</option></select></label><label><input id="recr-full-access-enabled" name="enabled" type="checkbox" required> Vollzugriff für diesen Zeitraum aktivieren</label><button type="submit" class="primary">Freigabe aktivieren</button></form>
            <p id="recr-full-access-status" class="adrecruitment-admin-status" role="status" aria-live="polite"></p>
            <div class="adrecruitment-table-scroll" tabindex="0" role="region" aria-label="Protokollierte Admin-Vollzugriffszeiträume"><table><caption>Protokollierte Admin-Vollzugriffszeiträume</caption><thead><tr><th>Ziel-Admin</th><th>Freigegeben von</th><th>Von</th><th>Geplant bis</th><th>Tatsächlich bis / Status</th><th>Aktion</th></tr></thead><tbody id="recr-full-access-history"><tr><td colspan="6">Freigaben werden geladen.</td></tr></tbody></table></div>
        </section>
    <?php endif; ?>

    <?php if ($_['hasRecruitmentAccess'] ?? false): ?>
        <div id="adrecruitment-error" class="adrecruitment-error" role="alert" hidden></div>
        <nav id="adrecruitment-tabs" class="adrecruitment-tabs" aria-label="AD-Recruitment-Bereiche"></nav>
        <section id="adrecruitment-content" class="adrecruitment-content" aria-live="polite">
            <p>AD Recruitment wird geladen …</p>
        </section>
    <?php endif; ?>
</main>
