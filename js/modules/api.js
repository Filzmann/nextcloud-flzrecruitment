(function (root) {
    'use strict'

    function currentRequestToken() {
        const token = root._nc_auth_requestToken
            || root.document?.head?.dataset?.requesttoken
            || root.OC?.requestToken
        return typeof token === 'string' && token.trim() !== '' ? token : null
    }

    async function request(path, method = 'GET', body = null) {
        const headers = { Accept: 'application/json' }
        if (body !== null) {
            headers['Content-Type'] = 'application/json'
        }
        if (method !== 'GET') {
            const requestToken = currentRequestToken()
            if (requestToken === null) {
                throw new Error('Der aktuelle CSRF-Token ist nicht verfügbar. Bitte laden Sie die Seite neu.')
            }
            headers.requesttoken = requestToken
            headers['X-Requested-With'] = 'XMLHttpRequest'
        }

        const response = await fetch(root.OC.generateUrl('/apps/recruitment' + path), {
            method,
            headers,
            credentials: 'same-origin',
            body: body === null ? null : JSON.stringify(body),
        })
        const payload = await response.json().catch(() => ({}))
        if (!response.ok) {
            const error = new Error(payload.message || 'Die Anfrage ist fehlgeschlagen.')
            error.status = response.status
            throw error
        }
        return payload
    }

    root.RecruitmentApi = {
        bootstrap: () => request('/api/bootstrap'),
        application: (id) => request(`/api/applications/${id}`),
        template: (id) => request(`/api/templates/${id}`),
        createJob: (data) => request('/api/jobs', 'POST', data),
        createPerson: (data) => request('/api/people', 'POST', data),
        createApplication: (data) => request('/api/applications', 'POST', data),
        createTemplate: (data) => request('/api/templates', 'POST', data),
        createQuestion: (templateId, data) => request(`/api/templates/${templateId}/questions`, 'POST', data),
        updateQuestion: (id, data) => request(`/api/questions/${id}`, 'PUT', data),
        createBubble: (questionId, data) => request(`/api/questions/${questionId}/bubbles`, 'POST', data),
        createInterview: (applicationId, templateId) => request(`/api/applications/${applicationId}/interviews`, 'POST', { templateId }),
        saveDraft: (id, answers, version) => request(`/api/interviews/${id}/draft`, 'PUT', { answers, version }),
        completeInterview: (id, answers, version) => request(`/api/interviews/${id}/complete`, 'POST', { answers, version }),
        transitionStatus: (id, status, version) => request(`/api/applications/${id}/status`, 'POST', { status, version }),
    }
}(window))
