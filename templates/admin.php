<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript('flzrecruitment', 'modules/api');
Util::addScript('flzrecruitment', 'admin');
Util::addStyle('flzrecruitment', 'style');

$mail = $_['mailSettings'] ?? ['testMode' => false, 'testRecipient' => '', 'revision' => 0];
$extraction = $_['resumeExtractionSettings'] ?? ['method' => 'rules', 'revision' => 0, 'options' => [], 'runtime' => ['pdfTextAvailable' => false, 'engine' => '']];
?>
<section id="flzrecruitment-admin" class="section flzrecruitment-admin" aria-labelledby="flzrecruitment-admin-heading">
    <h2 id="flzrecruitment-admin-heading"><?php p($l->t('Filzmann Recruitment')); ?></h2>
    <p><?php p($l->t('Hier werden ausschließlich systemweite Einstellungen gepflegt. Interviewfragen, Mailvorlagen und delegierte Berechtigungen bleiben für das Personalreferat im Recruitment-Arbeitsplatz erreichbar.')); ?></p>

    <form id="recr-admin-mail-form" class="flzrecruitment-admin-card" data-revision="<?php p((string)$mail['revision']); ?>">
        <h3><?php p($l->t('Mail-Testmodus')); ?></h3>
        <p><?php p($l->t('Im Testmodus werden freigegebene Nachrichten serverseitig an eine zentrale Testadresse umgeleitet.')); ?></p>
        <label><input id="recr-admin-mail-test-mode" type="checkbox" <?php if ($mail['testMode']): ?>checked<?php endif; ?>> <?php p($l->t('Alle Mails an die Testadresse umleiten')); ?></label>
        <label for="recr-admin-mail-test-recipient"><?php p($l->t('Standard-Testadresse')); ?></label>
        <input id="recr-admin-mail-test-recipient" type="email" maxlength="320" value="<?php p($mail['testRecipient']); ?>">
        <button id="recr-admin-mail-submit" type="submit" class="primary"><?php p($l->t('Mail-Testeinstellung speichern')); ?></button>
        <p id="recr-admin-mail-status" class="flzrecruitment-admin-status" role="status" aria-live="polite"></p>
    </form>

    <form id="recr-admin-extraction-form" class="flzrecruitment-admin-card" data-revision="<?php p((string)$extraction['revision']); ?>">
        <h3><?php p($l->t('Datenextraktion aus Lebensläufen')); ?></h3>
        <p><?php p($extraction['runtime']['pdfTextAvailable'] ? $l->t('Lokales PDF-Textwerkzeug: ') . $extraction['runtime']['engine'] : $l->t('Kein lokales PDF-Textwerkzeug verfügbar. Bereits normalisierter Text wird weiterhin ausgewertet.')); ?></p>
        <label for="recr-admin-extraction-method"><?php p($l->t('Verfahren')); ?></label>
        <select id="recr-admin-extraction-method" required>
            <?php foreach ($extraction['options'] as $option): ?>
                <option value="<?php p($option['value']); ?>" <?php if ($option['value'] === $extraction['method']): ?>selected<?php endif; ?> <?php if (!$option['available']): ?>disabled<?php endif; ?>><?php p($option['label']); ?><?php if (!$option['available']): ?> – <?php p($l->t('noch nicht angebunden')); ?><?php endif; ?></option>
            <?php endforeach; ?>
        </select>
        <p><?php p($l->t('Die lokale Stichworterkennung überträgt keine Bewerbungsdaten an KI-Dienste. Ein lokales Server-Modell ist vorbereitet, aber noch nicht angebunden.')); ?></p>
        <button id="recr-admin-extraction-submit" type="submit" class="primary"><?php p($l->t('Extraktionsverfahren speichern')); ?></button>
        <p id="recr-admin-extraction-status" class="flzrecruitment-admin-status" role="status" aria-live="polite"></p>
    </form>
</section>
