<script setup>
    import { ref, computed, watch } from 'vue'
    import { usePage, router, useForm } from '@inertiajs/vue3'
    import NavLink from '@/Components/NavLink.vue'
    import Swal from 'sweetalert2'

    const page = usePage()
    const user = computed(() => page.props.auth.user)
    const isAdmin = computed(() => user.value?.account_type === 'Admin')
    const fullName = computed(() => user.value?.name || '')
    const initials = computed(() =>
        (user.value?.name || '').split(' ').filter(Boolean).map(word => word[0]).join('').slice(0, 2).toUpperCase()
    )

    const drawer = ref(null)
    const open = ref(['Update'])      // keeps "Modify Selection List" expanded

    // ── Selection-list editors ───────────────────────────────────────────
    const submitStatus     = useForm({ id: '', newStatus: '' })
    const submitRentalType = useForm({ id: '', newRentalType: '' })
    const submitLocation   = useForm({ id: '', newLocation: '' })

    const editors = {
        status: {
            title: 'Status', icon: 'mdi-list-status',
            items: () => page.props.status,   col: 'status',
            form: submitStatus,     field: 'newStatus',     route: 'update.status',
        },
        rental: {
            title: 'Rental Type', icon: 'mdi-layers-outline',
            items: () => page.props.rental,   col: 'rental_type',
            form: submitRentalType, field: 'newRentalType', route: 'update.rental_type',
        },
        location: {
            title: 'Location', icon: 'mdi-map-marker-outline',
            items: () => page.props.location, col: 'location',
            form: submitLocation,   field: 'newLocation',   route: 'update.location',
        },
    }

    const dialogOpen   = ref(false)
    const activeEditor = ref('status')
    const editor       = computed(() => editors[activeEditor.value])
    const itemList     = computed(() => editor.value.items() ?? [])

    const selectedName = computed(() =>
        itemList.value.find(x => x.id == editor.value.form.id)?.[editor.value.col] ?? ''
    )
    const canSave = computed(() =>
        !!editor.value.form.id && !!String(editor.value.form[editor.value.field] ?? '').trim()
    )

    const openEditor = (key) => {
        activeEditor.value = key
        dialogOpen.value = true
    }

    const closeEditor = () => {
        editor.value.form.reset()
        editor.value.form.clearErrors()
        dialogOpen.value = false
    }

    // picking an item fills the text box with its current name, ready to edit
    Object.values(editors).forEach((e) => {
        watch(() => e.form.id, (id) => {
            e.form[e.field] = id ? (e.items()?.find(x => x.id == id)?.[e.col] ?? '') : ''
        })
    })

    const esc = (s) => String(s).replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

    const saveEditor = () => {
        const e = editor.value
        const to = String(e.form[e.field] ?? '').trim()
        if (!e.form.id || !to) return

        if (to === selectedName.value) {          // nothing changed
            closeEditor()
            return
        }

        Swal.fire({
            icon: 'warning',
            title: `Update this ${e.title}?`,
            html: `Rename <b>${esc(selectedName.value)}</b> to <b>${esc(to)}</b>?<br>
                This will also update all records that use it.`,
            showCancelButton: true,
            confirmButtonText: 'Yes, update',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#2d6a4f',
        }).then((result) => {
            if (!result.isConfirmed) return

            e.form.post(route(e.route), {
                preserveScroll: true,
                onSuccess: () => closeEditor(),
                onError: (errors) => Swal.fire({
                    icon: 'error',
                    title: 'Could not save',
                    text: Object.values(errors).join(' '),
                    confirmButtonColor: '#2d6a4f',
                }),
            })
        })
    }

    const logout = () => router.post(route('logout'))

    // ── November (Undas) theme ───────────────────────────────────────────
    // Set to true to preview the theme in any month, then back to false
    const FORCE_NOVEMBER = false

    const today = new Date()
    const isNovember = computed(() => FORCE_NOVEMBER || today.getMonth() === 10)   // 10 = November

    const seasonLabel = computed(() => {
        if (today.getMonth() === 10 && today.getDate() === 1) return "All Saints' Day"
        if (today.getMonth() === 10 && today.getDate() === 2) return "All Souls' Day"
        return 'Undas Season'
    })

    // Rising embers (fixed values, so it looks the same on every load)
    const embers = Array.from({ length: 16 }, (_, i) => ({
        left:  (i * 29 + 7) % 100,
        size:  2 + (i % 3),
        delay: (i % 7) * 1.3,
        dur:   9 + (i % 5) * 2,
    }))
