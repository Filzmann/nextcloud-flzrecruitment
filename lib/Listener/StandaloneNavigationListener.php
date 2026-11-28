<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Listener;

use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;
use OCP\Navigation\INavigationManager;

/** @template-implements IEventListener<LoadAdditionalEntriesEvent> */
final class StandaloneNavigationListener implements IEventListener {
    private const SUITE_APP_ID = 'orgsuite';

    public function __construct(
        private IUserSession $userSession,
        private IAppManager $appManager,
        private INavigationManager $navigation,
        private IURLGenerator $url,
        private IL10N $l10n,
    ) {
    }

    public function handle(Event $event): void {
        if (!$event instanceof LoadAdditionalEntriesEvent) {
            return;
        }
        $user = $this->userSession->getUser();
        if ($user === null || $this->appManager->isEnabledForUser(self::SUITE_APP_ID, $user)) return;
        $this->navigation->add(fn(): array => [
            'id' => 'flzrecruitment',
            'type' => INavigationManager::TYPE_APPS,
            'app' => 'flzrecruitment',
            'href' => $this->url->linkToRoute('flzrecruitment.page.index'),
            'icon' => $this->url->imagePath('flzrecruitment', 'app.svg'),
            'name' => $this->l10n->t('Recruitment'),
            'order' => 84,
        ]);
    }
}
