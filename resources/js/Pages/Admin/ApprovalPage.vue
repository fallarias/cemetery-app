<script setup>
    import { Head, router, useForm, usePage } from '@inertiajs/vue3';
    import { nextTick, ref, watch, computed } from 'vue';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

    const page = usePage()

    const search = ref(page.props.filters?.search ?? '')
    const itemsPerPage = computed(() => page.props.records.per_page)
    const totalItems   = computed(() => page.props.records.total)
    const currentPage  = computed(() => page.props.records.current_page)


    function fetchRecords(extra = {}) {
        router.get('/user/accounts', {
            search: search.value || undefined,
            per_page: itemsPerPage.value,
            ...extra,
        }, { preserveState: true, preserveScroll: true, replace: true })
    }

    let timer
    watch(search, () => {
        clearTimeout(timer)
        timer = setTimeout(() => fetchRecords({ page: 1 }), 200)
    })

    const onPageChange = (p) => fetchRecords({ page: p })
    const onItemsPerPageChange = (n) => fetchRecords({ per_page: n, page: 1 })

    // Closed Dialog
    const password_dialog = ref(false)
    const loading = ref(false);

    // ── Actions ──────────────────────────────────────────────────────────────────
    const changeStatus = (item, action) => {
        loading.value = true;
        router.post(route('accounts.change_status'), { id: item.id, action }, {
            preserveScroll: true,
            onFinish: () => { loading.value = false; },
        });
    };

    // ── Create Password form ───────────────────────────────────────────────────
    const update_password = useForm({
        id: '',
        password: '',
    })

    // ── Edit Deseased Records ───────────────────────────────────────────────────
    const forgot_password = (item) => {
        update_password.id              = item.id
        update_password.password        = ''
        password_dialog.value = true
    }

    const form_errors = ref([])

    const updatePassword = () => {
        form_errors.value = []

        const pw = String(update_password.password ?? '').trim()
        if (!pw) {
            form_errors.value = [{ field: 'password', message: 'Password is required' }]
            return
        }
        if (pw.length < 8) {
            form_errors.value = [{ field: 'password', message: 'Password must be at least 8 characters' }]
            return
        }

        update_password.post(route('update.password'), {
            preserveScroll: true,
            onSuccess: () => {
                update_password.reset()
                form_errors.value = []
                password_dialog.value = false   // close the dialog after saving
            },
        })
    }

</script>

<template>
    <AuthenticatedLayout>
        <div class="vr-card">
            <div class="vr-divider"></div>
            <!-- Table -->
            <v-data-table-server
                :headers="header"
                :items="$page.props.records.data"
                hover
                class="vr-vtable"
                :items-per-page="itemsPerPage"
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
                <template v-slot:item.name="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.name }}</span>
                    </div>
                </template>
                <template v-slot:item.email="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.email }}</span>
                    </div>
                </template>
                <template v-slot:item.account_status="{ item }">
                    <div class="vr-name-cell">
                        <span class="vr-name-text">{{ item.account_status }}</span>
                    </div>
                </template>
                <template v-slot:item.forgot_password="{ item }">
                    <div class="vr-name-cell">
                        <button class="vr-export-btn" @click="forgot_password(item)">
                            <v-icon size="16">mdi-lock-reset</v-icon> Forgot Password
                        </button>
                    </div>
                </template>
                <template v-slot:item.action="{ item }">
                    <v-menu offset-y>
                        <template #activator="{ props }">
                            <button
                                class="ac-action-btn"
                                v-bind="props"
                            >
                                <v-icon size="14">mdi-cog-outline</v-icon>
                                <span>Actions</span>
                                <v-icon size="12">mdi-chevron-down</v-icon>
                            </button>
                        </template>

                        <v-list
                            density="compact"
                            class="ac-menu-list"
                        >
                            <!-- Pending -->
                            <template v-if="item.account_status === 'inactive'">
                                <v-list-item
                                    class="ac-menu-item ac-menu-item--success"
                                    @click="changeStatus(item, 'active')"
                                >
                                    <template #prepend>
                                        <v-icon size="15">
                                            mdi-account-check-outline
                                        </v-icon>
                                    </template>

                                    <v-list-item-title>
                                        Activate Account
                                    </v-list-item-title>
                                </v-list-item>
                            </template>

                            <!-- Disabled -->
                            <v-list-item
                                v-else
                                class="ac-menu-item ac-menu-item--success"
                                @click="changeStatus(item, 'inactive')"
                            >
                                <template #prepend>
                                    <v-icon size="15">
                                        mdi-account-check-outline
                                    </v-icon>
                                </template>

                                <v-list-item-title>
                                    Inactive Account
                                </v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-menu>
                </template>
            </v-data-table-server>
        </div>

        <!-- DIALOG: Update Forgotten Password -->
        <v-dialog v-model="password_dialog" max-width="460">
            <div class="vr-dialog">
                <div class="vr-dialog-header">
                    <div class="vr-dialog-icon">
                        <v-icon size="18" color="white">mdi-forgot-password</v-icon>
                    </div>
                    <div>
                        <p class="vr-dialog-title">Enter New Password</p>
                    </div>
                    <button class="vr-dialog-close" @click="password_dialog = false">
                        <v-icon size="18">mdi-close</v-icon>
                    </button>
                </div>
                <div class="vr-dialog-body">
                    <form @submit.prevent="updatePassword">
                        <div class="vr-form-grid">
                            <div class="vr-field-group">
                                <label class="vr-label">New Password<span class="vr-req">*</span></label>
                                <v-text-field v-model="update_password.password" type="password" clearable
                                    placeholder="Enter New Password" variant="outlined" density="compact"
                                    class="vr-vfield" :error-messages="update_password.errors.password || form_errors.map(e => e.message)"
                                    hide-details="auto"/>
                            </div>
                        </div>

                        <div class="vr-dialog-footer">
                            <button type="button" class="vr-btn vr-btn--plain" @click="password_dialog = false">Cancel</button>
                            <v-btn
                                type="submit"
                                class="vr-btn vr-btn--primary"
                                :loading="update_password.processing"
                                :disabled="update_password.processing"
                                :class="{ 'opacity-50': update_password.processing }"
                            >
                                <v-icon size="14">mdi-content-save-outline</v-icon>
                                Save Password
                            </v-btn>
                        </div>
                    </form>
                </div>
            </div>
        </v-dialog>
    </AuthenticatedLayout>
