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

        const response = await fetch(root.OC.generateUrl('/apps/adrecruitment' + path), {
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

    function requestKey(prefix) {
        const value = root.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`
        return `${prefix}:${value}`.slice(0, 64)
    }

    root.RecruitmentApi = {
        bootstrap: () => request('/api/bootstrap'),
        application: (id) => request(`/api/applications/${id}`),
        template: (id) => request(`/api/templates/${id}`),
        createJob: (data) => request('/api/jobs', 'POST', data),
        jobResponsibilityUsers: (professionCategory, groupIds, query) => {
            const params = new URLSearchParams({ professionCategory, query })
            for (const groupId of groupIds) params.append('groupIds[]', groupId)
            return request(`/api/job-responsibility-users?${params.toString()}`)
        },
        createBasisQualificationRun: (data) => request('/api/basis-qualifications', 'POST', data),
        basisQualificationAssignments: (applicationId) => request(`/api/applications/${applicationId}/basis-qualifications`),
        assignBasisQualification: (applicationId, runId, version) => request(`/api/applications/${applicationId}/basis-qualification`, 'POST', { runId, version }),
        recordBasisQualificationResult: (assignmentId, result, note, version) => request(`/api/basis-qualification-assignments/${assignmentId}/result`, 'PUT', { result, note, version }),
        createPerson: (data) => request('/api/people', 'POST', data),
        createApplication: (data) => request('/api/applications', 'POST', data),
        createTemplate: (data) => request('/api/templates', 'POST', data),
        createQuestion: (templateId, data) => request(`/api/templates/${templateId}/questions`, 'POST', data),
        updateQuestion: (id, data) => request(`/api/questions/${id}`, 'PUT', data),
        createBubble: (questionId, data) => request(`/api/questions/${questionId}/bubbles`, 'POST', data),
        createInterview: (applicationId, templateId) => request(`/api/applications/${applicationId}/interviews`, 'POST', { templateId }),
        saveDraft: (id, answers, version) => request(`/api/interviews/${id}/draft`, 'PUT', { answers, version }),
        completeInterview: (id, answers, version) => request(`/api/interviews/${id}/complete`, 'POST', { answers, version }),
        transitionStatus: (id, status, version, areaKey = '', override = false) => request(`/api/applications/${id}/status`, 'POST', { status, version, areaKey, override, clientKey: requestKey('status') }),
        mailConfiguration: () => request('/api/mail/configuration'),
        createMailTemplate: (data) => request('/api/mail/templates', 'POST', data),
        reviseMailTemplate: (id, data) => request(`/api/mail/templates/${id}`, 'PUT', data),
        saveStatusMailRule: (data) => request('/api/mail/rules', 'PUT', data),
        createMailTextBlock: (data) => request('/api/mail/text-blocks', 'POST', data),
        applicationMailDrafts: (id) => request(`/api/applications/${id}/mail-drafts`),
        saveMailDraft: (id, data) => request(`/api/mail-drafts/${id}`, 'PUT', data),
        approveMailDraft: (id, data) => request(`/api/mail-drafts/${id}/approve`, 'POST', { ...data, jobKey: requestKey('mail') }),
        cancelMailDraft: (id, version) => request(`/api/mail-drafts/${id}/cancel`, 'POST', { version }),
        saveMailSettings: (data) => request('/api/mail/settings', 'PUT', data),
        hiringData: (id) => request(`/api/applications/${id}/hiring-data`),
        saveHiringData: (id, data, version) => request(`/api/applications/${id}/hiring-data`, 'PUT', { data, version }),
        savePayrollData: (id, data, version) => request(`/api/applications/${id}/payroll-data`, 'PUT', { data, version }),
        setFirstGuideAccess: (id, enabled, version) => request(`/api/applications/${id}/first-guide-access`, 'PUT', { enabled, version }),
        saveRepresentatives: (representatives, revision) => request('/api/permissions/representatives', 'PUT', { representatives, revision }),
        saveFirstGuideGroup: (groupId, revision) => request('/api/permissions/first-guide-group', 'PUT', { groupId, revision }),
        requestCandidatePool: (applicationId) => request(`/api/applications/${applicationId}/candidate-pool/request`, 'POST', {}),
        grantCandidatePoolConsent: (id, data) => request(`/api/candidate-pool/${id}/consent`, 'POST', data),
        withdrawCandidatePoolConsent: (id) => request(`/api/candidate-pool/${id}/withdraw`, 'POST', {}),
        saveCandidatePoolSettings: (data) => request('/api/candidate-pool/settings', 'PUT', data),
        saveResumeExtractionSettings: (data) => request('/api/resume-extraction/settings', 'PUT', data),
        inbox: () => request('/api/inbox'),
        inboxMessage: (id) => request(`/api/inbox/${id}`),
        applicationMessages: (applicationId) => request(`/api/applications/${applicationId}/messages`),
        assignInboxMessage: (id, applicationId, version, acceptedSuggestions = {}) => request(`/api/inbox/${id}/assign`, 'POST', { applicationId, version, acceptedSuggestions }),
        createApplicationFromInbox: (id, data) => request(`/api/inbox/${id}/application`, 'POST', data),
        ignoreInboxMessage: (id, version) => request(`/api/inbox/${id}/ignore`, 'POST', { version }),
        documentUrl: (attachmentId) => root.OC.generateUrl(`/apps/adrecruitment/api/attachments/${attachmentId}/document`),
        attachmentComments: (attachmentId) => request(`/api/attachments/${attachmentId}/comments`),
        createDocumentComment: (attachmentId, data) => request(`/api/attachments/${attachmentId}/comments`, 'POST', data),
        attachmentFieldContext: (attachmentId, targetField) => request(`/api/attachments/${attachmentId}/field-context/${encodeURIComponent(targetField)}`),
        createAttachmentFieldLink: (attachmentId, data) => request(`/api/attachments/${attachmentId}/field-links`, 'POST', data),
    }
}(window))
