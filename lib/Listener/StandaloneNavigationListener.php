<?php

declare(strict_types=1);

namespace OCA\Recruitment\Listener;

use OCA\LocalBase\Service\StandaloneAppNavigationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

/** @template-implements IEventListener<LoadAdditionalEntriesEvent> */
final class StandaloneNavigationListener implements IEventListener {
    public function __construct(private StandaloneAppNavigationService $navigation) {
    }

    public function handle(Event $event): void {
        if (!$event instanceof LoadAdditionalEntriesEvent) {
            return;
        }

        $this->navigation->addCatalogProductWhenStandalone('adrecruitment', 'Recruitment', 'app.svg');
    }
}
