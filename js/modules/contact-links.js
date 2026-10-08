(function (root) {
    'use strict'

    function emailHref(value) {
        const email = String(value || '').trim()
        if (/^[^\s<>"'\r\n@]+@[^\s<>"'\r\n@]+\.[^\s<>"'\r\n@]+$/.test(email) === false) return ''
        return `mailto:${email}`
    }

    function emailLinkAttributes(value) {
        const href = emailHref(value)
        return href ? { href, target: '_blank', rel: 'noopener noreferrer' } : null
    }

    function phoneHref(value) {
        let phone = String(value || '').trim()
        if (!phone || /^[+\d\s()./-]+$/.test(phone) === false) return ''
        phone = phone.replace(/^\+(\d{1,3})\s*\(0\)/, '+$1')
        const normalized = phone.replace(/[^\d+]/g, '')
        if (!/^\+?\d{3,20}$/.test(normalized)) return ''
        return `tel:${normalized}`
    }

    root.FlzRecruitmentContactLinks = { emailHref, emailLinkAttributes, phoneHref }
})(window)
