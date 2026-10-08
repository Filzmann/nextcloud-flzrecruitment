<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript('flzrecruitment', 'modules/api');
Util::addScript('flzrecruitment', 'admin');
Util::addStyle('flzrecruitment', 'style');

$mail = $_['mailSettings'] ?? ['testMode' => false, 'testRecipient' => '', 'revision' => 0];
$extraction = $_['resumeExtractionSettings'] ?? ['method' => 'rules', 'revision' => 0, 'options' => [], 'runtime' => ['pdfTextAvailable' => false, 'engine' => '']];
$permissions = $_['permissionSettings'] ?? ['firstGuideGroupId' => '', 'revision' => 0];
?>
<section id="flzrecruitment-admin" class="section flzrecruitment-admin" aria-labelledby="flzrecruitment-admin-heading">
    <h2 id="flzrecruitment-admin-heading">Filzmann Recruitment</h2>
    <p>Hier werden ausschließlich systemweite Einstellungen gepflegt. Interviewfragen, Mailvorlagen und delegierte Berechtigungen bleiben für das Personalreferat im Recruitment-Arbeitsplatz erreichbar.</p>

    <form id="recr-admin-mail-form" class="flzrecruitment-admin-card" data-revision="<?php p((string)$mail['revision']); ?>">
        <h3>Mail-Testmodus</h3>
        <p>Im Testmodus werden freigegebene Nachrichten serverseitig an eine zentrale Testadresse umgeleitet.</p>
        <label><input id="recr-admin-mail-test-mode" type="checkbox" <?php if ($mail['testMode']): ?>checked<?php endif; ?>> Alle Mails an die Testadresse umleiten</label>
        <label for="recr-admin-mail-test-recipient">Standard-Testadresse</label>
        <input id="recr-admin-mail-test-recipient" type="email" maxlength="320" value="<?php p($mail['testRecipient']); ?>">
        <button id="recr-admin-mail-submit" type="submit" class="primary">Mail-Testeinstellung speichern</button>
        <p id="recr-admin-mail-status" class="flzrecruitment-admin-status" role="status" aria-live="polite"></p>
    </form>

    <form id="recr-admin-extraction-form" class="flzrecruitment-admin-card" data-revision="<?php p((string)$extraction['revision']); ?>">
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
        <p id="recr-admin-extraction-status" class="flzrecruitment-admin-status" role="status" aria-live="polite"></p>
    </form>

    <form id="recr-admin-first-guide-form" class="flzrecruitment-admin-card" data-revision="<?php p((string)$permissions['revision']); ?>">
        <h3>Strukturelle Erstbegleitungsgruppe</h3>
        <label for="recr-admin-first-guide-group">Nextcloud-Gruppe für Erstbegleitungen</label>
        <input id="recr-admin-first-guide-group" type="text" maxlength="255" required value="<?php p($permissions['firstGuideGroupId']); ?>">
        <button id="recr-admin-first-guide-submit" type="submit" class="primary">Erstbegleitungsgruppe speichern</button>
        <p id="recr-admin-first-guide-status" class="flzrecruitment-admin-status" role="status" aria-live="polite"></p>
    </form>
</section>
