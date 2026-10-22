<script setup>
import { reactive, ref, computed, watch, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { buildItems, pesoWords } from './engine'
import { makePdf } from './pdf'

// ── Printer settings (found with the calibration test on scrap paper) ─────────
// Shift moves the print in mm (+X right, +Y down). Scale stretches it in percent:
// SumatraPDF squeezes the print horizontally to ~60% on this printer, so X is 166.7.
const SHIFT_X = 1.2
const SHIFT_Y = -1
const SCALE_X = 166.7
const SCALE_Y = 100
const OFFSET = { x: SHIFT_X, y: SHIFT_Y }
const PRINT_SCALE = { x: SCALE_X / 100, y: SCALE_Y / 100 }
const UPPER = true // capital letters are always on

// ── Laravel requests ──────────────────────────────────────────────────────────
// Inertia's layout has no csrf meta tag, so send Laravel's XSRF-TOKEN cookie as X-XSRF-TOKEN.
const csrfHeaders = () => {
    const meta = document.querySelector('meta[name="csrf-token"]')?.content
    const cookie = decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) || [])[1] || '')
    return { ...(meta ? { 'X-CSRF-TOKEN': meta } : {}), ...(cookie ? { 'X-XSRF-TOKEN': cookie } : {}) }
}
const api = async (url, opt = {}) => {
    const r = await fetch(url, {
        ...opt,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...csrfHeaders(), ...(opt.headers || {}) },
    })
    if (!r.ok) throw new Error((await r.json().catch(() => ({}))).message || r.statusText)
    return r.status === 204 ? null : r.json()
}

const toast = (icon, title) =>
    Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2200 })

const showError = (title, text) =>
    Swal.fire({ icon: 'error', title, text, confirmButtonText: 'OK', confirmButtonColor: '#2d6a4f' })

// ── Table: search + paging (the server returns one page at a time) ────────────
const records = ref([])
const search = ref('')
const page = ref(1)
const itemsPerPage = ref(10)
const totalItems = ref(0)
// Like the employee page, searching / paging / saving / deleting never shows the loading skeleton.
// It only shows once, while the very first list is on its way (otherwise the table would flash "No data").
const loading = ref(true)

const headers = [
    { title: '#',              align: 'center', key: 'number',     sortable: false },
    { title: 'Name',           align: 'center', key: 'full_name',  sortable: false },
    { title: 'CCI2023 Serial', align: 'center', key: 'serial',     sortable: false },
    { title: 'Date Saved',     align: 'center', key: 'created_at', sortable: false },
    { title: 'Action',         align: 'center', key: 'action',     sortable: false },
]

// Silent reload: the rows on screen stay in place until the new rows arrive, then they swap.
// Same idea as Inertia's router.get on the employee page: a new request cancels the old one,
// and an answer that arrives late is ignored, so fast typing never shows older results.
let controller
let requestId = 0
const loadRecords = async () => {
    controller?.abort()
    controller = new AbortController()
    const id = ++requestId
    try {
        const r = await api(
            `/cedula/records?q=${encodeURIComponent(search.value)}&page=${page.value}&per_page=${itemsPerPage.value}`,
            { signal: controller.signal },
        )
        if (id !== requestId) return
        records.value = r.data
        totalItems.value = r.total
        page.value = r.current_page
    } catch (e) {
        if (e.name === 'AbortError') return // replaced by a newer search, nothing to show
        if (id === requestId) showError('Could not load the records', e.message)
    } finally {
        if (id === requestId) loading.value = false // only matters for the first load
    }
}

const onPageChange = (p) => { page.value = p; loadRecords() }
const onItemsPerPageChange = (n) => { itemsPerPage.value = n; page.value = 1; loadRecords() }

let searchTimer
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => { page.value = 1; loadRecords() }, 200)
})

onMounted(loadRecords)

const formatDate = (v) => (v ? new Date(v).toLocaleDateString() : '')

