<script setup>
import { ref, computed } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const showPassword = ref(false)
const showConfirm  = ref(false)

const strength = computed(() => {
    const p = form.password
    if (!p) return 0
    let s = 0
    if (p.length >= 8) s++
    if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++
    if (/\d/.test(p)) s++
    if (/[^A-Za-z0-9]/.test(p)) s++
    return s
})

const strengthLabel = computed(() => ['', 'Weak', 'Fair', 'Good', 'Strong'][strength.value])

const matchState = computed(() => {
    if (!form.password_confirmation) return null
    return form.password === form.password_confirmation
})

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <div class="login-header">
            <div class="login-icon">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round">
                    <path d="M12 3v18M7 8h10" />
                </svg>
            </div>
            <h1 class="login-title">Create an account</h1>
            <p class="login-sub">Register to access cemetery records</p>
        </div>

        <form @submit.prevent="submit" novalidate>
            <!-- Name -->
            <div class="lf-group">
                <label for="name" class="lf-label">Name</label>
                <div class="lf-shell" :class="{ 'lf-shell--error': form.errors.name }">
                    <svg class="lf-icon" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="4" />
                        <path d="M4 20c0-4 3.6-6 8-6s8 2 8 6" />
                    </svg>
                    <input
                        id="name"
                        type="text"
                        class="lf-input"
                        v-model="form.name"
                        placeholder="Full name"
                        required
                        autofocus
                        autocomplete="name"
                    />
                </div>
                <InputError class="lf-error" :message="form.errors.name" />
            </div>

            <!-- Email -->
            <div class="lf-group">
                <label for="email" class="lf-label">Email</label>
                <div class="lf-shell" :class="{ 'lf-shell--error': form.errors.email }">
                    <svg class="lf-icon" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="14" rx="2" />
                        <path d="M3 7l9 6 9-6" />
                    </svg>
                    <input
                        id="email"
                        type="email"
                        class="lf-input"
                        v-model="form.email"
                        placeholder="you@example.com"
                        required
                        autocomplete="username"
                    />
                </div>
                <InputError class="lf-error" :message="form.errors.email" />
            </div>

            <!-- Password -->
            <div class="lf-group">
                <label for="password" class="lf-label">Password</label>
                <div class="lf-shell" :class="{ 'lf-shell--error': form.errors.password }">
                    <svg class="lf-icon" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="11" width="14" height="9" rx="2" />
                        <path d="M8 11V8a4 4 0 0 1 8 0v3" />
                    </svg>
                    <input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="lf-input"
                        v-model="form.password"
                        placeholder="Create a password"
                        required
                        autocomplete="new-password"
                    />
                    <button type="button" class="lf-eye" :aria-label="showPassword ? 'Hide password' : 'Show password'" @click="showPassword = !showPassword">
                        <svg v-if="!showPassword" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3l18 18" />
                            <path d="M10.6 6.1A10.6 10.6 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-3.2 3.9M6.6 7.6A17 17 0 0 0 2 12s3.5 7 10 7c1.7 0 3.2-.4 4.5-1" />
                            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                        </svg>
                    </button>
                </div>

                <div v-if="form.password" class="meter">
                    <div class="meter-bars">
                        <span v-for="n in 4" :key="n" class="meter-bar" :class="n <= strength ? 'meter-bar--s' + strength : ''"></span>
                    </div>
                    <span class="meter-label" :class="'meter-label--s' + strength">{{ strengthLabel }}</span>
                </div>
                <InputError class="lf-error" :message="form.errors.password" />
            </div>

            <!-- Confirm password -->
            <div class="lf-group">
                <label for="password_confirmation" class="lf-label">Confirm password</label>
                <div class="lf-shell" :class="{ 'lf-shell--error': form.errors.password_confirmation || matchState === false }">
                    <svg class="lf-icon" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3z" />
                        <path d="M9 12l2 2 4-4" />
                    </svg>
                    <input
                        id="password_confirmation"
                        :type="showConfirm ? 'text' : 'password'"
                        class="lf-input"
                        v-model="form.password_confirmation"
                        placeholder="Re-enter your password"
                        required
                        autocomplete="new-password"
                    />
                    <button type="button" class="lf-eye" :aria-label="showConfirm ? 'Hide password' : 'Show password'" @click="showConfirm = !showConfirm">
                        <svg v-if="!showConfirm" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3l18 18" />
                            <path d="M10.6 6.1A10.6 10.6 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-3.2 3.9M6.6 7.6A17 17 0 0 0 2 12s3.5 7 10 7c1.7 0 3.2-.4 4.5-1" />
                            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                        </svg>
                    </button>
                </div>
                <p v-if="matchState !== null" class="match" :class="matchState ? 'match--ok' : 'match--bad'">
                    {{ matchState ? 'Passwords match' : 'Passwords do not match' }}
                </p>
                <InputError class="lf-error" :message="form.errors.password_confirmation" />
            </div>

            <!-- Submit -->
            <button type="submit" class="lf-submit" :disabled="form.processing">
                <span v-if="form.processing" class="lf-spinner"></span>
                {{ form.processing ? 'Creating account…' : 'Register' }}
            </button>

            <p class="alt">
                Already registered?
                <Link :href="route('login')" class="lf-link">Log in</Link>
            </p>
        </form>
    </GuestLayout>
