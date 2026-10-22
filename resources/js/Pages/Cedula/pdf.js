import { jsPDF } from 'jspdf'
import { PAGE, FIELD_RECTS, RULES } from './engine'

// Size of the PDF page sent to the printer (mm). Letter height, like the Windows test page, so the
// driver starts printing at the left edge. The cedula (155 x 110 mm) is drawn in the top-left corner.
// The page is wider than Letter on purpose: SumatraPDF squeezes the print horizontally to about 60%
// on this printer (240 x 144 dpi), so the drawing is stretched by Scale X (about 167%) before printing
// and needs room for 155 mm x 1.67 = 258 mm.
const OUT_W = 260
const OUT_H = 279.4

const newDoc = () => new jsPDF({ orientation: 'portrait', unit: 'mm', format: [OUT_W, OUT_H] })

// Printer compensation. The driver prints with different horizontal and vertical resolution
// (240 x 144 dpi), so a page can come out stretched in one direction. Scale X / Y (1 = 100%)
// pre-shrinks or pre-stretches the whole drawing, anchored at the top-left corner of the page.
const MM = 72 / 25.4
const scaleOn = (s) => s && (s.x !== 1 || s.y !== 1)
const beginScale = (doc, s) => {
  if (!scaleOn(s)) return
  doc.internal.write('q')
  doc.internal.write(`${s.x} 0 0 ${s.y} 0 ${(OUT_H * MM * (1 - s.y)).toFixed(3)} cm`)
}
const endScale = (doc, s) => { if (scaleOn(s)) doc.internal.write('Q') }

// Builds a Letter-size PDF; the cedula area is the top-left 155 x 110 mm.
export function makePdf(items, { guides = false, scale } = {}) {
  const doc = newDoc()
  beginScale(doc, scale)
  doc.setFont('helvetica', 'bold')
  doc.setTextColor(0)

  if (guides) {
    // The printed lines of the real cedula, in black (grey would print as dots on a dot matrix).
    // guides === 'boxes' also draws the text cells in green (screen / PDF only).
    doc.setDrawColor(0)
    doc.setLineWidth(0.2)
    RULES.h.forEach(([y, x1, x2]) => doc.line(x1, y, x2, y))
    RULES.v.forEach(([x, y1, y2]) => doc.line(x, y1, x, y2))
    if (guides === 'boxes') {
      doc.setDrawColor(29, 158, 117)
      doc.setLineWidth(0.1)
      FIELD_RECTS.forEach((f) => doc.rect(f.x, f.y, f.w, f.h))
    }
  }

  doc.setDrawColor(0)
  doc.setLineWidth(0.3)
  doc.setLineCap('round')
  for (const it of items) {
    if (it.t === 'text') {
      doc.setFontSize(it.size)
      doc.text(it.text, it.x, it.y, { align: it.align })
    } else {
      doc.line(it.cx - 0.8, it.cy + 0.1, it.cx - 0.25, it.cy + 0.85)
      doc.line(it.cx - 0.25, it.cy + 0.85, it.cx + 0.9, it.cy - 0.9)
    }
  }
  endScale(doc, scale)
  return doc
}

// Grid with a line every 10 mm, labelled with its position on the page.
// It follows Shift X / Y, so changing them moves the grid on the paper.
export function makeCalibrationPdf({ x: ox = 0, y: oy = 0 } = {}, scale) {
  const doc = newDoc()
  beginScale(doc, scale)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(9)
  doc.setLineWidth(0.3)
  doc.setTextColor(0)
  for (let x = 0; x <= PAGE.w; x += 10) {
    doc.line(ox + x, oy, ox + x, oy + PAGE.h)
    doc.text(String(x), ox + x + 1, oy + 6)
    doc.text(String(x), ox + x + 1, oy + PAGE.h / 2)
  }
  for (let y = 0; y <= PAGE.h; y += 10) {
    doc.line(ox, oy + y, ox + PAGE.w, oy + y)
    doc.text(String(y), ox + 1, oy + y + 4)
    doc.text(String(y), ox + PAGE.w / 2, oy + y + 4)
  }
  endScale(doc, scale)
  return doc
}
