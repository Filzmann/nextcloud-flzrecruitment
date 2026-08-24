(function (root) {
    'use strict'

    const DEFAULT_APPLICATION_VIEW = 'board'

    function filterApplications(data, filters) {
        const query = String(filters.query || '').trim().toLocaleLowerCase('de')
        const filtered = data.applications.filter((application) => {
            if (filters.jobId && String(application.jobId) !== String(filters.jobId)) return false
            if (filters.status && application.status !== filters.status) return false
            if (filters.areaKey && application.areaKey !== filters.areaKey) return false
            if (filters.assigneeUid === '__unassigned__' && application.assigneeUid) return false
            if (filters.assigneeUid && filters.assigneeUid !== '__unassigned__' && application.assigneeUid !== filters.assigneeUid) return false
            if (filters.receivedFrom && application.receivedOn < filters.receivedFrom) return false
            if (filters.receivedTo && application.receivedOn > filters.receivedTo) return false
            if (!query) return true
            const person = data.people.find((item) => item.id === application.personId)
            const job = data.jobs.find((item) => item.id === application.jobId)
            return [
                person?.givenName,
                person?.familyName,
                person?.email,
                job?.internalTitle,
                job?.publicTitle,
                application.assigneeUid,
                application.status,
                application.basisQualification?.label,
            ].filter(Boolean).join(' ').toLocaleLowerCase('de').includes(query)
        })
        return filters.sort ? sortApplications(data, filtered, filters.sort) : filtered
    }

    function sortApplications(data, applications, sort) {
        const sorted = [...applications]
        const personName = (application) => {
            const person = data.people.find((item) => item.id === application.personId)
            return `${person?.familyName || ''} ${person?.givenName || ''}`.trim()
        }
        const jobName = (application) => data.jobs.find((item) => item.id === application.jobId)?.internalTitle || ''
        sorted.sort((left, right) => {
            let result = 0
            if (sort === 'received_asc') result = String(left.receivedOn).localeCompare(String(right.receivedOn))
            else if (sort === 'received_desc') result = String(right.receivedOn).localeCompare(String(left.receivedOn))
            else if (sort === 'person_asc') result = personName(left).localeCompare(personName(right), 'de')
            else if (sort === 'job_asc') result = jobName(left).localeCompare(jobName(right), 'de')
            else if (sort === 'status_asc') {
                const order = data.applicationStatuses || []
                const leftPosition = order.indexOf(left.status)
                const rightPosition = order.indexOf(right.status)
                result = (leftPosition < 0 ? Number.MAX_SAFE_INTEGER : leftPosition)
                    - (rightPosition < 0 ? Number.MAX_SAFE_INTEGER : rightPosition)
            }
            return result || Number(left.id) - Number(right.id)
        })
        return sorted
    }

    function groupApplicationsByStatus(applications, orderedStatuses = []) {
        const groups = {}
        for (const status of orderedStatuses) if (status !== 'withdrawn') groups[status] = []
        for (const application of applications) {
            if (application.status === 'withdrawn') continue
            groups[application.status] ??= []
            groups[application.status].push(application)
        }
        return groups
    }

    function canMoveApplication(application, targetStatus) {
        return Boolean(application)
            && Array.isArray(application.allowedStatuses)
            && application.allowedStatuses.includes(targetStatus)
    }

    function requiresStatusOverride(application, targetStatus) {
        return Boolean(application) && !canMoveApplication(application, targetStatus)
    }

    function statusTargets(application, orderedStatuses = [], canOverride = false) {
        const regular = (application?.allowedStatuses || []).filter((status) => status !== 'withdrawn')
        if (!canOverride) return regular
        const exceptional = orderedStatuses.filter((status) => {
            if (status === application?.status || status === 'withdrawn' || regular.includes(status)) return false
            if (status === 'hired' && application?.status !== 'approved_for_hire') return false
            if (status === 'approved_for_hire' && application?.basisQualification
                && application.basisQualification.result !== 'suitable') return false
            return true
        })
        return [...regular, ...exceptional]
    }

    root.RecruitmentApplicationWorkbench = {
        DEFAULT_APPLICATION_VIEW,
        canMoveApplication,
        filterApplications,
        groupApplicationsByStatus,
        requiresStatusOverride,
        sortApplications,
        statusTargets,
    }
})(typeof window === 'undefined' ? globalThis : window)
