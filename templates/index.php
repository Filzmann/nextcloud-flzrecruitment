<?php

declare(strict_types=1);

use OCP\Util;

Util::addStyle('adrecruitment', 'style');
Util::addScript('adrecruitment', 'modules/bubble-text');
Util::addScript('adrecruitment', 'modules/ui-data');
Util::addScript('adrecruitment', 'modules/api');
Util::addScript('adrecruitment', 'main');
?>
<main id="adrecruitment-app" class="adrecruitment-app" aria-labelledby="adrecruitment-title">
    <div class="orgsuite-host" data-orgsuite data-suite="ad" data-current-app="adrecruitment"></div>
    <header class="adrecruitment-header">
        <div>
            <p class="adrecruitment-eyebrow">Bewerbungsmanagement</p>
            <h1 id="adrecruitment-title">AD Recruitment</h1>
        </div>
        <p id="adrecruitment-status" class="adrecruitment-status" role="status">Daten werden geladen …</p>
    </header>

    <div id="adrecruitment-error" class="adrecruitment-error" role="alert" hidden></div>
    <nav id="adrecruitment-tabs" class="adrecruitment-tabs" aria-label="AD-Recruitment-Bereiche"></nav>
    <section id="adrecruitment-content" class="adrecruitment-content" aria-live="polite">
        <p>AD Recruitment wird geladen …</p>
    </section>
</main>
