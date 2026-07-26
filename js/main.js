(function (root) {
    'use strict'

    const api = root.RecruitmentApi
    const { appendBubbleText } = root.RecruitmentBubbleText
    const { csvList, statusLabel } = root.RecruitmentUiData
    const content = document.getElementById('recruitment-content')
    const tabs = document.getElementById('recruitment-tabs')
    const status = document.getElementById('recruitment-status')
    const errorBox = document.getElementById('recruitment-error')
    const state = { data: null, capabilities: {}, activeTab: 'applications' }

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

    function field(labelText, control, hint = '') {
        const label = element('label', { className: 'recruitment-field' }, [
            element('span', { text: labelText }),
            control,
        ])
        if (hint) label.append(element('small', { text: hint }))
        return label
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
        return control
    }

    function button(text, type = 'submit', className = '') {
        return element('button', { type, text, className })
    }

    function showError(error) {
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
        const definitions = [
            ['applications', 'Bewerbungen'],
            ['jobs', 'Stellen'],
            ['templates', 'Interviewvorlagen'],
        ]
        for (const [id, label] of definitions) {
            const tab = button(label, 'button', 'recruitment-tab')
            tab.id = `recruitment-tab-${id}`
            tab.setAttribute('role', 'tab')
            tab.setAttribute('aria-controls', `recruitment-panel-${id}`)
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
                document.getElementById(`recruitment-tab-${next}`)?.focus()
            })
            tabs.append(tab)
        }
    }

    function showTab(id) {
        state.activeTab = id
        renderTabs()
        if (id === 'jobs') renderJobs()
        else if (id === 'templates') renderTemplates()
        else renderApplications()
    }

    function panel(id, title) {
        return element('section', {
            id: `recruitment-panel-${id}`,
            className: 'recruitment-panel',
            role: 'tabpanel',
            'aria-labelledby': `recruitment-tab-${id}`,
        }, [element('h2', { text: title })])
    }

    function emptyState(text) {
        return element('p', { className: 'recruitment-empty', text })
    }

    function renderJobs() {
        const view = panel('jobs', 'Stellen und Ausschreibungen')
        if (state.capabilities.manage_catalog) {
            const form = element('form', { className: 'recruitment-form recruitment-card' }, [
                element('h3', { text: 'Stelle anlegen' }),
                field('Interne Bezeichnung', input('internalTitle', 'text', true)),
                field('Öffentliche Bezeichnung', input('publicTitle')),
                field('Zuständige Benutzer-UIDs', input('responsibleUsers'), 'Kommagetrennt'),
                field('Zuständige Gruppen-IDs', input('responsibleGroups'), 'Kommagetrennt'),
                field('Zuordnungsschlüssel', input('assignmentKey')),
                button('Stelle anlegen'),
            ])
            form.addEventListener('submit', (event) => {
                event.preventDefault()
                const data = new FormData(form)
                run(() => api.createJob({
                    internalTitle: data.get('internalTitle'),
                    publicTitle: data.get('publicTitle'),
                    active: true,
                    responsibleUsers: csvList(data.get('responsibleUsers')),
                    responsibleGroups: csvList(data.get('responsibleGroups')),
                    assignmentKey: data.get('assignmentKey'),
                }), 'Stelle wurde angelegt.')
            })
            view.append(form)
        }

        const jobs = state.data.jobs
        if (jobs.length === 0) {
            view.append(emptyState('Noch keine Stellen vorhanden.'))
        } else {
            const list = element('div', { className: 'recruitment-grid' })
            for (const job of jobs) {
                list.append(element('article', { className: 'recruitment-card' }, [
                    element('h3', { text: job.internalTitle }),
                    job.publicTitle ? element('p', { text: job.publicTitle }) : null,
                    element('p', { text: job.active ? 'Aktiv' : 'Deaktiviert' }),
                    element('small', {
                        text: [
                            job.responsibleUsers.length ? `Benutzer: ${job.responsibleUsers.join(', ')}` : '',
                            job.responsibleGroups.length ? `Gruppen: ${job.responsibleGroups.join(', ')}` : '',
                        ].filter(Boolean).join(' · ') || 'Noch keine Zuständigkeit zugeordnet.',
                    }),
                ]))
            }
            view.append(list)
        }
        content.replaceChildren(view)
    }

    function renderApplications() {
        const view = panel('applications', 'Bewerbungen')
        if (state.capabilities.edit_applications) {
            const forms = element('div', { className: 'recruitment-grid recruitment-grid--forms' })
            const personForm = element('form', { className: 'recruitment-form recruitment-card' }, [
                element('h3', { text: 'Person anlegen' }),
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
            const applicationForm = element('form', { className: 'recruitment-form recruitment-card' }, [
                element('h3', { text: 'Bewerbung anlegen' }),
                field('Person', select('personId', personOptions, '', true)),
                field('Stelle', select('jobId', jobOptions, '', true)),
                field('Eingangskanal', select('source', [
                    { value: 'manual', label: 'Manuell' },
                    { value: 'referral', label: 'Empfehlung' },
                    { value: 'other', label: 'Sonstiger Kanal' },
                ])),
                field('Eingangsdatum', input('receivedOn', 'date', true, new Date().toISOString().slice(0, 10))),
                field('Zuständige Benutzer-UID', input('assigneeUid')),
                button('Bewerbung anlegen'),
            ])
            applicationForm.addEventListener('submit', (event) => {
                event.preventDefault()
                const data = Object.fromEntries(new FormData(applicationForm))
                data.personId = Number(data.personId)
                data.jobId = Number(data.jobId)
                run(() => api.createApplication(data), 'Bewerbung wurde angelegt.')
            })
            forms.append(personForm, applicationForm)
            view.append(forms)
        }

        const applications = state.data.applications
        if (applications.length === 0) {
            view.append(emptyState('Noch keine Bewerbungen vorhanden.'))
        } else {
            const tableWrap = element('div', { className: 'recruitment-table-wrap' })
            const table = element('table')
            table.append(element('thead', {}, element('tr', {}, [
                element('th', { text: 'Person' }),
                element('th', { text: 'Stelle' }),
                element('th', { text: 'Eingang' }),
                element('th', { text: 'Status' }),
                element('th', { text: 'Aktion' }),
            ])))
            const body = element('tbody')
            for (const application of applications) {
                const person = state.data.people.find((item) => item.id === application.personId)
                const job = state.data.jobs.find((item) => item.id === application.jobId)
                const open = button('Detail öffnen', 'button')
                open.addEventListener('click', () => openApplication(application.id))
                body.append(element('tr', {}, [
                    element('td', { text: person ? `${person.givenName} ${person.familyName}` : 'Unbekannt' }),
                    element('td', { text: job?.internalTitle || 'Unbekannt' }),
                    element('td', { text: application.receivedOn }),
                    element('td', { text: statusLabel(application.status) }),
                    element('td', {}, open),
                ]))
            }
            table.append(body)
            tableWrap.append(table)
            view.append(tableWrap)
        }
        content.replaceChildren(view)
    }

    async function openApplication(id) {
        clearError()
        setBusy()
        try {
            const detail = await api.application(id)
            renderApplicationDetail(detail)
            setReady()
        } catch (error) {
            showError(error)
        }
    }

    function renderApplicationDetail(detail) {
        const view = element('section', { className: 'recruitment-panel' })
        const back = button('← Zurück zu Bewerbungen', 'button', 'recruitment-secondary')
        back.addEventListener('click', () => showTab('applications'))
        view.append(back, element('h2', {
            text: `${detail.person.givenName} ${detail.person.familyName} · ${detail.job.internalTitle}`,
        }))
        view.append(element('dl', { className: 'recruitment-facts' }, [
            element('div', {}, [element('dt', { text: 'Status' }), element('dd', { text: statusLabel(detail.application.status) })]),
            element('div', {}, [element('dt', { text: 'Eingang' }), element('dd', { text: detail.application.receivedOn })]),
            element('div', {}, [element('dt', { text: 'Kanal' }), element('dd', { text: detail.application.source })]),
            element('div', {}, [element('dt', { text: 'Zuständigkeit' }), element('dd', { text: detail.application.assigneeUid || 'Nicht zugeordnet' })]),
        ]))

        if (state.capabilities.edit_applications && detail.allowedStatuses.length) {
            const statusForm = element('form', { className: 'recruitment-inline-form recruitment-card' }, [
                field('Neuer Status', select('status', detail.allowedStatuses.map((value) => ({
                    value,
                    label: statusLabel(value),
                })))),
                button('Status ändern'),
            ])
            statusForm.addEventListener('submit', (event) => {
                event.preventDefault()
                const target = new FormData(statusForm).get('status')
                run(
                    () => api.transitionStatus(detail.application.id, target, detail.application.version),
                    'Status wurde geändert.',
                    () => openApplication(detail.application.id),
                )
            })
            view.append(statusForm)
        }

        if (state.capabilities.interview && state.data.templates.some((template) => template.active)) {
            const interviewForm = element('form', { className: 'recruitment-inline-form recruitment-card' }, [
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
            const list = element('ol', { className: 'recruitment-history' })
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
        const article = element('article', { className: 'recruitment-card recruitment-interview' }, [
            element('h4', { text: `${interview.snapshot.name} · Revision ${interview.templateRevision}` }),
            element('p', { text: `Status: ${statusLabel(interview.status)}` }),
        ])
        const form = element('form', { className: 'recruitment-form' })
        for (const question of interview.snapshot.questions || []) {
            if (!question.active) continue
            form.append(renderAnswer(question, interview.answers[String(question.id)], interview.status === 'completed'))
        }
        if (interview.status !== 'completed' && state.capabilities.interview) {
            const actions = element('div', { className: 'recruitment-actions' }, [
                button('Entwurf speichern', 'button', 'recruitment-secondary'),
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
        const wrapper = element('fieldset', { className: 'recruitment-question' })
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
            const bubbles = element('div', { className: 'recruitment-bubbles', role: 'group', 'aria-label': 'Antwortbausteine' })
            for (const bubble of question.bubbles.filter((item) => item.active)) {
                const bubbleButton = button(bubble.label, 'button', 'recruitment-bubble')
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

    function renderTemplates() {
        const view = panel('templates', 'Interviewvorlagen')
        if (state.capabilities.manage_catalog) {
            const form = element('form', { className: 'recruitment-form recruitment-card' }, [
                element('h3', { text: 'Vorlage anlegen' }),
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
            view.append(form)
        }

        if (state.data.templates.length === 0) {
            view.append(emptyState('Noch keine Interviewvorlagen vorhanden.'))
        } else {
            const list = element('div', { className: 'recruitment-grid' })
            for (const template of state.data.templates) {
                const open = button('Vorlage bearbeiten', 'button')
                open.addEventListener('click', () => openTemplate(template.id))
                list.append(element('article', { className: 'recruitment-card' }, [
                    element('h3', { text: template.name }),
                    element('p', { text: `${template.type} · Revision ${template.revision}` }),
                    element('p', { text: template.active ? 'Aktiv' : 'Deaktiviert' }),
                    open,
                ]))
            }
            view.append(list)
        }
        content.replaceChildren(view)
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
        const view = element('section', { className: 'recruitment-panel' })
        const back = button('← Zurück zu Vorlagen', 'button', 'recruitment-secondary')
        back.addEventListener('click', () => showTab('templates'))
        view.append(back, element('h2', { text: `${template.name} · Revision ${template.revision}` }))

        if (state.capabilities.manage_catalog) {
            const form = questionForm()
            form.prepend(element('h3', { text: 'Frage hinzufügen' }))
            form.addEventListener('submit', (event) => {
                event.preventDefault()
                run(
                    () => api.createQuestion(template.id, questionPayload(form)),
                    'Frage wurde hinzugefügt.',
                    () => openTemplate(template.id),
                )
            })
            view.append(form)
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
        const form = element('form', { className: 'recruitment-form recruitment-card' }, [
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
        const checks = element('div', { className: 'recruitment-checks' })
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
        const article = element('article', { className: 'recruitment-question-editor' })
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

        const bubbles = element('div', { className: 'recruitment-card' }, [
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
            const bubbleForm = element('form', { className: 'recruitment-form' }, [
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
            bubbles.append(bubbleForm)
        }
        article.append(bubbles)
        return article
    }

    async function load() {
        clearError()
        setBusy()
        try {
            const payload = await api.bootstrap()
            state.data = payload.data
            state.capabilities = payload.capabilities
            renderTabs()
            showTab(state.activeTab)
            setReady()
        } catch (error) {
            content.replaceChildren(emptyState('Recruitment konnte nicht geladen werden.'))
            showError(error)
        }
    }

    load()
}(window))
