import assert from 'node:assert/strict'

globalThis.window = globalThis
await import('../js/modules/application-workbench.js')

const data = {
    applicationStatuses: ['received', 'screening', 'basis_qualification'],
    people: [
        { id: 1, givenName: 'Ari', familyName: 'Beispiel' },
        { id: 2, givenName: 'Mika', familyName: 'Muster' },
    ],
    jobs: [
        { id: 10, internalTitle: 'Assistenz' },
        { id: 11, internalTitle: 'Pflegefachkraft' },
    ],
    applications: [
        { id: 100, personId: 1, jobId: 10, status: 'received', assigneeUid: 'hr-a', areaKey: '', receivedOn: '2026-07-29' },
        { id: 101, personId: 2, jobId: 10, status: 'basis_qualification', assigneeUid: 'hr-b', areaKey: 'west', receivedOn: '2026-07-22' },
        { id: 102, personId: 2, jobId: 11, status: 'screening', assigneeUid: 'hr-a', areaKey: 'south', receivedOn: '2026-07-25' },
        { id: 103, personId: 1, jobId: 11, status: 'screening', assigneeUid: '', areaKey: 'west', receivedOn: '2026-07-24' },
    ],
}

const { DEFAULT_APPLICATION_VIEW, canMoveApplication, filterApplications, groupApplicationsByStatus, requiresStatusOverride, sortApplications, statusTargets } = globalThis.RecruitmentApplicationWorkbench

assert.equal(DEFAULT_APPLICATION_VIEW, 'board')

assert.deepEqual(filterApplications(data, { query: 'mika', jobId: '', status: '' }).map(({ id }) => id), [101, 102])
assert.deepEqual(filterApplications(data, { query: '', jobId: '10', status: '' }).map(({ id }) => id), [100, 101])
assert.deepEqual(filterApplications(data, { query: 'hr-a', jobId: '', status: 'screening' }).map(({ id }) => id), [102])
assert.deepEqual(filterApplications(data, { query: 'nicht vorhanden', jobId: '', status: '' }), [])
assert.deepEqual(filterApplications(data, { areaKey: 'west' }).map(({ id }) => id), [101, 103])
assert.deepEqual(filterApplications(data, { assigneeUid: 'hr-a' }).map(({ id }) => id), [100, 102])
assert.deepEqual(filterApplications(data, { assigneeUid: '__unassigned__' }).map(({ id }) => id), [103])
assert.deepEqual(filterApplications(data, { receivedFrom: '2026-07-24', receivedTo: '2026-07-25' }).map(({ id }) => id), [102, 103])

const originalOrder = data.applications.map(({ id }) => id)
assert.deepEqual(sortApplications(data, data.applications, 'received_asc').map(({ id }) => id), [101, 103, 102, 100])
assert.deepEqual(sortApplications(data, data.applications, 'person_asc').map(({ id }) => id), [100, 103, 101, 102])
assert.deepEqual(sortApplications(data, data.applications, 'status_asc').map(({ id }) => id), [100, 102, 103, 101])
assert.deepEqual(data.applications.map(({ id }) => id), originalOrder)

data.applications.push({ id: 104, personId: 1, jobId: 10, status: 'withdrawn', receivedOn: '2026-07-20' })
const groups = groupApplicationsByStatus(data.applications)
assert.deepEqual(Object.keys(groups), ['received', 'basis_qualification', 'screening'])
assert.deepEqual(groups.basis_qualification.map(({ id }) => id), [101])

const completeGroups = groupApplicationsByStatus(data.applications, ['received', 'screening', 'phone_planned'])
assert.deepEqual(Object.keys(completeGroups), ['received', 'screening', 'phone_planned', 'basis_qualification'])
assert.deepEqual(completeGroups.phone_planned, [])
assert.equal(Object.hasOwn(completeGroups, 'withdrawn'), false)

const movable = { id: 100, allowedStatuses: ['screening', 'withdrawn'] }
assert.equal(canMoveApplication(movable, 'screening'), true)
assert.equal(canMoveApplication(movable, 'hired'), false)
assert.equal(canMoveApplication(null, 'screening'), false)
assert.deepEqual(statusTargets(movable, ['received', 'screening', 'phone_planned', 'withdrawn'], false), ['screening'])
assert.deepEqual(statusTargets(movable, ['received', 'screening', 'phone_planned', 'hired', 'withdrawn'], true), ['screening', 'received', 'phone_planned'])
assert.deepEqual(statusTargets({ status: 'basis_qualification', allowedStatuses: ['rejected'], basisQualification: { result: 'pending' } }, ['approved_for_hire', 'rejected'], true), ['rejected'])
assert.equal(requiresStatusOverride(movable, 'screening'), false)
assert.equal(requiresStatusOverride(movable, 'phone_planned'), true)
