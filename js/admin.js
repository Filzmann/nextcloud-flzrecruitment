(function (root) {
    'use strict'

    const api = root.RecruitmentApi

    function control(id) {
        return document.getElementById(id)
    }

    function bind(formId, submitId, statusId, save) {
        const form = control(formId)
        const submit = control(submitId)
        const status = control(statusId)
        if (!form || !submit || !status || !api) return
        form.addEventListener('submit', async (event) => {
            event.preventDefault()
            submit.disabled = true
            status.textContent = 'Einstellung wird gespeichert …'
            try {
                const result = await save(Number(form.dataset.revision || 0))
                form.dataset.revision = String(result.revision)
                status.textContent = 'Einstellung wurde gespeichert.'
            } catch (error) {
                status.textContent = error?.message || 'Die Einstellung konnte nicht gespeichert werden.'
            } finally {
                submit.disabled = false
            }
        })
    }

    function initFullAccess() {
        const form = control('recr-full-access-form'); const history = control('recr-full-access-history'); const status = control('recr-full-access-status');
        if (!form || !history || !status || !api) return;
        const show = message => { status.textContent = message; };
        const date = value => value ? new Date(value).toLocaleString() : '—';
        const load = async () => { try { const state=await api.adminFullAccess();history.replaceChildren();if(!state.history?.length){const row=document.createElement('tr');const cell=document.createElement('td');cell.colSpan=6;cell.textContent='Noch keine Freigaben protokolliert.';row.append(cell);history.append(row);return;}state.history.forEach(grant=>{const row=document.createElement('tr');const active=!grant.revokedAt&&new Date(grant.startsAt).getTime()<=Date.now()&&new Date(grant.endsAt).getTime()>Date.now();[grant.targetUid,grant.grantedBy,date(grant.startsAt),date(grant.endsAt),grant.revokedAt?date(grant.revokedAt):(active?'Aktiv':'Planmäßig beendet')].forEach(value=>{const cell=document.createElement('td');cell.textContent=value;row.append(cell);});const action=document.createElement('td');if(active){const button=document.createElement('button');button.type='button';button.textContent='Widerrufen';button.dataset.revokeUid=grant.targetUid;action.append(button);}row.append(action);history.append(row);});}catch(error){show(error?.message||'Die Vollzugriffshistorie konnte nicht geladen werden.');} };
        form.addEventListener('submit',async event=>{event.preventDefault();const fields=new FormData(form);if(fields.get('enabled')!=='on')return;try{await api.activateAdminFullAccess(String(fields.get('targetUid')||'').trim(),Number(fields.get('durationMinutes')));form.elements.enabled.checked=false;show('Der zeitlich begrenzte Vollzugriff wurde aktiviert.');await load();}catch(error){show(error?.message||'Der Vollzugriff konnte nicht aktiviert werden.');}});
        history.addEventListener('click',async event=>{const button=event.target.closest('button[data-revoke-uid]');if(!button)return;button.disabled=true;try{await api.revokeAdminFullAccess(button.dataset.revokeUid);show('Der Vollzugriff wurde widerrufen.');await load();}catch(error){button.disabled=false;show(error?.message||'Der Vollzugriff konnte nicht widerrufen werden.');}});
        load();
    }

    bind('recr-admin-mail-form', 'recr-admin-mail-submit', 'recr-admin-mail-status', (revision) => api.saveMailSettings({
        testMode: control('recr-admin-mail-test-mode').checked,
        testRecipient: control('recr-admin-mail-test-recipient').value,
        revision,
    }))
    bind('recr-admin-extraction-form', 'recr-admin-extraction-submit', 'recr-admin-extraction-status', (revision) => api.saveResumeExtractionSettings({
        method: control('recr-admin-extraction-method').value,
        revision,
    }))
    bind('recr-admin-first-guide-form', 'recr-admin-first-guide-submit', 'recr-admin-first-guide-status', (revision) => api.saveFirstGuideGroup(
        control('recr-admin-first-guide-group').value,
        revision,
    ))
    initFullAccess()
}(window))
