<script setup>
    import { Head, router, useForm, usePage } from '@inertiajs/vue3';
    import { nextTick, ref, watch, computed } from 'vue';
    import ExportSearchWrapper from '@/Components/ExportSearchWrapper.vue';
    import ExportButton from '@/Components/ExportButton.vue';
    import { exportExcel } from '@/Utils/ExcelGenerator';
    import Swal from 'sweetalert2';
    import TextInput from '@/Components/TextInput.vue';
    import SelectInput from '@/Components/SelectInput.vue';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

    const page = usePage()

    // ── Search ────────────────────────────────────────────────────────────────
    const search = ref(page.props.filters?.search ?? '')

    // ── Search ────────────────────────────────────────────────────────────────
    const itemsPerPage = computed(() => page.props.records.per_page)
    const totalItems   = computed(() => page.props.records.total)
    const currentPage  = computed(() => page.props.records.current_page)

    // ── Custom Creation Location/Status/RentalType ────────────────────────────────────────────────────────────────
    const newLocation = ref('');
    const newStatus = ref('');
    const newRental = ref('');

    //Hide the INPUT BOX ON THE SELECT OPTION (DROPDOWN)
    const showNewLocation = ref(false);
    const showNewStatus = ref(false);
    const showNewRental = ref(false);

    // ── Unhide Text-Field for Location/Status/RentalType ────────────────────────────────────────────────────────────────
    const handleRentalChange = () => {
        showNewRental.value = add_deceased.rental_type === 'add_rental';

        if (!showNewRental.value) {
            newRental.value = '';
        }
    };

    const handleStatusChange = () => {
        showNewStatus.value = add_deceased.status === 'add_status';

        if (!showNewStatus.value) {
            newStatus.value = '';
        }
    };

    const handleLocationChange = () => {
        showNewLocation.value = add_deceased.location === 'add_location';

        if (!showNewLocation.value) {
            newLocation.value = '';
        }
    };

    // ── Fetch Records for the Next Page ────────────────────────────────────────────────────────────────
    function fetchRecords(extra = {}) {
        router.get('/employee/page', {
            search: search.value || undefined,
            per_page: itemsPerPage.value,
            ...extra,
        }, { preserveState: true, preserveScroll: true, replace: true })
    }

    const onPageChange = (p) => fetchRecords({ page: p })
    const onItemsPerPageChange = (n) => fetchRecords({ per_page: n, page: 1 })

    // ── Speed of the Search ────────────────────────────────────────────────────────────────
    let timer
    watch(search, () => {
        clearTimeout(timer)
        timer = setTimeout(() => fetchRecords({ page: 1 }), 200)
    })

    // re-run the current search/page (use after create/update/delete)
    const refreshSearch = () => fetchRecords({ page: currentPage.value })

    // ── Dialog state ──────────────────────────────────────────────────────────
    const dialog      = ref(false)
    const edit_dialog = ref(false)

    // ── Export popup ─────────────────────────────────────────
    const export_dialog  = ref(false)
    const export_all     = ref(true)    // default = All
    const export_year    = ref('year_covered')
    const export_from    = ref(null)
    const export_to      = ref(null)
    const exporting      = ref(false)

    const thisYear = new Date().getFullYear()
    const years = Array.from({ length: thisYear - 1994 }, (_, i) => thisYear + 5 - i)   // 2026 down to 2000

    // To-list only shows years from "From" upward
    const toYears = computed(() =>
        export_from.value ? years.filter(y => y >= export_from.value) : years
    )

    // Picking From auto-fills To with the same year
    watch(export_from, (y) => {
        if (y && (!export_to.value || export_to.value < y)) export_to.value = y
    })

    // Checking All clears the years
    watch(export_all, (v) => {
        if (v) { export_from.value = null; export_to.value = null; export_year.value = null } else { export_year.value = 'year_covered' }
    })

    // Every time the popup opens, start fresh with All
    watch(export_dialog, (open) => {
        if (open) {
            export_all.value  = true
            export_year.value = null
            export_from.value = null
            export_to.value   = null
        }
    })

    // Going to backend for starting export excel
    const runExport = () => {
        const params = export_all.value
            ? { all: 1 }
            : { export_year: export_year.value, from: export_from.value, to: export_to.value }

        window.location.href = route('employee.export', params)
        export_dialog.value = false
    }

    // ── Today's date (local, YYYY-MM-DD) ───────────────────────────────────
    const todayStr_now = () => new Date(
        Date.now() - new Date().getTimezoneOffset() * 60000
    ).toISOString().slice(0, 10)

    const todayStr = todayStr_now()      // still used by :max="todayStr" in the template

    // Every time the New Record dialog opens, set date of death to today
    watch(dialog, (open) => {
        if (open) add_deceased.date_of_death = todayStr_now()
    })

    // ── Create Deseased form ───────────────────────────────────────────────────
    const add_deceased = useForm({
        surname:            '',
        givenname:          '',
        middlename:         '',
        suffix:             '',
        name_of_payor:      '',
        or_number:          '',
        rental_type:        '',
        status:             '',
        location:           '',
        date_of_death:      todayStr,
        date_of_payment:    '',
        year_covered:       '',
        amount_due:         '',
        remarks:            '',
    })

    // ── Edit Deseased Records ─────────────────────────────────────────────────────
    const edit_deseased_records = useForm({
        id:              '',
        surname:         '',
        givenname:       '',
        middlename:      '',
        suffix:          '',
        name_of_payor:   '',
        or_number:       '',
        rental_id:       '',
        status_id:       '',
        location_id:     '',
        date_of_death:   '',
        date_of_payment: '',
        year_covered:    '',
        amount_due:      '',
        remarks:         '',
    })

    // For required input that is empty or forgot to add a data
    const validateRecord = (data) => {
        const errs = []

        if (data.date_of_death && data.date_of_death > todayStr) {
            errs.push('Date of death cannot be later than today.')
        }

        if (data.or_number && !/^\d+$/.test(String(data.or_number).trim())) {
            errs.push('OR number must contain numbers only (no letters or symbols).')
        }

        if (data.year_covered) {
            const y = String(data.year_covered).trim()
            if (!/^\d{4}(\s*-\s*\d{4})?$/.test(y)) {
                errs.push('Year covered must be a year (2023) or a range (2023 - 2026).')
            } else {
                const yrs = y.match(/\d{4}/g).map(Number)
                if (yrs.length === 2 && yrs[1] < yrs[0]) errs.push('The end year must not be earlier than the start year.')
            }
        }

        return errs
    }

    const showRuleAlert = (errs) =>
        Swal.fire({
            icon: 'error',
            title: 'Please check your input',
            html: '<ul style="text-align:left;margin:0;padding-left:18px">'
                + errs.map(e => `<li>${e}</li>`).join('')
                + '</ul>',
            confirmButtonText: 'OK',
            confirmButtonColor: '#2d6a4f',
        })


    // small toast when someone types a letter in a numbers-only field
    const warnNumbersOnly = (label) =>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'warning',
            title: `${label}: numbers only`,
            showConfirmButton: false,
            timer: 1800,
        })

    const makeDigitCleaner = (form, field, label, maxLen = null) => (e) => {
        const raw = e.target.value
        let cleaned = raw.replace(/\D/g, '')
        if (maxLen) cleaned = cleaned.slice(0, maxLen)
        if (/\D/.test(raw)) warnNumbersOnly(label)
        e.target.value = cleaned
        form[field] = cleaned
    }

    const makeYearRangeCleaner = (form) => (e) => {
        const raw = e.target.value
        const cleaned = raw.replace(/[^\d\s-]/g, '').slice(0, 11)
        if (raw !== cleaned) {
            Swal.fire({
                toast: true, position: 'top-end', icon: 'warning',
                title: 'Year covered: numbers and "-" only',
                showConfirmButton: false, timer: 1800,
            })
        }
        e.target.value = cleaned
        form.year_covered = cleaned
    }

    const formatMoney = (v) => {
        if (v === null || v === undefined || v === '') return ''
        return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    }

    const formatAmountInput = (v) => {
        if (v === null || v === undefined || v === '') return ''
        const [int, dec] = String(v).split('.')
        const withCommas = (int || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',')
        return dec !== undefined ? `${withCommas}.${dec}` : withCommas
    }

    const makeAmountCleaner = (form) => (e) => {
        const raw   = e.target.value.replace(/[^\d.]/g, '')
        const parts = raw.split('.')
        let int     = parts[0].replace(/^0+(?=\d)/, '')
        const dec   = parts.length > 1 ? parts.slice(1).join('').slice(0, 2) : null
        if (int === '' && dec !== null) int = '0'
        form.amount_due = dec !== null ? `${int}.${dec}` : int
        e.target.value  = formatAmountInput(form.amount_due)
    }

    const cleanAmountAdd  = makeAmountCleaner(add_deceased)
    const cleanAmountEdit = makeAmountCleaner(edit_deseased_records)
    const cleanYear     = makeYearRangeCleaner(add_deceased)
    const cleanEditYear = makeYearRangeCleaner(edit_deseased_records)
    const cleanOrAdd    = makeDigitCleaner(add_deceased,          'or_number',    'OR number')
    const cleanOrEdit   = makeDigitCleaner(edit_deseased_records, 'or_number',    'OR number')

    // ── Edit Deseased Records ───────────────────────────────────────────────────
    const edit_record = (item) => {
        edit_deseased_records.id              = item.id
        edit_deseased_records.surname         = item.surname
        edit_deseased_records.givenname       = item.givenname
        edit_deseased_records.middlename      = item.middlename ?? ''
        edit_deseased_records.suffix          = item.suffix ?? ''
        edit_deseased_records.name_of_payor   = item.name_of_payor
        edit_deseased_records.date_of_payment = item.date_of_payment
        edit_deseased_records.or_number       = item.or_number
        edit_deseased_records.rental_id       = item.rental_id
        edit_deseased_records.status_id       = item.status_id
        edit_deseased_records.location_id     = item.location_id ?? ''
        edit_deseased_records.date_of_death   = String(item.date_of_death ?? '').slice(0, 10)
        edit_deseased_records.year_covered    = item.year_covered
        edit_deseased_records.amount_due      = item.amount_due
        edit_deseased_records.remarks         = item.remarks ?? ''
        edit_dialog.value = true
    }

    // ── Delete Deseased Records (Disabled) ───────────────────────────────────────────────────
    const delete_record = (item) => {
        Swal.fire({
            title: 'Are you sure?',
            text: `This will delete ${item.deseased_name}.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'No, cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                router.delete(route('employee.deseased_delete', item.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        // remove it from the search results right away
                        //records.value = records.value.filter(x => x.id !== item.id)

                        // re-run the current search so the list matches the DB
                        refreshSearch()
                    },
                })
            }
        })
    }

    const form_errors = ref([])

    const required_fields = {
        surname:         'Surname',
        givenname:       'Given Name',
        name_of_payor:   'Name of Payor',
        date_of_payment: 'Date of Payment',
        or_number:       'OR Number',
        rental_type:     'Rental Type',
        status:          'Status',
        year_covered:    'Year Covered',
        amount_due:      'Amount Due',
    }

    let errorTimer
    watch(form_errors, (errs) => {
        clearTimeout(errorTimer)
        if (errs.length) {
            errorTimer = setTimeout(() => {
                form_errors.value = []
            }, 3000)
        }
    })

    // Saving New Deseased Record
    const submitDeceased = () => {
        form_errors.value = []
        const errs = []

        const adding = {
            rental_type: add_deceased.rental_type === 'add_rental',
            status:      add_deceased.status      === 'add_status',
            location:    add_deceased.location    === 'add_location',
        }
        const typed = {
            rental_type: newRental.value?.trim()   ?? '',
            status:      newStatus.value?.trim()   ?? '',
            location:    newLocation.value?.trim() ?? '',
        }

        for (const [field, label] of Object.entries(required_fields)) {
            const value = adding[field] ? typed[field] : add_deceased[field]
            if (value === null || value === undefined || String(value).trim() === '') {
                errs.push({
                    type: 'required', field,
                    message: `${label} is required`,
                    sub: `Please enter the ${label.toLowerCase()} before saving.`,
                })
            }
        }

        // Location is optional, but if "ADD LOCATION" is picked the box must be filled
        if (adding.location && !typed.location) {
            errs.push({
                type: 'required', field: 'location',
                message: 'Location is required',
                sub: 'Type the new location or choose another option.',
            })
        }

        if (errs.length) {
            form_errors.value = errs
            return
        }

        const ruleErrs = validateRecord(add_deceased)
        if (ruleErrs.length) {
            showRuleAlert(ruleErrs)
            return
        }

        add_deceased
            .transform((data) => ({
                ...data,
                rental_type:     adding.rental_type ? '' : data.rental_type,
                status:          adding.status      ? '' : data.status,
                location:        adding.location    ? '' : data.location,
                new_rental_type: adding.rental_type ? typed.rental_type : '',
                new_status:      adding.status      ? typed.status      : '',
                new_location:    adding.location    ? typed.location    : '',
            }))
            .post(route('employee.deseased_new_record'), {
                onSuccess: () => {
                    add_deceased.reset()
                    dialog.value = false
                    newRental.value = newStatus.value = newLocation.value = ''
                    showNewRental.value = showNewStatus.value = showNewLocation.value = false
                    form_errors.value = []
                },
            })
    }

    watch(dialog, (val) => { if (!val) form_errors.value = [] })

    // Saving Edited Deseased Record
    const submitEditedRecord = () => {
        const ruleErrs = validateRecord(edit_deseased_records)
        if (ruleErrs.length) {
            showRuleAlert(ruleErrs)
            return
        }
        edit_deseased_records.put(route('desease.update_record', edit_deseased_records.id), {
            preserveScroll: true,
            onSuccess: () => {
                edit_deseased_records.reset()
                edit_dialog.value = false
                refreshSearch()
            },
        })
    }

</script>

<template>
    <AuthenticatedLayout>
        <div class="vr-card">
            <!-- Toolbar -->
            <div class="vr-toolbar">
                <div class="vr-toolbar-left">
                    <div class="vr-search-shell">
                    <v-icon size="16" class="vr-search-icon">mdi-magnify</v-icon>
                    <input v-model="search" class="vr-search-input" placeholder="Search by Name, OR Number, Payor, Status, Indexed By..." />
                    <button v-if="search" type="button" @click="search = ''">
                        <v-icon size="14">mdi-close</v-icon>
                    </button>
                </div>
                </div>
                <div class="vr-toolbar-right">
                    <button class="vr-new-btn" @click="dialog = true">
                        <v-icon size="16">mdi-plus</v-icon>
                        New Record
                    </button>
                    <button class="vr-export-btn" @click="export_dialog=true">
                        <v-icon size="16">mdi-microsoft-excel</v-icon> Excel
                    </button>
                </div>
            </div>
            <div class="vr-divider"></div>
            <!-- Table -->
            <v-data-table-server
                :headers="header"
                :items="$page.props.records.data"
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
                :page="currentPage"
                @update:page="onPageChange"
                @update:items-per-page="onItemsPerPageChange"
            >
                <template v-slot:loading>
                    <v-skeleton-loader type="table-row@5"></v-skeleton-loader>
                </template>
                <template v-slot:item.number="{ index }">
                    <span class="vr-td-num">
                        {{ ($page.props.records.current_page - 1) * $page.props.records.per_page + index + 1 }}
                    </span>
                </template>
                <template v-slot:item.deseased_name="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.deseased_name }}</span>
                    </div>
                </template>
                <template v-slot:item.date_of_death="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.date_of_death }}</span>
                    </div>
                </template>
                <template v-slot:item.or_number="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.or_number }}</span>
                    </div>
                </template>
                <template v-slot:item.name_of_payor="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.name_of_payor }}</span>
                    </div>
                </template>
                <template v-slot:item.date_of_payment="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.date_of_payment }}</span>
                    </div>
                </template>
                <template v-slot:item.rental_type="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.rental_type }}</span>
                    </div>
                </template>
                <template v-slot:item.location="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.location }}</span>
                    </div>
                </template>
                <template v-slot:item.year_covered="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.year_covered }}</span>
                    </div>
                </template>
                <template v-slot:item.amount_due="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ formatMoney(item.amount_due) }}</span>
                    </div>
                </template>
                <template v-slot:item.status="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.status }}</span>
                    </div>
                </template>
                <template v-slot:item.remarks="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.remarks }}</span>
                    </div>
                </template>
                <template v-slot:item.encoded_by="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.encoded_by }}</span>
                    </div>
                </template>
                <template v-slot:item.action="{ item }">
                    <div class="vr-actions">
                        <v-tooltip text="Edit" location="top">
                            <template #activator="{ props }">
                                <button class="vr-icon-btn vr-icon-btn--amber" v-bind="props" @click="edit_record(item)">
                                    <v-icon size="14">mdi-pencil-outline</v-icon>
                                </button>
                            </template>
                        </v-tooltip>
                    </div>
                </template>
            </v-data-table-server>
        </div>

        <!-- DIALOG: Excel Export -->
        <v-dialog v-model="export_dialog" max-width="510">
            <div class="vr-dialog">
                <div class="vr-dialog-header">
                    <div class="vr-dialog-icon">
                        <v-icon size="18" color="white">mdi-microsoft-excel</v-icon>
                    </div>
                    <div>
                        <p class="vr-dialog-title">Export to Excel</p>
                        <p class="vr-dialog-sub">Choose which records to export</p>
                    </div>
                    <button class="vr-dialog-close" @click="export_dialog = false">
                        <v-icon size="18">mdi-close</v-icon>
                    </button>
                </div>
                <div class="vr-dialog-body">
                    <label class="d-flex align-center ga-2 mb-4" style="cursor: pointer;">
                        <input type="checkbox" v-model="export_all" style="width: 18px; height: 18px;" />
                        <span>All Records</span>
                    </label>

                    <fieldset
                        :disabled="export_all"
                        :style="{ border: 0, padding: 0, margin: 0, minWidth: 0, opacity: export_all ? 0.45 : 1 }"
                    >

                        <div class="d-flex flex-wrap align-center ga-6 mb-4">
                            <label class="d-flex align-center ga-2" style="cursor: pointer;">
                                <input type="radio" name="export_year" value="year_covered"
                                    v-model="export_year" style="width: 18px; height: 18px;" />
                                <span>Year Covered</span>
                            </label>

                            <label class="d-flex align-center ga-2" style="cursor: pointer;">
                                <input type="radio" name="export_year" value="date_of_death"
                                    v-model="export_year" style="width: 18px; height: 18px;" />
                                <span>Date of Death</span>
                            </label>

                            <label class="d-flex align-center ga-2" style="cursor: pointer;">
                                <input type="radio" name="export_year" value="date_of_payment"
                                    v-model="export_year" style="width: 18px; height: 18px;" />
                                <span>Date of Payment</span>
                            </label>
                        </div>

                        <div class="vr-form-grid">
                            <div class="vr-field-group">
                                <label class="vr-label">From</label>
                                <div class="vr-select-shell">
                                    <SelectInput class="vr-sel" v-model="export_from" :bordered="false">
                                        <option :value="null">Select year</option>
                                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                                    </SelectInput>
                                </div>
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">To</label>
                                <div class="vr-select-shell">
                                    <SelectInput class="vr-sel" v-model="export_to" :bordered="false" :disabled="!export_from">
                                        <option :value="null">Select year</option>
                                        <option v-for="y in toYears" :key="y" :value="y">{{ y }}</option>
                                    </SelectInput>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <div class="vr-dialog-footer">
                        <button type="button" class="vr-btn vr-btn--plain" @click="export_dialog = false">Cancel</button>
                        <v-btn
                            class="vr-btn vr-btn--primary"
                            :loading="exporting"
                            :disabled="!export_all && !export_from"
                            @click="runExport"
                        >
                            <v-icon size="16">mdi-download</v-icon>
                            Export
                        </v-btn>
                    </div>
                </div>
            </div>
        </v-dialog>

        <!-- DIALOG: New Voucher -->
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
                    <div class="vr-dialog-icon">
                        <v-icon size="18" color="white">mdi-file-plus-outline</v-icon>
                    </div>
                    <div>
                        <p class="vr-dialog-title">New Record</p>
                        <p class="vr-dialog-sub">Register a new deseased record</p>
                    </div>
                    <button class="vr-dialog-close" @click="dialog = false">
                        <v-icon size="18">mdi-close</v-icon>
                    </button>
                </div>
                <div class="vr-dialog-body">
                    <form @submit.prevent="submitDeceased">
                        <div class="vr-form-grid">
                            <!-- Row 1 -->
                            <div class="vr-field-group">
                                <label class="vr-label">Date of Death<span class="vr-req">*</span></label>
                                <v-text-field type="date" :max="todayStr" clearable variant="outlined"
                                    v-model="add_deceased.date_of_death" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">OR Number<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Enter OR Number" variant="outlined"
                                    :model-value="add_deceased.or_number" @input="cleanOrAdd"
                                    inputmode="numeric" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <!-- Row 2 -->
                            <div class="vr-field-group">
                                <label class="vr-label">Surname<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Dela Cruz" variant="outlined"
                                    v-model="add_deceased.surname" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Rental Type<span class="vr-req">*</span></label>
                                <div class="vr-select-shell">
                                    <SelectInput class="vr-sel" v-model="add_deceased.rental_type" :bordered="false" @change="handleRentalChange">
                                        <option value="">Select Rental Type</option>
                                        <option v-for="c in $page.props.rental" :key="c.id" :value="c.id">{{ c.rental_type }}</option>
                                        <option value="add_rental">ADD STATUS</option>
                                    </SelectInput>
                                </div>
                                <v-text-field v-if="showNewRental" v-model="newRental" clearable placeholder="New Rental Type" variant="outlined"
                                    density="compact" class="vr-vfield mt-2" hide-details />
                            </div>

                            <!-- Row 3 -->
                            <div class="vr-field-group">
                                <label class="vr-label">Given Name<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Juan" variant="outlined"
                                    v-model="add_deceased.givenname" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Status<span class="vr-req">*</span></label>
                                <div class="vr-select-shell">
                                    <SelectInput class="vr-sel" v-model="add_deceased.status" :bordered="false" @change="handleStatusChange">
                                        <option value="">Select Status</option>
                                        <option v-for="c in $page.props.status" :key="c.id" :value="c.id">{{ c.status }}</option>
                                        <option value="add_status">ADD STATUS</option>
                                    </SelectInput>
                                </div>
                                <v-text-field v-if="showNewStatus" v-model="newStatus" clearable placeholder="New Status" variant="outlined"
                                    density="compact" class="vr-vfield mt-2" hide-details />
                            </div>

                            <!-- Row 4 -->
                            <div class="vr-field-group">
                                <label class="vr-label">Middle Name</label>
                                <v-text-field clearable placeholder="Alicia" variant="outlined"
                                    v-model="add_deceased.middlename" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Year Covered<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Ex. 2026 or 2023 - 2026" variant="outlined"
                                    :model-value="add_deceased.year_covered" @input="cleanYear"
                                    maxlength="11" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <!-- Row 5 -->
                            <div class="vr-field-group">
                                <label class="vr-label">Suffix (Optional)</label>
                                <v-text-field clearable placeholder="Jr., Sr., II, III, IV, V" variant="outlined"
                                    v-model="add_deceased.suffix" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Amount Due<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Enter Amount Due" variant="outlined"
                                    :model-value="formatAmountInput(add_deceased.amount_due)" @input="cleanAmountAdd"
                                    @click:clear="add_deceased.amount_due = ''"
                                    inputmode="decimal" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <!-- Row 6 -->
                            <div class="vr-field-group">
                                <label class="vr-label">Name of Payor<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Enter Name of Payor" variant="outlined"
                                    v-model="add_deceased.name_of_payor" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Date of Payment<span class="vr-req">*</span></label>
                                <v-text-field type="date" clearable placeholder="Enter Date of Payment" variant="outlined"
                                    v-model="add_deceased.date_of_payment" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <!-- Row 7: Location on the left -->
                            <div class="vr-field-group">
                                <label class="vr-label">Location (optional)</label>
                                <div class="vr-select-shell">
                                    <SelectInput class="vr-sel" v-model="add_deceased.location" :bordered="false" @change="handleLocationChange">
                                        <option value="">Select Location</option>
                                        <option v-for="c in $page.props.location" :key="c.id" :value="c.id">{{ c.location }}</option>
                                        <option value="add_location">ADD LOCATION</option>
                                    </SelectInput>
                                </div>
                                <v-text-field v-if="showNewLocation" v-model="newLocation" clearable placeholder="New Location" variant="outlined"
                                    density="compact" class="vr-vfield mt-2" hide-details />
                            </div>

                            <!-- Row 8: Remarks below -->
                            <div class="vr-field-group vr-col-full">
                                <label class="vr-label">Remarks (Optional)</label>
                                <v-textarea clearable placeholder="Enter Remarks" variant="outlined"
                                    v-model="add_deceased.remarks" density="compact" class="vr-vfield" hide-details />
                            </div>
                        </div>

                        <div class="vr-dialog-footer">
                            <button type="button" class="vr-btn vr-btn--plain" @click="dialog = false">Cancel</button>
                            <v-btn
                                type="submit"
                                class="vr-btn vr-btn--primary"
                                :loading="add_deceased.processing"
                                :disabled="add_deceased.processing"
                                :class="{ 'opacity-50': add_deceased.processing }"
                            >
                                <v-icon size="14">mdi-content-save-outline</v-icon>
                                Save Record
                            </v-btn>
                        </div>
                    </form>
                </div>
            </div>
        </v-dialog>

        <!-- DIALOG: Edit Voucher -->
        <v-dialog v-model="edit_dialog" persistent max-width="900">
            <div class="vr-dialog">
                <div class="vr-dialog-header">
                    <div class="vr-dialog-icon vr-dialog-icon--amber">
                        <v-icon size="18" color="white">mdi-pencil-outline</v-icon>
                    </div>
                    <div>
                        <p class="vr-dialog-title">Edit Desease Records</p>
                        <p class="vr-dialog-sub">Update Records</p>
                    </div>
                    <button class="vr-dialog-close" @click="edit_dialog = false">
                        <v-icon size="18">mdi-close</v-icon>
                    </button>
                </div>
                <div class="vr-dialog-body">
                    <form @submit.prevent="submitEditedRecord">
                        <div v-if="Object.keys(edit_deseased_records.errors).length" class="vr-error-banners">
                            <div v-for="(msg, field) in edit_deseased_records.errors" :key="field" class="vr-error-banner vr-error-banner--red">
                                <v-icon size="15" color="#b91c1c">mdi-information-outline</v-icon>
                                <div class="vr-error-body">
                                    <span class="vr-error-title">{{ msg }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="vr-form-grid">
                            <div class="vr-field-group">
                                <label class="vr-label">Date of Death<span class="vr-req">*</span></label>
                                <v-text-field type="date" :max="todayStr" clearable variant="outlined"
                                    v-model="edit_deseased_records.date_of_death" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">OR Number<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Enter OR Number" variant="outlined"
                                    :model-value="edit_deseased_records.or_number" @input="cleanOrEdit"
                                    inputmode="numeric" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Surname<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Enter Surname" variant="outlined"
                                    v-model="edit_deseased_records.surname" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Rental Type<span class="vr-req">*</span></label>
                                <div class="vr-select-shell">
                                    <SelectInput class="vr-sel" v-model="edit_deseased_records.rental_id" :bordered="false">
                                        <option value="">Select Rental Type</option>
                                        <option v-for="c in $page.props.rental" :key="c.id" :value="c.id">{{ c.rental_type }}</option>
                                    </SelectInput>
                                </div>
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Given Name<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Enter Given Name" variant="outlined"
                                    v-model="edit_deseased_records.givenname" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Status<span class="vr-req">*</span></label>
                                <div class="vr-select-shell">
                                    <SelectInput class="vr-sel" v-model="edit_deseased_records.status_id" :bordered="false">
                                        <option v-for="c in $page.props.status" :key="c.id" :value="c.id">{{ c.status }}</option>
                                    </SelectInput>
                                </div>
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Middle Name</label>
                                <v-text-field clearable placeholder="Enter Middle Name" variant="outlined"
                                    v-model="edit_deseased_records.middlename" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Year Covered<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Ex. 2026 or 2023 - 2026" variant="outlined"
                                    :model-value="edit_deseased_records.year_covered" @input="cleanEditYear"
                                    maxlength="11" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Suffix (Optional)</label>
                                <v-text-field clearable placeholder="Jr., Sr., II, III, IV, V" variant="outlined"
                                    v-model="edit_deseased_records.suffix" density="compact" class="vr-vfield" hide-details />
                            </div>
                            <div class="vr-field-group">
                                <label class="vr-label">Amount Due<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Enter Amount Due" variant="outlined"
                                    :model-value="formatAmountInput(edit_deseased_records.amount_due)" @input="cleanAmountEdit"
                                    @click:clear="edit_deseased_records.amount_due = ''"
                                    inputmode="decimal" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Name of Payor<span class="vr-req">*</span></label>
                                <v-text-field clearable placeholder="Enter Name of Payor" variant="outlined"
                                    v-model="edit_deseased_records.name_of_payor" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <div class="vr-field-group">
                                <label class="vr-label">Date of Payment<span class="vr-req">*</span></label>
                                <v-text-field type="date" clearable placeholder="Enter Date of Payment" variant="outlined"
                                    v-model="edit_deseased_records.date_of_payment" density="compact" class="vr-vfield" hide-details />
                            </div>

                            <!-- Row 7: Location on the left -->
                            <div class="vr-field-group">
                                <label class="vr-label">Location (optional)</label>
                                <div class="vr-select-shell">
                                    <SelectInput class="vr-sel" v-model="edit_deseased_records.location_id" :bordered="false">
                                        <option value="">Select Location</option>
                                        <option v-for="c in $page.props.location" :key="c.id" :value="c.id">{{ c.location }}</option>
                                    </SelectInput>
                                </div>
                                <v-text-field v-if="showNewLocation" v-model="newLocation" clearable placeholder="New Location" variant="outlined"
                                    density="compact" class="vr-vfield mt-2" hide-details />
                            </div>

                            <div class="vr-field-group vr-col-full">
                                <label class="vr-label">Remarks (Optional)</label>
                                <v-textarea clearable placeholder="Enter Remarks" variant="outlined"
                                    v-model="edit_deseased_records.remarks" density="compact" class="vr-vfield" hide-details />
                            </div>
                        </div>


                        <div class="vr-dialog-footer">
                            <button type="button" class="vr-btn vr-btn--plain" @click="edit_dialog = false">Cancel</button>
                            <v-btn
                                type="submit"
                                class="vr-btn vr-btn--primary"
                                :loading="edit_deseased_records.processing"
                                :disabled="edit_deseased_records.processing"
                                :class="{ 'opacity-50': edit_deseased_records.processing }"
                            >
                                <v-icon size="14">mdi-content-save-outline</v-icon>
                                Edit Record
                            </v-btn>
                        </div>
                    </form>
                </div>
            </div>
        </v-dialog>
    </AuthenticatedLayout>
</template>

<script>
    import SelectInput from '@/Components/SelectInput.vue';
    import { useFlashWatcher } from '@/Utils/useFlashWatcher';
    export default {
        data() {
            return {
                header: [
                    { title: '#',               align: 'center',      key: 'number',          sortable: false },
                    { title: 'Deceased Name',   align: 'center',      key: 'deseased_name',   sortable: false },
                    { title: 'Date of Death',   align: 'center',      key: 'date_of_death',   sortable: false },
                    { title: 'OR Number',       align: 'center',      key: 'or_number',       sortable: false },
                    { title: 'Name of Payor',   align: 'center',      key: 'name_of_payor',   sortable: false },
                    { title: 'Date of Payment', align: 'center',      key: 'date_of_payment', sortable: false },
                    { title: 'Rental Type',     align: 'center',      key: 'rental_type',     sortable: false },
                    { title: 'Location',        align: 'center',      key: 'location',        sortable: false },
                    { title: 'Year Covered',    align: 'center',      key: 'year_covered',    sortable: false },
                    { title: 'Amount Due',      align: 'center',      key: 'amount_due',      sortable: false },
                    { title: 'Status',          align: 'center',      key: 'status',          sortable: false },
                    { title: 'Remarks',         align: 'center',      key: 'remarks',         sortable: false },
                    { title: 'Indexed By',      align: 'center',      key: 'encoded_by',      sortable: false },
                    { title: 'Action',          align: 'center',      key: 'action',          sortable: false },
                ]
            }
        },
    }
</script>

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

    .vr-select-shell :deep(select),
    .vr-select-shell :deep(select:focus),
    .vr-select-shell :deep(select:focus-visible) {
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
        --tw-ring-shadow: 0 0 #0000 !important;
        --tw-ring-offset-shadow: 0 0 #0000 !important;
        background-color: transparent !important;
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

    .vr-fade-enter-active {
        transition: opacity 0.3s ease;
    }
    .vr-fade-leave-active {
        transition: opacity 1.5s ease;
    }
    .vr-fade-enter-from,
    .vr-fade-leave-to {
        opacity: 0;
    }

    .vr-card {
        margin: 10px 30px 24px 30px;    /* top/bottom 24px, left/right 32px */
    }

    #nprogress {
        display: none;
    }
    .swal2-container {
            z-index: 99999 !important;
        }
    /* Hide Vuetify's own outline, we draw our own border */
    .vr-dialog .v-field__outline {
        display: none !important;
    }

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

    /* Native input/textarea: no border or ring */
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

    /* Select shell: same height and border as the text fields */
    .vr-dialog .vr-select-shell {
        width: 100%;
        height: 40px;
        border: 1px solid #d8e8dc !important;
        border-radius: 12px !important;
        box-shadow: none;
        display: flex;
        align-items: center;
        background: #fff;
    }
    .vr-dialog .vr-select-shell:focus-within {
        border-color: #10b981 !important;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, .1);
    }

    /* Select element: override the py-4 from SelectInput */
    .vr-dialog .vr-select-shell select,
    .vr-dialog .vr-select-shell select:focus {
        height: 100% !important;
        padding: 0 32px 0 12px !important;
        border: 0 !important;
        outline: 0 !important;
        box-shadow: none !important;
        font-size: .82rem;
        font-weight: 500;
        color: #0d2117;
        cursor: pointer;
        text-align: left;
    }

</style>

<style scoped>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap');
    /* ─── Root ───────────────────────────────── */
    .vr-root {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #f0f4f1;
        background-image:
            radial-gradient(ellipse 70% 50% at 5% 0%, rgba(16,185,129,.06) 0%, transparent 55%),
            radial-gradient(ellipse 50% 40% at 95% 100%, rgba(6,95,70,.05) 0%, transparent 55%);
        min-height: 100vh;
        padding-bottom: 2rem;
    }
    .vr-wrap {
        max-width: 100%;
        margin: 0 auto;
        padding: 0 2rem;
    }
    /* ─── Page Header ────────────────────────── */
    .vr-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        padding: 2rem 0 2rem;
        gap: 20px;
        flex-wrap: wrap;
        border-bottom: 1px solid #d8e8dc;
        margin-bottom: 1.5rem;
        position: relative;
        animation: rise .4s ease both;
    }
    .vr-header::after {
        content: '';
        position: absolute;
        bottom: -1px; left: 0;
        width: 56px; height: 2px;
        background: linear-gradient(90deg, #10b981, transparent);
        border-radius: 2px;
    }
    .vr-overline {
        font-size: .65rem; font-weight: 700;
        letter-spacing: 2px; text-transform: uppercase;
        color: #10b981; margin-bottom: 6px;
    }
    .vr-title {
        font-family: 'Instrument Serif', serif;
        font-size: 2rem; font-weight: 400;
        color: #0d2117; line-height: 1; letter-spacing: -.5px; margin-bottom: 5px;
    }
    .vr-sub { font-size: .78rem; color: #7a9e87; }
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

    /* ─── Search Results ─────────────────────── */
    .vr-result-list {
        width: 100%;
        background: white;
        border: 1px solid #d8e8dc;
        border-radius: 16px;
        box-shadow: 0 1px 4px rgba(13,33,23,.05), 0 8px 24px rgba(13,33,23,.06);
        overflow: hidden;
        max-height: 340px;
        overflow-y: auto;
        animation: rise .25s ease both;
    }
    .vr-result-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-bottom: 1px solid #f0f7f2;
        transition: background .15s;
    }
    .vr-result-row:last-child { border-bottom: none; }
    .vr-result-row:hover { background: #f5faf6; }

    .vr-result-avatar {
        width: 34px; height: 34px;
        flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px;
        background: #eaf5ee;
        border: 1px solid #a7f3d0;
        color: #065f46;
    }
    .vr-result-info {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .vr-result-name {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .875rem;
        font-weight: 600;
        color: #0d2117;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .vr-result-meta {
        font-size: .72rem;
        color: #7a9e87;
    }
    .vr-result-actions {
        display: flex;
        align-items: center;
        gap: 8px;          /* space between the edit and delete icons */
        flex-shrink: 0;
    }
    .vr-empty-hint { text-align: center; width: 100%; }

    /* ─── Two-column form grid ───────────────── */
    .vr-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        column-gap: 20px;
    }
    .vr-col-full { grid-column: 1 / -1; }

    .vr-select-shell {
        width: 100%;
        min-height: 40px;
        display: flex;
        align-items: center;
    }
    .vr-sel { width: 100%; }

    @media (max-width: 640px) {
        .vr-form-grid { grid-template-columns: 1fr; }
    }
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
    .vr-search-wrap { display: flex; flex-direction: column; align-items: center; gap: 12px; width: 100%; max-width: 760px; margin: 24px auto; }
    .vr-search-row { display: flex; align-items: center; gap: 12px; width: 100%; }
    .vr-search-row .vr-search-shell { flex: 1; min-width: 0; }
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
    .vr-export-btn {
        display: inline-flex !important; align-items: center; gap: 5px;
        padding: 7px 13px !important;
        background: white !important; color: #2d6a4f !important;
        border: 1.5px solid #d8e8dc !important; border-radius: 12px !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        font-size: .77rem !important; font-weight: 700 !important;
        cursor: pointer; white-space: nowrap;
        box-shadow: 0 1px 3px rgba(13,33,23,.04) !important;
        transition: all .15s ease;
    }
    .vr-export-btn:hover { background: #f0fdf4 !important; border-color: #10b981 !important; transform: translateY(-1px); }
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
    .vr-name-cell { display: flex; align-items: center; gap: 10px; justify-content: center;}
    .vr-name-icon {
        width: 30px; height: 30px; border-radius: 10px;
        background: #eaf5ee; border: 1px solid #a7f3d0;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .vr-name-text { font-weight: 600; color: #0d2117; font-size: .855rem; }
    .vr-doc-count {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: .78rem; color: #3d5e48;
    }
    /* Action buttons */
    .vr-actions { display: flex; align-items: center; gap: 6px; justify-content: center; }
    .vr-icon-btn {
        width: 30px; height: 30px;
        display: inline-flex !important; align-items: center; justify-content: center;
        border-radius: 10px !important; border: 1.5px solid transparent !important;
        cursor: pointer; transition: all .15s ease;
    }
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
    .vr-dialog-icon--blue  { background: linear-gradient(135deg, #3b82f6, #1e40af); box-shadow: 0 3px 10px rgba(59,130,246,.3); }
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
    .vr-error-banners {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 20px;
    }
    .vr-error-banner {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 11px 14px;
        border-radius: 14px;
        border-left: 4px solid;
        font-size: .8rem;
        line-height: 1.5;
    }
    .vr-error-banner--red   { background: #fff5f5; border-color: #b91c1c; color: #7f1d1d; }
    .vr-error-banner--amber { background: #fffbeb; border-color: #d97706; color: #78350f; }
    .vr-error-body { display: flex; flex-direction: column; gap: 2px; }
    .vr-error-title { font-weight: 700; font-size: .8rem; display: flex; align-items: center; gap: 6px; }
    .vr-error-sub { font-size: .73rem; opacity: .8; }
    .vr-error-tag {
        display: inline-block;
        background: #fee2e2; color: #991b1b;
        font-size: .68rem; font-weight: 700;
        border-radius: 5px; padding: 1px 7px;
    }
    .vr-error-tag--amber { background: #fef3c7; color: #92400e; }
    /* ─── Form elements ──────────────────────── */
    .vr-field-group { margin-bottom: 20px; }
    .vr-label { display: block; font-size: .78rem; font-weight: 700; color: #3d5e48; margin-bottom: 6px; letter-spacing: .2px; }
    .vr-req { color: #ef4444; }
    .vr-vfield { font-family: 'Plus Jakarta Sans', sans-serif !important; }
    .vr-text-input {
        width: 100%;
        border: 1.5px solid #d8e8dc !important;
        border-radius: 12px !important;
        padding: 8px 12px !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        font-size: .85rem !important; color: #0d2117 !important;
        outline: none !important;
        transition: border-color .15s, box-shadow .15s;
    }
    .vr-text-input:focus { border-color: #10b981 !important; box-shadow: 0 0 0 3px rgba(16,185,129,.1) !important; }
    .vr-text-input--sm { font-size: .8rem !important; padding: 6px 10px !important; }
    /* Section block */
    .vr-section-block { margin-bottom: 24px; }
    .vr-section-head {
        display: flex; align-items: center; gap: 8px;
        margin-bottom: 12px;
    }
    .vr-section-marker { width: 3px; height: 14px; background: linear-gradient(180deg,#10b981,#065f46); border-radius: 3px; flex-shrink: 0; }
    .vr-section-title  { font-size: .82rem; font-weight: 700; color: #0d2117; flex: 1; }
    .vr-add-btn {
        display: inline-flex !important; align-items: center; gap: 4px;
        padding: 4px 10px !important;
        background: #eaf5ee !important; color: #15803d !important;
        border: 1px solid #a7f3d0 !important; border-radius: 7px !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        font-size: .73rem !important; font-weight: 700 !important;
        cursor: pointer; white-space: nowrap;
        transition: all .15s;
    }
    .vr-add-btn:hover { background: #dcfce7 !important; }
    /* File row */
    .vr-file-row {
        display: flex; align-items: center; gap: 10px;
        background: #f7fbf8; border: 1px solid #eaf3ec;
        border-radius: 14px; padding: 10px 14px; margin-bottom: 8px;
    }
    .vr-file-id-tag {
        font-size: .7rem; font-weight: 700; color: #7a9e87;
        background: #eaf5ee; border: 1px solid #a7f3d0;
        border-radius: 6px; padding: 2px 8px; white-space: nowrap;
    }
    .vr-file-id-tag--new {
        background: #f0fdf4;
        color: #10b981;
        border-color: #a7f3d0;
        font-style: italic;
    }
    .vr-remove-btn {
        width: 30px; height: 30px; flex-shrink: 0;
        display: inline-flex !important; align-items: center; justify-content: center;
        background: #fff5f5 !important; color: #b91c1c !important;
        border: 1.5px solid #fecaca !important; border-radius: 10px !important;
        cursor: pointer; transition: all .15s;
    }
    .vr-remove-btn:hover { background: #fee2e2 !important; transform: scale(1.05); }
    /* Item row (add form) */
    .vr-item-row {
        display: flex; align-items: center; gap: 10px;
        background: #f7fbf8; border: 1px solid #eaf3ec;
        border-radius: 14px; padding: 12px 14px; margin-bottom: 8px;
        flex-wrap: wrap;
    }
    .vr-item-num {
        width: 26px; height: 26px; border-radius: 7px;
        background: linear-gradient(135deg, #10b981, #065f46);
        display: flex; align-items: center; justify-content: center;
        font-size: .72rem; font-weight: 800; color: white; flex-shrink: 0;
    }
    .vr-item-fields { display: flex; gap: 10px; flex: 1; flex-wrap: wrap; align-items: center; }
    /* Select shell */
    .vr-select-shell {
        background: white; border: 1.5px solid #d8e8dc; border-radius: 12px;
        overflow: hidden; box-shadow: 0 1px 3px rgba(13,33,23,.04);
        transition: border-color .15s, box-shadow .15s;
    }
    .vr-select-shell:focus-within { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.1); }
    .vr-sel {
        border: none !important; outline: none !important; background: transparent !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        font-size: .82rem !important; font-weight: 500 !important; color: #0d2117 !important;
        cursor: pointer; min-width: 140px;
    }
    .vr-empty-hint { font-size: .78rem; color: #a8c5b0; font-style: italic; padding: 6px 0; }
    /* ─── Compound Duration Picker ───────────────── */
    .vr-duration-picker {
        display: flex;
        align-items: center;
        gap: 4px;
        background: white;
        border: 1.5px solid #d8e8dc;
        border-radius: 12px;
        padding: 5px 10px;
        transition: border-color .15s, box-shadow .15s;
    }
    .vr-duration-picker:focus-within {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16,185,129,.1);
    }
    .vr-dur-unit {
        display: flex;
        align-items: center;
        gap: 3px;
    }
    .vr-dur-unit:not(:last-child)::after {
        content: ':';
        color: #d8e8dc;
        font-weight: 700;
        margin-left: 4px;
        font-size: .8rem;
    }
    .vr-dur-input {
        width: 36px;
        border: none;
        outline: none;
        background: transparent;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .82rem;
        font-weight: 600;
        color: #0d2117;
        text-align: center;
        -moz-appearance: textfield;
    }
    .vr-dur-input::-webkit-outer-spin-button,
    .vr-dur-input::-webkit-inner-spin-button { -webkit-appearance: none; }
    .vr-dur-label {
        font-size: .68rem;
        font-weight: 700;
        color: #7a9e87;
        letter-spacing: .3px;
    }
    /* View dialog table */
    .vr-view-table-wrap, .vr-edit-table-wrap { overflow-x: auto; border-radius: 14px; border: 1px solid #eaf3ec; }
    .vr-view-table { width: 100%; border-collapse: collapse; }
    .vr-view-th {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .65rem; font-weight: 700; letter-spacing: .8px;
        text-transform: uppercase; color: #7a9e87;
        background: #f7fbf8; padding: 10px 14px;
        text-align: left; border-bottom: 1px solid #eaf3ec;
        white-space: nowrap;
    }
    .vr-view-tr { transition: background .1s; }
    .vr-view-tr:hover .vr-view-td { background: #f5faf6; }
    .vr-view-td { padding: 10px 14px; font-size: .83rem; border-bottom: 1px solid #f0f7f2; color: #1a3d27; }
    .vr-view-tr:last-child .vr-view-td { border-bottom: none; }
    .vr-view-td--num { color: #a8c5b0; font-size: .75rem; font-weight: 600; }
    .vr-view-empty { text-align: center; padding: 28px; color: #a8c5b0; font-size: .82rem; }
    .vr-dept-tag {
        display: inline-block; padding: 2px 8px;
        background: #eaf5ee; border: 1px solid #a7f3d0;
        border-radius: 6px; font-size: .72rem; font-weight: 700; color: #065f46;
    }
    .vr-duration-tag {
        font-family: 'JetBrains Mono', monospace;
        font-size: .78rem;
        font-weight: 500;
        color: var(--your-text-primary);
        letter-spacing: .02em;
    }
    /* File view row */
    .vr-file-view-row {
        display: flex; align-items: center; gap: 8px;
        padding: 7px 10px; margin-bottom: 4px;
        background: #f7fbf8; border: 1px solid #eaf3ec; border-radius: 10px;
        font-size: .82rem;
    }
    .vr-file-name { font-weight: 500; color: #0d2117; flex: 1; }
    .vr-file-size { font-size: .72rem; color: #7a9e87; }
    .vr-flow-lock-banner {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 14px 16px;
        background: #fffbeb;
        border: 1.5px solid #f59e0b;
        border-left: 4px solid #d97706;
        border-radius: 14px;
        margin: 0 0 16px;
        position: sticky;
        top: 0;
        z-index: 10;
        box-shadow: 0 2px 8px rgba(245,158,11,.15);
    }
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
    /* ─── Responsive ──────────────────────────── */
    @media (max-width: 768px) {
        .vr-title { font-size: 1.5rem; }
        .vr-toolbar { flex-direction: column; align-items: stretch; }
        .vr-toolbar-left, .vr-toolbar-right { width: 100%; }
        .vr-search-shell { min-width: unset; width: 100%; }
        .vr-item-fields { flex-direction: column; width: 100%; }
        .vr-item-row { flex-direction: column; align-items: flex-start; }
    }
</style>
