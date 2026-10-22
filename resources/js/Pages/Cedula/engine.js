import layout from './layout.json'

export const PAGE = layout.page
export const PT = 0.352778 // mm per point
const PRINTED = new Set(layout.printed ?? Object.keys(layout.fields))
export const FIELD_RECTS = Object.entries(layout.fields).filter(([k]) => PRINTED.has(k)).map(([k, f]) => ({ k, ...f }))
export const RULES = layout.rules ?? { h: [], v: [] }

const F = layout.fields
const ON = new Set(layout.printed ?? Object.keys(F)) // only these fields are printed
const PAD = 1.0 // mm between a cell's left edge and the text
const BASE = 1.0 // mm between the text baseline and the cell's bottom edge (keeps text under the printed label)

// ── Fine-tuning, in mm. Change ONE number at a time, print, compare with the real cedula, repeat.
// Left / right: a higher number moves right, a lower (negative) number moves left.
// Up / down:    a higher number moves down,  a lower (negative) number moves up.
const YEAR_PRE = 5.0 // the "26": left / right (from where the pre-printed "20" ends)
const YEAR_DY = 0.5 // the "26": up / down
const ADDRESS_DY = 0.0 // address line: up / down
const DATE_DX = 0.0 // date issued (month, day, year boxes): left / right
const DATE_DY = 0.0 // date issued (month, day, year boxes): up / down
const TIN_DX = -0.6 // TIN digits: left / right
const TIN_DY = 0.5 // TIN digits: up / down
const TIN_PT = 8 // TIN digits: font size (pt)
const AMOUNT_DX = -0.5 // amounts column (A. to Total Amount Paid), all rows: left / right
const AMOUNT_DY = 0.0 // amounts column (A. to Total Amount Paid), all rows: up / down

// Each name has its own offset, in mm.
// dx: negative = left, positive = right.  dy: negative = up, positive = down.
const NAME_ADJUST = {
  name_surname: { dx: 0.0, dy: 0.5 },
  name_first:   { dx: -0.3, dy: 0.0 },
  name_middle:  { dx: -0.3, dy: 0.0 },
}

// Each check mark has its own offset (Male, Female, Single, Married, Widow, Divorced).
// dx: negative = left, positive = right.  dy: negative = up, positive = down.
const TICK_ADJUST = {
  sex_male:         { dx: -0.7, dy: -0.7 },
  sex_female:       { dx: -0.1, dy: 0.8 },
  civil_1_single:   { dx: -0.5, dy: -0.8 },
  civil_2_married:  { dx: -0.3, dy: -0.9 },
  civil_3_widow:    { dx: 0.3, dy: -0.7 },
  civil_4_divorced: { dx: -0.1, dy: -0.3 },
}

// Extra up / down nudge for a single row (added on top of AMOUNT_DY). Leave at 0 unless one row sits off.
const AMOUNT_ROW_DY = {
  basic_tax_due: 0, // A. Basic community tax
  additional_tax_due: 0, // B. Additional community tax
  item1_due: 0, // 1. Gross receipts
  item2_due: 0, // 2. Salaries / profession
  item3_due: 0, // 3. Income from real property
  total: 0,
  interest: 0,
  total_paid: 0, // Total amount paid
}

// ── "In words" when the amount is above 999: bigger text, split on two lines (second line below the first)
const WORDS_PT = 6.5 // starting font size (pt)
const WORDS_MIN_PT = 4.5 // smallest size it may shrink to
const WORDS_LINE_GAP = 2.6 // mm between line 1 and line 2 (a higher number spreads the lines apart)
const WORDS_DX = 0.0 // in words (both lines): left / right
const WORDS_DY = 0.0 // in words (both lines): up / down

let ctx
const emWidth = (s) => {
  ctx ??= document.createElement('canvas').getContext('2d')
  ctx.font = '100px Helvetica, Arial, sans-serif'
  return ctx.measureText(s).width / 100
}

// Largest font size (pt) that keeps the text inside maxW mm, never above `start`.
const fit = (s, maxW, start = 9, min = 5.5) => {
  const perPt = emWidth(s) * PT
  return perPt ? Math.max(min, Math.min(start, maxW / perPt)) : start
}

