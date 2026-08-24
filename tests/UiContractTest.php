<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('app shell exposes accessible tabs, status and error regions', static function (): void {
    $template = file_get_contents(dirname(__DIR__) . '/templates/index.php');
    $css = file_get_contents(dirname(__DIR__) . '/css/style.css');
    $main = file_get_contents(dirname(__DIR__) . '/js/main.js');
    if ($template === false || $css === false || $main === false) {
        throw new RuntimeException('UI source is missing');
    }

    assertTrue(str_contains($template, 'role="status"'));
    assertTrue(str_contains($template, 'role="alert"'));
    assertTrue(str_contains($template, 'aria-label="AD-Recruitment-Bereiche"'));
    assertTrue(
        str_contains($template, 'data-orgsuite data-suite="ad" data-current-app="adrecruitment"'),
        'The optional OrgSuite menu host is missing',
    );
    assertTrue(!str_contains($template, "addScript('orgsuite'") && !str_contains($template, "addStyle('orgsuite'"));
    assertTrue(
        preg_match('/\.adrecruitment-app\s*\{[^}]*width:\s*100%/s', $css) === 1,
        'The app root does not use the full available width',
    );
    assertTrue(str_contains($css, 'overflow-y: auto'));
    assertTrue(str_contains($css, ':focus-visible'));
    assertTrue(str_contains($template, "addScript('adrecruitment', 'modules/dialog-overlay');"), 'Dialog controller is not loaded before the app UI');
    assertTrue(str_contains($template, "addScript('adrecruitment', 'modules/application-workbench');"), 'Application workbench model is not loaded before the app UI');
    assertTrue(str_contains($template, "addScript('adrecruitment', 'modules/settings-navigation');"), 'Settings navigation model is not loaded before the app UI');
    assertTrue(str_contains($template, "addScript('adrecruitment', 'modules/contact-links');"), 'Safe contact links are not loaded before the app UI');
    assertTrue(str_contains($css, '.adrecruitment-overlay'), 'Creation overlays have no bounded layout');
    assertTrue(str_contains($css, '.adrecruitment-overlay::backdrop'), 'Creation overlays have no modal backdrop');
    assertTrue(
        preg_match('/\.adrecruitment-overlay:not\(\[open\]\)\s*\{[^}]*display:\s*none/s', $css) === 1,
        'Closed creation overlays are not protected from conflicting Nextcloud dialog styles',
    );
    assertTrue(str_contains($main, "createFormOverlay('Neue Stelle'"), 'New jobs are not opened through an overlay button');
    assertTrue(str_contains($main, "field('Beteiligte Gruppen'"), 'Job responsibilities have no bounded group choice');
    assertTrue(str_contains($main, "field('Verantwortliche Personen suchen'"), 'Job responsibilities have no user search');
    assertTrue(str_contains($main, 'api.jobResponsibilityUsers'), 'The job form does not search users inside selected groups');
    assertTrue(str_contains($main, "event.key !== 'Enter'"), 'The job user search cannot be started with Enter');
    assertTrue(str_contains($main, 'Assistenzstellen benötigen immer eine Basisqualifikation.'), 'The automatic assistant BQ rule is not explained');
    assertTrue(!str_contains($main, "input('responsibleUsers')"), 'Job responsibility users are still entered as free UID text');
    assertTrue(!str_contains($main, "input('responsibleGroups')"), 'Job responsibility groups are still entered as free group-ID text');
    assertTrue(!str_contains($main, "input('basisQualificationRequired', 'checkbox')"), 'The derived BQ rule is still exposed as a checkbox');
    assertTrue(str_contains($main, "createFormOverlay('Bewerber*in manuell anlegen'"), 'Manual applicant creation is not an explicit overlay exception');
    assertTrue(str_contains($main, "createFormOverlay('Bewerbung manuell anlegen'"), 'Manual application creation is not an explicit overlay exception');
    assertTrue(str_contains($main, 'Im Regelbetrieb entstehen Bewerber*innen und Bewerbungen aus eingehenden E-Mails.'), 'Manual application forms do not explain the normal email workflow');
    assertTrue(str_contains($main, "button('Tabelle'"), 'Applications have no table view switch');
    assertTrue(str_contains($main, "button('Karten'"), 'Applications have no card view switch');
    assertTrue(str_contains($main, "field('Bewerbungen durchsuchen'"), 'Application workbench has no search filter');
    assertTrue(str_contains($main, "field('Nach Bürobereich filtern'"), 'Application workbench has no area filter');
    assertTrue(str_contains($main, "field('Nach Zuständigkeit filtern'"), 'Application workbench has no assignee filter');
    assertTrue(str_contains($main, "field('Eingang von'"), 'Application workbench has no inclusive received-from filter');
    assertTrue(str_contains($main, "field('Eingang bis'"), 'Application workbench has no inclusive received-to filter');
    assertTrue(str_contains($main, "field('Sortierung'"), 'Application workbench has no sorting control');
    assertTrue(str_contains($css, '.adrecruitment-board'), 'Application cards have no responsive board layout');
    assertTrue(str_contains($main, "field('Wunschstunden von (ca.)'"), 'Application workflow has no lower approximate desired-hours field');
    assertTrue(str_contains($main, "field('Wunschstunden bis (ca., optional)'"), 'Application workflow has no optional upper desired-hours field');
    assertTrue(str_contains($main, 'desiredHoursLabel('), 'Application views do not format single values and ranges consistently');
    assertTrue(!str_contains($main, "['maritalStatus', 'Familienstand']"), 'Family status is still shown in recruitment');
    assertTrue(str_contains($main, "value: 'student', label: 'Studentisch'"), 'Student employment is missing from contract choices');
    assertTrue(str_contains($main, "value: 'kapovaz', label: 'KAPOVAZ'"), 'KAPOVAZ is missing from contract work-time choices');
    assertTrue(str_contains($main, "['payGrade', 'Entgeltgruppe']"), 'Tariff pay grade is missing from the contract area');
    assertTrue(str_contains($main, "['payStep', 'Tarifstufe']"), 'Tariff pay step is missing from the contract area');
    assertTrue(str_contains($main, 'Haustarifvertrag ambulante dienste e.V.'), 'The supplied collective agreement is not identified as contract basis');
    assertTrue(str_contains($main, "card.addEventListener('dragstart'"), 'Application cards cannot be dragged');
    assertTrue(str_contains($main, "column.addEventListener('drop'"), 'Status columns do not accept application drops');
    assertTrue(str_contains($main, "button('Status verschieben'"), 'Application cards have no keyboard-operable status move');
    assertTrue(str_contains($main, 'function renderApplicationStatusMove(application)'), 'Table and cards have no shared status action');
    assertTrue(str_contains($main, 'const tableStatusMove = renderApplicationStatusMove(application)'), 'Application table has no direct status action');
    assertTrue(str_contains($main, "['payroll', 'Vertragsvorbereitung']"), 'Payroll has no capability-bound UI area');
    assertTrue(str_contains($main, "['settings', 'Einstellungen']"), 'Occasional administration is not bundled in one settings tab');
    assertTrue(str_contains($main, "'aria-label': 'Einstellungsbereiche'"), 'The settings menu has no accessible label');
    assertTrue(str_contains($main, 'RecruitmentSettingsNavigation'), 'Settings visibility is not derived from one capability-bound navigation model');
    assertTrue(str_contains($css, '.adrecruitment-settings-tab[aria-selected="true"]'), 'The active settings section has no visible state');
    assertTrue(str_contains($main, "['basis-qualifications', 'Basisqualifikationen']"), 'HR has no capability-bound BQ area');
    assertTrue(str_contains($main, 'bis die eigenständige BQ-Planer-App angebunden ist'), 'The temporary local BQ administration is not explained.');
    assertTrue(str_contains($main, 'api.createBasisQualificationRun'), 'BQ runs cannot be created from the UI');
    assertTrue(!str_contains($main, 'api.setJobBasisQualificationRequired'), 'The derived BQ rule is still toggleable in the UI');
    assertTrue(str_contains($main, 'api.assignBasisQualification'), 'Eligible applications cannot be assigned to BQ from the UI');
    assertTrue(str_contains($main, 'api.recordBasisQualificationResult'), 'Simple BQ results cannot be recorded from the UI');
    assertTrue(str_contains($main, "field('Bürobereich bei Einstellungsfreigabe'"), 'Hire approval does not expose its mandatory area');
    assertTrue(str_contains($main, 'api.setFirstGuideAccess'), 'Manual first-guide termination is not reachable');
    assertTrue(str_contains($main, "['inbox', 'Posteingang']"), 'Authorized HR users have no incoming-mail tab');
    assertTrue(str_contains($main, 'function renderInbox()'), 'The incoming-mail list is missing');
    assertTrue(str_contains($main, 'function renderInboxDetail(message)'), 'The immutable mail detail is missing');
    assertTrue(str_contains($main, 'api.assignInboxMessage'), 'Incoming mail cannot be assigned to an existing application');
    assertTrue(str_contains($main, 'api.ignoreInboxMessage'), 'Non-application mail cannot be closed in a controlled way');
    assertTrue(str_contains($main, 'detail.inboxMessages'), 'Assigned incoming mail is not visible in the applicant dossier');
    assertTrue(str_contains($main, "contactNode(detail.person.email, 'email')"), 'Applicant email is not a mailto contact action');
    assertTrue(str_contains($main, "contactNode(detail.person.phone, 'phone')"), 'Applicant phone is not a tel contact action');
    assertTrue(str_contains($main, 'api.documentUrl'), 'PDF originals have no scoped inline viewer URL');
    assertTrue(str_contains($template, "modules/pdf-lightbox"), 'The app-local PDF lightbox module is not loaded');
    assertTrue(str_contains($template, "modules/rich-text-editor"), 'The small status-mail RTE is not loaded before the main UI');
    assertTrue(str_contains($main, 'RecruitmentPdfLightbox'), 'PDF originals are not opened through the lightbox');
    assertTrue(str_contains($main, 'PDF in Lightbox öffnen'), 'Documents have no explicit lightbox action');
    assertTrue(!str_contains($main, "element('iframe'"), 'The inaccessible browser PDF iframe is still used');
    assertTrue(str_contains($main, "freeComment: 'Freier Kommentar der Bewerbung'"), 'The lightbox cannot target the additive application comment');
    assertTrue(str_contains($main, 'api.createAttachmentFieldLink'), 'PDF selections cannot be linked to applicant fields');
    assertTrue(str_contains($main, 'attachmentFieldContext'), 'Existing target values are not checked before linking');
    assertTrue(str_contains($main, 'function renderSettings()'), 'The settings tab has no grouped settings menu');
    assertTrue(str_contains($main, "settingsPanel('candidate-pool', 'Datenschutz & Rückstellungen')"), 'HR has no in-module candidate-pool settings.');
    assertTrue(str_contains($main, "element('h2', { text: statusLabel(fromStatus) })"), 'Mail rules are not grouped by their source status');
    assertTrue(str_contains($main, 'text: `${statusLabel(fromStatus)} → ${statusLabel(toStatus)}`'), 'Transitions are not visible as direct mail-rule rows');
    assertTrue(str_contains($main, 'root.RecruitmentRichTextEditor') && str_contains($main, 'richTextEditor.create'), 'Mail templates do not use the small rich-text editor');
    assertTrue(str_contains($main, 'Diese Regel ist zunächst ausgeschaltet.'), 'Prepared transition templates do not explain their safe disabled default');
    assertTrue(str_contains($main, 'function renderOutgoingMailDrafts(detail)'), 'Application details have no outgoing-mail drafts');
    assertTrue(str_contains($main, "input('recipient', 'email'"), 'The recipient cannot be corrected before final approval');
    assertTrue(str_contains($main, 'Ursprüngliche Adresse'), 'A recipient correction would hide the canonical source address');
    assertTrue(str_contains($main, "approve('Kommenden Montag freigeben', 'next_monday')"), 'Drafts cannot be scheduled for next Monday');
    assertTrue(str_contains($main, "input('scheduledAt', 'datetime-local')"), 'Drafts have no arbitrary scheduling control');
    assertTrue(str_contains($main, 'tatsächliche Zustellung an'), 'Test routing is not visibly disclosed before delivery');
    assertTrue(str_contains($main, 'Aktiver Testmodus: Nach Freigabe'), 'Approvers are not warned about test routing before final approval');
    assertTrue(str_contains($main, "target: '_blank'") && str_contains($main, "rel: 'noopener noreferrer'"), 'Email links do not consistently open in a protected new tab');
    assertTrue(str_contains($main, 'Dieser Statuswechsel ist im Regelprozess nicht vorgesehen.'), 'Exceptional status changes have no explicit safety confirmation');
    assertTrue(str_contains($main, 'override_status_transitions'), 'The status override dropdown is not capability-bound');
});

