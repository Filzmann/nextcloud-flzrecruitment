(function (root, factory) {
    'use strict'

    const api = factory()
    if (typeof module === 'object' && module.exports) {
        module.exports = api
    }
    if (root) {
        root.RecruitmentBubbleText = api
    }
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict'

    function appendBubbleText(currentValue, insertedText) {
        const current = String(currentValue ?? '')
        const inserted = String(insertedText ?? '').trim()
        if (inserted === '') {
            return current
        }
        if (current.trim() === '') {
            return inserted
        }
        if (/\s$/.test(current)) {
            return current + inserted
        }
        if (/[.!?;:,]$/.test(current)) {
            return current + ' ' + inserted
        }
        return current + '. ' + inserted
    }

    return { appendBubbleText }
}))
