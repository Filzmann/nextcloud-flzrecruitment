<?php

declare(strict_types=1);

namespace OCA\Recruitment\AppInfo;

use OCA\Recruitment\Contract\MailAttachmentStorage;
use OCA\Recruitment\Contract\MailInboxStore;
use OCA\Recruitment\Contract\DocumentFieldLinkStore;
use OCA\Recruitment\Contract\DocumentReviewStore;
use OCA\Recruitment\Listener\StandaloneNavigationListener;
use OCA\Recruitment\Privacy\RecruitmentPrivacyProviderListener;
use OCA\LocalBase\Privacy\PersonalDataProviderRegistryEvent;
use OCA\Recruitment\Repository\RecruitmentRepository;
use OCA\Recruitment\Service\AppDataMailAttachmentStorage;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

final class Application extends App implements IBootstrap {
    public const APP_ID = 'adrecruitment';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(LoadAdditionalEntriesEvent::class, StandaloneNavigationListener::class);
        $context->registerEventListener(PersonalDataProviderRegistryEvent::class, RecruitmentPrivacyProviderListener::class);
        $context->registerServiceAlias(MailInboxStore::class, RecruitmentRepository::class);
        $context->registerServiceAlias(MailAttachmentStorage::class, AppDataMailAttachmentStorage::class);
        $context->registerServiceAlias(DocumentReviewStore::class, RecruitmentRepository::class);
        $context->registerServiceAlias(DocumentFieldLinkStore::class, RecruitmentRepository::class);
    }

    public function boot(IBootContext $context): void {
    }
}
