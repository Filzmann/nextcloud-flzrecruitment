<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript('adrecruitment', 'modules/api');
Util::addScript('adrecruitment', 'admin');
Util::addStyle('adrecruitment', 'style');

$mail = $_['mailSettings'] ?? ['testMode' => false, 'testRecipient' => '', 'revision' => 0];
$extraction = $_['resumeExtractionSettings'] ?? ['method' => 'rules', 'revision' => 0, 'options' => [], 'runtime' => ['pdfTextAvailable' => false, 'engine' => '']];
$permissions = $_['permissionSettings'] ?? ['firstGuideGroupId' => '', 'revision' => 0];
?>
<section id="adrecruitment-admin" class="section adrecruitment-admin" aria-labelledby="adrecruitment-admin-heading">
    <h2 id="adrecruitment-admin-heading">AD Recruitment</h2>
    <p>Hier werden ausschließlich systemweite Einstellungen gepflegt. Interviewfragen, Mailvorlagen und delegierte Berechtigungen bleiben für das Personalreferat im Recruitment-Arbeitsplatz erreichbar.</p>

    <section class="adrecruitment-admin-card" aria-labelledby="recr-full-access-heading">
        <h3 id="recr-full-access-heading">Zeitlich begrenzter Admin-Vollzugriff</h3>
        <p>Native Nextcloud-Administration erteilt keinen automatischen Zugriff auf Bewerbungsdaten. Eine Freigabe gilt nur für das angegebene Administrationskonto. Maximal 24 Stunden sind zulässig.</p>
        <form id="recr-full-access-form"><label>Admin-Benutzerkennung <input name="targetUid" required maxlength="64" autocomplete="off"></label><label>Dauer <select name="durationMinutes" required><option value="60">1 Stunde</option><option value="240">4 Stunden</option><option value="480">8 Stunden</option><option value="1440">24 Stunden</option></select></label><label><input id="recr-full-access-enabled" name="enabled" type="checkbox" required> Vollzugriff für diesen Zeitraum aktivieren</label><button type="submit" class="primary">Freigabe aktivieren</button></form>
        <p id="recr-full-access-status" class="adrecruitment-admin-status" role="status" aria-live="polite"></p>
        <table><caption>Protokollierte Admin-Vollzugriffszeiträume</caption><thead><tr><th>Ziel-Admin</th><th>Freigegeben von</th><th>Von</th><th>Geplant bis</th><th>Tatsächlich bis / Status</th><th>Aktion</th></tr></thead><tbody id="recr-full-access-history"><tr><td colspan="6">Freigaben werden geladen.</td></tr></tbody></table>
    </section>

    <form id="recr-admin-mail-form" class="adrecruitment-admin-card" data-revision="<?php p((string)$mail['revision']); ?>">
        <h3>Mail-Testmodus</h3>
        <p>Im Testmodus werden freigegebene Nachrichten serverseitig an eine zentrale Testadresse umgeleitet.</p>
        <label><input id="recr-admin-mail-test-mode" type="checkbox" <?php if ($mail['testMode']): ?>checked<?php endif; ?>> Alle Mails an die Testadresse umleiten</label>
        <label for="recr-admin-mail-test-recipient">Standard-Testadresse</label>
        <input id="recr-admin-mail-test-recipient" type="email" maxlength="320" value="<?php p($mail['testRecipient']); ?>">
        <button id="recr-admin-mail-submit" type="submit" class="primary">Mail-Testeinstellung speichern</button>
        <p id="recr-admin-mail-status" class="adrecruitment-admin-status" role="status" aria-live="polite"></p>
    </form>

    <form id="recr-admin-extraction-form" class="adrecruitment-admin-card" data-revision="<?php p((string)$extraction['revision']); ?>">
        <h3>Datenextraktion aus Lebensläufen</h3>
        <p><?php p($extraction['runtime']['pdfTextAvailable'] ? 'Lokales PDF-Textwerkzeug: ' . $extraction['runtime']['engine'] : 'Kein lokales PDF-Textwerkzeug verfügbar. Bereits normalisierter Text wird weiterhin ausgewertet.'); ?></p>
        <label for="recr-admin-extraction-method">Verfahren</label>
        <select id="recr-admin-extraction-method" required>
            <?php foreach ($extraction['options'] as $option): ?>
                <option value="<?php p($option['value']); ?>" <?php if ($option['value'] === $extraction['method']): ?>selected<?php endif; ?> <?php if (!$option['available']): ?>disabled<?php endif; ?>><?php p($option['label']); ?><?php if (!$option['available']): ?> – noch nicht angebunden<?php endif; ?></option>
            <?php endforeach; ?>
        </select>
        <p>Die lokale Stichworterkennung überträgt keine Bewerbungsdaten an KI-Dienste. Ein lokales Server-Modell ist vorbereitet, aber noch nicht angebunden.</p>
        <button id="recr-admin-extraction-submit" type="submit" class="primary">Extraktionsverfahren speichern</button>
        <p id="recr-admin-extraction-status" class="adrecruitment-admin-status" role="status" aria-live="polite"></p>
    </form>

    <form id="recr-admin-first-guide-form" class="adrecruitment-admin-card" data-revision="<?php p((string)$permissions['revision']); ?>">
        <h3>Strukturelle Erstbegleitungsgruppe</h3>
        <label for="recr-admin-first-guide-group">Nextcloud-Gruppe für Erstbegleitungen</label>
        <input id="recr-admin-first-guide-group" type="text" maxlength="255" required value="<?php p($permissions['firstGuideGroupId']); ?>">
        <button id="recr-admin-first-guide-submit" type="submit" class="primary">Erstbegleitungsgruppe speichern</button>
        <p id="recr-admin-first-guide-status" class="adrecruitment-admin-status" role="status" aria-live="polite"></p>
    </form>
</section>
