<script setup>
import { ref } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const showPassword = ref(false)

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div class="login-header">
            <div class="login-icon">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round">
                    <path d="M12 3v18M7 8h10" />
                </svg>
            </div>
            <h1 class="login-title">Welcome back</h1>
            <p class="login-sub">Sign in to manage cemetery records</p>
        </div>

        <div v-if="status" class="login-status">
            {{ status }}
        </div>

        <form @submit.prevent="submit" novalidate>
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
                        autofocus
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
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    />
                    <button
                        type="button"
                        class="lf-eye"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        @click="showPassword = !showPassword"
                    >
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
                <InputError class="lf-error" :message="form.errors.password" />
            </div>

            <!-- Remember + Forgot -->
            <div class="lf-row">
                <label class="lf-check">
                    <input type="checkbox" name="remember" v-model="form.remember" />
                    <span>Remember me</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="lf-link"
                >
                    Forgot password?
                </Link>
            </div>

            <!-- Submit -->
            <button type="submit" class="lf-submit" :disabled="form.processing">
                <span v-if="form.processing" class="lf-spinner"></span>
                {{ form.processing ? 'Signing in…' : 'Log in' }}
            </button>

            <Link :href="route('register')">
                <span class="lf-register">Register</span>
            </Link>
        </form>
    </GuestLayout>
</template>

<style scoped>
/* ── Header ── */
.login-header {
    text-align: center;
    margin-bottom: 1.5rem;
}
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
.login-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #0d2117;
    line-height: 1.2;
}
.login-sub {
    margin-top: 4px;
    font-size: 0.9rem;
    color: #6b7f73;
}

.login-status {
    margin-bottom: 1rem;
    padding: 10px 12px;
    border-radius: 12px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
    font-size: 0.875rem;
    font-weight: 500;
}

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
.lf-shell--error {
    border-color: #dc2626;
    background: #fef8f8;
}
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

/* ── Remember / forgot ── */
.lf-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0.25rem 0 1.25rem;
}
.lf-check {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: #4b5563;
    cursor: pointer;
}
.lf-check input {
    width: 16px;
    height: 16px;
    accent-color: #16a34a;
    border-radius: 4px;
    cursor: pointer;
}
.lf-link {
    font-size: 0.85rem;
    font-weight: 500;
    color: #15803d;
    text-decoration: none;
}
.lf-link:hover { text-decoration: underline; color: #14532d; }

/* ── Submit ── */
.lf-submit {
    width: 100%;
    height: 46px;
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

/* ── Register ── */
.lf-register {
    width: 100%;
    height: 46px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 5px;
    gap: 8px;
    border: none;
    border-radius: 14px;
    background: linear-gradient(135deg, #151e18, #92d2ad);
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    cursor: pointer;
    box-shadow: 0 6px 16px rgba(20, 83, 45, 0.35);
    transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
}
.lf-register:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 9px 22px rgba(20, 83, 45, 0.42);
}
.lf-register:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
