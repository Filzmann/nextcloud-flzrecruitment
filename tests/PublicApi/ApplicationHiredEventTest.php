<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    if (!class_exists(Event::class)) {
        class Event {
            public function __construct() {
            }
        }
    }
    if (!interface_exists(IEventDispatcher::class)) {
        interface IEventDispatcher {
            public function dispatchTyped(Event $event): Event;
        }
    }
}

namespace {
    use OCA\FlzRecruitment\Contract\ApplicationStatusStore;
    use OCA\FlzRecruitment\PublicApi\V1\ApplicationHiredEvent;
    use OCA\FlzRecruitment\Service\ApplicationStatusService;
    use OCP\EventDispatcher\Event;
    use OCP\EventDispatcher\IEventDispatcher;
    use RecruitmentTests\TestRunner;

    use function RecruitmentTests\assertSame;
    use function RecruitmentTests\assertThrows;

    final class CapturingDispatcher implements IEventDispatcher {
        /** @var list<Event> */
        public array $events = [];

        public function dispatchTyped(Event $event): Event {
            $this->events[] = $event;
            return $event;
        }
    }

    final class HiredEventStoreFake implements ApplicationStatusStore {
        /** @var array<int,array<string,mixed>> */
        public array $applications;

        public function findApplication(int $id): array {
            return $this->applications[$id];
        }

        public function statusMailPreparation(int $id, string $fromStatus, string $toStatus): ?array {
            return null;
        }

        public function transitionStatus(
            int $id,
            string $fromStatus,
            string $toStatus,
            int $expectedVersion,
            string $actorUid,
            ?string $areaKey,
            bool $enableFirstGuideAccess,
            ?array $mailDraft = null,
            bool $override = false,
        ): array {
            $application = $this->applications[$id];
            $application['status'] = $toStatus;
            $application['version'] = $expectedVersion + 1;
            $this->applications[$id] = $application;
            return $application;
        }
    }

    TestRunner::test('ApplicationHiredEvent carries only contract identifiers and validates them', static function (): void {
        $event = new ApplicationHiredEvent(7, 'approved_for_hire', 'area-south', 'hr-user', new DateTimeImmutable('2026-11-28T10:00:00Z'));
        assertSame(7, $event->applicationId());
        assertSame('approved_for_hire', $event->fromStatus());
        assertSame('area-south', $event->areaKey());
        assertSame('hr-user', $event->actorUid());

        assertThrows(static fn () => new ApplicationHiredEvent(0, 'approved_for_hire', 'a', 'hr-user', new DateTimeImmutable()), InvalidArgumentException::class);
        assertThrows(static fn () => new ApplicationHiredEvent(7, 'hired', 'a', 'hr-user', new DateTimeImmutable()), InvalidArgumentException::class);
        assertThrows(static fn () => new ApplicationHiredEvent(7, 'approved_for_hire', 'bad key!', 'hr-user', new DateTimeImmutable()), InvalidArgumentException::class);
        assertThrows(static fn () => new ApplicationHiredEvent(7, 'approved_for_hire', 'a', 'bad uid!', new DateTimeImmutable()), InvalidArgumentException::class);
    });

    TestRunner::test('transition to hired dispatches exactly one typed event', static function (): void {
        $store = new HiredEventStoreFake();
        $store->applications[7] = ['id' => 7, 'status' => 'approved_for_hire', 'version' => 3, 'areaKey' => 'area-south'];
        $dispatcher = new CapturingDispatcher();
        $service = new ApplicationStatusService(null, $dispatcher);

        $changed = $service->transition($store, 7, 'hired', 3, 'hr-user');

        assertSame('hired', $changed['status']);
        assertSame(1, count($dispatcher->events));
        $event = $dispatcher->events[0];
        assertSame(ApplicationHiredEvent::class, $event::class);
        assertSame(7, $event->applicationId());
        assertSame('approved_for_hire', $event->fromStatus());
        assertSame('area-south', $event->areaKey());
        assertSame('hr-user', $event->actorUid());
    });

    TestRunner::test('non-hired transitions and missing dispatcher stay silent', static function (): void {
        $store = new HiredEventStoreFake();
        $store->applications[8] = ['id' => 8, 'status' => 'screening', 'version' => 1, 'areaKey' => ''];
        $dispatcher = new CapturingDispatcher();
        $service = new ApplicationStatusService(null, $dispatcher);

        $service->transition($store, 8, 'rejected', 1, 'hr-user');
        assertSame(0, count($dispatcher->events));

        $store->applications[9] = ['id' => 9, 'status' => 'approved_for_hire', 'version' => 1, 'areaKey' => ''];
        $silent = new ApplicationStatusService();
        $changed = $silent->transition($store, 9, 'hired', 1, 'hr-user');
        assertSame('hired', $changed['status']);
    });

}
