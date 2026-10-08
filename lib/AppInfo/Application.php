<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\AppInfo;

use OCA\FlzRecruitment\Contract\MailAttachmentStorage;
use OCA\FlzRecruitment\Contract\PdfTextExtractor;
use OCA\FlzRecruitment\Contract\CandidatePoolStore;
use OCA\FlzRecruitment\Contract\MailInboxStore;
use OCA\FlzRecruitment\Contract\DocumentFieldLinkStore;
use OCA\FlzRecruitment\Contract\OutboundMailTransport;
use OCA\FlzRecruitment\Contract\StatusMailOutboxStore;
use OCA\FlzRecruitment\Contract\DocumentReviewStore;
use OCA\FlzRecruitment\Listener\StandaloneNavigationListener;
use OCA\FlzRecruitment\Permission\NextcloudRecruitmentPermissionSource;
use OCA\FlzRecruitment\Permission\RecruitmentPermissionProviderListener;
use OCA\FlzRecruitment\Repository\TemporaryAdminAccessRepository;
use OCA\FlzRecruitment\Repository\TemporaryAdminAccessRepositoryInterface;
use OCA\FlzRecruitment\Service\TemporaryAdminAccessChecker;
use OCA\FlzRecruitment\Service\TemporaryAdminAccessService;
use OCA\FlzRecruitment\Permission\RecruitmentPermissionSourceInterface;
use OCA\FlzRecruitment\Privacy\RecruitmentPrivacyProviderListener;
use OCA\FlzRecruitment\Privacy\RecruitmentProcessingMetadataProviderListener;
use OCA\FlzDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
use OCA\FlzPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
use OCA\FlzRecruitment\Repository\RecruitmentRepository;
use OCA\FlzRecruitment\Service\AppDataMailAttachmentStorage;
use OCA\FlzRecruitment\Service\NextcloudOutboundMailTransport;
use OCA\FlzRecruitment\Service\LocalPdfTextExtractor;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

final class Application extends App implements IBootstrap {
    public const APP_ID = 'flzrecruitment';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(LoadAdditionalEntriesEvent::class, StandaloneNavigationListener::class);
        $context->registerEventListener(RegisterPersonalDataProvidersEvent::class, RecruitmentPrivacyProviderListener::class);
        $context->registerEventListener(RegisterProcessingMetadataProvidersEvent::class, RecruitmentProcessingMetadataProviderListener::class);
        $context->registerEventListener(RegisterPermissionProvidersEvent::class, RecruitmentPermissionProviderListener::class);
        $context->registerServiceAlias(TemporaryAdminAccessChecker::class, TemporaryAdminAccessService::class);
        $context->registerServiceAlias(TemporaryAdminAccessRepositoryInterface::class, TemporaryAdminAccessRepository::class);
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
