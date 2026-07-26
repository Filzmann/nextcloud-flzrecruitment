<?php

declare(strict_types=1);

namespace OCA\Recruitment\Controller;

use OCA\Recruitment\AppInfo\Application;
use OCA\Recruitment\Exception\AccessDeniedException;
use OCA\Recruitment\Service\RecruitmentAccessService;
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
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        try {
            $this->access->require(RecruitmentAccessService::VIEW);
            return new TemplateResponse(Application::APP_ID, 'index');
        } catch (AccessDeniedException) {
            return new TemplateResponse('core', '403', [], 'guest', Http::STATUS_FORBIDDEN);
        }
    }
}