// Splits text into at most two lines that fit maxW (mm) at font size pt. Returns null when it does not fit.
const wrap2 = (str, maxW, pt) => {
  const w = (t) => emWidth(t) * PT * pt
  const words = str.split(' ')
  let l1 = ''
  let i = 0
  while (i < words.length) {
    const next = l1 ? l1 + ' ' + words[i] : words[i]
    if (l1 && w(next) > maxW) break
    l1 = next
    i++
  }
  const l2 = words.slice(i).join(' ')
  if (w(l1) > maxW || (l2 && w(l2) > maxW)) return null
  return l2 ? [l1, l2] : [l1]
}

const money = (v) => {
  const n = parseFloat(String(v ?? '').replace(/,/g, ''))
  return Number.isFinite(n)
    ? n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : ''
}

const ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen']
const tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety']
const below1000 = (n) => {
  let s = ''
  if (n >= 100) {
    s += ones[Math.floor(n / 100)] + ' hundred'
    n %= 100
    if (n) s += ' '
  }
  if (n >= 20) s += tens[Math.floor(n / 10)] + (n % 10 ? '-' + ones[n % 10] : '')
  else if (n) s += ones[n]
  return s
}

export function pesoWords(value) {
  const cents = Math.round(parseFloat(String(value ?? '').replace(/,/g, '')) * 100)
  if (!Number.isFinite(cents) || cents <= 0) return ''
  let pesos = Math.floor(cents / 100)
  const c = cents % 100
  const out = []
  for (const [u, name] of [[1e9, 'billion'], [1e6, 'million'], [1e3, 'thousand']]) {
    if (pesos >= u) {
      out.push(below1000(Math.floor(pesos / u)) + ' ' + name)
      pesos %= u
    }
  }
  if (pesos || !out.length) out.push(below1000(pesos))
  const unit = Math.floor(cents / 100) === 1 ? ' peso' : ' pesos'
  return out.join(' ') + unit + (c ? ` & ${String(c).padStart(2, '0')}/100` : '') + ' only'
}

export const sampleForm = () => ({
  year: '2026', place_of_issue: 'Manila', date_issued: '2026-05-01',
  surname: 'Dela Cruz', first_name: 'Juan', middle_name: 'Santos',
  tin: '12345678901234', sex: 'M', citizenship: 'Filipino', icr_no: '',
  place_of_birth: 'Manila', height: "5'6\"", weight: '65 kg',
  basic_tax_due: '5.00', additional_tax_due: '', item1_due: '', item2_due: '296.00', item3_due: '',
  total: '301.00', interest: '', total_paid: '301.00', in_words: '', treasurer: 'Maria L. Reyes',
})

