<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('status mail HTTP surface separates administration, scoped drafts and admin-only test routing', static function (): void {
    $root = dirname(__DIR__);
    $routes = file_get_contents($root . '/appinfo/routes.php');
    $controller = file_get_contents($root . '/lib/Controller/ApiController.php');
    $info = file_get_contents($root . '/appinfo/info.xml');
    assertTrue($routes !== false && $controller !== false && $info !== false);
    foreach (['api#mailConfiguration', 'api#createMailTemplate', 'api#reviseMailTemplate', 'api#saveStatusMailRule', 'api#createMailTextBlock', 'api#applicationMailDrafts', 'api#saveMailDraft', 'api#approveMailDraft', 'api#cancelMailDraft', 'api#saveMailSettings'] as $route) {
        assertTrue(str_contains($routes, $route), "Missing status-mail route {$route}");
    }
    assertTrue(substr_count($controller, 'RecruitmentAccessService::MANAGE_MAIL_TEMPLATES') >= 5, 'Mail administration has no dedicated server-side gate');
    assertTrue(substr_count($controller, 'RecruitmentAccessService::COMMUNICATE') >= 4, 'Mail drafts have no application-scoped communication gate');
    assertTrue(str_contains($controller, 'if (!$this->access->isNextcloudAdmin())'), 'Central test routing is not restricted to Nextcloud admins');
    $transport = file_get_contents($root . '/lib/Service/NextcloudOutboundMailTransport.php');
    assertTrue($transport !== false && str_contains($transport, 'setHtmlBody($htmlBody)') && str_contains($transport, 'setPlainBody($plainBody)'), 'Nextcloud transport does not send equivalent HTML and plain bodies');
    $repository = file_get_contents($root . '/lib/Repository/RecruitmentRepository.php');
    assertTrue($repository !== false && str_contains($repository, 'saveStatusMailRule(string $fromStatus, string $toStatus, int $templateId, bool $enabled, string $timing, int $expectedVersion'), 'Concurrent mail-rule edits have no optimistic version contract');
    assertTrue(str_contains($controller, 'public function approveMailDraft') && !preg_match('/#\[NoCSRFRequired\]\s+public function approveMailDraft/', $controller), 'Mail approval bypasses CSRF protection');
    assertTrue(str_contains($info, 'OCA\FlzRecruitment\BackgroundJob\DeliverStatusMailJob'), 'The due-mail background job is not registered');
});
