<?php

declare(strict_types=1);

namespace OCP\AppFramework {
    class App { public function __construct(string $appName, array $urlParams = []) {} }
}

namespace OCP\AppFramework\Bootstrap {
    interface IBootContext {}
    interface IBootstrap {}
    interface IRegistrationContext {}
}

namespace OCP\EventDispatcher {
    class Event { public function __construct() {} }
    interface IEventListener { public function handle(Event $event): void; }
}

namespace {
    use OCA\FilzmannDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
    use OCA\Recruitment\Privacy\RecruitmentProcessingMetadataProvider;
    use OCA\Recruitment\Privacy\RecruitmentProcessingMetadataProviderListener;
    use OCP\EventDispatcher\Event;

    $provider = new RecruitmentProcessingMetadataProvider();
    $catalog = $provider->catalog();
    $descriptor = $provider->descriptor();
    if ($descriptor->appId() !== 'adrecruitment' || $descriptor->displayName() !== 'AD Recruitment' || $descriptor->contractVersion() !== '1.0') {
        throw new RuntimeException('Der Processing-Metadata-Provider beschreibt AD Recruitment nicht korrekt.');
    }
    if ($catalog->appId() !== 'adrecruitment') {
        throw new RuntimeException('Processing-Metadata-Provider und Katalog verwenden nicht die kanonische App-ID.');
    }
    if ($catalog->processingIds() !== [
        'application_case_management',
        'interview_and_basis_qualification',
        'recruitment_inbox_and_documents',
        'hiring_master_data_release',
        'status_mail_communication',
        'candidate_pool_management',
        'temporary_admin_full_access',
    ]) {
        throw new RuntimeException('Der app-lokale Processing-Katalog ist unvollständig.');
    }
    if (array_key_exists('personal_runtime_data', $catalog->toArray())) {
        throw new RuntimeException('Der Processing-Katalog enthält personenbezogene Laufzeitdaten.');
    }
    $encodedCatalog = json_encode($catalog->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if (!str_contains($encodedCatalog, 'Freigabesteuerung in der AD-Recruitment-Fachoberfläche') || str_contains($encodedCatalog, 'Allow-, Deny- und Manipulationsprüfungen stehen aus')) {
        throw new RuntimeException('Der Processing-Katalog bildet die durchgesetzte Datenschutzrollen-Grenze nicht ab.');
    }

    $registration = new RegisterProcessingMetadataProvidersEvent();
    $listener = new RecruitmentProcessingMetadataProviderListener($provider);
    $listener->handle(new Event());
    if ($registration->providers() !== []) {
        throw new RuntimeException('Ein fremdes Event registriert den Processing-Metadata-Provider.');
    }
    $listener->handle($registration);
    if (($registration->providers()['adrecruitment'] ?? null) !== $provider) {
        throw new RuntimeException('Der Processing-Metadata-Provider wird nicht lazy registriert.');
    }

    $application = (string)file_get_contents(dirname(__DIR__) . '/lib/AppInfo/Application.php');
    if (!str_contains($application, 'registerEventListener(RegisterProcessingMetadataProvidersEvent::class, RecruitmentProcessingMetadataProviderListener::class)')) {
        throw new RuntimeException('Der Bootstrap registriert den Processing-Metadata-Provider nicht am öffentlichen V1-Event.');
    }

    echo "AD Recruitment processing metadata provider test passed\n";
}
