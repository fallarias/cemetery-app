// /Utils/ExcelLayout.js
import ExcelJS from 'exceljs'
import { saveAs } from 'file-saver'

export const exportExcel = async (data, headers, title = 'Report') => {
    if (!data || data.length === 0) return

    const wb = new ExcelJS.Workbook()
    const ws = wb.addWorksheet('Sheet1')

    const baseFont  = { name: 'Times New Roman', size: 12 }
    const centered  = { horizontal: 'center', vertical: 'middle', wrapText: true }

    // Header row
    const headerRow = ws.addRow(headers)
    headerRow.height = 24
    headerRow.eachCell((cell) => {
        cell.font      = { ...baseFont, bold: true, color: { argb: 'FFFFFFFF' } }
        cell.fill      = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF008000' } }
        cell.alignment = centered
        cell.border    = {
            top:    { style: 'thin', color: { argb: 'FF006400' } },
            bottom: { style: 'thin', color: { argb: 'FF006400' } },
            left:   { style: 'thin', color: { argb: 'FF006400' } },
            right:  { style: 'thin', color: { argb: 'FF006400' } },
        }
    })

    // Data rows
    data.forEach((rowData) => {
        const row = ws.addRow(headers.map((h) => rowData[h] ?? ''))
        row.eachCell({ includeEmpty: true }, (cell) => {
            cell.font      = baseFont
            cell.alignment = centered
            cell.border    = {
                top:    { style: 'thin', color: { argb: 'FFD1D5DB' } },
                bottom: { style: 'thin', color: { argb: 'FFD1D5DB' } },
                left:   { style: 'thin', color: { argb: 'FFD1D5DB' } },
                right:  { style: 'thin', color: { argb: 'FFD1D5DB' } },
            }
        })
    })

    // Auto column width (a bit wider since the font is larger)
    headers.forEach((header, i) => {
        const maxLength = Math.max(
            header.length,
            ...data.map((row) => String(row[header] ?? '').length)
        )
        ws.getColumn(i + 1).width = Math.min(maxLength + 6, 50)
    })

    // Freeze header row (no filter arrows)
    ws.views = [{ state: 'frozen', ySplit: 1 }]

    const buffer   = await wb.xlsx.writeBuffer()
    const filename = `${title}_${new Date().toISOString().slice(0, 10)}.xlsx`
    saveAs(new Blob([buffer], { type: 'application/octet-stream' }), filename)
}