</script>

<template>
    <v-app id="inspire" class="app-root" :class="{ 'theme-november': isNovember }">

        <!-- APP BAR -->
        <v-app-bar flat height="64" class="app-bar-custom">
            <v-app-bar-nav-icon @click="drawer = !drawer" color="white" />

            <v-app-bar-title>
                <div class="brand-wrapper">
                    <span class="brand-eyebrow">Cemetery Management</span>
                    <span class="brand-title">Cemetery Records System</span>
                </div>
            </v-app-bar-title>

            <template #append>
                <div class="topbar-right">
                    <!-- November badge -->
                    <span v-if="isNovember" class="season-chip">
                        <span class="candle">
                            <span class="candle-glow"></span>
                            <v-icon size="16">mdi-candle</v-icon>
                        </span>
                        {{ seasonLabel }}
                    </span>

                    <div class="user-chip">
                        <div class="user-avatar">{{ initials }}</div>
                        <div class="user-info">
                            <span class="user-name">{{ fullName }}</span>
                            <span class="user-role">{{ isAdmin ? 'Administrator' : 'Employee' }}</span>
                        </div>
                    </div>

                    <button type="button" class="logout-btn" @click="logout">
                        <v-icon size="16">mdi-logout-variant</v-icon>
                        <span>Logout</span>
                    </button>
                </div>
            </template>
        </v-app-bar>

        <!-- NAVIGATION DRAWER -->
        <v-navigation-drawer v-model="drawer" width="240" class="nav-drawer-custom">
            <div class="drawer-header">
                <div class="drawer-logo">
                    <v-icon size="26" color="#2e5e3e">mdi-grave-stone</v-icon>
                </div>
                <div>
                    <p class="drawer-header-title">Cemetery</p>
                    <p class="drawer-header-sub">{{ isAdmin ? 'Admin' : 'Employee' }} Panel</p>
                </div>
            </div>

            <!-- ADMIN SIDEBAR -->
            <v-list v-model:opened="open" nav class="nav-list">
                <p class="nav-section-label">Main</p>
                <NavLink :href="route('employee.display_employee')" :active="route().current('employee.display_employee')" icon="mdi-file-document-multiple">
                    <v-list-item-title class="nav-item-title">Deceased Records</v-list-item-title>
                </NavLink>
                <NavLink v-if="isAdmin" :href="route('admin.display_accounts')" :active="route().current('admin.display_accounts')" icon="mdi-account-group">
                    <v-list-item-title class="nav-item-title">Accounts</v-list-item-title>
                </NavLink>
                <NavLink :href="route('display.cedula')" :active="route().current('display.cedula')" icon="mdi-list-box-outline">
                    <v-list-item-title class="nav-item-title">Cedula</v-list-item-title>
                </NavLink>

                <p class="nav-section-label mt-3">Settings</p>
                <v-list-group value="Update">
                    <template #activator="{ props }">
                        <v-list-item v-bind="props" rounded="lg">
                            <template #prepend>
                                <v-icon size="20">mdi-tune-variant</v-icon>
                            </template>
                            <v-list-item-title class="nav-item-title nav-group-title">Modify Selection List</v-list-item-title>
                        </v-list-item>
                    </template>

                    <v-list-item
                        v-for="(e, key) in editors"
                        :key="key"
                        rounded="lg"
                        @click="openEditor(key)"
                    >
                        <template #prepend>
                            <v-icon size="18">{{ e.icon }}</v-icon>
                        </template>
                        <v-list-item-title class="nav-item-title">{{ e.title }}</v-list-item-title>
                    </v-list-item>
                </v-list-group>
            </v-list>

            <!-- EMPLOYEE SIDEBAR -->
        </v-navigation-drawer>

        <!-- MAIN CONTENT -->
        <v-main class="main-content">
            <!-- Rising embers (November only) -->
            <div v-if="isNovember" class="embers" aria-hidden="true">
                <span
                    v-for="(e, i) in embers"
                    :key="i"
                    class="ember"
                    :style="{
                        left: e.left + '%',
                        width: e.size + 'px',
                        height: e.size + 'px',
                        animationDelay: e.delay + 's',
                        animationDuration: e.dur + 's',
                    }"
                ></span>
            </div>

            <div class="content-wrapper">
                <FlashMessage />
                <slot />
            </div>
        </v-main>

        <!-- ═══════════════════════════════════════
             UPDATE STATUS/RENTAL TYPE/LOCATION DIALOG
        ════════════════════════════════════════ -->
        <v-dialog v-model="dialogOpen" persistent max-width="520">
            <v-card class="ed-card" rounded="xl">
                <div class="ed-header">
                    <div class="ed-icon"><v-icon size="22" color="white">{{ editor.icon }}</v-icon></div>
                    <div>
                        <p class="ed-title">Modify {{ editor.title }} List</p>
                        <p class="ed-sub">Rename an item in the {{ editor.title.toLowerCase() }} selection list</p>
                    </div>
                    <v-spacer />
                    <v-btn icon variant="text" size="small" class="ed-close" @click="closeEditor">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </div>
                <v-divider />

                <v-card-text class="ed-body">
                    <v-alert type="warning" variant="tonal" rounded="lg" density="compact"
                            icon="mdi-alert-circle-outline" class="mb-4 ed-alert">
                        Changing this name will also change it on every record that uses it.
                    </v-alert>

                    <form @submit.prevent="saveEditor">
                        <label class="ed-label">Item to rename</label>
                        <v-select
                            v-model="editor.form.id"
                            :items="itemList"
                            :item-title="editor.col"
                            item-value="id"
                            :placeholder="`Select ${editor.title}`"
                            variant="outlined" density="compact" rounded="lg"
                            bg-color="#f7f5f0" hide-details="auto"
                            :error-messages="editor.form.errors.id"
                            persistent-placeholder
                        />

                        <label class="ed-label mt-4">New name</label>
                        <v-text-field
                            v-model="editor.form[editor.field]"
                            :placeholder="`New ${editor.title}`"
                            variant="outlined" density="compact" rounded="lg"
                            bg-color="#f7f5f0" clearable hide-details="auto"
                            :disabled="!editor.form.id"
                            :error-messages="editor.form.errors[editor.field]"
                        />

                        <div v-if="selectedName" class="ed-preview">
                            <span class="ed-chip ed-chip--old">{{ selectedName }}</span>
                            <v-icon size="16" color="#9ca3af">mdi-arrow-right</v-icon>
                            <span class="ed-chip ed-chip--new">{{ editor.form[editor.field] || '…' }}</span>
                        </div>

                        <v-divider class="my-4" />
                        <div class="ed-actions">
                            <v-btn variant="text" rounded="lg" class="ed-cancel" @click="closeEditor">Cancel</v-btn>
                            <v-btn color="green-darken-3" variant="flat" rounded="lg" type="submit"
                                class="ed-submit"
                                :loading="editor.form.processing"
                                :disabled="!canSave || editor.form.processing">
                                <v-icon size="18" class="mr-2">mdi-content-save-outline</v-icon>
                                Save Changes
                            </v-btn>
                        </div>
                    </form>
                </v-card-text>
            </v-card>
        </v-dialog>

    </v-app>
