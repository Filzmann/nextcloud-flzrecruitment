<?php

declare(strict_types=1);

use OCP\Util;

Util::addStyle('recruitment', 'style');
Util::addScript('recruitment', 'modules/bubble-text');
Util::addScript('recruitment', 'modules/ui-data');
Util::addScript('recruitment', 'modules/api');
Util::addScript('recruitment', 'main');
?>
<main id="recruitment-app" class="recruitment-app" aria-labelledby="recruitment-title">
    <header class="recruitment-header">
        <div>
            <p class="recruitment-eyebrow">Bewerbungsmanagement</p>
            <h1 id="recruitment-title">Recruitment</h1>
        </div>
        <p id="recruitment-status" class="recruitment-status" role="status">Daten werden geladen …</p>
    </header>

    <div id="recruitment-error" class="recruitment-error" role="alert" hidden></div>
    <nav id="recruitment-tabs" class="recruitment-tabs" aria-label="Recruitment-Bereiche"></nav>
    <section id="recruitment-content" class="recruitment-content" aria-live="polite">
        <p>Recruitment wird geladen …</p>
    </section>
</main>
