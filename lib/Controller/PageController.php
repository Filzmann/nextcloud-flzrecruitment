<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Controller;

use OCA\FlzRecruitment\AppInfo\Application;
use OCA\FlzRecruitment\Exception\AccessDeniedException;
use OCA\FlzRecruitment\Service\RecruitmentAccessService;
use OCA\FlzRecruitment\Service\TemporaryAdminAccessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

final class PageController extends Controller {
    public function __construct(
        IRequest $request,
        private RecruitmentAccessService $access,
        private TemporaryAdminAccessService $temporaryAdminAccess,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        $canManageAdminAccess = $this->temporaryAdminAccess->canManage();
        $showMissingAdminGrant = $this->temporaryAdminAccess->currentAdminNeedsGrant();
        $hasRecruitmentAccess = true;
        try {
            $this->access->requireAnyAccess();
        } catch (AccessDeniedException) {
            $hasRecruitmentAccess = false;
            if (!$canManageAdminAccess && !$showMissingAdminGrant) {
                return new TemplateResponse('core', '403', [], 'guest', Http::STATUS_FORBIDDEN);
            }
        }
        return new TemplateResponse(Application::APP_ID, 'index', [
            'canManageAdminAccess' => $canManageAdminAccess,
            'showMissingAdminGrant' => $showMissingAdminGrant,
            'showAdminAccessLink' => $canManageAdminAccess && $showMissingAdminGrant,
            'hasRecruitmentAccess' => $hasRecruitmentAccess,
        ]);
    }
}
