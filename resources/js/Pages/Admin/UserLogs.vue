<script setup>
import { router, usePage } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const page = usePage()

// ── Search ──────────────────────────────────────────────
const search = ref(page.props.filters?.search ?? '')

const itemsPerPage = computed(() => page.props.logs.per_page)
const totalItems   = computed(() => page.props.logs.total)
const currentPage  = computed(() => page.props.logs.current_page)

function fetchLogs(extra = {}) {
    router.get(route('employee.logs'), {
        search: search.value || undefined,
        per_page: itemsPerPage.value,
        ...extra,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

const onPageChange         = (p) => fetchLogs({ page: p })
const onItemsPerPageChange = (n) => fetchLogs({ per_page: n, page: 1 })

let timer
watch(search, () => {
    clearTimeout(timer)
    timer = setTimeout(() => fetchLogs({ page: 1 }), 200)
})

// ── Table ───────────────────────────────────────────────
const headers = [
    { title: '#',            align: 'center', key: 'number',        sortable: false },
    { title: 'Date & Time',  align: 'center', key: 'created_at',    sortable: false },
    { title: 'Action',       align: 'center', key: 'type',          sortable: false },
    { title: 'Deceased',     align: 'center', key: 'deseased_name', sortable: false },
    { title: 'Details',      align: 'left',   key: 'message',       sortable: false },
    { title: 'Done By',      align: 'center', key: 'user_name',     sortable: false },
]

const typeLabel = (t) => (t === 'created' ? 'Created' : t === 'updated' ? 'Edited' : t)
</script>

<template>
    <AuthenticatedLayout>
        <div class="vr-card">
            <!-- Toolbar -->
            <div class="vr-toolbar">
                <div class="vr-toolbar-left">
                    <div class="vr-search-shell">
                        <v-icon size="16" class="vr-search-icon">mdi-magnify</v-icon>
                        <input v-model="search" class="vr-search-input"
                            placeholder="Search by Deceased, Details, Action, User..." />
                        <button v-if="search" type="button" @click="search = ''">
                            <v-icon size="14">mdi-close</v-icon>
                        </button>
                    </div>
                </div>
            </div>
            <div class="vr-divider"></div>

            <!-- Table -->
            <v-data-table-server
                :headers="headers"
                :items="$page.props.logs.data"
                hover
                class="vr-vtable"
                :items-per-page="itemsPerPage"
                :items-per-page-options="[
                    { value: 10,  title: '10' },
                    { value: 25,  title: '25' },
                    { value: 50,  title: '50' },
                    { value: 100, title: '100' },
                ]"
                :items-length="totalItems"
                :page="currentPage"
                @update:page="onPageChange"
                @update:items-per-page="onItemsPerPageChange"
            >
                <template v-slot:item.number="{ index }">
                    <span class="vr-td-num">
                        {{ (currentPage - 1) * itemsPerPage + index + 1 }}
                    </span>
                </template>

                <template v-slot:item.created_at="{ item }">
                    <span class="vr-name-text">{{ item.created_at }}</span>
                </template>

                <template v-slot:item.type="{ item }">
                    <span class="vr-badge" :class="item.type === 'New Record' ? 'vr-badge--new' : 'vr-badge--updated'">
                        {{ item.type }}
                    </span>
                </template>

                <template v-slot:item.deseased_name="{ item }">
                    <span class="vr-name-text">{{ item.deseased_name }}</span>
                </template>

                <template v-slot:item.message="{ item }">
                    <div class="vr-msg-list">
                        <div v-for="(line, i) in item.message.split('; ')" :key="i"
                            class="vr-msg-text" :class="{ 'vr-msg-changed': line.includes(' → ') }">
                            {{ line }}
                        </div>
                    </div>
                </template>

                <template v-slot:item.user_name="{ item }">
                    <span class="vr-name-text">{{ item.user_name }}</span>
                </template>
            </v-data-table-server>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.vr-card { margin: 10px 30px 24px 30px; }

.vr-vtable .v-data-table-footer__items-per-page { min-width: 200px; }
.vr-vtable .v-data-table-footer__items-per-page .v-select { min-width: 90px; }
.vr-vtable .v-data-table-footer__items-per-page .v-select .v-field__input {
    min-width: 50px;
    padding-inline: 8px;
}
</style>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

/* ─── Card ─── */
.vr-card {
    background: white;
    border: 1px solid #d8e8dc;
    border-radius: 18px;
    box-shadow: 0 1px 4px rgba(13,33,23,.05), 0 4px 20px rgba(13,33,23,.04);
    overflow: hidden;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

/* ─── Toolbar ─── */
.vr-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px;
    padding: 14px 20px;
    background: #f7fbf8;
    border-bottom: 1px solid #eaf3ec;
}
.vr-toolbar-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
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
.vr-search-input,
.vr-search-input:focus,
.vr-search-input:focus-visible {
    border: none !important; outline: none !important; box-shadow: none !important;
    --tw-ring-shadow: 0 0 #0000 !important;
    background: transparent !important;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .92rem; color: #0d2117; width: 100%;
}
.vr-search-input::placeholder { color: #a8c5b0; }
.vr-divider { height: 1px; background: #eaf3ec; }

/* ─── Table ─── */
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
.vr-name-text { font-weight: 600; color: #0d2117; font-size: .855rem; }
.vr-msg-text { color: #3d5e48; font-size: .82rem; line-height: 1.5; }

.vr-badge--new     { background: #eaf5ee; color: #065f46; border-color: #a7f3d0; }
.vr-badge--updated { background: #fffbeb; color: #b45309; border-color: #fde68a; }

.vr-msg-list { display: flex; flex-direction: column; gap: 2px; padding: 4px 0; }
.vr-msg-changed { font-weight: 700; color: #b45309; }

/* ─── Action badges ─── */
.vr-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: .7rem; font-weight: 700; letter-spacing: .3px;
    border: 1px solid transparent;
}
.vr-badge--created { background: #eaf5ee; color: #065f46; border-color: #a7f3d0; }
.vr-badge--updated { background: #fffbeb; color: #b45309; border-color: #fde68a; }

@media (max-width: 768px) {
    .vr-toolbar { flex-direction: column; align-items: stretch; }
    .vr-search-shell { min-width: unset; width: 100%; }
}
</style>
