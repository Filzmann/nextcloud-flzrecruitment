<?php

declare(strict_types=1);

namespace OCP\EventDispatcher { class Event {} interface IEventListener { public function handle(Event $event): void; } }
namespace OCP\Navigation\Events { class LoadAdditionalEntriesEvent extends \OCP\EventDispatcher\Event {} }
namespace OCP {
    interface IURLGenerator { public function linkToRoute(string $routeName, array $arguments = []): string; public function imagePath(string $appName, string $file): string; }
    interface INavigationManager { public const TYPE_APPS = 'link'; public function add(callable $entry): void; }
}
namespace OCP\App { interface IAppManager { public function isEnabledForUser($appId, $user = null); } }

namespace {
    use OCA\LocalBase\Catalog\FlzProductCatalog;
    use OCA\LocalBase\Service\StandaloneAppNavigationService;
    use OCA\FlzRecruitment\Listener\StandaloneNavigationListener;
    use OCP\App\IAppManager;
    use OCP\INavigationManager;
    use OCP\IURLGenerator;
    use OCP\IUser;
    use OCP\IUserSession;
    use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

    $user = new class implements IUser { public function getUID(): string { return 'navigation-user'; } };
    $session = new class($user) implements IUserSession { public function __construct(private ?IUser $user) {} public function getUser(): ?IUser { return $this->user; } };
    $apps = new class implements IAppManager { public bool $suiteEnabled = false; public function isEnabledForUser($appId, $user = null): bool { return $appId === 'orgsuite' && $this->suiteEnabled; } };
    $navigation = new class implements INavigationManager { public array $entries = []; public function add(callable $entry): void { $this->entries[] = $entry; } };
    $url = new class implements IURLGenerator {
        public function linkToRoute(string $routeName, array $arguments = []): string { return '/route/' . $routeName; }
        public function imagePath(string $appName, string $file): string { return '/image/' . $appName . '/' . $file; }
    };

    $service = new StandaloneAppNavigationService($session, $apps, $navigation, $url, new FlzProductCatalog());
    $listener = new StandaloneNavigationListener($service);
    $listener->handle(new LoadAdditionalEntriesEvent());
    $entry = ($navigation->entries[0] ?? static fn(): array => [])();
    if (($entry['id'] ?? null) !== 'flzrecruitment' || ($entry['href'] ?? null) !== '/route/flzrecruitment.page.index') {
        throw new RuntimeException('Filzmann Recruitment erhält keinen katalogisierten Standalone-Einstieg.');
    }

    $apps->suiteEnabled = true;
    $listener->handle(new LoadAdditionalEntriesEvent());
    if (count($navigation->entries) !== 1) {
        throw new RuntimeException('Aktive OrgSuite unterdrückt den Recruitment-Standalone-Einstieg nicht.');
    }

    echo "Filzmann Recruitment standalone navigation test passed\n";
}
