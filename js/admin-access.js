(function (root) {
    'use strict'

    const shared = root.OCA?.FlzRecruitment?.Shared?.ui
    const api = root.RecruitmentApi
    if (!shared?.AdminAccessOverlay || !api) return

    new shared.AdminAccessOverlay({
        overlayId: 'recr-admin-access-overlay',
        triggerId: 'recr-admin-grant-warning',
        closeId: 'recr-admin-access-overlay-close',
        formId: 'recr-full-access-form',
        historyId: 'recr-full-access-history',
        statusId: 'recr-full-access-status',
        api: {
            getState: () => api.adminFullAccess(),
            activate: (targetUid, durationMinutes) => api.activateAdminFullAccess(targetUid, durationMinutes),
            revoke: (targetUid) => api.revokeAdminFullAccess(targetUid),
        },
    })
}(window))