</template>

<style scoped>
/* ── Header ── */
.login-header { text-align: center; margin-bottom: 1.5rem; }
.login-icon {
    width: 52px;
    height: 52px;
    margin: 0 auto 12px;
    border-radius: 18px;
    background: linear-gradient(135deg, #14532d, #22a35a);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 18px rgba(20, 83, 45, 0.35);
}
.login-title { font-size: 1.5rem; font-weight: 600; color: #0d2117; line-height: 1.2; }
.login-sub { margin-top: 4px; font-size: 0.9rem; color: #6b7f73; }

/* ── Fields ── */
.lf-group { margin-bottom: 1rem; }
.lf-label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #374151;
    letter-spacing: 0.02em;
}
.lf-shell {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 12px;
    height: 46px;
    background: #f6faf7;
    border: 1.5px solid #d3e3d9;
    border-radius: 14px;
    transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
}
.lf-shell:focus-within {
    background: #ffffff;
    border-color: #22a35a;
    box-shadow: 0 0 0 3px rgba(34, 163, 90, 0.15);
}
.lf-shell--error { border-color: #dc2626; background: #fef8f8; }
.lf-shell--error:focus-within {
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
}
.lf-icon { color: #6b8f7a; flex-shrink: 0; }
.lf-input {
    flex: 1;
    min-width: 0;
    height: 100%;
    background: transparent;
    border: none;
    outline: none;
    box-shadow: none;
    padding: 0;
    font-size: 0.95rem;
    color: #111827;
}
.lf-input:focus { box-shadow: none; outline: none; }
.lf-input::placeholder { color: #9ca3af; }
.lf-eye {
    background: none;
    border: none;
    padding: 4px;
    color: #6b8f7a;
    cursor: pointer;
    display: flex;
    border-radius: 8px;
}
.lf-eye:hover { color: #14532d; background: rgba(34, 163, 90, 0.1); }
.lf-error { margin-top: 6px; }

/* ── Strength meter ── */
.meter { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
.meter-bars { display: flex; gap: 4px; flex: 1; }
.meter-bar { height: 4px; flex: 1; border-radius: 4px; background: #e2ebe5; transition: background 0.2s; }
.meter-bar--s1 { background: #dc2626; }
.meter-bar--s2 { background: #f59e0b; }
.meter-bar--s3 { background: #65a30d; }
.meter-bar--s4 { background: #15803d; }
.meter-label { font-size: 0.75rem; font-weight: 600; min-width: 44px; text-align: right; }
.meter-label--s1 { color: #dc2626; }
.meter-label--s2 { color: #b45309; }
.meter-label--s3 { color: #4d7c0f; }
.meter-label--s4 { color: #15803d; }

.match { margin-top: 6px; font-size: 0.78rem; font-weight: 500; }
.match--ok { color: #15803d; }
.match--bad { color: #dc2626; }

/* ── Submit ── */
.lf-submit {
    width: 100%;
    height: 46px;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: none;
    border-radius: 14px;
    background: linear-gradient(135deg, #14532d, #1f8a4c);
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    cursor: pointer;
    box-shadow: 0 6px 16px rgba(20, 83, 45, 0.35);
    transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
}
.lf-submit:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 9px 22px rgba(20, 83, 45, 0.42);
}
.lf-submit:disabled { opacity: 0.6; cursor: not-allowed; }

.lf-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: lf-spin 0.7s linear infinite;
}
@keyframes lf-spin { to { transform: rotate(360deg); } }

.alt { margin-top: 1.1rem; text-align: center; font-size: 0.85rem; color: #4b5563; }
.lf-link { font-weight: 600; color: #15803d; text-decoration: none; margin-left: 4px; }
.lf-link:hover { text-decoration: underline; color: #14532d; }
</style>