// Today as YYYY-MM-DD in local time (the default "Date Issued" of a new cedula).
const todayISO = () => {
    const d = new Date()
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

// ── The cedula form (lives in the dialog) ─────────────────────────────────────
const blank = () => ({
    serial: '',
    place_of_issue: 'POLILLO, QUEZON', date_issued: todayISO(), date_of_birth: '',
    surname: '', first_name: '', middle_name: '',
    tin: '', address: '', sex: '', civil: '', citizenship: 'Filipino', icr_no: '',
    place_of_birth: '', height: '', weight: '',
    basic_tax_due: '5.00', additional_tax_due: '',
    item1_due: '', item2_due: '', item3_due: '',
    total: '', interest: '', total_paid: '', in_words: '',
    treasurer: localStorage.getItem('cedula.treasurer') || '',
})

const fromRecord = (r) => {
    const base = { ...blank(), date_issued: '' }
    for (const k of Object.keys(base)) if (r[k] != null) base[k] = String(r[k])
    return base
}

const moneyFields = [
    ['basic_tax_due', 'A. Basic community tax'], ['additional_tax_due', 'B. Additional community tax'],
    ['item1_due', '1. Gross receipts (business)'], ['item2_due', '2. Salaries / profession'],
    ['item3_due', '3. Income from real property'], ['interest', 'Interest'],
]

const form = reactive(blank())
const dialog = ref(false)
const editing_id = ref(null)
const saving = ref(false)
const saveMode = ref('') // 'save' or 'print': which button shows the spinner
const form_errors = ref([])

const num = (v) => parseFloat(String(v ?? '').replace(/,/g, '')) || 0
const words = computed(() => pesoWords(form.total_paid).toUpperCase()) // shown as the placeholder, in capitals like the print

// Total and total amount paid are always calculated automatically.
watch(
    () => [form.basic_tax_due, form.additional_tax_due, form.item1_due, form.item2_due, form.item3_due, form.interest],
    () => {
        const t = ['basic_tax_due', 'additional_tax_due', 'item1_due', 'item2_due', 'item3_due'].reduce((s, k) => s + num(form[k]), 0)
        form.total = t ? t.toFixed(2) : ''
        form.total_paid = t || num(form.interest) ? (t + num(form.interest)).toFixed(2) : ''
    },
    { immediate: true },
)

// The treasurer's name is remembered for the next cedula.
watch(() => form.treasurer, (v) => localStorage.setItem('cedula.treasurer', v || ''))

// Keeps digits only (TIN, year) or a money amount with at most 2 decimals.
const onDigits = (field, e, max) => {
    const v = e.target.value.replace(/\D/g, '').slice(0, max)
    e.target.value = v
    form[field] = v
}
const onMoney = (field, e) => {
    const parts = e.target.value.replace(/[^\d.]/g, '').split('.')
    const int = parts[0]
    const dec = parts.length > 1 ? parts.slice(1).join('').slice(0, 2) : null
    const v = dec !== null ? `${int}.${dec}` : int
    e.target.value = v
    form[field] = v
}

// Validation banners, like the employee page (they fade after 3 seconds).
let errorTimer
watch(form_errors, (errs) => {
    clearTimeout(errorTimer)
    if (errs.length) errorTimer = setTimeout(() => { form_errors.value = [] }, 3000)
})
watch(dialog, (open) => { if (!open) form_errors.value = [] })

// ── Open the dialog ───────────────────────────────────────────────────────────
const newRecord = () => {
    Object.assign(form, blank())
    editing_id.value = null
    dialog.value = true
}

const editRecord = async (item) => {
    try {
        const r = await api(`/cedula/records/${item.id}`)
        Object.assign(form, fromRecord(r))
        editing_id.value = r.id
        dialog.value = true
    } catch (e) {
        showError('Could not open the record', e.message)
    }
}

// ── View (read-only details) ──────────────────────────────────────────────────
const viewDialog = ref(false)
const viewing = ref(null)

const SEX_LABEL = { M: 'Male', F: 'Female' }
const CIVIL_LABEL = { 1: 'Single', 2: 'Married', 3: 'Widow/er', 4: 'Divorced' }
const peso = (v) => (num(v) ? '₱ ' + num(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '')

const viewRows = computed(() => {
    const d = viewing.value
    if (!d) return []
    const full = [d.first_name, d.middle_name, d.surname].filter(Boolean).join(' ')
    return [
        ['Name', full, true],
        ['CCI2023 Serial No.', d.serial],
        ['Place of Issue', d.place_of_issue],
        ['Date Issued', d.date_issued],
        ['Citizenship', d.citizenship],
        ['TIN', d.tin],
        ['Address', d.address, true],
        ['ICR No.', d.icr_no],
        ['Place of Birth', d.place_of_birth],
        ['Date of Birth', d.date_of_birth],
        ['Height', d.height],
        ['Weight', d.weight],
        ['Sex', SEX_LABEL[d.sex]],
        ['Civil Status', CIVIL_LABEL[d.civil]],
        ['A. Basic community tax', peso(d.basic_tax_due)],
        ['B. Additional community tax', peso(d.additional_tax_due)],
        ['1. Gross receipts (business)', peso(d.item1_due)],
        ['2. Salaries / profession', peso(d.item2_due)],
        ['3. Income from real property', peso(d.item3_due)],
        ['Interest', peso(d.interest)],
        ['Total', peso(d.total)],
        ['Total Amount Paid', peso(d.total_paid)],
        ['In Words', d.in_words || pesoWords(d.total_paid).toUpperCase(), true],
        ['Municipal / City Treasurer', d.treasurer, true],
    ]
})

const viewRecord = async (item) => {
    try {
        const r = await api(`/cedula/records/${item.id}`)
        viewing.value = r
        viewDialog.value = true
    } catch (e) {
        showError('Could not open the record', e.message)
    }
}

// ── Printing: make the PDF and send it to Laravel, which prints it ────────────
// Sex and civil status are saved in the database only, so they are cleared here and no check mark is printed.
const sendToPrinter = async (data) => {
    const items = buildItems({ ...data, year: String(data.date_issued || '').slice(0, 4) }, { offset: OFFSET, upper: UPPER })
    const fd = new FormData()
    fd.append('pdf', makePdf(items, { scale: PRINT_SCALE }).output('blob'), 'cedula.pdf')
    const r = await fetch('/cedula/print', {
        method: 'POST',
        headers: { Accept: 'application/json', ...csrfHeaders() },
        body: fd,
    })
    if (!r.ok) throw new Error((await r.json().catch(() => ({}))).message || r.statusText)
}

// Print an existing record again (the printer icon in the table).
const printRecord = async (item) => {
    try {
        const r = await api(`/cedula/records/${item.id}`)
        await sendToPrinter(fromRecord(r))
        toast('success', 'Sent to printer')
    } catch (e) {
        showError('Could not print', e.message)
    }
}

// Save the record, and print it too when print is true.
const submitForm = async (print) => {
    form_errors.value = []
    if (!String(form.surname).trim()) {
        form_errors.value = [{ message: 'Surname is required', sub: 'Please enter the surname before saving.' }]
        return
    }

    const wasEditing = !!editing_id.value
    saving.value = true
    saveMode.value = print ? 'print' : 'save'
    try {
        const saved = await api('/cedula/records', {
            method: 'POST',
            body: JSON.stringify({ id: editing_id.value, data: { ...form } }),
        })
        editing_id.value = saved.id
    } catch (e) {
        saving.value = false
        showError('Could not save the record', e.message)
        return // nothing is printed when the save fails
    }

    if (print) {
        try {
            await sendToPrinter({ ...form })
            toast('success', 'Saved and sent to printer')
        } catch (e) {
            Swal.fire({
                icon: 'warning',
                title: 'Saved, but not printed',
                text: `${e.message} You can print it again from the table.`,
                confirmButtonText: 'OK',
                confirmButtonColor: '#2d6a4f',
            })
        }
    } else {
        toast('success', 'Saved')
    }
    saving.value = false

    dialog.value = false
    if (!wasEditing) page.value = 1 // edit: stay on the same page, like the employee page
    loadRecords()
}

const saveAndPrint = () => submitForm(true) // Enter key and the Save & Print button
const saveOnly = () => submitForm(false)   // the Save button (new and edit dialog)

// ── Delete ────────────────────────────────────────────────────────────────────
const deleteRecord = (item) => {
    Swal.fire({
        title: 'Are you sure?',
        text: `This will delete ${item.full_name}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'No, cancel',
    }).then(async (result) => {
        if (!result.isConfirmed) return
        try {
            await api(`/cedula/records/${item.id}`, { method: 'DELETE' })
            if (records.value.length === 1 && page.value > 1) page.value--
            loadRecords()
        } catch (e) {
            showError('Could not delete the record', e.message)
        }
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Cedula" />

        <div class="vr-card">
            <!-- Toolbar -->
            <div class="vr-toolbar">
                <div class="vr-toolbar-left">
                    <div class="vr-search-shell">
                        <v-icon size="16" class="vr-search-icon">mdi-magnify</v-icon>
                        <input v-model="search" class="vr-search-input" placeholder="Search by Name or CCI2023 Serial..." />
                        <button v-if="search" type="button" @click="search = ''">
                            <v-icon size="14">mdi-close</v-icon>
                        </button>
                    </div>
                </div>
                <div class="vr-toolbar-right">
                    <button class="vr-new-btn" @click="newRecord">
                        <v-icon size="16">mdi-plus</v-icon>
                        New
                    </button>
                </div>
            </div>
            <div class="vr-divider"></div>

            <!-- Table -->
            <v-data-table-server
                :headers="headers"
                :items="records"
                :loading="loading"
                hover
                class="vr-vtable"
                :items-per-page="itemsPerPage"
                :items-per-page-options="[
                    { value: 10,  title: '10' },
                    { value: 25,  title: '25' },
                    { value: 50,  title: '50' },
                    { value: 100, title: '100' },
                    { value: 500, title: '500' },
                ]"
                :items-length="totalItems"
                :page="page"
                @update:page="onPageChange"
                @update:items-per-page="onItemsPerPageChange"
            >
                <template v-slot:loading>
                    <v-skeleton-loader type="table-row@5"></v-skeleton-loader>
                </template>
                <template v-slot:item.number="{ index }">
                    <span class="vr-td-num">{{ (page - 1) * itemsPerPage + index + 1 }}</span>
                </template>
                <template v-slot:item.full_name="{ item }">
                    <div class="vr-name-cell"><span class="vr-name-text">{{ item.full_name }}</span></div>
                </template>
                <template v-slot:item.serial="{ item }">
                    <div class="vr-name-cell"><span class="vr-name-text">{{ item.serial || '-' }}</span></div>
                </template>
                <template v-slot:item.created_at="{ item }">
                    <div class="vr-name-cell"><span class="vr-name-text">{{ formatDate(item.created_at) }}</span></div>
                </template>
                <template v-slot:item.action="{ item }">
                    <div class="vr-actions">
                        <v-tooltip text="View" location="top">
                            <template #activator="{ props }">
                                <button class="vr-icon-btn vr-icon-btn--green" v-bind="props" @click="viewRecord(item)">
                                    <v-icon size="14">mdi-eye-outline</v-icon>
                                </button>
                            </template>
                        </v-tooltip>
                        <v-tooltip text="Print again" location="top">
                            <template #activator="{ props }">
                                <button class="vr-icon-btn vr-icon-btn--blue" v-bind="props" @click="printRecord(item)">
                                    <v-icon size="14">mdi-printer-outline</v-icon>
                                </button>
                            </template>
                        </v-tooltip>
                        <v-tooltip text="Edit" location="top">
                            <template #activator="{ props }">
                                <button class="vr-icon-btn vr-icon-btn--amber" v-bind="props" @click="editRecord(item)">
                                    <v-icon size="14">mdi-pencil-outline</v-icon>
                                </button>
                            </template>
                        </v-tooltip>
                        <!-- <v-tooltip text="Delete" location="top">
                            <template #activator="{ props }">
                                <button class="vr-icon-btn vr-icon-btn--red" v-bind="props" @click="deleteRecord(item)">
                                    <v-icon size="14">mdi-trash-can-outline</v-icon>
                                </button>
                            </template>
                        </v-tooltip> -->
                    </div>
                </template>
            </v-data-table-server>
        </div>

        <!-- DIALOG: New / Edit Cedula -->
        <v-dialog v-model="dialog" persistent max-width="900">
            <transition name="vr-fade">
                <div v-if="form_errors.length" class="vr-error-banners">
                    <div v-for="(err, i) in form_errors" :key="i" class="vr-error-banner vr-error-banner--red">
                        <v-icon size="15" color="#b91c1c">mdi-information-outline</v-icon>
                        <div class="vr-error-body">
                            <span class="vr-error-title">{{ err.message }}</span>
                            <span class="vr-error-sub">{{ err.sub }}</span>
                        </div>
                    </div>
                </div>
            </transition>
            <div class="vr-dialog">
                <div class="vr-dialog-header">
                    <div class="vr-dialog-icon" :class="{ 'vr-dialog-icon--amber': editing_id }">
                        <v-icon size="18" color="white">{{ editing_id ? 'mdi-pencil-outline' : 'mdi-file-plus-outline' }}</v-icon>
                    </div>
                    <div>
                        <p class="vr-dialog-title">{{ editing_id ? 'Edit Cedula' : 'New Cedula' }}</p>
                        <p class="vr-dialog-sub">Fill in the details, then Save &amp; Print</p>
                    </div>
                    <button class="vr-dialog-close" @click="dialog = false">
                        <v-icon size="18">mdi-close</v-icon>
                    </button>
                </div>
                <div class="vr-dialog-body">
                    <form @submit.prevent="saveAndPrint">
                        <div class="vr-form-grid">
                            <div class="vr-field-group">
                                <label class="vr-label">CCI2023 Serial No. (saved, not printed)</label>
                                <v-text-field clearable placeholder="Enter serial number" variant="outlined"
                                    v-model="form.serial" inputmode="numeric" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Place of Issue</label>
                                <v-text-field clearable placeholder="Polillo, Quezon" variant="outlined"
                                    v-model="form.place_of_issue" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Date Issued</label>
                                <v-text-field type="date" variant="outlined"
                                    v-model="form.date_issued" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Surname<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Dela Cruz" variant="outlined"
                                    v-model="form.surname" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">First Name</label>
                                <v-text-field clearable placeholder="Juan" variant="outlined"
                                    v-model="form.first_name" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Middle Name</label>
                                <v-text-field clearable placeholder="Santos" variant="outlined"
                                    v-model="form.middle_name" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Citizenship</label>
                                <v-text-field clearable placeholder="Filipino" variant="outlined"
                                    v-model="form.citizenship" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group vr-col-full">
                                <label class="vr-label">TIN (leave empty if none, up to 14 digits)</label>
                                <v-text-field clearable placeholder="Numbers only" variant="outlined"
                                    :model-value="form.tin" @input="onDigits('tin', $event, 14)" @click:clear="form.tin = ''"
                                    inputmode="numeric" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group vr-col-full">
                                <label class="vr-label">Address</label>
                                <v-text-field clearable placeholder="Poblacion, Polillo, Quezon" variant="outlined"
                                    v-model="form.address" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">ICR No. (only if an alien)</label>
                                <v-text-field clearable placeholder="Optional" variant="outlined"
                                    v-model="form.icr_no" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Place of Birth</label>
                                <v-text-field clearable placeholder="Manila" variant="outlined"
                                    v-model="form.place_of_birth" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Date of Birth</label>
                                <v-text-field type="date" variant="outlined"
                                    v-model="form.date_of_birth" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Height</label>
                                <v-text-field clearable placeholder="170" variant="outlined"
                                    v-model="form.height" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Weight</label>
                                <v-text-field clearable placeholder="65" variant="outlined"
                                    v-model="form.weight" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group vr-col-full">
                                <span class="vr-label">
                                    Sex
                                    <!-- <span class="vr-note">(saved to the database only, the check mark is not printed on the cedula)</span> -->
                                </span>
                                <div class="d-flex flex-wrap align-center ga-6">
                                    <label class="d-flex align-center ga-2" style="cursor: pointer;">
                                        <input type="radio" name="sex" value="M" v-model="form.sex" style="width: 18px; height: 18px;" />
                                        <span>Male</span>
                                    </label>
                                    <label class="d-flex align-center ga-2" style="cursor: pointer;">
                                        <input type="radio" name="sex" value="F" v-model="form.sex" style="width: 18px; height: 18px;" />
                                        <span>Female</span>
                                    </label>
                                    <label class="d-flex align-center ga-2" style="cursor: pointer;">
                                        <input type="radio" name="sex" value="" v-model="form.sex" style="width: 18px; height: 18px;" />
                                        <span>Blank</span>
                                    </label>
                                </div>
                            </div>

                            <div class="vr-field-group vr-col-full">
                                <span class="vr-label">
                                    Civil Status
                                    <!-- <span class="vr-note">(saved to the database only, the check mark is not printed on the cedula)</span> -->
                                </span>
                                <div class="d-flex flex-wrap align-center ga-6">
                                    <label class="d-flex align-center ga-2" style="cursor: pointer;">
                                        <input type="radio" name="civil" value="1" v-model="form.civil" style="width: 18px; height: 18px;" />
                                        <span>Single</span>
                                    </label>
                                    <label class="d-flex align-center ga-2" style="cursor: pointer;">
                                        <input type="radio" name="civil" value="2" v-model="form.civil" style="width: 18px; height: 18px;" />
                                        <span>Married</span>
                                    </label>
                                    <label class="d-flex align-center ga-2" style="cursor: pointer;">
                                        <input type="radio" name="civil" value="3" v-model="form.civil" style="width: 18px; height: 18px;" />
                                        <span>Widow/er</span>
                                    </label>
                                    <label class="d-flex align-center ga-2" style="cursor: pointer;">
                                        <input type="radio" name="civil" value="4" v-model="form.civil" style="width: 18px; height: 18px;" />
                                        <span>Divorced</span>
                                    </label>
                                    <label class="d-flex align-center ga-2" style="cursor: pointer;">
                                        <input type="radio" name="civil" value="" v-model="form.civil" style="width: 18px; height: 18px;" />
                                        <span>Blank</span>
                                    </label>
                                </div>
                            </div>

                            <div class="vr-col-full vr-section-head">
                                <div class="vr-section-marker"></div>
                                <span class="vr-section-title">Tax due amounts (₱)</span>
                            </div>

                            <div v-for="[k, label] in moneyFields" :key="k" class="vr-field-group">
                                <label class="vr-label">{{ label }}</label>
                                <v-text-field clearable placeholder="0.00" variant="outlined"
                                    :model-value="form[k]" @input="onMoney(k, $event)" @click:clear="form[k] = ''"
                                    inputmode="decimal" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Total (automatic)</label>
                                <v-text-field readonly variant="outlined" v-model="form.total"
                                    density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Total Amount Paid (automatic)</label>
                                <v-text-field readonly variant="outlined" v-model="form.total_paid"
                                    density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group vr-col-full">
                                <label class="vr-label">In Words (filled automatically when blank)</label>
                                <v-text-field clearable variant="outlined" v-model="form.in_words" :placeholder="words"
                                    density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group vr-col-full">
                                <label class="vr-label">Municipal / City Treasurer (remembered for next time)</label>
                                <v-text-field clearable placeholder="Enter treasurer's name" variant="outlined"
                                    v-model="form.treasurer" density="compact" class="vr-vfield" hide-details />
                            </div>
                        </div>

                        <div class="vr-dialog-footer">
                            <button type="button" class="vr-btn vr-btn--plain" @click="dialog = false">Cancel</button>
                            <v-btn
                                type="button"
                                class="vr-btn vr-btn--secondary"
                                :loading="saving && saveMode === 'save'"
                                :disabled="saving"
                                @click="saveOnly"
                            >
                                <v-icon size="14">mdi-content-save-outline</v-icon>
                                Save
                            </v-btn>
                            <v-btn
                                type="submit"
                                class="vr-btn vr-btn--primary"
                                :loading="saving && saveMode === 'print'"
                                :disabled="saving"
                            >
                                <v-icon size="14">mdi-printer-outline</v-icon>
                                Save &amp; Print
                            </v-btn>
                        </div>
                    </form>
                </div>
            </div>
        </v-dialog>

        <!-- DIALOG: View Cedula (read only) -->
        <v-dialog v-model="viewDialog" max-width="700">
            <div class="vr-dialog">
                <div class="vr-dialog-header">
                    <div class="vr-dialog-icon">
                        <v-icon size="18" color="white">mdi-eye-outline</v-icon>
                    </div>
                    <div>
                        <p class="vr-dialog-title">Cedula Details</p>
                        <p class="vr-dialog-sub">View only</p>
                    </div>
                    <button class="vr-dialog-close" @click="viewDialog = false">
                        <v-icon size="18">mdi-close</v-icon>
                    </button>
                </div>
                <div class="vr-dialog-body">
                    <div class="vr-view-grid">
                        <div v-for="[label, value, full] in viewRows" :key="label"
                            class="vr-view-item" :class="{ 'vr-col-full': full }">
                            <span class="vr-label">{{ label }}</span>
                            <span class="vr-view-value">{{ value || '-' }}</span>
                        </div>
                    </div>
                    <div class="vr-dialog-footer">
                        <button type="button" class="vr-btn vr-btn--plain" @click="viewDialog = false">Close</button>
                    </div>
                </div>
            </div>
        </v-dialog>
    </AuthenticatedLayout>
</template>

<style scoped>
    :deep(.v-field input:focus) {
        outline: none !important;
        box-shadow: none !important;
    }

    .vr-vtable :deep(.v-data-table-footer__items-per-page .v-select) {
        min-width: 90px;
    }

    .vr-search-input:focus,
    .vr-search-input:focus-visible {
        outline: none !important;
        border: none !important;
        box-shadow: none !important;
        --tw-ring-shadow: 0 0 #0000 !important;
    }
</style>

<style>
    .vr-vtable .v-data-table-footer__items-per-page {
        min-width: 200px;
    }
    .vr-vtable .v-data-table-footer__items-per-page .v-select {
        min-width: 90px;
    }
    .vr-vtable .v-data-table-footer__items-per-page .v-select .v-field__input {
        min-width: 50px;
        padding-inline: 8px;
    }

    .vr-fade-enter-active { transition: opacity 0.3s ease; }
    .vr-fade-leave-active { transition: opacity 1.5s ease; }
    .vr-fade-enter-from,
    .vr-fade-leave-to { opacity: 0; }

    .vr-card { margin: 10px 30px 24px 30px; }

    #nprogress { display: none; }
    .swal2-container { z-index: 99999 !important; }

    /* Hide Vuetify's own outline, we draw our own border */
    .vr-dialog .v-field__outline { display: none !important; }

    /* Our rounded border on the field */
    .vr-dialog .v-field {
        border: 1px solid #d8e8dc !important;
        border-radius: 12px !important;
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }
    .vr-dialog .v-field.v-field--focused {
        border-color: #10b981 !important;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, .1);
    }

    /* Everything typed in the form is shown in capitals (the print is already in capitals) */
    .vr-dialog .v-field input,
    .vr-dialog .v-field textarea {
        text-transform: uppercase !important;
    }

    /* Native input: no border or ring */
    .vr-dialog .v-field input,
    .vr-dialog .v-field textarea,
    .vr-dialog .v-field input:focus,
    .vr-dialog .v-field textarea:focus {
        border: 0 !important;
        outline: 0 !important;
        box-shadow: none !important;
        background: transparent !important;
        --tw-ring-shadow: 0 0 #0000 !important;
        --tw-ring-offset-shadow: 0 0 #0000 !important;
    }
</style>

<style scoped>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap');

    /* ─── Two-column form grid ───────────────── */
    .vr-form-grid { display: grid; grid-template-columns: 1fr 1fr; column-gap: 20px; }
    .vr-col-full { grid-column: 1 / -1; }
    @media (max-width: 640px) { .vr-form-grid { grid-template-columns: 1fr; } }

    /* ─── Main Card ──────────────────────────── */
    .vr-card {
        background: white;
        border: 1px solid #d8e8dc;
        border-radius: 18px;
        box-shadow: 0 1px 4px rgba(13,33,23,.05), 0 4px 20px rgba(13,33,23,.04);
        overflow: hidden;
        animation: rise .4s .06s ease both;
    }

    /* ─── Toolbar ────────────────────────────── */
    .vr-toolbar {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 12px;
        padding: 14px 20px;
        background: #f7fbf8;
        border-bottom: 1px solid #eaf3ec;
    }
    .vr-toolbar-left, .vr-toolbar-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .vr-search-shell {
        display: flex; align-items: center; gap: 8px;
        background: white;
        border: 1.5px solid #d8e8dc;
        border-radius: 12px;
        padding: 7px 12px;
        min-width: 500px;
        box-shadow: 0 1px 3px rgba(13,33,23,.04);
        transition: border-color .15s, box-shadow .15s;
    }
    .vr-search-shell:focus-within { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.1); }
    .vr-search-icon { color: #7a9e87 !important; flex-shrink: 0; }
    .vr-search-input {
        border: none !important; outline: none !important; background: transparent !important;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .92rem; color: #0d2117; width: 100%;
    }
    .vr-search-input::placeholder { color: #a8c5b0; }
    .vr-new-btn {
        display: inline-flex !important; align-items: center; gap: 6px;
        padding: 9px 18px !important;
        background: #2d6a4f !important;
        color: #ffffff !important;
        border: none !important; border-radius: 14px !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        font-size: .82rem !important; font-weight: 700 !important;
        cursor: pointer; letter-spacing: .2px;
        box-shadow: 0 2px 8px rgba(45,106,79,.3) !important;
        transition: background .15s, transform .1s, box-shadow .15s;
        white-space: nowrap;
    }
    .vr-new-btn:hover { background: #065f46 !important; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(45,106,79,.35) !important; }
    .vr-divider { height: 1px; background: #eaf3ec; }

    /* ─── Table ──────────────────────────────── */
    .vr-vtable :deep(.v-data-table__thead th) {
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        font-size: .66rem !important; font-weight: 700 !important;
        letter-spacing: .9px !important; text-transform: uppercase !important;
        color: #7a9e87 !important; background: #f7fbf8 !important;
        padding: 14px 16px !important; border-bottom: 1px solid #eaf3ec !important;
    }
    .vr-vtable :deep(.v-data-table__tr:hover td) { background: #f5faf6 !important; }
    .vr-vtable :deep(td) {
        padding: 14px 16px !important; border-bottom: 1px solid #f0f7f2 !important;
        font-size: .845rem !important;
    }
    .vr-vtable :deep(.v-data-table__tr:last-child td) { border-bottom: none !important; }
    .vr-td-num { font-size: .75rem; font-weight: 600; color: #2bb853; }
    .vr-name-cell { display: flex; align-items: center; gap: 10px; justify-content: center; }
    .vr-name-text { font-weight: 600; color: #0d2117; font-size: .855rem; text-transform: uppercase; }

    /* Action buttons */
    .vr-actions { display: flex; align-items: center; gap: 6px; justify-content: center; }
    .vr-icon-btn {
        width: 30px; height: 30px;
        display: inline-flex !important; align-items: center; justify-content: center;
        border-radius: 10px !important; border: 1.5px solid transparent !important;
        cursor: pointer; transition: all .15s ease;
    }
    .vr-icon-btn--green { background: #f0fdf4 !important; color: #15803d !important; border-color: #bbf7d0 !important; }
    .vr-icon-btn--green:hover { background: #dcfce7 !important; transform: translateY(-1px); }
    .vr-icon-btn--blue  { background: #eff6ff !important; color: #1d4ed8 !important; border-color: #bfdbfe !important; }
    .vr-icon-btn--blue:hover  { background: #dbeafe !important; transform: translateY(-1px); }
    .vr-icon-btn--amber { background: #fffbeb !important; color: #b45309 !important; border-color: #fde68a !important; }
    .vr-icon-btn--amber:hover { background: #fef3c7 !important; transform: translateY(-1px); }
    .vr-icon-btn--red   { background: #fff5f5 !important; color: #b91c1c !important; border-color: #fecaca !important; }
    .vr-icon-btn--red:hover   { background: #fee2e2 !important; transform: translateY(-1px); }

    /* ─── Dialog ─────────────────────────────── */
    .vr-dialog {
        background: white;
        border-radius: 18px;
        overflow: hidden;
        font-family: 'Plus Jakarta Sans', sans-serif;
        box-shadow: 0 20px 60px rgba(0,0,0,.15);
    }
    .vr-dialog-header {
        display: flex; align-items: center; gap: 14px;
        padding: 20px 24px;
        background: #f7fbf8;
        border-bottom: 1px solid #eaf3ec;
        position: relative;
    }
    .vr-dialog-icon {
        width: 38px; height: 38px; border-radius: 14px;
        background: linear-gradient(135deg, #10b981, #065f46);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        box-shadow: 0 3px 10px rgba(16,185,129,.3);
    }
    .vr-dialog-icon--amber { background: linear-gradient(135deg, #f59e0b, #92400e); box-shadow: 0 3px 10px rgba(245,158,11,.3); }
    .vr-dialog-title { font-size: .95rem; font-weight: 700; color: #0d2117; }
    .vr-dialog-sub   { font-size: .75rem; color: #7a9e87; margin-top: 1px; }
    .vr-dialog-close {
        margin-left: auto;
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        background: white; border: 1px solid #d8e8dc;
        border-radius: 10px; cursor: pointer; color: #7a9e87;
        transition: all .15s;
    }
    .vr-dialog-close:hover { background: #fee2e2; border-color: #fecaca; color: #b91c1c; }
    .vr-dialog-body { padding: 24px; max-height: 70vh; overflow-y: auto; }

    .vr-error-banners { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
    .vr-error-banner {
        display: flex; align-items: flex-start; gap: 10px;
        padding: 11px 14px; border-radius: 14px; border-left: 4px solid;
        font-size: .8rem; line-height: 1.5;
    }
    .vr-error-banner--red { background: #fff5f5; border-color: #b91c1c; color: #7f1d1d; }
    .vr-error-body { display: flex; flex-direction: column; gap: 2px; }
    .vr-error-title { font-weight: 700; font-size: .8rem; display: flex; align-items: center; gap: 6px; }
    .vr-error-sub { font-size: .73rem; opacity: .8; }

    /* ─── Form elements ──────────────────────── */
    .vr-field-group { margin-bottom: 20px; }
    .vr-label { display: block; font-size: .78rem; font-weight: 700; color: #3d5e48; margin-bottom: 6px; letter-spacing: .2px; }
    .vr-note { font-weight: 500; font-size: .72rem; color: #b45309; margin-left: 4px; }
    .vr-req { color: #ef4444; }
    .vr-vfield { font-family: 'Plus Jakarta Sans', sans-serif !important; }
    .vr-section-head { display: flex; align-items: center; gap: 8px; margin: 4px 0 14px; }
    .vr-section-marker { width: 3px; height: 14px; background: linear-gradient(180deg,#10b981,#065f46); border-radius: 3px; flex-shrink: 0; }
    .vr-section-title  { font-size: .82rem; font-weight: 700; color: #0d2117; flex: 1; }

    /* ─── View dialog (read only) ────────────── */
    .vr-view-grid { display: grid; grid-template-columns: 1fr 1fr; column-gap: 20px; row-gap: 14px; }
    .vr-view-item { display: flex; flex-direction: column; }
    .vr-view-item .vr-label { margin-bottom: 2px; }
    .vr-view-value {
        font-size: .88rem; font-weight: 600; color: #0d2117;
        padding: 8px 12px; background: #f7fbf8;
        border: 1px solid #eaf3ec; border-radius: 10px;
        min-height: 36px; word-break: break-word; text-transform: uppercase;
    }
    @media (max-width: 640px) { .vr-view-grid { grid-template-columns: 1fr; } }

    /* Dialog footer */
    .vr-dialog-footer {
        display: flex; align-items: center; justify-content: flex-end; gap: 10px;
        padding-top: 20px;
        border-top: 1px solid #eaf3ec;
        margin-top: 20px;
    }
    .vr-btn {
        display: inline-flex !important; align-items: center; gap: 6px;
        padding: 8px 18px !important; border-radius: 12px !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        font-size: .82rem !important; font-weight: 700 !important;
        cursor: pointer; transition: all .15s ease;
        white-space: nowrap;
    }
    .vr-btn--plain {
        background: transparent !important; color: #7a9e87 !important;
        border: 1.5px solid #d8e8dc !important;
    }
    .vr-btn--plain:hover { background: #f7fbf8 !important; color: #3d5e48 !important; }
    .vr-btn--secondary {
        background: #ffffff !important; color: #2d6a4f !important;
        border: 1.5px solid #2d6a4f !important;
    }
    .vr-btn--secondary:hover { background: #f0fdf4 !important; transform: translateY(-1px); }
    .vr-btn--secondary:disabled { opacity: .5; cursor: not-allowed; transform: none !important; }
    .vr-btn--primary {
        background: #2d6a4f !important; color: #ffffff !important;
        border: none !important;
        box-shadow: 0 2px 8px rgba(45,106,79,.3) !important;
    }
    .vr-btn--primary:hover { background: #065f46 !important; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(45,106,79,.35) !important; }
    .vr-btn--primary:disabled { opacity: .5; cursor: not-allowed; transform: none !important; }

    /* ─── Animation ───────────────────────────── */
    @keyframes rise {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
        .vr-toolbar { flex-direction: column; align-items: stretch; }
        .vr-toolbar-left, .vr-toolbar-right { width: 100%; }
        .vr-search-shell { min-width: unset; width: 100%; }
    }
</style>