TestRunner::test('withdrawal is a confirmed destructive action and mail rules choose reusable templates', static function (): void {
    $main = file_get_contents(dirname(__DIR__) . '/js/main.js');
    assertTrue(str_contains($main, "confirm('Bewerbung wirklich zurückziehen?')"), 'Withdrawal lacks an explicit confirmation.');
    assertTrue(str_contains($main, "'Bewerbung zurückziehen'"), 'Withdrawal action is not clearly named.');
    assertTrue(str_contains($main, "field('Mailvorlage', templateChoice)"), 'Transition rules cannot choose a reusable template.');
});

TestRunner::test('job form owns tariff-backed contract facts and omits manual salary fields', static function (): void {
    $main = file_get_contents(dirname(__DIR__) . '/js/main.js');
    foreach (['advertisedWeeklyHours', 'fullTimeWeeklyHours', 'vacationDays', 'contractTerm', 'payGrade'] as $field) assertTrue(str_contains($main, "name: '{$field}'") || str_contains($main, "'{$field}'"), "Job field missing: {$field}");
    assertTrue(!str_contains($main, "['salaryAmount', 'Monatsentgelt'"), 'Manual salary field must be removed.');
    assertTrue(!str_contains($main, "['salaryCurrency', 'Währung'"), 'Currency field must be removed.');
});

TestRunner::test('contract data stays compact until editing and uses content-sized controls', static function (): void {
    $main = file_get_contents(dirname(__DIR__) . '/js/main.js');
    assertTrue($main !== false && str_contains($main, "button('Vertragsdaten bearbeiten'"), 'Contract area is not read-first.');
    assertTrue(str_contains($main, "salutation: [") && str_contains($main, "title: ["), 'Salutation and title are not selections.');
    assertTrue(str_contains($main, "maxlength") && str_contains($main, 'adrecruitment-compact-form'), 'Contract controls have no content-oriented sizing.');
});