</template>

<style>
    /* ═══════════════ BASE ═══════════════ */
    .app-root { background-color: #0a1f13 !important; }
    .app-root .v-application__wrap { background: transparent !important; }

    /* ═══════════════ NOVEMBER (UNDAS) THEME ═══════════════ */
    .app-root.theme-november { background-color: #120c06 !important; }

    /* App bar: dark brown to amber, with a candle-lit edge */
    .app-root.theme-november .app-bar-custom {
        background: linear-gradient(135deg, #1c1409 0%, #3a2a12 55%, #5a3d14 100%) !important;
        border-bottom: 1px solid rgba(251, 191, 36, 0.35) !important;
        box-shadow: 0 6px 24px rgba(245, 158, 11, 0.18) !important;
    }
    .app-root.theme-november .brand-eyebrow { color: #fbbf24; }
    .app-root.theme-november .logout-btn:hover { background: rgba(217, 119, 6, 0.6); }

    /* Drawer accents */
    .app-root.theme-november .nav-drawer-custom { border-right-color: #f3e3c3 !important; }
    .app-root.theme-november .drawer-header { border-bottom-color: #f3e3c3; }
    .app-root.theme-november .drawer-logo {
        background: linear-gradient(135deg, #b45309, #f59e0b);
        box-shadow: 0 0 18px rgba(245, 158, 11, 0.45);
    }
    .app-root.theme-november .drawer-header-title { color: #4a2f0b; }

    /* Active menu item = amber pill */
    .app-root.theme-november .nav-list .v-list-item.is-active {
        background: rgba(245, 158, 11, 0.20) !important;
        color: #7c4a03 !important;
    }
    .app-root.theme-november .nav-list .v-list-item.is-active .v-icon,
    .app-root.theme-november .nav-list .v-list-item.is-active .nav-item-title {
        color: #7c4a03 !important;
    }

    /* Content: warmer wash, plus candle glow from the bottom */
    .app-root.theme-november .content-wrapper {
        background:
            radial-gradient(ellipse at 50% 108%, rgba(251, 191, 36, 0.22), transparent 62%),
            rgba(122, 104, 80, 0.42);
    }

    /* ═══════════════ VUETIFY FIELDS (all dialogs) ═══════════════ */
    /* Tailwind forms plugin styles the raw <input> inside Vuetify's field.
       Reset it so only Vuetify's own outline is drawn. */
    .v-field .v-field__input input,
    .v-field .v-field__input textarea {
        border: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        outline: none !important;
        padding: 0 !important;
        --tw-ring-shadow: 0 0 #0000 !important;
        --tw-ring-offset-shadow: 0 0 #0000 !important;
    }

    /* Focus border: green instead of Vuetify's default blue */
    .v-field--focused .v-field__outline { color: #4a8c5c !important; }
    .v-field--focused .v-field__outline__start,
    .v-field--focused .v-field__outline__end,
    .v-field--focused .v-field__outline__notch::before,
    .v-field--focused .v-field__outline__notch::after {
        border-color: #4a8c5c !important;
    }

    /* SweetAlert above Vuetify dialogs */
    .swal2-container { z-index: 99999 !important; }
</style>

<style scoped>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Syne:wght@600;700&display=swap');

    /* ═══════════════ APP BAR ═══════════════ */
    .app-bar-custom { background: linear-gradient(135deg, #1a3a2a 0%, #2e5e3e 100%) !important; border-bottom: 1px solid rgba(255,255,255,0.07) !important; }
    .brand-wrapper { display: flex; flex-direction: column; gap: 2px; line-height: 1; }
    .brand-eyebrow { font-family: 'DM Sans', sans-serif; font-size: 0.71rem; font-weight: 500; letter-spacing: 0.12em; text-transform: uppercase; color: #72c484; }
    .brand-title { font-family: 'Syne', sans-serif; font-size: 1.07rem; font-weight: 700; color: #fff; }
    .topbar-right { display: flex; align-items: center; gap: 12px; padding-right: 16px; }
    .user-chip { display: flex; align-items: center; gap: 10px; padding: 6px 10px; border-radius: 14px; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); }
    .user-avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #4a8c5c, #72c484); display: flex; align-items: center; justify-content: center; font-family: 'Syne', sans-serif; font-size: 0.86rem; font-weight: 700; color: #1a3a2a; flex-shrink: 0; }
    .user-info { display: flex; flex-direction: column; gap: 1px; }
    .user-name { font-family: 'DM Sans', sans-serif; font-size: 0.89rem; font-weight: 600; color: #fff; line-height: 1; }
    .user-role { font-size: 0.75rem; color: rgba(255,255,255,0.5); line-height: 1; }

    .logout-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.18); background: rgba(255,255,255,0.08); color: #fff; font-family: 'DM Sans', sans-serif; font-size: 0.86rem; font-weight: 600; cursor: pointer; transition: background .15s; }
    .logout-btn:hover { background: rgba(185,28,28,0.55); }

    /* ═══════════════ DRAWER ═══════════════ */
    .nav-drawer-custom { background: #fff !important; border-right: 1px solid #ede8df !important; box-shadow: 1px 0 12px rgba(0,0,0,0.06) !important; }
    .drawer-header { display: flex; align-items: center; gap: 12px; padding: 20px 16px 14px; border-bottom: 1px solid #ede8df; margin-bottom: 8px; }
    .drawer-logo { width: 52px; height: 52px; border-radius: 12px; background: #ffffff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 8px rgba(46,94,62,0.3); overflow: hidden; padding: 4px; }
    .drawer-header-title { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; color: #1a3a2a; line-height: 1; }
    .drawer-header-sub { font-size: 0.79rem; color: #6b7280; margin-top: 2px; line-height: 1; }
    .nav-list { padding: 4px 10px !important; }
    .nav-section-label { font-family: 'DM Sans', sans-serif; font-size: 0.71rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #9ca3af; padding: 4px 8px; margin-bottom: 2px; }
    .nav-item-title { font-family: 'DM Sans', sans-serif; font-size: 0.96rem; font-weight: 500; }

    /* menu items */
    .nav-list :deep(.v-list-item) {
        border-radius: 10px !important;
        min-height: 44px !important;
        margin-bottom: 4px;
        padding-inline: 12px !important;
        color: #4b5563;
        background: transparent;
        transition: background .15s, color .15s;
    }
    .nav-list :deep(.v-list-item .v-icon) { color: #9ca3af; opacity: 1; }
    .nav-list :deep(.v-list-item__overlay) { display: none; }        /* removes the gray overlay */
    .nav-list :deep(.v-list-item__spacer) { display: none !important; width: 0 !important; }

    .nav-list :deep(.v-list-item .v-list-item__prepend) {
        width: auto !important;                                      /* stop the wide icon column */
        margin-inline-end: 12px !important;
    }
    .nav-list :deep(.v-list-item .v-list-item__prepend > .v-icon) { margin-inline-end: 0 !important; }

    .nav-list :deep(.v-list-item-title) {
        text-align: left !important;
        white-space: nowrap;
        overflow: visible;
        text-overflow: clip;
    }

    /* hover / active */
    .nav-list :deep(.v-list-item:not(.is-active):hover) { background: rgba(74, 140, 92, 0.08) !important; }
    .nav-list :deep(.v-list-item.is-active) { background: rgba(74, 140, 92, 0.20) !important; color: #1f5c36 !important; }
    .nav-list :deep(.v-list-item.is-active .v-icon) { color: #1f5c36 !important; }
    .nav-list :deep(.v-list-item.is-active .nav-item-title) { font-weight: 700; color: #1f5c36; }

    /* ── Modify Selection List: dropdown look ── */
    .nav-list :deep(.v-list-group__items) {
        margin-left: 22px;                                           /* moves the sub-items to the right */
        padding-left: 6px;
        border-left: 2px solid rgba(74, 140, 92, 0.28);              /* guide line */
    }
    .nav-list :deep(.v-list-group__items .v-list-item) { min-height: 40px !important; }
    .nav-list :deep(.v-list-group__items .nav-item-title) { font-size: 0.9rem; }
    .nav-list :deep(.v-list-group__header) { padding-inline: 10px 8px !important; }
    .nav-list :deep(.v-list-group__header .v-list-item__prepend) { margin-inline-end: 10px !important; }
    .nav-list :deep(.v-list-group__header .v-list-item__append) { width: auto; margin-inline-start: 2px; }
    .nav-list :deep(.v-list-group__header .v-list-item__append > .v-icon) {
        margin-inline-start: 0 !important;                           /* removes Vuetify's 32px gap before the arrow */
        font-size: 18px;
    }
    .nav-group-title { font-size: 0.92rem !important; }

    /* ═══════════════ EDITOR DIALOG ═══════════════ */
    .ed-card { overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.18) !important; }
    .ed-header { display: flex; align-items: center; gap: 14px; padding: 20px 24px 16px; background: #fff; }
    .ed-icon { width: 44px; height: 44px; border-radius: 14px; background: linear-gradient(135deg, #2e5e3e, #4a8c5c); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 12px rgba(46,94,62,0.3); }
    .ed-title { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; color: #1a3a2a; line-height: 1.1; margin: 0; }
    .ed-sub { font-family: 'DM Sans', sans-serif; font-size: 0.82rem; color: #6b7280; margin: 4px 0 0; line-height: 1.1; }
    .ed-close { color: #9ca3af !important; }
    .ed-body { padding: 20px 24px 22px !important; }
    .ed-alert { font-family: 'DM Sans', sans-serif; font-size: 0.88rem !important; }
    .ed-label { display: block; font-family: 'DM Sans', sans-serif; font-size: 0.82rem; font-weight: 600; color: #374151; margin-bottom: 6px; letter-spacing: 0.01em; }

    .ed-preview { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 14px; padding: 10px 12px; background: #f7f5f0; border: 1.5px dashed #d6cfc1; border-radius: 12px; }
    .ed-chip { font-family: 'DM Sans', sans-serif; font-size: 0.85rem; font-weight: 600; padding: 3px 10px; border-radius: 999px; }
    .ed-chip--old { background: #f3f4f6; color: #6b7280; text-decoration: line-through; }
    .ed-chip--new { background: rgba(74,140,92,0.15); color: #1f5c36; }

    .ed-actions { display: flex; justify-content: flex-end; align-items: center; gap: 12px; }
    .ed-cancel {
        font-family: 'DM Sans', sans-serif !important;
        letter-spacing: 0.01em !important;
        color: #6b7280 !important;
        text-transform: none !important;
        padding: 0 18px !important;
        height: 42px !important;
    }
    .ed-submit {
        font-family: 'DM Sans', sans-serif !important;
        font-size: 0.92rem !important;
        font-weight: 600 !important;
        letter-spacing: 0.01em !important;
        text-transform: none !important;
        height: 42px !important;
        padding: 0 22px !important;
        border-radius: 12px !important;
        color: #fff !important;
        background: linear-gradient(135deg, #2e5e3e, #4a8c5c) !important;
        box-shadow: 0 3px 12px rgba(46, 94, 62, 0.30) !important;
        transition: box-shadow .15s, transform .15s;
    }
    .ed-submit:hover:not(.v-btn--disabled) {
        box-shadow: 0 5px 16px rgba(46, 94, 62, 0.40) !important;
        transform: translateY(-1px);
    }
    /* disabled = soft gray, still readable */
    .ed-submit.v-btn--disabled {
        opacity: 1 !important;
        background: #e8e6e1 !important;
        color: #9ca3af !important;
        box-shadow: none !important;
        transform: none !important;
    }

    /* ═══════════════ MAIN CONTENT ═══════════════ */
    .main-content { background: transparent !important; }
    :deep(.v-main) { background: transparent !important; }
    :deep(.v-main__wrap) { background: transparent !important; }
    .content-wrapper {
        position: relative;
        padding: 2rem 2.5rem 3rem 2.5rem;
        background: rgba(111, 138, 122, 0.45);
        min-height: 100vh;
    }

    /* ═══════════════ NOVEMBER BADGE + EMBERS ═══════════════ */
    .season-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px 6px 8px;
        border-radius: 999px;
        background: rgba(251, 191, 36, 0.12);
        border: 1px solid rgba(251, 191, 36, 0.4);
        color: #fde68a;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        white-space: nowrap;
    }
    .candle { position: relative; display: inline-flex; align-items: center; justify-content: center; color: #fcd34d; }
    .candle-glow {
        position: absolute;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(252, 211, 77, 0.55), transparent 70%);
        animation: flicker 2.4s ease-in-out infinite;
    }

    .embers { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
    .ember {
        position: absolute;
        bottom: -10px;
        border-radius: 50%;
        background: #fbbf24;
        box-shadow: 0 0 10px 3px rgba(251, 146, 60, 0.6);
        opacity: 0;
        animation: rise 12s linear infinite;
    }

    @keyframes flicker {
        0%, 100% { opacity: 0.85; transform: scale(1); }
        25%      { opacity: 0.55; transform: scale(0.88); }
        50%      { opacity: 1;    transform: scale(1.08); }
        75%      { opacity: 0.7;  transform: scale(0.95); }
    }
    @keyframes rise {
        0%   { opacity: 0;    transform: translate(0, 0); }
        12%  { opacity: 0.9; }
        60%  { opacity: 0.5;  transform: translate(24px, -55vh); }
        100% { opacity: 0;    transform: translate(-16px, -105vh); }
    }

    @media (prefers-reduced-motion: reduce) {
        .ember, .candle-glow { animation: none; }
        .ember { opacity: 0.35; }
    }
    @media (max-width: 600px) {
        .season-chip { display: none; }
    }
</style>