// Turns the form into a list of print primitives (text and check marks) in mm.
// The browser preview and the PDF both draw this same list, so they always match.
export function buildItems(form, { offset = { x: 0, y: 0 }, upper = true } = {}) {
  const out = []
  const U = (s) => (upper ? String(s ?? '').toUpperCase() : String(s ?? ''))

  const text = (key, str, { align = 'left', pt = 10, dx = 0, dy = 0, min } = {}) => {
    const f = F[key]
    if (!f || !ON.has(key) || str === undefined || str === null || str === '') return
    str = String(str)
    const pad = align === 'right' ? 1.5 : PAD
    const size = fit(str, f.w - 2 * pad, pt, min) // min undefined -> fit() uses its default (5.5)
    const x = (align === 'left' ? f.x + pad : align === 'right' ? f.x + f.w - pad : f.x + f.w / 2) + dx
    out.push({ t: 'text', text: str, x, y: f.y + f.h - BASE + dy, size, align })
  }
  const tick = (key) => {
    const f = F[key]
    const { dx = 0, dy = 0 } = TICK_ADJUST[key] ?? {}
    if (f && ON.has(key)) out.push({ t: 'check', cx: f.x + (f.cw ?? 3) / 2 + dx, cy: f.y + f.h / 2 + dy })
  }

  // The cedula already has "20" printed in the year box, so only the last two digits are printed ("2026" -> "26").
  const yy = String(form.year ?? '').trim().slice(-2)
  if (yy && F.year && ON.has('year')) {
    out.push({ t: 'text', text: yy, x: F.year.x + YEAR_PRE, y: F.year.y + F.year.h - BASE + YEAR_DY, size: fit(yy, F.year.w - YEAR_PRE - 1, 10), align: 'left' })
  }
  text('place_of_issue', U(form.place_of_issue))

  // Date issued: the cedula has three boxes in the order month, day, year (10 19 26). The form stores it as YYYY-MM-DD.
  const dt = String(form.date_issued ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (dt) {
    text('date_issued_dd', dt[3], { align: 'center', dx: DATE_DX, dy: DATE_DY })
    text('date_issued_mm', dt[2], { align: 'center', dx: DATE_DX, dy: DATE_DY })
    text('date_issued_yy', dt[1].slice(-2), { align: 'center', dx: DATE_DX, dy: DATE_DY })
  }

  text('name_surname', U(form.surname), NAME_ADJUST.name_surname)
  text('name_first', U(form.first_name), NAME_ADJUST.name_first)
  text('name_middle', U(form.middle_name), NAME_ADJUST.name_middle)
  text('address', U(form.address), { dy: ADDRESS_DY })

  const dob = String(form.date_of_birth ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (dob) text('date_of_birth', `${dob[2]}/${dob[3]}/${dob[1]}`, { align: 'center' })

  // TIN is optional: nothing prints when it is empty. One digit per box.
  const digits = String(form.tin || '').replace(/\D/g, '').slice(0, 14)
  let p = 0
  for (let n = 1; n <= 4; n++) {
    const key = 'tin_group_' + n
    const g = F[key]
    const chunk = digits.slice(p, p + g.digits)
    p += g.digits
    if (!ON.has(key)) continue
    for (let i = 0; i < chunk.length; i++) {
      out.push({
        t: 'text',
        text: chunk[i],
        x: g.x + (i + 0.5) * (g.w / g.digits) + TIN_DX,
        y: g.y + g.h - 0.8 + TIN_DY,
        size: TIN_PT,
        align: 'center',
      })
    }
  }

  if (form.sex === 'M') tick('sex_male')
  if (form.sex === 'F') tick('sex_female')

  // Civil status: 1 Single, 2 Married, 3 Widow/Widower/Legally separated, 4 Divorced
  const civil = { 1: 'civil_1_single', 2: 'civil_2_married', 3: 'civil_3_widow', 4: 'civil_4_divorced' }[form.civil]
  if (civil) tick(civil)

  text('citizenship', U(form.citizenship))
  text('icr_no', U(form.icr_no)) // optional
  text('place_of_birth', U(form.place_of_birth))
  text('height', form.height, { align: 'center' })
  text('weight', U(form.weight), { align: 'center' })

  // right column under "Community tax due": A, B, items 1 to 3; then total, interest, total amount paid
  for (const k of Object.keys(AMOUNT_ROW_DY)) {
    text(k, money(form[k]), { align: 'right', dx: AMOUNT_DX, dy: AMOUNT_DY + AMOUNT_ROW_DY[k] })
  }

  // In words: one line normally. Above 999 it gets bigger and wraps to a second line below the first.
  const paid = parseFloat(String(form.total_paid ?? '').replace(/,/g, '')) || 0
  const inWords = U(form.in_words || pesoWords(form.total_paid))
  if (paid > 999 && inWords && F.in_words && ON.has('in_words')) {
    const f = F.in_words
    const maxW = f.w - 2 * PAD
    let size = WORDS_PT
    let lines = wrap2(inWords, maxW, size)
    while (!lines && size > WORDS_MIN_PT) {
      size = Math.max(WORDS_MIN_PT, size - 0.25)
      lines = wrap2(inWords, maxW, size)
    }
    if (lines) {
      const last = f.y + f.h - BASE + WORDS_DY // baseline of the bottom line
      lines.forEach((ln, i) => {
        out.push({ t: 'text', text: ln, x: f.x + PAD + WORDS_DX, y: last - (lines.length - 1 - i) * WORDS_LINE_GAP, size, align: 'left' })
      })
    } else {
      text('in_words', inWords, { pt: WORDS_MIN_PT, min: WORDS_MIN_PT }) // too long even on two lines: shrink to one line
    }
  } else {
    text('in_words', inWords)
  }

  text('treasurer', U(form.treasurer), { align: 'center' })

  return out.map((it) =>
    it.t === 'text'
      ? { ...it, x: it.x + offset.x, y: it.y + offset.y }
      : { ...it, cx: it.cx + offset.x, cy: it.cy + offset.y },
  )
}
