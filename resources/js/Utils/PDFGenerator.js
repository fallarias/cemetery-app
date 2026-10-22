import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'
import { nextTick } from 'vue'

// ─── Helpers ──────────────────────────────────────────────────────────────────

/** Returns a formatted date string like "2026-03-28 at 2:05 PM" */
export const formatPrintedDate = () => {
    const now     = new Date()
    const year    = now.getFullYear()
    const month   = String(now.getMonth() + 1).padStart(2, '0')
    const day     = String(now.getDate()).padStart(2, '0')
    let   hours   = now.getHours()
    const minutes = String(now.getMinutes()).padStart(2, '0')
    const ampm    = hours >= 12 ? 'PM' : 'AM'
    hours         = hours % 12 || 12
    return `${year}-${month}-${day} at ${hours}:${minutes} ${ampm}`
}

/** Returns a filename-safe report title with a random suffix */
export const generatePdfTitle = (reportTitle = 'Report') => {
    const now      = new Date()
    const year     = now.getFullYear()
    const month    = String(now.getMonth() + 1).padStart(2, '0')
    const day      = String(now.getDate()).padStart(2, '0')
    const randomId = Math.random().toString(36).substring(2, 8).toUpperCase()
    return `${year}-${month}-${day}_${reportTitle}_${randomId}`
}

// ─── Main generator ───────────────────────────────────────────────────────────

/**
 * generatePDF
 *
 * Renders a branded PDF table using jsPDF + jsPDF-AutoTable.
 *
 * @param {object}  options
 * @param {string}  options.reportTitle      - Title shown in the PDF header
 * @param {import('vue').Ref<boolean>} options.isExporting - Reactive flag set to true during generation
 * @param {object}  options.currentUser      - { name, campus }
 * @param {string[]} options.headers         - Column header labels
 * @param {any[][]}  options.rows            - Data rows (array of arrays matching header order)
 * @param {string}  [options.logoPath]       - Relative URL to the logo (e.g. 'storage/logos/logo.svg').
 *                                            FIX: was previously hardcoded to '/storage/isu_seal.png',
 *                                            breaking any environment where the logo was customised
 *                                            via System Settings. Now accepts any path from the caller.
 * @param {'p'|'l'} [options.orientation]   - 'p' portrait (default) | 'l' landscape
 * @param {object}  [options.tableOptions]  - Extra jsPDF-AutoTable options (merged / override)
 * @param {string}  [options.pageSize]      - jsPDF page size string (default 'a4')
 */
export const generatePDF = async ({
    reportTitle,
    isExporting,
    currentUser,
    headers      = [],
    rows         = [],
    logoPath      = null,
    orientation  = 'p',
    tableOptions = {},
    pageSize     = 'a4',
}) => {
    isExporting.value = true
    await nextTick()

    const doc        = new jsPDF(orientation, 'mm', pageSize)
    const pdfTitle   = generatePdfTitle(reportTitle)
    const pageWidth  = doc.internal.pageSize.getWidth()
    const pageHeight = doc.internal.pageSize.getHeight()

    doc.setProperties({ title: pdfTitle })

    // ── Header drawn on every page ─────────────────────────────────────────
    const addHeader = () => {
        // FIX: logo path now comes from the caller instead of being hardcoded.
        // Falls back gracefully (no image) if logoPath is null/undefined.
        if (logoPath) {
            try {
                const logo = new Image()
                logo.src   = `/${logoPath}?t=${Date.now()}`
                doc.addImage(logo, 'PNG', 15, 10, 18, 18)
            } catch (_) {
                // logo failed to load — continue without it
            }
        }

        doc.setFontSize(11).setFont('helvetica', 'normal').setTextColor(0)
        doc.text('Republic of the Philippines', 40, 15)

        doc.setFontSize(13).setFont('helvetica', 'bold')
        doc.text('ISABELA STATE UNIVERSITY', 40, 20)

        doc.setFontSize(11).setFont('helvetica', 'normal')
        doc.text(`${currentUser?.campus ?? 'Echague'}, Isabela`, 40, 25)

        doc.setLineWidth(0.1).line(10, 30, pageWidth - 10, 30)

        doc.setFontSize(12).setFont('helvetica', 'bold')
        doc.text(reportTitle, pageWidth / 2, 38, { align: 'center' })
    }

    // ── Table ──────────────────────────────────────────────────────────────
    autoTable(doc, {
        startY: 45,
        head:   [headers],
        body:   rows,
        theme:  'grid',
        styles:     { fontSize: 6, cellPadding: 2, halign: 'left' },
        headStyles: { fillColor: [33, 37, 41], textColor: 255, fontStyle: 'bold' },
        ...tableOptions,
        didDrawPage(data) {
            addHeader()

            // ── Footer ────────────────────────────────────────────────────
            const pageCount  = doc.internal.getNumberOfPages()
            const pageNumber = doc.internal.getCurrentPageInfo().pageNumber
            const printedBy  = `Printed by: ${currentUser?.name ?? ''}`.toUpperCase()
            const printedDate = formatPrintedDate().toUpperCase()

            doc.setFontSize(6).setTextColor(0)
            doc.text(`Page ${pageNumber} of ${pageCount}`, 10, pageHeight - 10)

            const byWidth = doc.getTextWidth(printedBy)
            doc.text(printedBy, pageWidth - byWidth - 10, pageHeight - 10)

            doc.setFontSize(8).setTextColor(160, 160, 160)
            const dtWidth = doc.getTextWidth(printedDate)
            doc.text(printedDate, pageWidth - dtWidth - 10, pageHeight - 6)
        },
        margin: { top: 50, bottom: 20 },
    })

    doc.output('dataurlnewwindow')
    isExporting.value = false
}
