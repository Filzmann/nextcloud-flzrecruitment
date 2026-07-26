<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/Exception/AccessDeniedException.php';
require_once dirname(__DIR__) . '/lib/Service/RecruitmentAccessService.php';

use OCA\Recruitment\Exception\AccessDeniedException;
use OCA\Recruitment\Service\RecruitmentAccessService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;
use function RecruitmentTests\assertTrue;

final class TestUser implements IUser {
    public function __construct(private string $uid) {}
    public function getUID(): string { return $this->uid; }
}

final class TestSession implements IUserSession {
    public function __construct(private ?IUser $user) {}
    public function getUser(): ?IUser { return $this->user; }
}

final class TestGroup implements IGroup {
    /** @param list<string> $members */
    public function __construct(private array $members) {}
    public function inGroup(IUser $user): bool { return in_array($user->getUID(), $this->members, true); }
}

final class TestGroups implements IGroupManager {
    /** @param array<string,list<string>> $members */
    public function __construct(private array $members, private array $admins = []) {}
    public function isAdmin(string $uid): bool { return in_array($uid, $this->admins, true); }
    public function get(string $gid): ?IGroup {
        return isset($this->members[$gid]) ? new TestGroup($this->members[$gid]) : null;
    }
}

TestRunner::test('anonymous and unassigned users are denied server-side', static function (): void {
    $anonymous = new RecruitmentAccessService(new TestSession(null), new TestGroups([]));
    assertThrows(static fn () => $anonymous->require(RecruitmentAccessService::VIEW), AccessDeniedException::class);

    $ordinary = new RecruitmentAccessService(new TestSession(new TestUser('neutral-user')), new TestGroups([]));
    assertSame(false, $ordinary->can(RecruitmentAccessService::VIEW));
    assertThrows(static fn () => $ordinary->require(RecruitmentAccessService::EDIT_APPLICATIONS), AccessDeniedException::class);
});

TestRunner::test('group capabilities are least-privilege and Nextcloud admins retain access', static function (): void {
    $reader = new RecruitmentAccessService(
        new TestSession(new TestUser('reader-user')),
        new TestGroups(['recruitment-readers' => ['reader-user']]),
    );
    assertTrue($reader->can(RecruitmentAccessService::VIEW));
    assertSame(false, $reader->can(RecruitmentAccessService::EDIT_APPLICATIONS));

    $admin = new RecruitmentAccessService(
        new TestSession(new TestUser('admin-user')),
        new TestGroups([], ['admin-user']),
    );
    assertTrue($admin->can(RecruitmentAccessService::MANAGE_CATALOG));
    assertTrue($admin->can(RecruitmentAccessService::INTERVIEW));
});
