(function (root) {
    'use strict'

    let pdfModulePromise = null

    function normalizeRectangles(rectangles, pageBounds) {
        if (!pageBounds || pageBounds.width <= 0 || pageBounds.height <= 0) {
            throw new Error('Die PDF-Markierung besitzt keine gültige Seitengröße.')
        }
        const normalized = []
        for (const rectangle of rectangles || []) {
            const left = Math.max(rectangle.left, pageBounds.left)
            const top = Math.max(rectangle.top, pageBounds.top)
            const right = Math.min(rectangle.left + rectangle.width, pageBounds.left + pageBounds.width)
            const bottom = Math.min(rectangle.top + rectangle.height, pageBounds.top + pageBounds.height)
            const width = right - left
            const height = bottom - top
            if (![left, top, width, height].every(Number.isFinite) || width <= 0 || height <= 0) {
                throw new Error('Die PDF-Markierung ist leer oder liegt außerhalb der Seite.')
            }
            normalized.push({
                x: round((left - pageBounds.left) / pageBounds.width),
                y: round((top - pageBounds.top) / pageBounds.height),
                width: round(width / pageBounds.width),
                height: round(height / pageBounds.height),
            })
        }
        if (normalized.length > 50) throw new Error('Die PDF-Markierung enthält zu viele Teilbereiche.')
        return normalized
    }

    function round(value) {
        return Math.round(value * 1000000) / 1000000
    }

    function assetUrl(path) {
        if (!root.OC?.linkTo) throw new Error('Die lokale PDF.js-Adresse konnte nicht erzeugt werden.')
        return root.OC.linkTo('flzrecruitment', `js/vendor/pdfjs/${path}`)
    }

    async function pdfModule() {
        if (!pdfModulePromise) {
            pdfModulePromise = import(assetUrl('pdf.min.mjs')).then((module) => {
                module.GlobalWorkerOptions.workerSrc = assetUrl('pdf.worker.min.mjs')
                return module
            })
        }
        return pdfModulePromise
    }

    function node(document, tag, options = {}, children = []) {
        const result = document.createElement(tag)
        for (const [key, value] of Object.entries(options)) {
            if (key === 'text') result.textContent = value
            else if (key === 'className') result.className = value
            else if (key === 'dataset') Object.assign(result.dataset, value)
            else result.setAttribute(key, value)
        }
        result.append(...children)
        return result
    }

    async function open({ url, title, sidePanel = null, fieldLinks = [], onSelection = null, onError = null, pdfjs = null }) {
        const document = root.document
        if (!document?.body || typeof document.createElement !== 'function') {
            throw new Error('Die PDF-Lightbox ist in dieser Umgebung nicht verfügbar.')
        }
        const dialog = node(document, 'dialog', {
            className: 'flzrecruitment-pdf-lightbox',
            'aria-label': `PDF-Ansicht: ${title}`,
        })
        const heading = node(document, 'h2', { text: title })
        const closeButton = node(document, 'button', { type: 'button', text: 'Schließen', className: 'primary' })
        const zoomOut = node(document, 'button', { type: 'button', text: 'Verkleinern', 'aria-label': 'PDF verkleinern' })
        const zoomIn = node(document, 'button', { type: 'button', text: 'Vergrößern', 'aria-label': 'PDF vergrößern' })
        const takeSelection = node(document, 'button', { type: 'button', text: 'Textauswahl übernehmen' })
        const markArea = node(document, 'button', { type: 'button', text: 'Bereich markieren', 'aria-pressed': 'false' })
        const status = node(document, 'p', { className: 'flzrecruitment-pdf-status', text: 'PDF wird geladen …', role: 'status' })
        const pages = node(document, 'div', { className: 'flzrecruitment-pdf-pages', tabindex: '0' })
        const toolbar = node(document, 'div', { className: 'flzrecruitment-pdf-toolbar', role: 'toolbar', 'aria-label': 'PDF-Werkzeuge' }, [
            zoomOut, zoomIn, takeSelection, markArea, status, closeButton,
        ])
        const viewerColumn = node(document, 'section', { className: 'flzrecruitment-pdf-viewer-column', 'aria-label': 'PDF-Seiten' }, [toolbar, pages])
        const layoutChildren = [viewerColumn]
        if (sidePanel) {
            sidePanel.classList.add('flzrecruitment-pdf-side-panel')
            layoutChildren.push(sidePanel)
        }
        dialog.append(heading, node(document, 'div', { className: 'flzrecruitment-pdf-lightbox-layout' }, layoutChildren))
        const returnFocus = document.activeElement
        document.body.append(dialog)

        let scale = 1.25
        let pdf = null
        let loadingTask = null
        let loadedPdfModule = pdfjs
        let marking = false
        let lastSelection = null
        let destroyed = false

        const report = (error) => {
            status.textContent = error instanceof Error ? error.message : String(error)
            if (onError) onError(error)
        }
        const close = () => dialog.close()
        closeButton.addEventListener('click', close)
        dialog.addEventListener('close', () => {
            destroyed = true
            loadingTask?.destroy?.()
            pdf?.destroy?.()
            dialog.remove()
            returnFocus?.focus?.()
        })
        markArea.addEventListener('click', () => {
            marking = !marking
            markArea.setAttribute('aria-pressed', String(marking))
            pages.classList.toggle('is-marking', marking)
            status.textContent = marking
                ? 'Ziehen Sie mit dem Zeiger einen Bereich auf genau einer PDF-Seite auf.'
                : 'Bereichsmarkierung beendet.'
        })
        takeSelection.addEventListener('click', () => {
            if (!lastSelection) {
                report(new Error('Wählen Sie zuerst Text innerhalb genau einer PDF-Seite aus.'))
                return
            }
            onSelection?.(lastSelection)
            status.textContent = `Textauswahl auf Seite ${lastSelection.pageNumber} übernommen.`
        })

        const captureTextSelection = () => {
            const selection = root.getSelection?.()
            if (!selection || selection.rangeCount !== 1 || selection.isCollapsed) return
            const range = selection.getRangeAt(0)
            const startPage = closestPage(range.startContainer)
            const endPage = closestPage(range.endContainer)
            if (!startPage || startPage !== endPage || !pages.contains(startPage)) return
            try {
                const rectangles = normalizeRectangles(Array.from(range.getClientRects()), startPage.getBoundingClientRect())
                if (!rectangles.length) return
                lastSelection = {
                    pageNumber: Number(startPage.dataset.pageNumber),
                    selectedText: selection.toString().trim(),
                    rectangles,
                }
                status.textContent = 'Textauswahl erkannt. Mit „Textauswahl übernehmen“ bestätigen.'
            } catch (error) {
                report(error)
            }
        }
        pages.addEventListener('mouseup', captureTextSelection)
        pages.addEventListener('keyup', captureTextSelection)

        let drag = null
        pages.addEventListener('pointerdown', (event) => {
            if (!marking) return
            const page = event.target.closest?.('.flzrecruitment-pdf-page')
            if (!page) return
            event.preventDefault()
            const bounds = page.getBoundingClientRect()
            const marker = node(document, 'span', { className: 'flzrecruitment-pdf-active-mark', 'aria-hidden': 'true' })
            page.append(marker)
            drag = { page, bounds, marker, startX: event.clientX, startY: event.clientY }
            page.setPointerCapture?.(event.pointerId)
        })
        pages.addEventListener('pointermove', (event) => {
            if (!drag) return
            positionMarker(drag, event.clientX, event.clientY)
        })
        pages.addEventListener('pointerup', (event) => {
            if (!drag) return
            positionMarker(drag, event.clientX, event.clientY)
            try {
                const markerBounds = drag.marker.getBoundingClientRect()
                const selected = {
                    pageNumber: Number(drag.page.dataset.pageNumber),
                    selectedText: '',
                    rectangles: normalizeRectangles([markerBounds], drag.bounds),
                }
                onSelection?.(selected)
                status.textContent = `Bereich auf Seite ${selected.pageNumber} übernommen.`
            } catch (error) {
                drag.marker.remove()
                report(error)
            }
            drag = null
            marking = false
            markArea.setAttribute('aria-pressed', 'false')
            pages.classList.remove('is-marking')
        })

        const render = async () => {
            if (!pdf || destroyed) return
            pages.replaceChildren()
            status.textContent = `${pdf.numPages} PDF-Seite${pdf.numPages === 1 ? '' : 'n'} werden dargestellt …`
            const module = loadedPdfModule || await pdfModule()
            for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                if (destroyed) return
                const page = await pdf.getPage(pageNumber)
                const viewport = page.getViewport({ scale })
                const pageElement = node(document, 'section', {
                    className: 'flzrecruitment-pdf-page',
                    dataset: { pageNumber: String(pageNumber) },
                    'aria-label': `PDF-Seite ${pageNumber}`,
                    tabindex: '0',
                })
                pageElement.style.width = `${viewport.width}px`
                pageElement.style.height = `${viewport.height}px`
                pageElement.style.setProperty('--scale-factor', scale)
                const canvas = node(document, 'canvas', { 'aria-hidden': 'true' })
                const outputScale = root.devicePixelRatio || 1
                canvas.width = Math.floor(viewport.width * outputScale)
                canvas.height = Math.floor(viewport.height * outputScale)
                canvas.style.width = `${viewport.width}px`
                canvas.style.height = `${viewport.height}px`
                const textLayerElement = node(document, 'div', { className: 'flzrecruitment-pdf-text-layer' })
                pageElement.append(canvas, textLayerElement)
                pages.append(pageElement)
                await page.render({
                    canvasContext: canvas.getContext('2d'),
                    viewport,
                    transform: outputScale === 1 ? null : [outputScale, 0, 0, outputScale, 0, 0],
                }).promise
                const textLayer = new module.TextLayer({
                    textContentSource: await page.getTextContent(),
                    container: textLayerElement,
                    viewport,
                })
                await textLayer.render()
                renderStoredMarks(document, pageElement, fieldLinks.filter((link) => link.pageNumber === pageNumber))
            }
            status.textContent = `${pdf.numPages} PDF-Seite${pdf.numPages === 1 ? '' : 'n'} geladen.`
        }
        zoomOut.addEventListener('click', () => {
            scale = Math.max(0.75, scale - 0.25)
            render().catch(report)
        })
        zoomIn.addEventListener('click', () => {
            scale = Math.min(2.5, scale + 0.25)
            render().catch(report)
        })

        dialog.showModal()
        closeButton.focus()
        const ready = (async () => {
            try {
                const module = loadedPdfModule || await pdfModule()
                loadedPdfModule = module
                loadingTask = module.getDocument({
                    url,
                    cMapUrl: assetUrl('cmaps/'),
                    cMapPacked: true,
                    standardFontDataUrl: assetUrl('standard_fonts/'),
                    wasmUrl: assetUrl('wasm/'),
                    withCredentials: true,
                })
                pdf = await loadingTask.promise
                await render()
            } catch (error) {
                if (!destroyed) report(error)
            }
        })()
        return { close, dialog, ready }
    }

    function closestPage(node) {
        const element = node?.nodeType === 1 ? node : node?.parentElement
        return element?.closest?.('.flzrecruitment-pdf-page') || null
    }

    function positionMarker(drag, clientX, clientY) {
        const left = Math.max(drag.bounds.left, Math.min(drag.startX, clientX)) - drag.bounds.left
        const top = Math.max(drag.bounds.top, Math.min(drag.startY, clientY)) - drag.bounds.top
        const right = Math.min(drag.bounds.right, Math.max(drag.startX, clientX)) - drag.bounds.left
        const bottom = Math.min(drag.bounds.bottom, Math.max(drag.startY, clientY)) - drag.bounds.top
        Object.assign(drag.marker.style, {
            left: `${left}px`, top: `${top}px`, width: `${Math.max(0, right - left)}px`, height: `${Math.max(0, bottom - top)}px`,
        })
    }

    function renderStoredMarks(document, page, links) {
        for (const link of links) {
            for (const rectangle of link.rectangles || []) {
                const marker = node(document, 'span', {
                    className: 'flzrecruitment-pdf-stored-mark',
                    title: `${targetLabel(link.targetField)}: ${link.appliedValue}`,
                    'aria-hidden': 'true',
                })
                Object.assign(marker.style, {
                    left: `${rectangle.x * 100}%`, top: `${rectangle.y * 100}%`,
                    width: `${rectangle.width * 100}%`, height: `${rectangle.height * 100}%`,
                })
                page.append(marker)
            }
        }
    }

    function targetLabel(target) {
        return ({
            previousExperience: 'Vorerfahrung', germanLanguageLevel: 'Deutschniveau',
            birthDate: 'Geburtsdatum', birthPlace: 'Geburtsort', freeComment: 'Freier Kommentar',
        })[target] || target
    }

    root.RecruitmentPdfLightbox = { normalizeRectangles, open }
}(window))
