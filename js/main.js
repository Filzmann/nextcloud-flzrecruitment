(function (root) {
    'use strict'

    const api = root.RecruitmentApi
    const { appendBubbleText } = root.RecruitmentBubbleText
    const { csvList, desiredHoursLabel, sourceLabel, statusLabel } = root.RecruitmentUiData
    const { createController: createDialogController } = root.RecruitmentDialogOverlay
    const pdfLightbox = root.RecruitmentPdfLightbox
    const richTextEditor = root.RecruitmentRichTextEditor
    const settingsNavigation = root.RecruitmentSettingsNavigation
    const contactLinks = root.FlzRecruitmentContactLinks || { emailHref: () => '', emailLinkAttributes: () => null, phoneHref: () => '' }
    const {
        DEFAULT_APPLICATION_VIEW,
        canMoveApplication,
        filterApplications,
        groupApplicationsByStatus,
        requiresStatusOverride,
        statusTargets,
    } = root.RecruitmentApplicationWorkbench
    const content = document.getElementById('flzrecruitment-content')
    const tabs = document.getElementById('flzrecruitment-tabs')
    const status = document.getElementById('flzrecruitment-status')
    const errorBox = document.getElementById('flzrecruitment-error')
    let horizontalScrollCleanup = () => {}
    const state = {
        data: null,
        capabilities: {},
        areas: [],
        hiringData: [],
        basisQualificationRuns: [],
        jobResponsibilityGroups: [],
        permissionSettings: null,
        mailConfiguration: null,
        candidatePool: null,
        delegatableCapabilities: [],
        inboxMessages: [],
        activeTab: 'applications',
        activeSettingsSection: 'templates',
        applicationView: DEFAULT_APPLICATION_VIEW,
        applicationFilters: {
            query: '', jobId: '', status: '', areaKey: '', assigneeUid: '',
            receivedFrom: '', receivedTo: '', sort: 'received_desc',
        },
        applicationMoveAreaKey: '',
    }
    const hiringFields = [
        ['salutation', 'Anrede'], ['title', 'Titel'], ['birthName', 'Geburtsname'],
        ['birthDate', 'Geburtsdatum', 'date'], ['birthPlace', 'Geburtsort'],
        ['street', 'Straße'], ['houseNumber', 'Hausnummer'], ['postalCode', 'Postleitzahl'],
        ['city', 'Ort'], ['country', 'Land'], ['nationality', 'Staatsangehörigkeit'],
        ['privateEmail', 'Private E-Mail', 'email'], ['privatePhone', 'Private Telefonnummer', 'tel'],
        ['plannedStartDate', 'Geplanter Eintritt', 'date'], ['contractType', 'Beschäftigungsform'],
        ['positionTitle', 'Tätigkeitsbezeichnung'], ['payStep', 'Tarifstufe'],
        ['contractEndDate', 'Vertragsende', 'date'],
    ]
    const payrollOnlyFields = [
        ['iban', 'IBAN'], ['bic', 'BIC'], ['accountHolder', 'Kontoinhaber*in'],
        ['healthInsurance', 'Krankenkasse'], ['healthInsuranceType', 'Versicherungsart'],
        ['socialSecurityNumber', 'Sozialversicherungsnummer'], ['taxId', 'Steuer-ID'], ['taxClass', 'Steuerklasse'],
    ]
    const jobDerivedHiringFields = [
        ['contractTerm', 'Vertragsdauer'], ['workingTimeModel', 'Arbeitszeitmodell'],
        ['workLocation', 'Arbeitsort'], ['payGrade', 'Entgeltgruppe'],
        ['weeklyHours', 'Ausgeschriebene Wochenstunden'], ['vacationDays', 'Urlaubstage gemäß HTV'],
    ]
    const hiringChoices = {
        salutation: [
            { value: 'female', label: 'Frau' }, { value: 'male', label: 'Herr' },
            { value: 'diverse', label: 'Divers' }, { value: 'neutral', label: 'Keine Anrede' },
        ],
        title: [
            { value: 'dr', label: 'Dr.' }, { value: 'prof', label: 'Prof.' },
            { value: 'prof_dr', label: 'Prof. Dr.' },
        ],
        healthInsuranceType: [
            { value: 'statutory', label: 'Gesetzlich' },
            { value: 'private', label: 'Privat' },
            { value: 'other', label: 'Sonstige' },
        ],
        taxClass: ['1', '2', '3', '4', '5', '6'].map((value) => ({ value, label: `Steuerklasse ${value}` })),
        contractType: [
            { value: 'marginal', label: 'Geringfügig' },
            { value: 'social_insurance', label: 'Sozialversicherungspflichtig' },
            { value: 'student', label: 'Studentisch' },
            { value: 'other', label: 'Sonstige' },
        ],
        contractTerm: [
            { value: 'permanent', label: 'Unbefristet' },
            { value: 'fixed_term_reason', label: 'Befristet mit Sachgrund' },
        ],
        workingTimeModel: [
            { value: 'fixed', label: 'Feste Arbeitszeit' },
            { value: 'kapovaz', label: 'KAPOVAZ' },
        ],
        payGrade: ['3', '5', '8', '9a', '9b', '10', '11', '12', '13'].map((value) => ({ value, label: `EG ${value}` })),
        payStep: ['1', '2', '3', '4', '5', '6'].map((value) => ({ value, label: `Stufe ${value}` })),
    }
    const hiringLengths = {
        birthName: 80, birthPlace: 80, street: 100, houseNumber: 12,
        postalCode: 10, city: 80, country: 80, nationality: 80,
        privateEmail: 254, privatePhone: 40, positionTitle: 120,
        iban: 34, bic: 11, accountHolder: 120, healthInsurance: 120,
        socialSecurityNumber: 20, taxId: 11,
    }

    function hiringControl(name, type, value, choices = null) {
        const control = choices
            ? select(name, [{ value: '', label: 'Bitte wählen' }].concat(choices), value ?? '')
            : input(name, type, false, value ?? '')
        control.classList.add('flzrecruitment-content-input')
        if (type === 'date') control.classList.add('is-date')
        const maxlength = hiringLengths[name]
        if (maxlength) {
            control.maxLength = maxlength
            control.size = Math.min(maxlength, name === 'privateEmail' || name === 'accountHolder' ? 32 : 20)
        }
        if (name === 'postalCode') { control.inputMode = 'numeric'; control.pattern = '[0-9A-Za-z -]{3,10}' }
        if (name === 'taxId') { control.inputMode = 'numeric'; control.pattern = '[0-9]{11}' }
        if (name === 'iban' || name === 'bic') control.style.textTransform = 'uppercase'
        return control
    }
    const professionCategories = [
        { value: 'assistance', label: 'Assistenz' },
        { value: 'nursing', label: 'Pflegefachkraft' },
        { value: 'social_work', label: 'Sozialarbeit' },
        { value: 'administration', label: 'Verwaltung' },
        { value: 'other', label: 'Sonstige' },
    ]
    const capabilityLabels = {
        view_dossier: 'Bewerberakte lesen', manage_catalog: 'Stellen und Vorlagen verwalten',
        edit_applications: 'Bewerbungen bearbeiten', interview: 'Interviews bearbeiten',
        edit_hiring_data: 'Einstellungsstammdaten bearbeiten', view_hiring_data: 'Einstellungsstammdaten lesen',
        manage_documents: 'Dokumente verwalten', communicate: 'Kommunikation bearbeiten',
        manage_mail_templates: 'Mailvorlagen verwalten',
        manage_candidate_pool: 'Bewerberpool verwalten',
        manage_first_guide_access: 'Erstbegleitungszugriff steuern',
    }

    function element(tag, options = {}, children = []) {
        const node = document.createElement(tag)
        for (const [key, value] of Object.entries(options)) {
            if (key === 'className') node.className = value
            else if (key === 'text') node.textContent = value
            else if (key === 'dataset') Object.assign(node.dataset, value)
            else if (key in node) node[key] = value
            else node.setAttribute(key, value)
        }
        for (const child of Array.isArray(children) ? children : [children]) {
            if (child !== null && child !== undefined) node.append(child)
        }
        return node
    }

    function bindPersistentHorizontalScroll(target) {
        horizontalScrollCleanup()
        if (!target) return
        const root = document.getElementById('flzrecruitment-app')
        if (!root) return
        const proxy = element('div', {
            className: 'app-horizontal-scroll-proxy',
            tabIndex: 0,
            role: 'region',
            'aria-label': 'Horizontal durch die Bewerbungsansicht scrollen',
        })
        const track = element('div', { className: 'app-horizontal-scroll-proxy__track', 'aria-hidden': 'true' })
        proxy.append(track)
        root.append(proxy)
        const update = () => {
            const visible = target.scrollWidth > target.clientWidth
            proxy.hidden = !visible
            track.style.width = `${target.scrollWidth}px`
            if (visible) proxy.scrollLeft = target.scrollLeft
        }
        const fromProxy = () => { target.scrollLeft = proxy.scrollLeft }
        const fromTarget = () => { proxy.scrollLeft = target.scrollLeft }
        proxy.addEventListener('scroll', fromProxy)
        target.addEventListener('scroll', fromTarget)
        const observer = typeof ResizeObserver === 'function' ? new ResizeObserver(update) : null
        observer?.observe(root)
        observer?.observe(target)
        window.addEventListener('resize', update)
        update()
        horizontalScrollCleanup = () => {
            observer?.disconnect()
            window.removeEventListener('resize', update)
            proxy.removeEventListener('scroll', fromProxy)
            target.removeEventListener('scroll', fromTarget)
            proxy.remove()
            horizontalScrollCleanup = () => {}
        }
    }

    function field(labelText, control, hint = '') {
        const label = element('label', { className: 'flzrecruitment-field' }, [
            element('span', { text: labelText }),
            control,
        ])
        if (hint) label.append(element('small', { text: hint }))
        return label
    }

    function richTextField(labelText, editor, hint = '') {
        const wrapper = element('div', { className: 'flzrecruitment-field' }, [
            element('span', { text: labelText }), editor.element,
        ])
        if (hint) wrapper.append(element('small', { text: hint }))
        return wrapper
    }

    function input(name, type = 'text', required = false, value = '') {
        return element('input', { name, type, required, value })
    }

    function select(name, options, selected = '', required = false) {
        const control = element('select', { name, required })
        for (const option of options) {
            control.append(element('option', {
                value: String(option.value),
                text: option.label,
                selected: String(option.value) === String(selected),
            }))
        }
        control.value = String(selected !== '' ? selected : (options[0]?.value ?? ''))
        return control
    }

    function button(text, type = 'submit', className = '') {
        return element('button', { type, text, className })
    }

    function contactNode(value, kind) {
        const text = String(value || '').trim()
        if (kind === 'email') {
            const attributes = contactLinks.emailLinkAttributes(text)
            return attributes
                ? element('a', { ...attributes, target: '_blank', rel: 'noopener noreferrer', text, className: 'flzrecruitment-contact-link' })
                : element('span', { text })
        }
        const href = contactLinks.phoneHref(text)
        return href ? element('a', { href, text, className: 'flzrecruitment-contact-link' }) : element('span', { text })
    }

    function contactLine(email, phone) {
        const children = []
        if (email) children.push(contactNode(email, 'email'))
        if (email && phone) children.push(element('span', { text: ' · ', 'aria-hidden': 'true' }))
        if (phone) children.push(contactNode(phone, 'phone'))
        return element('span', { className: 'flzrecruitment-contact-line' }, children)
    }

    function mailBodyHtml(body, format = 'plain') {
        if (format === 'html') return String(body || '')
        const escaped = String(body || '').trim()
            .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;').replaceAll("'", '&#039;')
        return `<p>${escaped.replaceAll(/\r\n|\r|\n/g, '<br>')}</p>`
    }

    function mailEditor(body, format = 'plain') {
        return richTextEditor.create({ name: 'body', html: mailBodyHtml(body, format) })
    }

    let overlaySequence = 0
    function createFormOverlay(title, triggerLabel, form, description = '') {
        const headingId = `flzrecruitment-overlay-title-${++overlaySequence}`
        const trigger = button(triggerLabel, 'button', 'primary')
        const close = button('Schließen', 'button', 'flzrecruitment-secondary')
        const body = element('div', { className: 'flzrecruitment-overlay__body' })
        if (description) body.append(element('p', { text: description }))
        body.append(form)
        const dialog = element('dialog', {
            className: 'flzrecruitment-overlay',
            'aria-labelledby': headingId,
        }, [
            element('header', { className: 'flzrecruitment-overlay__header' }, [
                element('h2', { id: headingId, text: title }),
                close,
            ]),
            body,
        ])
        const controller = createDialogController(dialog, trigger)
        trigger.addEventListener('click', controller.open)
        close.addEventListener('click', controller.close)
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) controller.close()
        })
        return element('div', { className: 'flzrecruitment-actions' }, [trigger, dialog])
    }

    function showError(error) {
        content.removeAttribute('aria-busy')
        errorBox.hidden = false
        if (error?.status === 403) {
            errorBox.textContent = 'Ihre Berechtigung reicht für diese Aktion nicht aus.'
        } else if (error?.status === 404) {
            errorBox.textContent = 'Der angeforderte Datensatz wurde nicht gefunden.'
        } else if (error?.status === 409) {
            errorBox.textContent = 'Der Datensatz wurde parallel geändert. Bitte laden Sie ihn neu.'
        } else {
            errorBox.textContent = error?.message || 'Ein technischer Fehler ist aufgetreten.'
        }
        status.textContent = 'Fehler'
    }

    function clearError() {
        errorBox.hidden = true
        errorBox.textContent = ''
    }

    function setBusy(message = 'Daten werden geladen …') {
        status.textContent = message
        content.setAttribute('aria-busy', 'true')
    }

    function setReady(message = 'Bereit') {
        status.textContent = message
        content.removeAttribute('aria-busy')
    }

    async function run(operation, successMessage, after = load) {
        clearError()
        setBusy('Änderung wird gespeichert …')
        try {
            const result = await operation()
            await after(result)
            setReady(successMessage)
        } catch (error) {
            showError(error)
        }
    }

    function renderTabs() {
        tabs.replaceChildren()
        tabs.setAttribute('role', 'tablist')
        const settingsSections = settingsNavigation.sections(state.capabilities)
        const definitions = [
            ...(state.capabilities.manage_unassigned_inbox ? [['inbox', 'Posteingang']] : []),
            ...(state.capabilities.view_dossier ? [['applications', 'Bewerbungen']] : []),
            ...(state.capabilities.manage_catalog ? [['jobs', 'Stellen']] : []),
            ...(state.capabilities.manage_basis_qualification ? [['basis-qualifications', 'Basisqualifikationen']] : []),
            ...(state.capabilities.manage_candidate_pool ? [['candidate-pool', 'Rückstellungen']] : []),
            ...(state.capabilities.view_hiring_data ? [['payroll', 'Vertragsvorbereitung']] : []),
            ...(settingsSections.length ? [['settings', 'Einstellungen']] : []),
        ]
        if (!definitions.some(([id]) => id === state.activeTab)) {
            state.activeTab = definitions[0]?.[0] || ''
        }
        for (const [id, label] of definitions) {
            const tab = button(label, 'button', 'flzrecruitment-tab')
            tab.id = `flzrecruitment-tab-${id}`
            tab.setAttribute('role', 'tab')
            tab.setAttribute('aria-controls', `flzrecruitment-panel-${id}`)
            tab.setAttribute('aria-selected', String(state.activeTab === id))
            tab.tabIndex = state.activeTab === id ? 0 : -1
            tab.addEventListener('click', () => showTab(id))
            tab.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return
                event.preventDefault()
                const index = definitions.findIndex(([definitionId]) => definitionId === id)
                const offset = event.key === 'ArrowRight' ? 1 : -1
                const next = definitions[(index + offset + definitions.length) % definitions.length][0]
                showTab(next)
                document.getElementById(`flzrecruitment-tab-${next}`)?.focus()
            })
            tabs.append(tab)
        }
    }

    function showTab(id) {
        horizontalScrollCleanup()
        state.activeTab = id
        renderTabs()
        if (id === 'inbox') renderInbox()
        else if (id === 'jobs') renderJobs()
        else if (id === 'basis-qualifications') renderBasisQualifications()
        else if (id === 'candidate-pool') renderCandidatePool()
        else if (id === 'payroll') renderPayroll()
        else if (id === 'settings') renderSettings()
        else renderApplications()
    }

    function panel(id, title) {
        return element('section', {
            id: `flzrecruitment-panel-${id}`,
            className: 'flzrecruitment-panel',
            role: 'tabpanel',
            'aria-labelledby': `flzrecruitment-tab-${id}`,
        }, [element('h2', { text: title })])
    }

    function settingsPanel(id, title) {
        return element('section', {
            id: `flzrecruitment-settings-panel-${id}`,
            className: 'flzrecruitment-settings-panel',
            role: 'tabpanel',
            'aria-labelledby': `flzrecruitment-settings-tab-${id}`,
        }, [element('h3', { text: title })])
    }

    function showSettingsSection(id, moveFocus = false) {
        state.activeSettingsSection = id
        renderSettings()
        if (moveFocus) document.getElementById(`flzrecruitment-settings-tab-${id}`)?.focus()
    }

    function renderSettings() {
        const definitions = settingsNavigation.sections(state.capabilities)
        state.activeSettingsSection = settingsNavigation.resolveActiveSection(
            state.activeSettingsSection,
            definitions,
        )
        const view = panel('settings', 'Einstellungen')
        view.append(element('p', {
            text: 'Hier finden Sie Funktionen, die nur gelegentlich für die Einrichtung und Pflege des Recruitings benötigt werden.',
        }))
        const navigation = element('div', {
            className: 'flzrecruitment-settings-nav',
            role: 'tablist',
            'aria-label': 'Einstellungsbereiche',
        })
        for (const [index, definition] of definitions.entries()) {
            const tab = button(definition.label, 'button', 'flzrecruitment-settings-tab')
            tab.id = `flzrecruitment-settings-tab-${definition.id}`
            tab.setAttribute('role', 'tab')
            tab.setAttribute('aria-controls', `flzrecruitment-settings-panel-${definition.id}`)
            tab.setAttribute('aria-selected', String(state.activeSettingsSection === definition.id))
            tab.tabIndex = state.activeSettingsSection === definition.id ? 0 : -1
            tab.addEventListener('click', () => showSettingsSection(definition.id, true))
            tab.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
                event.preventDefault()
                let nextIndex = index
                if (event.key === 'ArrowLeft') nextIndex = (index - 1 + definitions.length) % definitions.length
                else if (event.key === 'ArrowRight') nextIndex = (index + 1) % definitions.length
                else if (event.key === 'Home') nextIndex = 0
                else if (event.key === 'End') nextIndex = definitions.length - 1
                showSettingsSection(definitions[nextIndex].id, true)
            })
            navigation.append(tab)
        }
        const settingsContent = element('div', { className: 'flzrecruitment-settings-content' })
        view.append(navigation, settingsContent)
        content.replaceChildren(view)

        if (state.activeSettingsSection === 'mail-templates') renderMailTemplates(settingsContent, true)
        else if (state.activeSettingsSection === 'permissions') renderPermissions(settingsContent, true)
        else if (state.activeSettingsSection === 'candidate-pool') renderCandidatePoolSettings(settingsContent)
        else renderTemplates(settingsContent, true)
    }

    function renderCandidatePool() {
        const view = panel('candidate-pool', 'Datenschutzgerechte Rückstellungen')
        const pool = state.candidatePool || { entries: [], settings: {} }
        view.append(element('p', { text: 'Nur reduzierte Profile mit dokumentierter, freiwilliger Einwilligung werden berücksichtigt. Vorschläge sind Hinweise für eine menschliche Prüfung und lösen weder Kontakt noch Entscheidung automatisch aus.' }))
        if (!pool.settings.enabled) view.append(element('p', { className: 'flzrecruitment-warning', text: 'Der Bewerberpool ist bis zur Freigabe des Datenschutzhinweises deaktiviert.' }))
        const requestForm = element('form', { className: 'flzrecruitment-form flzrecruitment-card' }, [
            element('h3', { text: 'Rückstellung anfragen' }),
            field('Bewerbungs-ID', input('applicationId', 'number', true)),
            button('Einwilligung anfragen'),
        ])
        requestForm.addEventListener('submit', (event) => { event.preventDefault(); run(() => api.requestCandidatePool(Number(new FormData(requestForm).get('applicationId'))), 'Rückstellung wurde angefragt.', load) })
        view.append(requestForm)
        if (!pool.entries.length) view.append(emptyState('Noch keine Rückstellung vorhanden.'))
        for (const entry of pool.entries) {
            const card = element('article', { className: 'flzrecruitment-card' }, [
                element('h3', { text: `Bewerbung #${entry.sourceApplicationId}` }),
                element('p', { text: `Status: ${entry.status} · Berufsgruppe: ${entry.professionCategory}` }),
                element('p', { text: entry.expiresAt ? `Einwilligung bis ${entry.expiresAt}` : 'Einwilligung noch nicht erfasst' }),
            ])
            for (const match of entry.matches || []) card.append(element('p', { text: `Passende Stelle #${match.jobId}: ${(match.reasons || []).join(' ')}` }))
            if (entry.status === 'requested') {
                const form = element('form', { className: 'flzrecruitment-form' }, [
                    field('Nachweisreferenz', input('evidenceReference', 'text', true), 'Zum Beispiel die Message-ID der bestätigenden E-Mail; nicht den Mailinhalt kopieren.'),
                    button('Einwilligung erfassen'),
                ])
                form.addEventListener('submit', (event) => { event.preventDefault(); run(() => api.grantCandidatePoolConsent(entry.id, { evidenceType: 'email_reply', evidenceReference: new FormData(form).get('evidenceReference'), noticeVersion: pool.settings.noticeVersion, areaKeys: [] }), 'Einwilligung wurde dokumentiert.', load) })
                card.append(form)
            } else if (entry.status === 'active') {
                const revoke = button('Einwilligung widerrufen', 'button', 'flzrecruitment-secondary')
                revoke.addEventListener('click', () => run(() => api.withdrawCandidatePoolConsent(entry.id), 'Einwilligung wurde widerrufen.', load))
                card.append(revoke)
            }
            view.append(card)
        }
        content.replaceChildren(view)
    }

    function renderCandidatePoolSettings(target) {
        const view = settingsPanel('candidate-pool', 'Datenschutz & Rückstellungen')
        const settings = state.candidatePool?.settings || { enabled: false, noticeVersion: '', consentMonths: 12, reminderDays: 30, revision: 0 }
        view.append(element('p', {
            text: 'Das Personalreferat pflegt hier Datenschutzhinweis und Einwilligungsfristen. Die Funktion bleibt aus, bis ein versionierter Datenschutzhinweis freigegeben ist.',
        }))
        const enabled = input('enabled', 'checkbox'); enabled.checked = settings.enabled
        const form = element('form', { className: 'flzrecruitment-form flzrecruitment-card' }, [
            field('Bewerberpool aktiv', enabled),
            field('Version Datenschutzhinweis', input('noticeVersion', 'text', false, settings.noticeVersion)),
            field('Einwilligung (Monate)', input('consentMonths', 'number', true, settings.consentMonths)),
            field('Erinnerung vor Ablauf (Tage)', input('reminderDays', 'number', true, settings.reminderDays)),
            button('Einstellung speichern'),
        ])
        form.addEventListener('submit', (event) => {
            event.preventDefault()
            const data = new FormData(form)
            run(
                () => api.saveCandidatePoolSettings({
                    enabled: data.has('enabled'),
                    noticeVersion: data.get('noticeVersion'),
                    consentMonths: Number(data.get('consentMonths')),
                    reminderDays: Number(data.get('reminderDays')),
                    revision: settings.revision,
                }),
                'Bewerberpool-Einstellung wurde gespeichert.',
                load,
            )
        })
        view.append(form)
        target.replaceChildren(view)
    }

    function emptyState(text) {
        return element('p', { className: 'flzrecruitment-empty', text })
    }

    async function renderInbox() {
        const view = panel('inbox', 'Posteingang für Bewerbungen')
        view.append(element('p', {
            text: 'Eingegangene Mails bleiben unverändert. Ausgelesene Angaben sind Vorschläge und werden erst durch die Zuordnung Teil einer Bewerberakte.',
        }))
        content.replaceChildren(view)
        setBusy('Posteingang wird geladen …')
        try {
            state.inboxMessages = (await api.inbox()).messages || []
            if (!state.inboxMessages.length) {
                view.append(emptyState('Aktuell liegen keine Eingangsnachrichten vor.'))
            } else {
                const stateLabels = { new: 'Neu', unclear: 'Unklar', assigned: 'Zugeordnet', error: 'Importfehler', ignored: 'Ignoriert' }
                const list = element('div', { className: 'flzrecruitment-grid' })
                for (const message of state.inboxMessages) {
                    const open = button('Nachricht prüfen', 'button')
                    open.addEventListener('click', () => openInboxMessage(message.id))
                    list.append(element('article', { className: 'flzrecruitment-card' }, [
                        element('h3', { text: message.subject || 'Ohne Betreff' }),
                        element('p', {}, [contactNode(message.senderAddress, 'email'), element('span', { text: ` · ${message.receivedAt}` })]),
                        element('p', { text: stateLabels[message.state] || message.state }),
                        open,
                    ]))
                }
                view.append(list)
            }
            setReady()
        } catch (error) {
            showError(error)
        }
    }

    async function openInboxMessage(id) {
        clearError()
        setBusy('Nachricht wird geladen …')
        try {
            renderInboxDetail(await api.inboxMessage(id))
            setReady()
        } catch (error) {
            showError(error)
        }
    }

    function renderInboxDetail(message) {
        const view = element('section', { className: 'flzrecruitment-panel' })
        const back = button('← Zurück zum Posteingang', 'button', 'flzrecruitment-secondary')
        back.addEventListener('click', renderInbox)
        view.append(back, element('h2', { text: message.subject || 'Ohne Betreff' }))
        view.append(element('dl', { className: 'flzrecruitment-facts' }, [
            element('div', {}, [element('dt', { text: 'Absender' }), element('dd', {}, contactNode(message.senderAddress, 'email'))]),
            element('div', {}, [element('dt', { text: 'Empfangen' }), element('dd', { text: message.receivedAt })]),
            element('div', {}, [element('dt', { text: 'Postfach' }), element('dd', { text: message.mailbox.label })]),
        ]))
        view.append(element('section', { className: 'flzrecruitment-card' }, [
            element('h3', { text: 'Unveränderter Mailtext' }),
            element('pre', { className: 'flzrecruitment-mail-body', text: message.bodyText }),
        ]))
        view.append(renderMailSuggestions(message.fieldSuggestions))
        view.append(renderMailAttachments(message.attachments, message.applicationId, () => openInboxMessage(message.id)))

        if (['new', 'unclear'].includes(message.state) && state.capabilities.edit_applications) {
            const jobs = (state.data?.jobs || []).filter((job) => job.active)
            if (jobs.length) {
                const suggestedName = String(message.fieldSuggestions?.name?.value || '').trim()
                const nameParts = suggestedName ? suggestedName.split(/\s+/) : []
                const familyName = nameParts.length > 1 ? nameParts.pop() : ''
                const givenName = nameParts.join(' ') || suggestedName
                const suggestionSelection = renderAssignableMailSuggestions(message.fieldSuggestions)
                const creationForm = element('form', { className: 'flzrecruitment-inline-form flzrecruitment-card' }, [
                    element('h3', { text: 'Neue Bewerbung aus diesem Eingang' }),
                    field('Stelle', select('jobId', jobs.map((job) => ({
                        value: job.id,
                        label: job.publicTitle || job.internalTitle || `Stelle #${job.id}`,
                    })), jobs[0].id, true)),
                    field('Vorname', input('givenName', 'text', true, givenName)),
                    field('Nachname', input('familyName', 'text', true, familyName)),
                    field('E-Mail', input('email', 'email', false, message.fieldSuggestions?.email?.value || message.senderAddress || '')),
                    field('Telefon', input('phone', 'text', false, message.fieldSuggestions?.phone?.value || '')),
                    field('Zuständige Person (UID, optional)', input('assigneeUid')),
                    suggestionSelection.node,
                    button('Person und Bewerbung anlegen'),
                ])
                creationForm.addEventListener('submit', (event) => {
                    event.preventDefault()
                    const data = new FormData(creationForm)
                    run(
                        () => api.createApplicationFromInbox(message.id, {
                            version: message.version,
                            jobId: Number(data.get('jobId')),
                            givenName: data.get('givenName'),
                            familyName: data.get('familyName'),
                            email: data.get('email'),
                            phone: data.get('phone'),
                            assigneeUid: data.get('assigneeUid'),
                            acceptedSuggestions: suggestionSelection.values(),
                        }),
                        'Person und Bewerbung wurden angelegt und der Eingang wurde zugeordnet.',
                        () => renderInbox(),
                    )
                })
                view.append(creationForm)
            } else {
                view.append(element('section', { className: 'flzrecruitment-card' }, [
                    element('h3', { text: 'Neue Bewerbung anlegen' }),
                    element('p', { text: 'Es ist keine aktive Stelle verfügbar. Legen Sie zuerst eine Stelle an oder aktivieren Sie eine vorhandene Stelle.' }),
                ]))
            }
        }

        if (['new', 'unclear', 'assigned'].includes(message.state)) {
            const candidates = state.data?.applications || []
            const applicationControl = candidates.length
                ? select('applicationId', candidates.map((application) => ({
                    value: application.id,
                    label: `Bewerbung #${application.id} · ${statusLabel(application.status)}`,
                })), message.applicationId || '')
                : input('applicationId', 'number', true, message.applicationId || '')
            const suggestionSelection = renderAssignableMailSuggestions(message.fieldSuggestions)
            const form = element('form', { className: 'flzrecruitment-inline-form flzrecruitment-card' }, [
                field('Vorhandene Bewerbung', applicationControl, 'Falls keine Auswahl sichtbar ist, kann die Bewerbungs-ID eingetragen werden.'),
                suggestionSelection.node,
                button(message.state === 'assigned' ? 'Zuordnung korrigieren' : 'Bewerbung zuordnen'),
            ])
            form.addEventListener('submit', (event) => {
                event.preventDefault()
                run(
                    () => api.assignInboxMessage(
                        message.id,
                        Number(new FormData(form).get('applicationId')),
                        message.version,
                        suggestionSelection.values(),
                    ),
                    'Eingangsnachricht wurde zugeordnet.',
                    () => renderInbox(),
                )
            })
            view.append(form)
        }
        if (['new', 'unclear', 'error'].includes(message.state)) {
            const ignore = button('Als keine Bewerbung schließen', 'button', 'flzrecruitment-secondary')
            ignore.addEventListener('click', () => run(
                () => api.ignoreInboxMessage(message.id, message.version),
                'Eingangsnachricht wurde geschlossen.',
                () => renderInbox(),
            ))
            view.append(ignore)
        }
        content.replaceChildren(view)
    }

    function renderMailSuggestions(suggestions) {
        const card = element('section', { className: 'flzrecruitment-card' }, [element('h3', { text: 'Ausgelesene Vorschläge' })])
        const facts = element('dl', { className: 'flzrecruitment-facts' })
        const labels = { salutation: 'Anrede', title: 'Titel', name: 'Name', email: 'E-Mail', phone: 'Telefon', jobPreference: 'Stellenwunsch', desiredWeeklyHours: 'Wunschstunden', availableFrom: 'Verfügbar ab', previousExperience: 'Berufserfahrung', germanLanguageLevel: 'Deutschkenntnisse', location: 'Wohnort', message: 'Nachricht' }
        for (const [key, label] of Object.entries(labels)) {
            if (!suggestions?.[key]?.value) continue
            const value = suggestions[key].value
            const displayed = key === 'email' || key === 'phone'
                ? contactNode(value, key)
                : element('span', { text: value })
            facts.append(element('div', {}, [element('dt', { text: label }), element('dd', {}, displayed)]))
        }
        card.append(facts.childElementCount ? facts : emptyState('Keine zusätzlichen Felder erkannt.'))
        return card
    }

    function renderAssignableMailSuggestions(suggestions) {
        const labels = {
            salutation: 'Anrede', title: 'Titel', email: 'Private E-Mail', phone: 'Telefon',
            availableFrom: 'Geplanter Eintritt', location: 'Wohnort',
            previousExperience: 'Vorerfahrung', germanLanguageLevel: 'Deutschniveau',
            desiredWeeklyHours: 'Wunschstunden',
        }
        const controls = new Map()
        const fields = []
        for (const [key, label] of Object.entries(labels)) {
            const suggestion = suggestions?.[key]?.value
            if (!suggestion) continue
            const accepted = input(`accept-${key}`, 'checkbox')
            let editor
            let read
            if (key === 'germanLanguageLevel') {
                const level = ({ muttersprachlich: 'native', 'nicht bewertet': 'not_assessed' })[String(suggestion).toLowerCase()] || String(suggestion).toUpperCase()
                editor = select(`suggestion-${key}`, [
                    ...['A1', 'A2', 'B1', 'B2', 'C1', 'C2'].map((value) => ({ value, label: value })),
                    { value: 'native', label: 'Muttersprachlich' },
                    { value: 'not_assessed', label: 'Nicht bewertet' },
                ], level)
            } else if (key === 'previousExperience') {
                editor = element('textarea', { name: `suggestion-${key}`, rows: 3, maxLength: 8000, value: suggestion })
            } else if (key === 'desiredWeeklyHours') {
                const match = String(suggestion).replace(',', '.').match(/(\d+(?:\.\d+)?)\s*(?:(?:-|bis)\s*(\d+(?:\.\d+)?))?/i)
                const minimum = element('input', { name: `suggestion-${key}-min`, type: 'number', min: 0.01, max: 80, step: 0.5, required: true, value: match?.[1] || '' })
                const maximum = element('input', { name: `suggestion-${key}-max`, type: 'number', min: 0.01, max: 80, step: 0.5, value: match?.[2] || '' })
                editor = element('div', { className: 'flzrecruitment-hours-range' }, [
                    field('Von', minimum), field('Bis (optional)', maximum),
                ])
                read = () => maximum.value === '' ? minimum.value : `${minimum.value}-${maximum.value}`
            } else {
                editor = input(`suggestion-${key}`, key === 'email' ? 'email' : 'text', false, suggestion)
            }
            controls.set(key, { accepted, read: read || (() => editor.value) })
            fields.push(element('div', { className: 'flzrecruitment-suggestion-choice' }, [
                element('label', {}, [accepted, element('span', { text: `${label} übernehmen` })]),
                field(`Wert für ${label}`, editor),
            ]))
        }
        const node = element('fieldset', { className: 'flzrecruitment-suggestion-selection' }, [
            element('legend', { text: 'Vorschläge einzeln übernehmen' }),
            element('p', { text: 'Nur markierte Werte werden übernommen. Werte können vor der Zuordnung korrigiert werden.' }),
            ...(fields.length ? fields : [emptyState('Keine übernehmbaren Kontakt- oder Eintrittsdaten erkannt.')]),
        ])
        return {
            node,
            values: () => Object.fromEntries(Array.from(controls.entries())
                .filter(([, control]) => control.accepted.checked)
                .map(([key, control]) => [key, control.read()])),
        }
    }

    let documentCommentSequence = 0
    function documentCommentKey() {
        return root.crypto?.randomUUID?.() || `document-comment-${Date.now()}-${++documentCommentSequence}`
    }

    function renderMailAttachments(attachments, applicationId = null, reload = null) {
        const card = element('section', { className: 'flzrecruitment-card' }, [element('h3', { text: 'Bewerbungsunterlagen' })])
        if (!attachments?.length) {
            card.append(emptyState('Keine PDF-Anhänge vorhanden.'))
            return card
        }
        for (const attachment of attachments) {
            const review = element('article', { className: 'flzrecruitment-document-review' }, [
                element('h4', { text: `${attachment.originalName} · ${Math.ceil(attachment.sizeBytes / 1024)} KB · PDF` }),
            ])
            const openButton = button('PDF in Lightbox öffnen', 'button', 'primary')
            openButton.addEventListener('click', () => {
                let lightbox = null
                const panel = createPdfFieldPanel(attachment, applicationId, async () => {
                    lightbox?.close()
                    if (reload) await reload()
                })
                pdfLightbox.open({
                    url: api.documentUrl(attachment.id),
                    title: attachment.originalName,
                    sidePanel: panel.element,
                    fieldLinks: attachment.fieldLinks || [],
                    onSelection: panel.onSelection,
                    onError: showError,
                }).then((handle) => { lightbox = handle }).catch(showError)
            })
            review.append(openButton, renderDocumentFieldLinks(attachment.fieldLinks || []))
            if (attachment.comments?.length) review.append(renderLegacyDocumentComments(attachment.comments))
            card.append(review)
        }
        card.append(element('p', { text: 'Markierungen und Feldverknüpfungen liegen getrennt vom unveränderten PDF-Original.' }))
        return card
    }

    function renderLegacyDocumentComments(comments) {
        const section = element('section', { className: 'flzrecruitment-document-comments' }, [
            element('h5', { text: 'Bisherige Dokumentnotizen' }),
        ])
        const list = element('ol', { className: 'flzrecruitment-history' })
        for (const comment of comments) {
            const location = comment.pageNumber ? `Seite ${comment.pageNumber} · ` : ''
            list.append(element('li', { text: `${location}${comment.body} · ${comment.actorUid} · ${comment.createdAt}` }))
        }
        section.append(list)
        return section
    }

    const documentTargetLabels = {
        previousExperience: 'Vorerfahrung',
        germanLanguageLevel: 'Deutschniveau',
        birthDate: 'Geburtsdatum',
        birthPlace: 'Geburtsort',
        freeComment: 'Freier Kommentar der Bewerbung',
    }

    function renderDocumentFieldLinks(links) {
        const section = element('section', { className: 'flzrecruitment-document-comments' }, [
            element('h5', { text: 'Verknüpfte PDF-Fundstellen' }),
        ])
        if (!links.length) {
            section.append(emptyState('Noch keine PDF-Fundstelle mit Bewerbungsdaten verknüpft.'))
            return section
        }
        const list = element('ol', { className: 'flzrecruitment-field-link-list' })
        for (const link of links) {
            const source = link.selectedText ? `„${link.selectedText}“ → ` : ''
            list.append(element('li', {
                text: `Seite ${link.pageNumber}: ${source}${documentTargetLabels[link.targetField] || link.targetField}: ${link.appliedValue} · ${link.actorUid} · ${link.createdAt}`,
            }))
        }
        section.append(list)
        return section
    }

    function createPdfFieldPanel(attachment, applicationId, afterSave) {
        const panel = element('aside', {}, [
            element('h3', { text: 'Fundstelle zuordnen' }),
            element('p', { text: 'Markieren Sie Text oder einen Bereich. Alternativ können Seite und Quelltext vollständig per Tastatur eingetragen werden.' }),
        ])
        const selection = { pageNumber: 1, selectedText: '', rectangles: [] }
        const summary = element('p', { className: 'flzrecruitment-pdf-selection-summary', text: 'Noch keine Fundstelle ausgewählt.' })
        panel.append(summary, renderDocumentFieldLinks(attachment.fieldLinks || []))

        const canEditApplication = Boolean(state.capabilities.manage_documents && state.capabilities.edit_applications)
        const canEditHiring = Boolean(state.capabilities.manage_documents && state.capabilities.edit_hiring_data)
        if (!applicationId || (!canEditApplication && !canEditHiring)) {
            panel.append(element('p', { text: applicationId
                ? 'Sie dürfen vorhandene Verknüpfungen lesen, aber keine Bewerbungsfelder aus dem PDF ergänzen.'
                : 'Das Dokument muss vor einer Feldzuordnung zuerst einer Bewerbung zugeordnet werden.' }))
            return { element: panel, onSelection: () => {} }
        }

        const targets = [
            ...(canEditApplication ? [
                { value: 'previousExperience', label: 'Vorerfahrung' },
                { value: 'germanLanguageLevel', label: 'Deutschniveau' },
                { value: 'freeComment', label: 'Freier Kommentar der Bewerbung' },
            ] : []),
            ...(canEditHiring ? [
                { value: 'birthDate', label: 'Geburtsdatum' },
                { value: 'birthPlace', label: 'Geburtsort' },
            ] : []),
        ]
        const target = select('targetField', targets, targets[0].value, true)
        const page = input('pageNumber', 'number', true, 1)
        page.min = 1
        page.max = 2000
        const sourceText = element('textarea', { name: 'selectedText', rows: 4, maxLength: 4000 })
        const valueHost = element('div')
        const currentValue = element('p', { text: 'Aktueller Feldwert wird geladen …' })
        const replace = input('replaceExisting', 'checkbox')
        const replaceField = field('Vorhandenen strukturierten Wert ausdrücklich ersetzen', replace)
        replaceField.hidden = true
        const contextStatus = element('p', { role: 'status' })
        const form = element('form', { className: 'flzrecruitment-form' }, [
            field('Zielfeld', target),
            field('PDF-Seite', page, 'Für eine rein tastaturbediente Erfassung kann die Seite manuell angegeben werden.'),
            field('Markierter Quelltext', sourceText, 'Bei einer grafischen Bereichsmarkierung darf dieses Feld leer bleiben.'),
            currentValue,
            valueHost,
            replaceField,
            contextStatus,
            button('Fundstelle mit Datenfeld verbinden'),
        ])
        panel.append(form)
        let fieldContext = null
        let valueControl = null

        const renderValue = () => {
            const fieldName = target.value
            if (fieldName === 'germanLanguageLevel') {
                valueControl = select('appliedValue', [
                    { value: '', label: 'Bitte auswählen' },
                    ...['A1', 'A2', 'B1', 'B2', 'C1', 'C2'].map((value) => ({ value, label: value })),
                    { value: 'native', label: 'Muttersprachlich' },
                    { value: 'not_assessed', label: 'Nicht bewertet' },
                ], '', true)
            } else {
                const type = fieldName === 'birthDate' ? 'date' : 'text'
                valueControl = fieldName === 'previousExperience' || fieldName === 'freeComment'
                    ? element('textarea', { name: 'appliedValue', rows: 5, maxLength: 8000, required: true })
                    : input('appliedValue', type, true)
            }
            valueHost.replaceChildren(field('Zu übernehmender Wert', valueControl))
            if (sourceText.value && fieldName !== 'germanLanguageLevel') valueControl.value = sourceText.value
        }

        const loadFieldContext = async () => {
            const requestedTarget = target.value
            fieldContext = null
            renderValue()
            contextStatus.textContent = 'Aktueller Feldwert wird geladen …'
            try {
                const loadedContext = await api.attachmentFieldContext(attachment.id, requestedTarget)
                if (target.value !== requestedTarget) return
                fieldContext = loadedContext
                const existing = fieldContext.value || ''
                currentValue.textContent = existing
                    ? `Aktueller Feldwert: ${existing}`
                    : 'Das Zielfeld ist derzeit leer.'
                replaceField.hidden = target.value === 'freeComment' || existing === ''
                replace.checked = false
                contextStatus.textContent = target.value === 'freeComment' && existing
                    ? 'Der neue Text wird an den bestehenden freien Kommentar angehängt.'
                    : 'Zielfeld ist bereit.'
            } catch (error) {
                contextStatus.textContent = error?.message || 'Das Zielfeld konnte nicht geladen werden.'
                showError(error)
            }
        }
        target.addEventListener('change', loadFieldContext)
        loadFieldContext()

        form.addEventListener('submit', (event) => {
            event.preventDefault()
            if (!fieldContext) {
                contextStatus.textContent = 'Warten Sie, bis der aktuelle Feldwert geladen wurde.'
                return
            }
            run(
                () => api.createAttachmentFieldLink(attachment.id, {
                    targetField: target.value,
                    selectedText: sourceText.value,
                    appliedValue: valueControl.value,
                    pageNumber: Number(page.value),
                    rectangles: selection.rectangles,
                    replaceExisting: replace.checked,
                    expectedVersion: fieldContext.version,
                    clientKey: documentCommentKey(),
                }),
                'PDF-Fundstelle wurde mit dem Bewerbungsfeld verbunden.',
                afterSave,
            )
        })
        return {
            element: panel,
            onSelection: (selected) => {
                selection.pageNumber = selected.pageNumber
                selection.selectedText = selected.selectedText
                selection.rectangles = selected.rectangles
                page.value = selected.pageNumber
                sourceText.value = selected.selectedText
                summary.textContent = selected.selectedText
                    ? `Seite ${selected.pageNumber}: „${selected.selectedText}“`
                    : `Grafischer Bereich auf Seite ${selected.pageNumber}`
                if (valueControl && target.value !== 'germanLanguageLevel' && selected.selectedText) {
                    valueControl.value = selected.selectedText
                }
            },
        }
    }

    function renderJobs() {
        const view = panel('jobs', 'Stellen und Ausschreibungen')
        if (state.capabilities.manage_catalog) {
            const profession = select('professionCategory', professionCategories, 'assistance', true)
            const groupSelect = select('responsibleGroups', [])
            groupSelect.multiple = true
            groupSelect.size = 6
            const userSearch = input('responsibilityUserSearch', 'search')
            userSearch.autocomplete = 'off'
            userSearch.placeholder = 'Name oder Benutzerkennung'
            const searchButton = button('Suchen', 'button', 'flzrecruitment-secondary')
            const searchResults = element('div', {
                className: 'flzrecruitment-responsibility-results',
                role: 'list',
                'aria-label': 'Gefundene verantwortliche Personen',
            })
            const selectedUsers = new Map()
            const selectedUserList = element('div', {
                className: 'flzrecruitment-responsibility-selection',
                'aria-live': 'polite',
            })
            const bqRule = element('p', { className: 'flzrecruitment-callout' })

            const selectedGroupIds = () => Array.from(groupSelect.selectedOptions, (option) => option.value)
            const renderSelectedUsers = () => {
                selectedUserList.replaceChildren()
                if (selectedUsers.size === 0) {
                    selectedUserList.append(element('small', { text: 'Keine einzelne Person zusätzlich ausgewählt.' }))
                    return
                }
                for (const user of selectedUsers.values()) {
                    const remove = button(`Entfernen: ${user.displayName}`, 'button', 'flzrecruitment-chip')
                    remove.addEventListener('click', () => {
                        selectedUsers.delete(user.uid)
                        renderSelectedUsers()
                    })
                    selectedUserList.append(remove)
                }
            }
            const updateProfessionFields = () => {
                const category = profession.value
                const groups = state.jobResponsibilityGroups.filter((group) =>
                    group.professionCategories.includes(category)
                )
                groupSelect.replaceChildren(...groups.map((group) => element('option', {
                    value: group.id,
                    text: group.label,
                })))
                groupSelect.size = Math.min(7, Math.max(3, groups.length))
                selectedUsers.clear()
                searchResults.replaceChildren()
                renderSelectedUsers()
                bqRule.textContent = category === 'assistance'
                    ? 'Assistenzstellen benötigen immer eine Basisqualifikation.'
                    : 'Für diese Berufsgruppe ist keine Basisqualifikation vorgesehen.'
            }
            profession.addEventListener('change', updateProfessionFields)
            groupSelect.addEventListener('change', () => {
                selectedUsers.clear()
                searchResults.replaceChildren()
                renderSelectedUsers()
            })
            searchButton.addEventListener('click', async () => {
                searchResults.replaceChildren()
                const query = userSearch.value.trim()
                const groupIds = selectedGroupIds()
                if (groupIds.length === 0) {
                    searchResults.append(element('small', { text: 'Wählen Sie zuerst mindestens eine beteiligte Gruppe.' }))
                    return
                }
                if (query.length < 2) {
                    searchResults.append(element('small', { text: 'Geben Sie mindestens zwei Zeichen ein.' }))
                    return
                }
                try {
                    const payload = await api.jobResponsibilityUsers(profession.value, groupIds, query)
                    if (!payload.users.length) {
                        searchResults.append(element('small', { text: 'Keine passende Person in den ausgewählten Gruppen gefunden.' }))
                        return
                    }
                    for (const user of payload.users) {
                        const add = button(`${user.displayName} (${user.uid})`, 'button', 'flzrecruitment-secondary')
                        add.addEventListener('click', () => {
                            selectedUsers.set(user.uid, user)
                            renderSelectedUsers()
                        })
                        searchResults.append(element('div', { role: 'listitem' }, [add]))
                    }
                } catch (error) {
                    showError(error)
                }
            })
            userSearch.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return
                event.preventDefault()
                searchButton.click()
            })

            const form = element('form', { className: 'flzrecruitment-form flzrecruitment-card' }, [
                field('Interne Bezeichnung', input('internalTitle', 'text', true)),
                field('Öffentliche Bezeichnung', input('publicTitle')),
                field('Berufsgruppe', profession),
                bqRule,
                field('Beteiligte Gruppen', groupSelect, 'Nur fachlich passende Gruppen der Filzmann-Organisation werden angeboten.'),
                field('Verantwortliche Personen suchen', userSearch, 'Die Suche berücksichtigt ausschließlich die ausgewählten Gruppen.'),
                searchButton,
                searchResults,
                selectedUserList,
                field('Vertragsdauer', select('contractTerm', [
                    { value: 'permanent', label: 'Unbefristet' },
                    { value: 'fixed_term_reason', label: 'Befristet mit Sachgrund' },
                ], 'permanent', true)),
                field('Entgeltgruppe gemäß HTV', select('payGrade', hiringChoices.payGrade, '5', true)),
                field('Ausgeschriebene Wochenstunden', input('advertisedWeeklyHours', 'number', true)),
                field('Vollzeitstunden gemäß HTV', input('fullTimeWeeklyHours', 'number', true)),
                field('Urlaubstage gemäß HTV', input('vacationDays', 'number', true)),
                field('Arbeitsort', input('workLocation', 'text', true, 'Berlin')),
                element('details', {}, [
                    element('summary', { text: 'Weitere Angaben' }),
                    field('Technischer Zuordnungsschlüssel', input('assignmentKey')),
                ]),
                button('Stelle anlegen'),
            ])
            updateProfessionFields()
            form.addEventListener('submit', (event) => {
                event.preventDefault()
                const data = new FormData(form)
                run(() => api.createJob({
                    internalTitle: data.get('internalTitle'),
                    publicTitle: data.get('publicTitle'),
                    active: true,
                    responsibleUsers: Array.from(selectedUsers.keys()),
                    responsibleGroups: selectedGroupIds(),
                    assignmentKey: data.get('assignmentKey'),
                    professionCategory: data.get('professionCategory'),
                    contractTerm: data.get('contractTerm'), payGrade: data.get('payGrade'),
                    advertisedWeeklyHours: Number(data.get('advertisedWeeklyHours')),
                    fullTimeWeeklyHours: Number(data.get('fullTimeWeeklyHours')),
                    vacationDays: Number(data.get('vacationDays')), workLocation: data.get('workLocation'),
                }), 'Stelle wurde angelegt.')
            })
            view.append(createFormOverlay('Neue Stelle', 'Stelle neu', form))
        }

        const jobs = state.data.jobs
        if (jobs.length === 0) {
            view.append(emptyState('Noch keine Stellen vorhanden.'))
        } else {
            const list = element('div', { className: 'flzrecruitment-grid' })
            for (const job of jobs) {
                const card = element('article', { className: 'flzrecruitment-card' }, [
                    element('h3', { text: job.internalTitle }),
                    job.publicTitle ? element('p', { text: job.publicTitle }) : null,
                    element('p', { text: job.active ? 'Aktiv' : 'Deaktiviert' }),
                    element('p', { text: professionCategories.find((item) => item.value === job.professionCategory)?.label || 'Sonstige Berufsgruppe' }),
                    element('p', { text: job.professionCategory === 'assistance' ? 'Basisqualifikation erforderlich' : 'Keine Basisqualifikation' }),
                    element('p', { text: `${job.workingTimeModel === 'kapovaz' ? 'KAPOVAZ' : 'Festgehalt'} · EG ${job.payGrade || '–'} · ${job.advertisedWeeklyHours ?? '–'} Std./Woche · ${job.workLocation || 'Berlin'}` }),
                    element('small', {
                        text: [
                            job.responsibleUsers.length ? `Benutzer: ${job.responsibleUsers.join(', ')}` : '',
                            job.responsibleGroups.length ? `Gruppen: ${job.responsibleGroups.map((groupId) =>
                                state.jobResponsibilityGroups.find((group) => group.id === groupId)?.label || groupId
                            ).join(', ')}` : '',
                        ].filter(Boolean).join(' · ') || 'Noch keine Zuständigkeit zugeordnet.',
                    }),
                ])
                list.append(card)
            }
            view.append(list)
        }
        content.replaceChildren(view)
    }

    function renderApplications() {
        const view = panel('applications', 'Bewerbungen')
        if (state.capabilities.edit_applications) {
            view.append(element('p', {
                text: 'Im Regelbetrieb entstehen Bewerber*innen und Bewerbungen aus eingehenden E-Mails. Die manuelle Anlage bleibt für Ausnahmen und Empfehlungen verfügbar.',
            }))
            const forms = element('div', { className: 'flzrecruitment-actions' })
            const personForm = element('form', { className: 'flzrecruitment-form flzrecruitment-card' }, [
                field('Vorname', input('givenName', 'text', true)),
                field('Nachname', input('familyName', 'text', true)),
                field('E-Mail', input('email', 'email')),
                field('Telefon', input('phone', 'tel')),
                button('Person anlegen'),
            ])
            personForm.addEventListener('submit', (event) => {
                event.preventDefault()
                const data = Object.fromEntries(new FormData(personForm))
                run(() => api.createPerson(data), 'Person wurde angelegt.')
            })

            const personOptions = [{ value: '', label: 'Person wählen' }].concat(
                state.data.people.map((person) => ({
                    value: person.id,
                    label: `${person.familyName}, ${person.givenName}`,
                })),
            )
            const jobOptions = [{ value: '', label: 'Stelle wählen' }].concat(
                state.data.jobs.filter((job) => job.active).map((job) => ({
                    value: job.id,
                    label: job.internalTitle,
                })),
            )
            const desiredHoursMinimum = input('desiredWeeklyHours', 'number')
            const desiredHoursMaximum = input('desiredWeeklyHoursMax', 'number')
            for (const control of [desiredHoursMinimum, desiredHoursMaximum]) {
                control.min = '0.01'
                control.max = '80'
                control.step = '0.01'
            }
            const applicationForm = element('form', { className: 'flzrecruitment-form flzrecruitment-card' }, [
                field('Person', select('personId', personOptions, '', true)),
                field('Stelle', select('jobId', jobOptions, '', true)),
                field('Eingangskanal', select('source', [
                    { value: 'manual', label: 'Manuelle Ausnahme' },
                    { value: 'referral', label: 'Empfehlung' },
                    { value: 'other', label: 'Sonstiger Kanal' },
                ])),
                field('Eingangsdatum', input('receivedOn', 'date', true, new Date().toISOString().slice(0, 10))),
                field('Wunschstunden von (ca.)', desiredHoursMinimum, 'Unverbindliche Untergrenze oder einzelner Circa-Wert'),
                field('Wunschstunden bis (ca., optional)', desiredHoursMaximum, 'Nur bei einer Von-bis-Angabe ausfüllen'),
                field('Zuständige Benutzer-UID', input('assigneeUid')),
                button('Bewerbung anlegen'),
            ])
            applicationForm.addEventListener('submit', (event) => {
                event.preventDefault()
                const data = Object.fromEntries(new FormData(applicationForm))
                data.personId = Number(data.personId)
                data.jobId = Number(data.jobId)
                data.desiredWeeklyHours = data.desiredWeeklyHours === '' ? null : Number(data.desiredWeeklyHours)
                data.desiredWeeklyHoursMax = data.desiredWeeklyHoursMax === '' ? null : Number(data.desiredWeeklyHoursMax)
                run(() => api.createApplication(data), 'Bewerbung wurde angelegt.')
            })
            forms.append(
                createFormOverlay('Bewerber*in manuell anlegen',
                    'Bewerber*in neu',
                    personForm,
                    'Diese Funktion ist für Ausnahmefälle bestimmt. Üblicherweise entsteht der Datensatz aus einer eingehenden Bewerbungsmail.',
                ),
                createFormOverlay('Bewerbung manuell anlegen',
                    'Bewerbung neu',
                    applicationForm,
                    'Diese Funktion ist für Empfehlungen und andere Bewerbungen außerhalb des konfigurierten E-Mail-Eingangs bestimmt.',
                ),
            )
            view.append(forms)
        }

        view.append(renderApplicationWorkbench())
        content.replaceChildren(view)
    }

    function renderApplicationWorkbench() {
        const workbench = element('section', { className: 'flzrecruitment-workbench', 'aria-label': 'Bewerbungsarbeitsplatz' })
        const result = element('div')
        const query = input('applicationQuery', 'search', false, state.applicationFilters.query)
        query.placeholder = 'Name, Stelle oder Zuständigkeit'
        const job = select('applicationJob', [{ value: '', label: 'Alle Stellen' }].concat(
            state.data.jobs.map((item) => ({ value: item.id, label: item.internalTitle })),
        ), state.applicationFilters.jobId)
        const statuses = Array.from(new Set(state.data.applications.map((item) => item.status)))
            .sort((left, right) => statusLabel(left).localeCompare(statusLabel(right), 'de'))
        const applicationStatus = select('applicationStatus', [{ value: '', label: 'Alle Status' }].concat(
            statuses.map((value) => ({ value, label: statusLabel(value) })),
        ), state.applicationFilters.status)
        const applicationArea = select('applicationArea', [{ value: '', label: 'Alle Bürobereiche' }].concat(
            state.areas.map((area) => ({ value: area.key, label: area.label })),
        ), state.applicationFilters.areaKey)
        const assignees = Array.from(new Set(state.data.applications
            .map((application) => application.assigneeUid)
            .filter(Boolean)))
            .sort((left, right) => left.localeCompare(right, 'de'))
        const assigneeOptions = [{ value: '', label: 'Alle Zuständigkeiten' }]
        if (state.data.applications.some((application) => !application.assigneeUid)) {
            assigneeOptions.push({ value: '__unassigned__', label: 'Nicht zugeordnet' })
        }
        assigneeOptions.push(...assignees.map((uid) => ({ value: uid, label: uid })))
        const applicationAssignee = select(
            'applicationAssignee',
            assigneeOptions,
            state.applicationFilters.assigneeUid,
        )
        const receivedFrom = input('applicationReceivedFrom', 'date', false, state.applicationFilters.receivedFrom)
        const receivedTo = input('applicationReceivedTo', 'date', false, state.applicationFilters.receivedTo)
        const applicationSort = select('applicationSort', [
            { value: 'received_desc', label: 'Eingang: neueste zuerst' },
            { value: 'received_asc', label: 'Eingang: älteste zuerst' },
            { value: 'person_asc', label: 'Person: A–Z' },
            { value: 'job_asc', label: 'Stelle: A–Z' },
            { value: 'status_asc', label: 'Status: Prozessreihenfolge' },
        ], state.applicationFilters.sort)
        const moveArea = select('applicationMoveAreaKey', [{ value: '', label: 'Bürobereich wählen' }].concat(
            state.areas.map((area) => ({ value: area.key, label: area.label })),
        ), state.applicationMoveAreaKey)
        moveArea.addEventListener('change', () => { state.applicationMoveAreaKey = moveArea.value })
        const tableButton = button('Tabelle', 'button')
        const boardButton = button('Karten', 'button')
        const viewSwitch = element('div', {
            className: 'flzrecruitment-actions flzrecruitment-view-switch',
            role: 'group',
            'aria-label': 'Darstellung der Bewerbungen',
        }, [tableButton, boardButton])
        const updatePressed = () => {
            tableButton.setAttribute('aria-pressed', String(state.applicationView === 'table'))
            boardButton.setAttribute('aria-pressed', String(state.applicationView === 'board'))
        }
        const updateResult = () => {
            const applications = filterApplications(state.data, state.applicationFilters)
            result.replaceChildren(applications.length === 0
                ? emptyState(state.data.applications.length === 0 ? 'Noch keine Bewerbungen vorhanden.' : 'Keine Bewerbung entspricht den Filtern.')
                : state.applicationView === 'board'
                    ? renderApplicationBoard(applications)
                    : renderApplicationTable(applications))
            bindPersistentHorizontalScroll(result.querySelector('.flzrecruitment-board, .flzrecruitment-table-wrap'))
        }
        query.addEventListener('input', () => {
            state.applicationFilters.query = query.value
            updateResult()
        })
        job.addEventListener('change', () => {
            state.applicationFilters.jobId = job.value
            updateResult()
        })
        applicationStatus.addEventListener('change', () => {
            state.applicationFilters.status = applicationStatus.value
            updateResult()
        })
        applicationArea.addEventListener('change', () => {
            state.applicationFilters.areaKey = applicationArea.value
            updateResult()
        })
        applicationAssignee.addEventListener('change', () => {
            state.applicationFilters.assigneeUid = applicationAssignee.value
            updateResult()
        })
        receivedFrom.addEventListener('change', () => {
            state.applicationFilters.receivedFrom = receivedFrom.value
            updateResult()
        })
        receivedTo.addEventListener('change', () => {
            state.applicationFilters.receivedTo = receivedTo.value
            updateResult()
        })
        applicationSort.addEventListener('change', () => {
            state.applicationFilters.sort = applicationSort.value
            updateResult()
        })
        tableButton.addEventListener('click', () => {
            state.applicationView = 'table'
            updatePressed()
            updateResult()
        })
        boardButton.addEventListener('click', () => {
            state.applicationView = 'board'
            updatePressed()
            updateResult()
        })
        const toolbar = element('div', { className: 'flzrecruitment-workbench__toolbar' }, [
            field('Bewerbungen durchsuchen', query),
            field('Nach Stelle filtern', job),
            field('Nach Status filtern', applicationStatus),
            field('Nach Bürobereich filtern', applicationArea),
            field('Nach Zuständigkeit filtern', applicationAssignee),
            field('Eingang von', receivedFrom),
            field('Eingang bis', receivedTo),
            field('Sortierung', applicationSort),
            viewSwitch,
        ])
        if (state.capabilities.edit_applications) {
            toolbar.append(field('Bürobereich für Einstellungsfreigaben', moveArea))
        }
        updatePressed()
        updateResult()
        workbench.append(toolbar, result)
        return workbench
    }

    function renderApplicationTable(applications) {
        const tableWrap = element('div', { className: 'flzrecruitment-table-wrap' })
        const table = element('table')
        table.append(element('thead', {}, element('tr', {}, [
            element('th', { text: 'Person' }),
            element('th', { text: 'Stelle' }),
            element('th', { text: 'Eingang' }),
            element('th', { text: 'Kanal' }),
            element('th', { text: 'Wunschstunden' }),
            element('th', { text: 'Status' }),
            element('th', { text: 'Aktion' }),
        ])))
        const body = element('tbody')
        for (const application of applications) {
            const { person, job } = applicationReferences(application)
            const tableActions = element('div', { className: 'flzrecruitment-table-actions' }, [applicationOpenButton(application.id)])
            const tableStatusMove = renderApplicationStatusMove(application)
            if (tableStatusMove) tableActions.append(tableStatusMove)
            const withdrawal = renderWithdrawalAction(application)
            if (withdrawal) tableActions.append(withdrawal)
            body.append(element('tr', {}, [
                element('td', { text: person ? `${person.givenName} ${person.familyName}` : 'Unbekannt' }),
                element('td', { text: job?.internalTitle || 'Unbekannt' }),
                element('td', { text: application.receivedOn }),
                element('td', { text: sourceLabel(application.source) }),
                element('td', { text: desiredHoursLabel(application.desiredWeeklyHours, application.desiredWeeklyHoursMax) }),
                element('td', { text: application.basisQualification?.label || statusLabel(application.status) }),
                element('td', {}, tableActions),
            ]))
        }
        table.append(body)
        tableWrap.append(table)
        return tableWrap
    }

    function renderApplicationStatusMove(application) {
        const targets = statusTargets(
            application,
            state.data.applicationStatuses || [],
            state.capabilities.override_status_transitions === true,
        )
        if (!state.capabilities.edit_applications || !targets.length) return null
        const target = select('targetStatus', targets.map((value) => ({
            value,
            label: `${statusLabel(value)}${requiresStatusOverride(application, value) ? ' · Ausnahme' : ''}`,
        })), '', true)
        const move = button('Status verschieben', 'button', 'flzrecruitment-secondary')
        move.addEventListener('click', () => {
            const override = requiresStatusOverride(application, target.value)
            if (override && !confirm('Dieser Statuswechsel ist im Regelprozess nicht vorgesehen. Als Personalreferentin trotzdem durchführen?')) return
            moveApplication(application, target.value, override)
        })
        return element('div', { className: 'flzrecruitment-card-move' }, [
            field('Nächster Status', target),
            move,
        ])
    }

    function renderWithdrawalAction(application) {
        if (!state.capabilities.edit_applications || !application.allowedStatuses?.includes('withdrawn')) return null
        const withdraw = button('Bewerbung zurückziehen', 'button', 'flzrecruitment-danger')
        withdraw.addEventListener('click', () => {
            if (!confirm('Bewerbung wirklich zurückziehen?')) return
            moveApplication(application, 'withdrawn')
        })
        return withdraw
    }

    function renderApplicationBoard(applications) {
        const board = element('div', { className: 'flzrecruitment-board' })
        const groups = groupApplicationsByStatus(applications, state.data.applicationStatuses || [])
        for (const [statusId, items] of Object.entries(groups)) {
            const headingId = `flzrecruitment-board-${statusId}`
            const cards = element('div', { className: 'flzrecruitment-board__cards' })
            for (const application of items) {
                const { person, job } = applicationReferences(application)
                const card = element('article', { className: 'flzrecruitment-card flzrecruitment-application-card' }, [
                    element('h4', { text: person ? `${person.givenName} ${person.familyName}` : 'Unbekannt' }),
                    element('p', { text: job?.internalTitle || 'Unbekannte Stelle' }),
                    element('p', { text: `Eingang: ${application.receivedOn}` }),
                    application.desiredWeeklyHours !== null && application.desiredWeeklyHours !== undefined
                        ? element('p', { text: `Wunschstunden: ${desiredHoursLabel(application.desiredWeeklyHours, application.desiredWeeklyHoursMax)}` })
                        : null,
                    element('p', { text: sourceLabel(application.source) }),
                    application.assigneeUid ? element('p', { text: `Zuständig: ${application.assigneeUid}` }) : null,
                    applicationOpenButton(application.id),
                ])
                if (state.capabilities.edit_applications && application.allowedStatuses?.length) {
                    card.draggable = true
                    card.addEventListener('dragstart', (event) => {
                        event.dataTransfer?.setData('text/plain', String(application.id))
                        event.dataTransfer?.setDragImage?.(card, 16, 16)
                    })
                    card.append(renderApplicationStatusMove(application))
                    const withdrawal = renderWithdrawalAction(application)
                    if (withdrawal) card.append(withdrawal)
                }
                cards.append(card)
            }
            if (items.length === 0) cards.append(element('p', { className: 'flzrecruitment-board__empty', text: 'Keine Bewerbung' }))
            const column = element('section', {
                className: 'flzrecruitment-board__column',
                'aria-labelledby': headingId,
                dataset: { statusId },
            }, [
                element('h3', { id: headingId, text: `${statusLabel(statusId)} (${items.length})` }),
                cards,
            ])
            if (state.capabilities.edit_applications) {
                column.addEventListener('dragover', (event) => {
                    event.preventDefault()
                    column.classList.add('flzrecruitment-board__column--drop')
                })
                column.addEventListener('dragleave', () => column.classList.remove('flzrecruitment-board__column--drop'))
                column.addEventListener('drop', (event) => {
                    event.preventDefault()
                    column.classList.remove('flzrecruitment-board__column--drop')
                    const applicationId = Number(event.dataTransfer?.getData('text/plain'))
                    const application = state.data.applications.find((item) => item.id === applicationId)
                    moveApplication(application, statusId)
                })
            }
            board.append(column)
        }
        return board
    }

    function moveApplication(application, targetStatus, override = false) {
        if (!canMoveApplication(application, targetStatus)
            && !(override && state.capabilities.override_status_transitions === true)) {
            showError(new Error('Dieser Statuswechsel ist für die Bewerbung nicht zulässig.'))
            return
        }
        const areaKey = targetStatus === 'approved_for_hire' ? state.applicationMoveAreaKey : ''
        if (targetStatus === 'approved_for_hire' && !areaKey) {
            showError(new Error('Für die Einstellungsfreigabe muss im Kartenarbeitsplatz ein Bürobereich gewählt werden.'))
            return
        }
        run(
            () => api.transitionStatus(application.id, targetStatus, application.version, areaKey, override),
            'Status wurde geändert.',
        )
    }

    function applicationReferences(application) {
        return {
            person: state.data.people.find((item) => item.id === application.personId),
            job: state.data.jobs.find((item) => item.id === application.jobId),
        }
    }

    function applicationOpenButton(applicationId) {
        const open = button('Detail öffnen', 'button')
        open.addEventListener('click', () => openApplication(applicationId))
        return open
    }

    async function openApplication(id) {
        clearError()
        setBusy()
        try {
            const detail = await api.application(id)
            if (state.capabilities.view_hiring_data || state.capabilities.edit_hiring_data) {
                detail.hiringData = await api.hiringData(id)
            }
            if (state.capabilities.manage_basis_qualification) {
                detail.basisQualifications = await api.basisQualificationAssignments(id)
            }
            if (state.capabilities.communicate) {
                detail.mailDrafts = (await api.applicationMailDrafts(id)).drafts || []
            }
            detail.inboxMessages = (await api.applicationMessages(id)).messages || []
            renderApplicationDetail(detail)
            setReady()
        } catch (error) {
            showError(error)
        }
    }

    function renderApplicationDetail(detail) {
        const view = element('section', { className: 'flzrecruitment-panel' })
        const back = button('← Zurück zu Bewerbungen', 'button', 'flzrecruitment-secondary')
        back.addEventListener('click', () => showTab('applications'))
        view.append(back, element('h2', {
            text: `${detail.person.givenName} ${detail.person.familyName} · ${detail.job.internalTitle}`,
        }))
        view.append(element('dl', { className: 'flzrecruitment-facts' }, [
            element('div', {}, [element('dt', { text: 'Status' }), element('dd', { text: statusLabel(detail.application.status) })]),
            element('div', {}, [element('dt', { text: 'Eingang' }), element('dd', { text: detail.application.receivedOn })]),
            element('div', {}, [element('dt', { text: 'Kanal' }), element('dd', { text: sourceLabel(detail.application.source) })]),
            element('div', {}, [element('dt', { text: 'E-Mail' }), element('dd', {}, contactNode(detail.person.email, 'email'))]),
            element('div', {}, [element('dt', { text: 'Telefon' }), element('dd', {}, contactNode(detail.person.phone, 'phone'))]),
            element('div', {}, [element('dt', { text: 'Zuständigkeit' }), element('dd', { text: detail.application.assigneeUid || 'Nicht zugeordnet' })]),
            element('div', {}, [
                element('dt', { text: 'Gewünschte Wochenstunden' }),
                element('dd', { text: desiredHoursLabel(detail.application.desiredWeeklyHours, detail.application.desiredWeeklyHoursMax) }),
            ]),
            element('div', {}, [element('dt', { text: 'Vorerfahrung' }), element('dd', { text: detail.application.previousExperience || 'Nicht erfasst' })]),
            element('div', {}, [element('dt', { text: 'Deutschniveau' }), element('dd', { text: ({ native: 'Muttersprachlich', not_assessed: 'Nicht bewertet' })[detail.application.germanLanguageLevel] || detail.application.germanLanguageLevel || 'Nicht erfasst' })]),
            element('div', {}, [element('dt', { text: 'Freier Kommentar' }), element('dd', { text: detail.application.freeComment || 'Nicht erfasst' })]),
        ]))

        const detailStatusTargets = statusTargets(
            detail.application,
            state.data.applicationStatuses || [],
            state.capabilities.override_status_transitions === true,
        )
        if (state.capabilities.edit_applications && detailStatusTargets.length) {
            const statusSelect = select('status', detailStatusTargets.map((value) => ({
                    value,
                    label: `${statusLabel(value)}${requiresStatusOverride(detail.application, value) ? ' · Ausnahme' : ''}`,
                })))
            const areaSelect = select('areaKey', [{ value: '', label: 'Bürobereich wählen' }].concat(
                state.areas.map((area) => ({ value: area.key, label: area.label })),
            ))
            const areaField = field('Bürobereich bei Einstellungsfreigabe', areaSelect)
            areaField.hidden = statusSelect.value !== 'approved_for_hire'
            areaSelect.required = !areaField.hidden
            statusSelect.addEventListener('change', () => {
                areaField.hidden = statusSelect.value !== 'approved_for_hire'
                areaSelect.required = !areaField.hidden
            })
            const statusForm = element('form', { className: 'flzrecruitment-inline-form flzrecruitment-card' }, [
                field('Neuer Status', statusSelect),
                areaField,
                button('Status ändern'),
            ])
            statusForm.addEventListener('submit', (event) => {
                event.preventDefault()
                const formData = new FormData(statusForm)
                const target = formData.get('status')
                const override = requiresStatusOverride(detail.application, target)
                if (override && !confirm('Dieser Statuswechsel ist im Regelprozess nicht vorgesehen. Als Personalreferentin trotzdem durchführen?')) return
                run(
                    () => api.transitionStatus(detail.application.id, target, detail.application.version, formData.get('areaKey') || '', override),
                    'Status wurde geändert.',
                    () => openApplication(detail.application.id),
                )
            })
            view.append(statusForm)
        }

        if (detail.hiringData) view.append(renderHiringData(detail))

        const mailCard = element('section', { className: 'flzrecruitment-card' }, [
            element('h3', { text: 'Eingegangene Bewerbungs-Mails' }),
        ])
        if (!detail.inboxMessages.length) {
            mailCard.append(emptyState('Dieser Bewerbung ist noch keine Eingangsnachricht zugeordnet.'))
        } else {
            for (const message of detail.inboxMessages) {
                mailCard.append(element('article', { className: 'flzrecruitment-question-editor' }, [
                    element('h4', { text: message.subject || 'Ohne Betreff' }),
                    element('p', {}, [contactNode(message.senderAddress, 'email'), element('span', { text: ` · ${message.receivedAt}` })]),
                    element('pre', { className: 'flzrecruitment-mail-body', text: message.bodyText }),
                    renderMailAttachments(message.attachments, detail.application.id, () => openApplication(detail.application.id)),
                ]))
            }
        }
        view.append(mailCard)

        if (state.capabilities.communicate) view.append(renderOutgoingMailDrafts(detail))

        if (state.capabilities.manage_basis_qualification && detail.job.basisQualificationRequired) {
            view.append(renderApplicationBasisQualification(detail))
        }

        if (state.capabilities.manage_first_guide_access
            && ['approved_for_hire', 'hired'].includes(detail.application.status)) {
            const enabled = detail.application.firstGuideAccess === true
            const guideCard = element('section', { className: 'flzrecruitment-card' }, [
                element('h3', { text: 'Erstbegleitung' }),
                element('p', { text: enabled
                    ? 'Der lesende Aktenzugriff für passende Erstbegleitungen ist aktiv.'
                    : 'Der Aktenzugriff für Erstbegleitungen ist beendet.' }),
            ])
            const toggle = button(enabled ? 'Zugriff beenden' : 'Zugriff wieder freigeben', 'button')
            toggle.addEventListener('click', () => run(
                () => api.setFirstGuideAccess(detail.application.id, !enabled, detail.application.version),
                'Erstbegleitungszugriff wurde geändert.',
                () => openApplication(detail.application.id),
            ))
            guideCard.append(toggle)
            view.append(guideCard)
        }

        if (state.capabilities.interview && state.data.templates.some((template) => template.active)) {
            const interviewForm = element('form', { className: 'flzrecruitment-inline-form flzrecruitment-card' }, [
                field('Interviewvorlage', select('templateId', state.data.templates
                    .filter((template) => template.active)
                    .map((template) => ({ value: template.id, label: `${template.name} (Revision ${template.revision})` })))),
                button('Interview erzeugen'),
            ])
            interviewForm.addEventListener('submit', (event) => {
                event.preventDefault()
                const templateId = Number(new FormData(interviewForm).get('templateId'))
                run(
                    () => api.createInterview(detail.application.id, templateId),
                    'Interview wurde erzeugt.',
                    () => openApplication(detail.application.id),
                )
            })
            view.append(interviewForm)
        }

        view.append(element('h3', { text: 'Interviews' }))
        if (detail.interviews.length === 0) {
            view.append(emptyState('Noch kein Interview für diese Bewerbung vorhanden.'))
        } else {
            for (const interview of detail.interviews) {
                view.append(renderInterview(interview, detail.application.id))
            }
        }

        view.append(element('h3', { text: 'Statusverlauf' }))
        if (detail.statusHistory.length === 0) {
            view.append(emptyState('Noch keine Statusänderung protokolliert.'))
        } else {
            const list = element('ol', { className: 'flzrecruitment-history' })
            for (const item of detail.statusHistory) {
                list.append(element('li', {
                    text: `${statusLabel(item.fromStatus)} → ${statusLabel(item.toStatus)} · ${item.changedAt} · ${item.actorUid}`,
                }))
            }
            view.append(list)
        }
        content.replaceChildren(view)
    }

    function renderInterview(interview, applicationId) {
        const article = element('article', { className: 'flzrecruitment-card flzrecruitment-interview' }, [
            element('h4', { text: `${interview.snapshot.name} · Revision ${interview.templateRevision}` }),
            element('p', { text: `Status: ${statusLabel(interview.status)}` }),
        ])
        const form = element('form', { className: 'flzrecruitment-form' })
        for (const question of interview.snapshot.questions || []) {
            if (!question.active) continue
            form.append(renderAnswer(question, interview.answers[String(question.id)], interview.status === 'completed'))
        }
        if (interview.status !== 'completed' && state.capabilities.interview) {
            const actions = element('div', { className: 'flzrecruitment-actions' }, [
                button('Entwurf speichern', 'button', 'flzrecruitment-secondary'),
                button('Interview abschließen', 'submit'),
            ])
            actions.firstElementChild.addEventListener('click', () => {
                run(
                    () => api.saveDraft(interview.id, collectAnswers(form), interview.version),
                    'Entwurf wurde gespeichert.',
                    () => openApplication(applicationId),
                )
            })
            form.addEventListener('submit', (event) => {
                event.preventDefault()
                run(
                    () => api.completeInterview(interview.id, collectAnswers(form), interview.version),
                    'Interview wurde abgeschlossen.',
                    () => openApplication(applicationId),
                )
            })
            form.append(actions)
        } else if (interview.status === 'completed') {
            form.querySelectorAll('input, textarea, select').forEach((control) => { control.disabled = true })
        }
        article.append(form)
        return article
    }

    function renderAnswer(question, answer, readonly) {
        const wrapper = element('fieldset', { className: 'flzrecruitment-question' })
        const legend = element('legend', { text: question.prompt + (question.required ? ' *' : '') })
        wrapper.append(legend)
        if (question.hint) wrapper.append(element('small', { text: question.hint }))
        let control
        if (question.type === 'textarea') {
            control = element('textarea', { rows: 4, required: question.required, value: answer || '' })
            control.textContent = answer || ''
        } else if (question.type === 'boolean') {
            control = select('', [
                { value: '', label: 'Bitte wählen' },
                { value: 'yes', label: 'Ja' },
                { value: 'no', label: 'Nein' },
            ], answer || '', question.required)
        } else if (question.type === 'single_choice') {
            control = select('', [{ value: '', label: 'Bitte wählen' }].concat(
                question.options.map((option) => ({ value: option, label: option })),
            ), answer || '', question.required)
        } else if (question.type === 'multiple_choice') {
            control = select('', question.options.map((option) => ({ value: option, label: option })))
            control.multiple = true
            for (const option of control.options) option.selected = Array.isArray(answer) && answer.includes(option.value)
        } else if (question.type === 'rating') {
            control = input('', 'number', question.required, answer || '')
            control.min = '1'
            control.max = '10'
        } else {
            control = input('', 'text', question.required, answer || '')
        }
        control.dataset.answerId = String(question.id)
        control.disabled = readonly
        wrapper.append(control)

        if (!readonly && ['text', 'textarea'].includes(question.type) && question.bubbles?.length) {
            const bubbles = element('div', { className: 'flzrecruitment-bubbles', role: 'group', 'aria-label': 'Antwortbausteine' })
            for (const bubble of question.bubbles.filter((item) => item.active)) {
                const bubbleButton = button(bubble.label, 'button', 'flzrecruitment-bubble')
                bubbleButton.addEventListener('click', () => {
                    control.value = appendBubbleText(control.value, bubble.insertText)
                    control.focus()
                })
                bubbles.append(bubbleButton)
            }
            wrapper.append(bubbles)
        }
        return wrapper
    }

    function collectAnswers(form) {
        const answers = {}
        for (const control of form.querySelectorAll('[data-answer-id]')) {
            answers[control.dataset.answerId] = control.multiple
                ? Array.from(control.selectedOptions, (option) => option.value)
                : control.value
        }
        return answers
    }

    function renderOutgoingMailDrafts(detail) {
        const card = element('section', { className: 'flzrecruitment-card' }, [
            element('h3', { text: 'Ausgehende E-Mails' }),
            element('p', { text: 'Die Empfängeradresse stammt zunächst aus dem Personendatensatz. Eine Korrektur vor der Freigabe wird getrennt vom ursprünglichen Wert dokumentiert.' }),
        ])
        const drafts = detail.mailDrafts || []
        if (!drafts.length) {
            card.append(emptyState('Für diese Bewerbung gibt es noch keinen Mailentwurf.'))
            return card
        }
        for (const draft of drafts) {
            const article = element('article', { className: 'flzrecruitment-question-editor' }, [
                element('h4', { text: `${statusLabel(draft.fromStatus)} → ${statusLabel(draft.toStatus)}` }),
                element('p', { text: `Ursprüngliche Adresse: ${draft.originalRecipient} · Zustand: ${draft.status}` }),
            ])
            if (draft.testMode) article.append(element('p', { className: 'flzrecruitment-warning', text: `Testmodus: tatsächliche Zustellung an ${draft.deliveryRecipient}` }))
            if (draft.status !== 'draft') {
                article.append(element('p', { text: `Freigegebene Zieladresse: ${draft.intendedRecipient || draft.originalRecipient}` }))
                article.append(element('p', { text: draft.scheduledAt ? `Geplant für ${draft.scheduledAt}` : 'Nicht zum Versand geplant.' }))
                article.append(element('strong', { text: draft.subject }), element('div', {
                    className: 'flzrecruitment-mail-body', innerHTML: mailBodyHtml(draft.body, draft.bodyFormat),
                }))
                card.append(article)
                continue
            }
            const subject = input('subject', 'text', true, draft.subject)
            const recipient = input('recipient', 'email', true, draft.originalRecipient)
            if (state.mailConfiguration?.settings?.testMode) {
                article.append(element('p', { className: 'flzrecruitment-warning', text: `Aktiver Testmodus: Nach Freigabe erfolgt die tatsächliche Zustellung an ${state.mailConfiguration.settings.testRecipient}.` }))
            }
            const body = mailEditor(draft.body, draft.bodyFormat)
            const form = element('form', { className: 'flzrecruitment-form' }, [
                field('Empfängeradresse', recipient), field('Betreff', subject), richTextField('Nachricht', body),
            ])
            const blocks = (state.mailConfiguration?.textBlocks || []).filter((block) => block.active)
            if (blocks.length) {
                const blockSelect = select('textBlock', [{ value: '', label: 'Textblock wählen' }].concat(blocks.map((block) => ({ value: block.id, label: block.label }))))
                const insertBlock = button('Textblock an Cursorposition einfügen', 'button', 'flzrecruitment-secondary')
                insertBlock.addEventListener('click', () => {
                    const block = blocks.find((item) => String(item.id) === String(blockSelect.value))
                    if (!block) return
                    body.insertText(block.insertText)
                })
                form.append(field('Vorbereiteter Textblock', blockSelect), insertBlock)
            }
            const scheduled = input('scheduledAt', 'datetime-local')
            form.append(field('Freier Versandzeitpunkt', scheduled))
            const actions = element('div', { className: 'flzrecruitment-actions' })
            const save = button('Entwurf speichern', 'button', 'flzrecruitment-secondary')
            save.addEventListener('click', () => run(
                () => api.saveMailDraft(draft.id, { subject: subject.value, body: body.value(), bodyFormat: 'html', version: draft.version }),
                'Mailentwurf wurde gespeichert.', () => openApplication(detail.application.id),
            ))
            const approve = (label, timing) => {
                const control = button(label + (draft.defaultTiming === timing ? ' (Voreinstellung)' : ''), 'button')
                control.addEventListener('click', () => {
                    const value = timing === 'scheduled' && scheduled.value ? new Date(scheduled.value).toISOString() : null
                    run(
                        () => api.approveMailDraft(draft.id, { recipient: recipient.value, subject: subject.value, body: body.value(), bodyFormat: 'html', timing, scheduledAt: value, version: draft.version }),
                        'Mail wurde verbindlich zum Versand eingeplant.', () => openApplication(detail.application.id),
                    )
                })
                return control
            }
            const cancel = button('Entwurf abbrechen', 'button', 'flzrecruitment-secondary')
            cancel.addEventListener('click', () => run(
                () => api.cancelMailDraft(draft.id, draft.version), 'Mailentwurf wurde abgebrochen.', () => openApplication(detail.application.id),
            ))
            actions.append(save, approve('Jetzt freigeben', 'immediate'), approve('Kommenden Montag freigeben', 'next_monday'), approve('Zum gewählten Zeitpunkt freigeben', 'scheduled'), cancel)
            form.append(actions); article.append(form); card.append(article)
        }
        return card
    }

    function renderMailTemplates(target = content, embedded = false) {
        const view = embedded
            ? settingsPanel('mail-templates', 'Mailvorlagen und Versandregeln')
            : panel('mail-templates', 'Mailvorlagen und Versandregeln')
        const configuration = state.mailConfiguration || { templates: [], rules: [], textBlocks: [], settings: {} }
        view.append(element('p', {
            className: 'flzrecruitment-callout',
            text: `${configuration.rules.length} erlaubte Übergänge sind mit bearbeitbaren Vorlagen vorbereitet. Diese Regel ist zunächst ausgeschaltet. Aktivieren Sie nur die gewünschten Entwürfe.`,
        }))
        const timingOptions = [
            { value: 'immediate', label: 'Sofort nach Freigabe' },
            { value: 'scheduled', label: 'Zeitpunkt im Entwurf wählen' },
            { value: 'next_monday', label: 'Kommenden Montag, 09:00 Uhr' },
        ]
        const renderTemplateEditor = (template) => {
            const name = input('name', 'text', true, template.name)
            const subject = input('subject', 'text', true, template.subject)
            const body = mailEditor(template.body, template.bodyFormat)
            const active = input('active', 'checkbox'); active.checked = template.active
            const form = element('form', { className: 'flzrecruitment-form flzrecruitment-mail-template-editor' }, [
                field('Vorlagenname', name), field('Betreff', subject),
                richTextField('Nachricht', body, 'Formatierung und Platzhalter können bis zum Versand erneut geändert werden.'),
                element('p', { text: 'Platzhalter: {{given_name}}, {{family_name}}, {{job_title}}' }),
                field('Vorlage aktiv', active), button('Vorlage als neue Revision speichern'),
            ])
            form.addEventListener('submit', (event) => {
                event.preventDefault()
                run(() => api.reviseMailTemplate(template.id, {
                    name: name.value, subject: subject.value, body: body.value(), bodyFormat: 'html',
                    active: active.checked, version: template.version,
                }), 'Neue Mailvorlagenrevision wurde gespeichert.')
            })
            return form
        }

        for (const fromStatus of state.data.applicationStatuses) {
            const rules = configuration.rules.filter((rule) => rule.fromStatus === fromStatus)
            if (!rules.length) continue
            const section = element('section', { className: 'flzrecruitment-mail-transition-group' }, [
                element('h2', { text: statusLabel(fromStatus) }),
            ])
            for (const rule of rules) {
                const toStatus = rule.toStatus
                const template = configuration.templates.find((item) => item.id === rule.templateId)
                const enabled = input(`enabled-${rule.id}`, 'checkbox'); enabled.checked = rule.enabled
                const timing = select(`timing-${rule.id}`, timingOptions, rule.defaultTiming)
                const templateChoice = select(`template-${rule.id}`, configuration.templates.filter((item) => item.active || item.id === rule.templateId).map((item) => ({ value: item.id, label: item.name })), rule.templateId, true)
                const settings = element('form', { className: 'flzrecruitment-mail-transition-settings' }, [
                    field('Entwurf erzeugen', enabled), field('Mailvorlage', templateChoice), field('Standardplanung', timing), button('Einstellung speichern'),
                ])
                settings.addEventListener('submit', (event) => {
                    event.preventDefault()
                    run(() => api.saveStatusMailRule({
                        fromStatus, toStatus, templateId: Number(templateChoice.value), enabled: enabled.checked,
                        defaultTiming: timing.value, version: rule.version,
                    }), 'Statusmail-Regel wurde gespeichert.')
                })
                const article = element('article', { className: 'flzrecruitment-card flzrecruitment-mail-transition' }, [
                    element('div', { className: 'flzrecruitment-mail-transition__heading' }, [
                        element('h3', { text: `${statusLabel(fromStatus)} → ${statusLabel(toStatus)}` }),
                        element('span', {
                            className: rule.enabled ? 'flzrecruitment-state flzrecruitment-state--active' : 'flzrecruitment-state',
                            text: rule.enabled ? 'Aktiv' : 'Aus',
                        }),
                    ]),
                    settings,
                ])
                const details = element('details', {}, [element('summary', { text: 'Vorlage bearbeiten' })])
                details.append(template ? renderTemplateEditor(template) : element('p', { className: 'flzrecruitment-warning', text: 'Die zugeordnete Vorlage fehlt.' }))
                article.append(details)
                section.append(article)
            }
            view.append(section)
        }

        const assignedTemplateIds = new Set(configuration.rules.map((rule) => rule.templateId))
        const unassignedTemplates = configuration.templates.filter((template) => !assignedTemplateIds.has(template.id))
        if (unassignedTemplates.length) {
            const additional = element('details', { className: 'flzrecruitment-card' }, [
                element('summary', { text: 'Bisher nicht zugeordnete Vorlagen' }),
                element('p', { text: 'Diese älteren Vorlagen bleiben bearbeitbar, lösen aber aktuell bei keinem Statusübergang einen Entwurf aus.' }),
                ...unassignedTemplates.map(renderTemplateEditor),
            ])
            view.append(additional)
        }
        const blockForm = element('form', { className: 'flzrecruitment-form flzrecruitment-card' }, [
            element('h3', { text: 'Textblock' }), field('Bezeichnung', input('label', 'text', true)),
            field('Einfügetext', element('textarea', { name: 'insertText', rows: 4, required: true })), button('Textblock anlegen'),
        ])
        blockForm.addEventListener('submit', (event) => { event.preventDefault(); run(
            () => api.createMailTextBlock(Object.fromEntries(new FormData(blockForm))), 'Textblock wurde angelegt.',
        ) })
        view.append(blockForm)
        target.replaceChildren(view)
    }

    function renderTemplates(target = content, embedded = false) {
        const view = embedded
            ? settingsPanel('templates', 'Interviewfragen und Vorlagen')
            : panel('templates', 'Interviewvorlagen')
        if (state.capabilities.manage_catalog) {
            const form = element('form', { className: 'flzrecruitment-form flzrecruitment-card' }, [
                field('Name', input('name', 'text', true)),
                field('Interviewtyp', select('type', [
                    { value: 'questionnaire', label: 'Kurzfragebogen' },
                    { value: 'phone', label: 'Telefoninterview' },
                    { value: 'live', label: 'Liveinterview' },
                    { value: 'other', label: 'Weiterer Typ' },
                ])),
                field('Zielgruppe', select('audience', [
                    { value: 'interviewer', label: 'Interviewer*in' },
                    { value: 'candidate', label: 'Bewerber*in' },
                ])),
                field('Beschreibung', element('textarea', { name: 'description', rows: 3 })),
                button('Vorlage anlegen'),
            ])
            form.addEventListener('submit', (event) => {
                event.preventDefault()
                run(() => api.createTemplate(Object.fromEntries(new FormData(form))), 'Vorlage wurde angelegt.')
            })
            view.append(createFormOverlay('Neue Interviewvorlage', 'Vorlage neu', form))
        }

        if (state.data.templates.length === 0) {
            view.append(emptyState('Noch keine Interviewvorlagen vorhanden.'))
        } else {
            const list = element('div', { className: 'flzrecruitment-grid' })
            for (const template of state.data.templates) {
                const open = button('Vorlage bearbeiten', 'button')
                open.addEventListener('click', () => openTemplate(template.id))
                list.append(element('article', { className: 'flzrecruitment-card' }, [
                    element('h3', { text: template.name }),
                    element('p', { text: `${template.type} · Revision ${template.revision}` }),
                    element('p', { text: template.active ? 'Aktiv' : 'Deaktiviert' }),
                    open,
                ]))
            }
            view.append(list)
        }
        target.replaceChildren(view)
    }

    async function openTemplate(id) {
        clearError()
        setBusy()
        try {
            renderTemplateDetail(await api.template(id))
            setReady()
        } catch (error) {
            showError(error)
        }
    }

    function renderTemplateDetail(template) {
        const view = panel('settings', `${template.name} · Revision ${template.revision}`)
        const back = button('← Zurück zu Vorlagen', 'button', 'flzrecruitment-secondary')
        back.addEventListener('click', () => showSettingsSection('templates', true))
        view.prepend(back)

        if (state.capabilities.manage_catalog) {
            const form = questionForm()
            form.addEventListener('submit', (event) => {
                event.preventDefault()
                run(
                    () => api.createQuestion(template.id, questionPayload(form)),
                    'Frage wurde hinzugefügt.',
                    () => openTemplate(template.id),
                )
            })
            view.append(createFormOverlay('Neue Interviewfrage', 'Frage neu', form))
        }

        if (template.questions.length === 0) {
            view.append(emptyState('Noch keine Fragen vorhanden.'))
        } else {
            for (const question of template.questions) {
                view.append(renderQuestionEditor(question, template.id))
            }
        }
        content.replaceChildren(view)
    }

    function questionForm(question = null) {
        const form = element('form', { className: 'flzrecruitment-form flzrecruitment-card' }, [
            field('Fragetext', input('prompt', 'text', true, question?.prompt || '')),
            field('Hinweis', input('hint', 'text', false, question?.hint || '')),
            field('Fragetyp', select('type', [
                { value: 'text', label: 'Einzeiliger Freitext' },
                { value: 'textarea', label: 'Mehrzeiliger Freitext' },
                { value: 'boolean', label: 'Ja/Nein' },
                { value: 'single_choice', label: 'Einfachauswahl' },
                { value: 'multiple_choice', label: 'Mehrfachauswahl' },
                { value: 'rating', label: 'Bewertung' },
            ], question?.type || 'text')),
            field('Optionen', input('options', 'text', false, question?.options?.join(', ') || ''), 'Für Auswahlfragen kommagetrennt'),
            field('Reihenfolge', input('sortOrder', 'number', true, question?.sortOrder ?? 10)),
            field('Sichtbarkeit', select('visibility', [
                { value: 'internal', label: 'Intern' },
                { value: 'external', label: 'Extern' },
            ], question?.visibility || 'internal')),
        ])
        const checks = element('div', { className: 'flzrecruitment-checks' })
        const required = input('required', 'checkbox')
        required.checked = question?.required || false
        checks.append(field('Pflichtfrage', required))
        if (question) {
            const active = input('active', 'checkbox')
            active.checked = question.active
            checks.append(field('Aktiv', active))
        }
        form.append(checks, button(question ? 'Frage speichern' : 'Frage hinzufügen'))
        return form
    }

    function questionPayload(form) {
        const data = new FormData(form)
        return {
            prompt: data.get('prompt'),
            hint: data.get('hint'),
            type: data.get('type'),
            required: data.has('required'),
            sortOrder: Number(data.get('sortOrder')),
            options: csvList(data.get('options')),
            visibility: data.get('visibility'),
            active: data.has('active'),
        }
    }

    function renderQuestionEditor(question, templateId) {
        const article = element('article', { className: 'flzrecruitment-question-editor' })
        const form = questionForm(question)
        form.addEventListener('submit', (event) => {
            event.preventDefault()
            run(
                () => api.updateQuestion(question.id, questionPayload(form)),
                'Frage wurde gespeichert.',
                () => openTemplate(templateId),
            )
        })
        article.append(form)

        const bubbles = element('div', { className: 'flzrecruitment-card' }, [
            element('h4', { text: 'Antwort-Bubbles' }),
        ])
        if (!['text', 'textarea'].includes(question.type)) {
            bubbles.append(element('p', { text: 'Für diesen Fragetyp sind keine Antwort-Bubbles möglich.' }))
        } else {
            if (question.bubbles.length) {
                const list = element('ul')
                for (const bubble of question.bubbles) {
                    list.append(element('li', { text: `${bubble.label}: ${bubble.insertText}` }))
                }
                bubbles.append(list)
            }
            const bubbleForm = element('form', { className: 'flzrecruitment-form' }, [
                field('Beschriftung', input('label', 'text', true)),
                field('Einfügetext', element('textarea', { name: 'insertText', rows: 3, required: true })),
                field('Reihenfolge', input('sortOrder', 'number', true, 10)),
                button('Bubble hinzufügen'),
            ])
            bubbleForm.addEventListener('submit', (event) => {
                event.preventDefault()
                const data = Object.fromEntries(new FormData(bubbleForm))
                data.sortOrder = Number(data.sortOrder)
                data.active = true
                run(
                    () => api.createBubble(question.id, data),
                    'Antwort-Bubble wurde hinzugefügt.',
                    () => openTemplate(templateId),
                )
            })
            bubbles.append(createFormOverlay('Neuer Antwortbaustein', 'Antwortbaustein neu', bubbleForm))
        }
        article.append(bubbles)
        return article
    }

    function renderHiringData(detail) {
        const card = element('section', { className: 'flzrecruitment-card' }, [
            element('h3', { text: 'Vertragsbereich' }),
            element('p', { text: 'Bank-, Steuer-, Sozialversicherungs- und Vertragsangaben werden ausschließlich hier für die Vertragsvorbereitung verarbeitet.' }),
            element('p', { text: 'Tarifgrundlage: Haustarifvertrag ambulante dienste e.V., bereitgestellte Fassung mit Änderungen 2024. Beträge werden nicht automatisch fortgeschrieben.' }),
        ])
        const stored = detail.hiringData
        const facts = hiringFacts(stored.data)
        card.append(facts)
        if (!state.capabilities.edit_hiring_data) return card
        const edit = button('Vertragsdaten bearbeiten', 'button', 'flzrecruitment-secondary')
        card.append(edit)
        edit.addEventListener('click', () => {
            edit.remove()
            facts.remove()
            card.append(hiringEditor(detail, stored))
        })
        return card
    }

    function hiringEditor(detail, stored) {
        const form = element('form', { className: 'flzrecruitment-form flzrecruitment-sensitive-form flzrecruitment-compact-form' })
        const fields = element('div', { className: 'flzrecruitment-grid' })
        for (const [name, label, type = 'text'] of hiringFields) {
            const availableChoices = name === 'workingTimeModel' && detail.job.professionCategory !== 'assistance'
                ? hiringChoices[name]?.filter((item) => item.value !== 'kapovaz')
                : hiringChoices[name]
            const control = hiringControl(name, type, stored.data[name], availableChoices)
            if (type === 'number') control.step = '0.01'
            fields.append(field(label, control))
        }
        form.append(fields, button('Einstellungsstammdaten speichern'))
        form.addEventListener('submit', (event) => {
            event.preventDefault()
            const values = Object.fromEntries(new FormData(form))
            for (const [name, , type] of hiringFields) {
                if (type === 'number') values[name] = values[name] === '' ? null : Number(values[name])
            }
            run(
                () => api.saveHiringData(detail.application.id, values, stored.version),
                'Einstellungsstammdaten wurden gespeichert.',
                () => openApplication(detail.application.id),
            )
        })
        return form
    }

    function hiringFacts(data) {
        const facts = element('dl', { className: 'flzrecruitment-facts' })
        for (const [name, label] of [...hiringFields, ...payrollOnlyFields, ...jobDerivedHiringFields]) {
            const value = data[name]
            if (value === '' || value === null || value === undefined) continue
            const visibleValue = hiringChoices[name]?.find((item) => item.value === value)?.label || String(value)
            const displayed = name === 'privateEmail'
                ? contactNode(visibleValue, 'email')
                : name === 'privatePhone'
                    ? contactNode(visibleValue, 'phone')
                    : element('span', { text: visibleValue })
            facts.append(element('div', {}, [element('dt', { text: label }), element('dd', {}, displayed)]))
        }
        if (!facts.childElementCount) return emptyState('Noch keine Einstellungsstammdaten erfasst.')
        return facts
    }

    function renderPayroll() {
        const view = panel('payroll', 'Vertragsvorbereitung')
        view.append(element('p', {
            text: 'Lohn sieht ausschließlich Vertragsstammdaten: bei Assistenz ab BQ-Zuordnung, ansonsten ab Einstellungsfreigabe.',
        }))
        if (!state.hiringData.length) {
            view.append(emptyState('Aktuell liegen keine freigegebenen Einstellungsdaten vor.'))
        } else {
            for (const item of state.hiringData) {
                const card = element('article', { className: 'flzrecruitment-card flzrecruitment-hiring-record' }, [
                    element('h3', { text: `${item.givenName} ${item.familyName}` }),
                    element('p', { text: `${item.position || 'Ohne Stellenbezeichnung'} · ${statusLabel(item.status)}` }),
                    element('p', {}, contactLine(item.email, item.phone)),
                ])
                const payrollFacts = hiringFacts(item.hiringData)
                card.append(payrollFacts)
                if (state.capabilities.edit_payroll_data) {
                    const edit = button('LoBu-Daten bearbeiten', 'button', 'flzrecruitment-secondary')
                    card.append(edit)
                    edit.addEventListener('click', () => {
                        edit.remove()
                        payrollFacts.remove()
                        const form = element('form', { className: 'flzrecruitment-form flzrecruitment-sensitive-form flzrecruitment-compact-form' })
                        const fields = element('div', { className: 'flzrecruitment-grid' })
                        for (const [name, label, type = 'text'] of payrollOnlyFields) {
                            fields.append(field(label, hiringControl(name, type, item.hiringData[name], hiringChoices[name])))
                        }
                        form.append(fields, button('LoBu-Daten speichern'))
                        form.addEventListener('submit', (event) => { event.preventDefault(); run(
                            () => api.savePayrollData(item.applicationId, Object.fromEntries(new FormData(form)), item.hiringDataVersion),
                            'LoBu-Stammdaten wurden gespeichert.', load,
                        ) })
                        card.append(form)
                    })
                }
                view.append(card)
            }
        }
        content.replaceChildren(view)
    }

    function renderBasisQualifications() {
        const view = panel('basis-qualifications', 'Basisqualifikationen')
        view.append(element('p', {
            text: 'BQ-Durchläufe gelten ausschließlich für Assistenz-Stellen. Diese lokale Verwaltung bleibt eine Übergangslösung, bis die eigenständige BQ-Planer-App angebunden ist; Bewertungen verwalten vorerst nur Personalreferent*innen.',
        }))
        const form = element('form', { className: 'flzrecruitment-inline-form flzrecruitment-card' }, [
            field('Beginn', input('startsOn', 'date', true)),
            field('Ende', input('endsOn', 'date', true)),
            button('BQ-Durchlauf anlegen'),
        ])
        form.addEventListener('submit', (event) => {
            event.preventDefault()
            run(
                () => api.createBasisQualificationRun(Object.fromEntries(new FormData(form))),
                'BQ-Durchlauf wurde angelegt.',
            )
        })
        view.append(createFormOverlay('Neuer BQ-Durchlauf', 'BQ-Durchlauf neu', form))

        if (!state.basisQualificationRuns.length) {
            view.append(emptyState('Noch kein BQ-Durchlauf angelegt.'))
        } else {
            const list = element('div', { className: 'flzrecruitment-grid' })
            for (const run of state.basisQualificationRuns) {
                list.append(element('article', { className: 'flzrecruitment-card' }, [
                    element('h3', { text: run.label }),
                    element('p', { text: `${run.startsOn} bis ${run.endsOn}` }),
                ]))
            }
            view.append(list)
        }
        content.replaceChildren(view)
    }

    function renderApplicationBasisQualification(detail) {
        const card = element('section', { className: 'flzrecruitment-card' }, [
            element('h3', { text: 'Basisqualifikation' }),
        ])
        const assignments = detail.basisQualifications || []
        if (detail.application.status === 'decision_pending' && state.basisQualificationRuns.length) {
            const form = element('form', { className: 'flzrecruitment-inline-form' }, [
                field('BQ-Durchlauf', select('runId', state.basisQualificationRuns.map((run) => ({
                    value: run.id,
                    label: `${run.label} · ${run.startsOn} bis ${run.endsOn}`,
                })))),
                button('BQ zuordnen'),
            ])
            form.addEventListener('submit', (event) => {
                event.preventDefault()
                run(
                    () => api.assignBasisQualification(
                        detail.application.id,
                        Number(new FormData(form).get('runId')),
                        detail.application.version,
                    ),
                    'Bewerbung wurde der BQ zugeordnet.',
                    () => openApplication(detail.application.id),
                )
            })
            card.append(form)
        }

        if (!assignments.length) {
            card.append(emptyState('Noch keine BQ-Zuordnung vorhanden.'))
            return card
        }
        const resultLabels = {
            pending: 'Bewertung ausstehend',
            suitable: 'Geeignet',
            not_suitable: 'Nicht geeignet',
            cancelled: 'Abgebrochen',
            no_show: 'Nicht teilgenommen',
        }
        for (const assignment of assignments) {
            const article = element('article', { className: 'flzrecruitment-question-editor' }, [
                element('h4', { text: `${assignment.label} · ${resultLabels[assignment.result] || assignment.result}` }),
                element('p', { text: `${assignment.startsOn} bis ${assignment.endsOn}` }),
            ])
            if (assignment.evaluationNote) article.append(element('p', { text: assignment.evaluationNote }))
            const resultForm = element('form', { className: 'flzrecruitment-form' }, [
                field('Einfaches Ergebnis', select('result', [
                    { value: 'suitable', label: 'Geeignet' },
                    { value: 'not_suitable', label: 'Nicht geeignet' },
                    { value: 'cancelled', label: 'Abgebrochen' },
                    { value: 'no_show', label: 'Nicht teilgenommen' },
                ], assignment.result === 'pending' ? '' : assignment.result, true)),
                field('Anmerkung', element('textarea', { name: 'note', rows: 3, maxLength: 2000, value: assignment.evaluationNote || '' })),
                button('BQ-Ergebnis speichern'),
            ])
            resultForm.addEventListener('submit', (event) => {
                event.preventDefault()
                const data = new FormData(resultForm)
                run(
                    () => api.recordBasisQualificationResult(
                        assignment.id,
                        data.get('result'),
                        data.get('note'),
                        assignment.version,
                    ),
                    'BQ-Ergebnis wurde gespeichert.',
                    () => openApplication(detail.application.id),
                )
            })
            article.append(resultForm)
            card.append(article)
        }
        return card
    }

    function renderPermissions(target = content, embedded = false) {
        const view = embedded
            ? settingsPanel('permissions', 'Vertretungen und Erstbegleitungen')
            : panel('permissions', 'Vertretungen und Erstbegleitungen')
        const settings = state.permissionSettings
        if (!settings) {
            view.append(emptyState('Die Berechtigungskonfiguration ist nicht verfügbar.'))
            target.replaceChildren(view)
            return
        }
        view.append(element('p', {
            text: 'Personalreferent*innen vergeben Vertretungsrechte einzeln nach Fähigkeit und Scope. Änderungen werden versioniert protokolliert.',
        }))

        if (settings.representatives.length) {
            const list = element('div', { className: 'flzrecruitment-grid' })
            for (const representative of settings.representatives) {
                const scope = representative.all
                    ? 'Alle Bewerbungen'
                    : [
                        representative.areaKeys.length ? `Bereiche: ${representative.areaKeys.join(', ')}` : '',
                        representative.applicationIds.length ? `Bewerbungen: ${representative.applicationIds.join(', ')}` : '',
                    ].filter(Boolean).join(' · ')
                const remove = button('Vertretung entfernen', 'button', 'flzrecruitment-secondary')
                remove.addEventListener('click', () => run(
                    () => api.saveRepresentatives(
                        settings.representatives.filter((item) => item.uid !== representative.uid),
                        settings.revision,
                    ),
                    'Vertretung wurde entfernt.',
                ))
                list.append(element('article', { className: 'flzrecruitment-card' }, [
                    element('h3', { text: representative.uid }),
                    element('p', { text: representative.capabilities.map((capability) => capabilityLabels[capability] || capability).join(', ') }),
                    element('p', { text: scope }),
                    remove,
                ]))
            }
            view.append(list)
        } else {
            view.append(emptyState('Noch keine Vertretungskraft konfiguriert.'))
        }

        const form = element('form', { className: 'flzrecruitment-form flzrecruitment-card' }, [
            element('h3', { text: 'Vertretungskraft hinzufügen' }),
            field('Nextcloud-Benutzerkennung', input('uid', 'text', true)),
        ])
        const capabilityFieldset = element('fieldset', { className: 'flzrecruitment-question' }, [
            element('legend', { text: 'Fähigkeiten' }),
        ])
        for (const capability of state.delegatableCapabilities) {
            const control = input('capabilities', 'checkbox')
            control.value = capability
            capabilityFieldset.append(field(capabilityLabels[capability] || capability, control))
        }
        const all = input('all', 'checkbox')
        const areaSelect = select('areaKeys', state.areas.map((area) => ({ value: area.key, label: area.label })))
        areaSelect.multiple = true
        areaSelect.size = Math.min(6, Math.max(2, state.areas.length))
        form.append(
            capabilityFieldset,
            field('Globaler Scope', all, 'Wenn aktiv, gelten die gewählten Fähigkeiten für alle Bewerbungen.'),
            field('Bürobereiche', areaSelect, 'Mehrfachauswahl mit Strg/Cmd möglich.'),
            field('Einzelne Bewerbungs-IDs', input('applicationIds'), 'Kommagetrennt; mit Bereichen kombinierbar.'),
            button('Vertretung hinzufügen'),
        )
        form.addEventListener('submit', (event) => {
            event.preventDefault()
            const data = new FormData(form)
            const representative = {
                uid: data.get('uid'),
                capabilities: data.getAll('capabilities'),
                all: data.has('all'),
                areaKeys: Array.from(areaSelect.selectedOptions, (option) => option.value),
                applicationIds: csvList(data.get('applicationIds')).map(Number),
            }
            run(
                () => api.saveRepresentatives([...settings.representatives, representative], settings.revision),
                'Vertretung wurde hinzugefügt.',
            )
        })
        view.append(form)

        target.replaceChildren(view)
    }

    async function load() {
        clearError()
        setBusy()
        try {
            const payload = await api.bootstrap()
            state.data = payload.data
            state.capabilities = payload.capabilities
            state.areas = payload.areas || []
            state.hiringData = payload.hiringData || []
            state.basisQualificationRuns = payload.basisQualificationRuns || []
            state.jobResponsibilityGroups = payload.jobResponsibilityGroups || []
            state.permissionSettings = payload.permissionSettings || null
            state.mailConfiguration = payload.mailConfiguration || null
            state.candidatePool = payload.candidatePool || null
            state.delegatableCapabilities = payload.delegatableCapabilities || []
            renderTabs()
            showTab(state.activeTab)
            setReady()
        } catch (error) {
            content.replaceChildren(emptyState('Filzmann Recruitment konnte nicht geladen werden.'))
            showError(error)
        }
    }

    load()
}(window))
