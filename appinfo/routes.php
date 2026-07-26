<?php

declare(strict_types=1);

return ['routes' => [
    ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
    ['name' => 'api#bootstrap', 'url' => '/api/bootstrap', 'verb' => 'GET'],
    ['name' => 'api#applicationDetail', 'url' => '/api/applications/{id}', 'verb' => 'GET'],
    ['name' => 'api#templateDetail', 'url' => '/api/templates/{id}', 'verb' => 'GET'],
    ['name' => 'api#createJob', 'url' => '/api/jobs', 'verb' => 'POST'],
    ['name' => 'api#createPerson', 'url' => '/api/people', 'verb' => 'POST'],
    ['name' => 'api#createApplication', 'url' => '/api/applications', 'verb' => 'POST'],
    ['name' => 'api#createTemplate', 'url' => '/api/templates', 'verb' => 'POST'],
    ['name' => 'api#createQuestion', 'url' => '/api/templates/{templateId}/questions', 'verb' => 'POST'],
    ['name' => 'api#updateQuestion', 'url' => '/api/questions/{id}', 'verb' => 'PUT'],
    ['name' => 'api#createBubble', 'url' => '/api/questions/{questionId}/bubbles', 'verb' => 'POST'],
    ['name' => 'api#createInterview', 'url' => '/api/applications/{applicationId}/interviews', 'verb' => 'POST'],
    ['name' => 'api#saveInterviewDraft', 'url' => '/api/interviews/{id}/draft', 'verb' => 'PUT'],
    ['name' => 'api#completeInterview', 'url' => '/api/interviews/{id}/complete', 'verb' => 'POST'],
    ['name' => 'api#transitionStatus', 'url' => '/api/applications/{id}/status', 'verb' => 'POST'],
]];
