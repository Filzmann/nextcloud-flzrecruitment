<?php

declare(strict_types=1);

namespace OCA\Recruitment\AppInfo;

use OCA\Recruitment\Contract\MailAttachmentStorage;
use OCA\Recruitment\Contract\PdfTextExtractor;
use OCA\Recruitment\Contract\CandidatePoolStore;
use OCA\Recruitment\Contract\MailInboxStore;
use OCA\Recruitment\Contract\DocumentFieldLinkStore;
use OCA\Recruitment\Contract\OutboundMailTransport;
use OCA\Recruitment\Contract\StatusMailOutboxStore;
use OCA\Recruitment\Contract\DocumentReviewStore;
use OCA\Recruitment\Listener\StandaloneNavigationListener;
use OCA\Recruitment\Permission\NextcloudRecruitmentPermissionSource;
use OCA\Recruitment\Permission\RecruitmentPermissionProviderListener;
use OCA\Recruitment\Permission\RecruitmentPermissionSourceInterface;
use OCA\Recruitment\Privacy\RecruitmentPrivacyProviderListener;
use OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCA\FilzmannPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
use OCA\Recruitment\Repository\RecruitmentRepository;
use OCA\Recruitment\Service\AppDataMailAttachmentStorage;
use OCA\Recruitment\Service\NextcloudOutboundMailTransport;
use OCA\Recruitment\Service\LocalPdfTextExtractor;
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
        $context->registerEventListener(RegisterPersonalDataProvidersEvent::class, RecruitmentPrivacyProviderListener::class);
        $context->registerEventListener(RegisterPermissionProvidersEvent::class, RecruitmentPermissionProviderListener::class);
        $context->registerServiceAlias(RecruitmentPermissionSourceInterface::class, NextcloudRecruitmentPermissionSource::class);
        $context->registerServiceAlias(MailInboxStore::class, RecruitmentRepository::class);
        $context->registerServiceAlias(CandidatePoolStore::class, RecruitmentRepository::class);
        $context->registerServiceAlias(MailAttachmentStorage::class, AppDataMailAttachmentStorage::class);
        $context->registerServiceAlias(PdfTextExtractor::class, LocalPdfTextExtractor::class);
        $context->registerServiceAlias(DocumentReviewStore::class, RecruitmentRepository::class);
        $context->registerServiceAlias(DocumentFieldLinkStore::class, RecruitmentRepository::class);
        $context->registerServiceAlias(StatusMailOutboxStore::class, RecruitmentRepository::class);
        $context->registerServiceAlias(OutboundMailTransport::class, NextcloudOutboundMailTransport::class);
    }

    public function boot(IBootContext $context): void {
    }
}