</template>

<script>
    import FlashMessage from '@/Components/FlashMessage.vue';
    export default {
        data() {
            return {
                header: [
                    { title: '#',               key: 'number',              sortable: false, align: 'center', width: '60px' },
                    { title: 'Name',            key: 'name',                sortable: false, align: 'center'  },
                    { title: 'Email',           key: 'email',               sortable: false, align: 'center'  },
                    { title: 'Status',          key: 'account_status',      sortable: false, align: 'center'  },
                    { title: 'Forgot Password', key: 'forgot_password',     sortable: false, align: 'center'  },
                    { title: 'Action',          key: 'action',              sortable: false, align: 'center'  },
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


<!-- 1. Scoped: table + dialog layout -->
<style scoped>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

    :deep(.v-field input:focus) {
        outline: none !important;
        box-shadow: none !important;
    }

    /* Card */
    .vr-card {
        background: white;
        border: 1px solid #d8e8dc;
        border-radius: 18px;
        box-shadow: 0 1px 4px rgba(13,33,23,.05), 0 4px 20px rgba(13,33,23,.04);
        overflow: hidden;
        animation: rise .4s .06s ease both;
    }
    .vr-divider { height: 1px; background: #eaf3ec; }

    /* Table */
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
    .vr-name-text { font-weight: 600; color: #0d2117; font-size: .855rem; }

    /* Forgot password button */
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

    /* Dialog */
    .vr-dialog {
        background: white; border-radius: 18px; overflow: hidden;
        font-family: 'Plus Jakarta Sans', sans-serif;
        box-shadow: 0 20px 60px rgba(0,0,0,.15);
    }
    .vr-dialog-header {
        display: flex; align-items: center; gap: 14px;
        padding: 20px 24px; background: #f7fbf8;
        border-bottom: 1px solid #eaf3ec; position: relative;
    }
    .vr-dialog-icon {
        width: 38px; height: 38px; border-radius: 14px;
        background: linear-gradient(135deg, #10b981, #065f46);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        box-shadow: 0 3px 10px rgba(16,185,129,.3);
    }
    .vr-dialog-title { font-size: .95rem; font-weight: 700; color: #0d2117; }
    .vr-dialog-close {
        margin-left: auto; width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        background: white; border: 1px solid #d8e8dc;
        border-radius: 10px; cursor: pointer; color: #7a9e87; transition: all .15s;
    }
    .vr-dialog-close:hover { background: #fee2e2; border-color: #fecaca; color: #b91c1c; }
    .vr-dialog-body { padding: 24px; max-height: 70vh; overflow-y: auto; }
    .vr-dialog-footer {
        display: flex; align-items: center; justify-content: flex-end; gap: 10px;
        padding-top: 20px; border-top: 1px solid #eaf3ec; margin-top: 20px;
    }

    /* Form */
    .vr-form-grid { display: grid; grid-template-columns: 1fr; }
    .vr-field-group { margin-bottom: 20px; }
    .vr-label { display: block; font-size: .78rem; font-weight: 700; color: #3d5e48; margin-bottom: 6px; letter-spacing: .2px; }
    .vr-req { color: #ef4444; }
    .vr-vfield { font-family: 'Plus Jakarta Sans', sans-serif !important; }

    /* Buttons */
    .vr-btn {
        display: inline-flex !important; align-items: center; gap: 6px;
        padding: 8px 18px !important; border-radius: 12px !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        font-size: .82rem !important; font-weight: 700 !important;
        cursor: pointer; transition: all .15s ease; white-space: nowrap;
    }
    .vr-btn--plain { background: transparent !important; color: #7a9e87 !important; border: 1.5px solid #d8e8dc !important; }
    .vr-btn--plain:hover { background: #f7fbf8 !important; color: #3d5e48 !important; }
    .vr-btn--primary {
        background: #2d6a4f !important; color: #ffffff !important; border: none !important;
        box-shadow: 0 2px 8px rgba(45,106,79,.3) !important;
    }
    .vr-btn--primary:hover { background: #065f46 !important; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(45,106,79,.35) !important; }
    .vr-btn--primary:disabled { opacity: .5; cursor: not-allowed; transform: none !important; }

    @keyframes rise {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
    }
</style>

<!-- 2. Global: page margin, progress bar, swal, dialog field styling -->
<style>
    .vr-card { margin: 10px 30px 24px 30px; }
    #nprogress { display: none; }
    .swal2-container { z-index: 99999 !important; }

    .vr-dialog .v-field__outline { display: none !important; }
    .vr-dialog .v-field {
        border: 1px solid #d8e8dc !important;
        border-radius: 12px !important;
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }
    .vr-dialog .v-field.v-field--focused {
        border-color: #10b981 !important;
        box-shadow: 0 0 0 3px rgba(16,185,129,.1);
    }
    .vr-dialog .v-field input,
    .vr-dialog .v-field input:focus {
        border: 0 !important; outline: 0 !important; box-shadow: none !important;
        background: transparent !important;
        --tw-ring-shadow: 0 0 #0000 !important;
        --tw-ring-offset-shadow: 0 0 #0000 !important;
    }
</style>

<!-- 3. Global: Actions dropdown (menu renders in an overlay, so not scoped) -->
<style>
    /* Trigger button */
    .ac-action-btn {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 7px 12px 7px 10px;
        background: #f0fdf4;
        color: #166534;
        border: 1.5px solid #bbf7d0;
        border-radius: 999px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .77rem; font-weight: 700; letter-spacing: .2px;
        cursor: pointer; white-space: nowrap;
        box-shadow: 0 1px 3px rgba(13,33,23,.05);
        transition: background .15s, border-color .15s, box-shadow .15s, transform .1s;
    }
    .ac-action-btn:hover {
        background: #dcfce7;
        border-color: #4ade80;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16,185,129,.18);
    }
    .ac-action-btn:active { transform: translateY(0); }
    .ac-action-btn:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(16,185,129,.25); }
    .ac-action-btn[aria-expanded="true"] {
        background: #2d6a4f; color: #fff; border-color: #2d6a4f;
        box-shadow: 0 4px 14px rgba(45,106,79,.35);
    }
    .ac-action-btn .ac-chevron { transition: transform .2s ease; }
    .ac-action-btn[aria-expanded="true"] .ac-chevron { transform: rotate(180deg); }

    /* Menu panel */
    .ac-menu-list.v-list {
        min-width: 190px;
        margin-top: 6px;
        padding: 6px !important;
        background: #fff;
        border: 1px solid #e3efe7;
        border-radius: 14px;
        box-shadow: 0 12px 32px rgba(13,33,23,.14), 0 2px 6px rgba(13,33,23,.06);
        animation: ac-pop .16s ease both;
    }
    @keyframes ac-pop {
        from { opacity: 0; transform: translateY(-6px) scale(.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* Menu items */
    .ac-menu-item.v-list-item {
        min-height: 38px;
        padding: 0 12px;
        border-radius: 10px;
        color: #1a3d27;
        transition: background .12s, color .12s;
    }
    .ac-menu-item.v-list-item:not(:last-child) { margin-bottom: 2px; }
    .ac-menu-item .v-list-item__prepend > .v-icon {
        margin-inline-end: 10px;
        opacity: 1;
        color: inherit;
    }
    .ac-menu-item .v-list-item-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .8rem; font-weight: 600;
    }

    .ac-menu-item--success:hover,
    .ac-menu-item--success.v-list-item--active { background: #ecfdf5; color: #047857; }

    .ac-menu-item--danger { color: #b91c1c; }
    .ac-menu-item--danger:hover { background: #fef2f2; color: #991b1b; }
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
        font-size: .82rem; color: #0d2117; width: 100%;
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
        padding: 7px 10px !important;
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
