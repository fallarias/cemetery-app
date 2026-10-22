import { watch } from "vue";
import Swal from "sweetalert2";
import { router, usePage } from "@inertiajs/vue3";

export function useFlashWatcher(redirectRoute = null, routeParams = null) {
    const page = usePage();

    const redirect = () => {
        if (redirectRoute) {
            if (routeParams) {
                router.get(route(redirectRoute, routeParams));
            } else {
                router.get(route(redirectRoute));
            }
        }
    };

    const baseConfig = {
        showConfirmButton: true,
        confirmButtonText: "Got it",
        allowOutsideClick: true,
        allowEscapeKey: true,
        customClass: {
            popup:         'flash-popup',
            title:         'flash-title',
            htmlContainer: 'flash-html',
            confirmButton: 'flash-btn',
            icon:          'flash-icon',
        },
    };

    const configs = {
        success: {
            title: 'Success',
            iconHtml: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" width="36" height="36">
                <path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"
                    fill="rgba(16,185,129,0.15)" stroke="#10b981" stroke-width="1.5" stroke-linejoin="round"/>
                <path d="M8.5 12.5l2.5 2.5 4.5-4.5"
                    stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`,
            customClass: {
                ...baseConfig.customClass,
                popup:         'flash-popup flash-popup--success',
                confirmButton: 'flash-btn flash-btn--success',
                icon:          'flash-icon flash-icon--success flash-icon--custom',
            },
        },
        info: {
            icon: 'info',
            title: 'Info',
            iconColor: '#3b82f6',
            customClass: {
                ...baseConfig.customClass,
                popup:         'flash-popup flash-popup--info',
                confirmButton: 'flash-btn flash-btn--info',
                icon:          'flash-icon flash-icon--info',
            },
        },
        warning: {
            icon: 'warning',
            title: 'Warning',
            iconColor: '#f59e0b',
            customClass: {
                ...baseConfig.customClass,
                popup:         'flash-popup flash-popup--warning',
                confirmButton: 'flash-btn flash-btn--warning',
                icon:          'flash-icon flash-icon--warning',
            },
        },
        error: {
            icon: 'error',
            title: 'Error',
            iconColor: '#ef4444',
            customClass: {
                ...baseConfig.customClass,
                popup:         'flash-popup flash-popup--error',
                confirmButton: 'flash-btn flash-btn--error',
                icon:          'flash-icon flash-icon--error',
            },
        },
    };

    const injectStyles = () => {
        if (document.getElementById('flash-watcher-styles')) return;
        const style = document.createElement('style');
        style.id = 'flash-watcher-styles';
        style.textContent = `
            @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

            /* ── Backdrop ── */
            .swal2-backdrop-show {
                background: rgba(10, 20, 15, 0.55) !important;
                backdrop-filter: blur(6px) !important;
            }

            /* ── Popup base ── */
            .flash-popup {
                font-family: 'Plus Jakarta Sans', sans-serif !important;
                border-radius: 20px !important;
                padding: 0 !important;
                overflow: hidden !important;
                box-shadow: 0 24px 80px rgba(0,0,0,.22), 0 4px 16px rgba(0,0,0,.08) !important;
                border: 1.5px solid #e5e7eb !important;
                max-width: 400px !important;
                width: 400px !important;
            }

            /* ── Color accent strips per type ── */
            .flash-popup--success { border-top: 4px solid #10b981 !important; }
            .flash-popup--info    { border-top: 4px solid #3b82f6 !important; }
            .flash-popup--warning { border-top: 4px solid #f59e0b !important; }
            .flash-popup--error   { border-top: 4px solid #ef4444 !important; }

            /* ── Icon container base ── */
            .flash-icon {
                margin: 28px auto 8px !important;
                width: 64px !important;
                height: 64px !important;
                border-radius: 50% !important;
            }

            /* ── Custom SVG icon (removes SweetAlert2 default borders/pseudo-elements) ── */
            .flash-icon--custom {
                border: none !important;
                background: transparent !important;
                box-shadow: none !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            .flash-icon--custom::before,
            .flash-icon--custom::after {
                display: none !important;
            }
            .flash-icon--custom .swal2-icon-content {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }

            /* ── Success icon ── */
            .flash-icon--success {
                background: rgba(16,185,129,.10) !important;
                border: 2px solid rgba(16,185,129,.25) !important;
            }
            .flash-icon--success .swal2-success-ring {
                border-color: rgba(16,185,129,.25) !important;
            }
            .flash-icon--success .swal2-success-line-tip,
            .flash-icon--success .swal2-success-line-long {
                background-color: #10b981 !important;
            }
            .flash-icon--success .swal2-success-fix {
                background-color: transparent !important;
            }
            .flash-icon--success::before,
            .flash-icon--success::after {
                background: transparent !important;
                background-color: transparent !important;
            }

            /* ── Info icon ── */
            .flash-icon--info {
                background: rgba(59,130,246,.10) !important;
                border: 2px solid rgba(59,130,246,.25) !important;
                color: #3b82f6 !important;
            }
            .flash-icon--info .swal2-icon-content {
                color: #3b82f6 !important;
                font-size: 2.2rem !important;
                font-weight: 800 !important;
            }

            /* ── Warning icon ── */
            .flash-icon--warning {
                background: rgba(245,158,11,.10) !important;
                border: 2px solid rgba(245,158,11,.25) !important;
                color: #f59e0b !important;
            }
            .flash-icon--warning .swal2-icon-content {
                color: #f59e0b !important;
                font-size: 2.2rem !important;
                font-weight: 800 !important;
            }

            /* ── Error icon ── */
            .flash-icon--error {
                background: rgba(239,68,68,.10) !important;
                border: 2px solid rgba(239,68,68,.25) !important;
                color: #ef4444 !important;
            }
            .flash-icon--error .swal2-icon-content {
                color: #ef4444 !important;
                font-size: 2.2rem !important;
            }
            .flash-icon--error .swal2-x-mark-line-left,
            .flash-icon--error .swal2-x-mark-line-right {
                background-color: #ef4444 !important;
            }

            /* ── Title ── */
            .flash-title {
                font-family: 'Plus Jakarta Sans', sans-serif !important;
                font-size: 1.05rem !important;
                font-weight: 800 !important;
                color: #0d1a10 !important;
                padding: 8px 28px 4px !important;
                letter-spacing: -.01em !important;
            }

            /* ── Body text ── */
            .flash-html {
                font-family: 'Plus Jakarta Sans', sans-serif !important;
                font-size: .84rem !important;
                color: #4b5563 !important;
                line-height: 1.6 !important;
                padding: 0 28px 4px !important;
                font-weight: 400 !important;
            }

            /* ── Actions area ── */
            .swal2-actions {
                padding: 16px 28px 24px !important;
                gap: 10px !important;
                margin: 0 !important;
            }

            /* ── Button base ── */
            .flash-btn {
                font-family: 'Plus Jakarta Sans', sans-serif !important;
                font-size: .82rem !important;
                font-weight: 700 !important;
                padding: 10px 28px !important;
                border-radius: 10px !important;
                border: none !important;
                cursor: pointer !important;
                letter-spacing: .01em !important;
                transition: all .18s ease !important;
                color: white !important;
                width: 100% !important;
            }

            .flash-btn:hover  { transform: translateY(-1px) !important; }
            .flash-btn:active { transform: translateY(0) !important; }
            .flash-btn:focus  { outline: none !important; box-shadow: none !important; }

            /* ── Button color variants ── */
            .flash-btn--success {
                background: linear-gradient(135deg, #10b981, #059669) !important;
                box-shadow: 0 4px 14px rgba(16,185,129,.30) !important;
            }
            .flash-btn--success:hover {
                background: linear-gradient(135deg, #059669, #047857) !important;
                box-shadow: 0 6px 20px rgba(16,185,129,.40) !important;
            }

            .flash-btn--info {
                background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
                box-shadow: 0 4px 14px rgba(59,130,246,.30) !important;
            }
            .flash-btn--info:hover {
                background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
                box-shadow: 0 6px 20px rgba(59,130,246,.40) !important;
            }

            .flash-btn--warning {
                background: linear-gradient(135deg, #f59e0b, #d97706) !important;
                box-shadow: 0 4px 14px rgba(245,158,11,.30) !important;
            }
            .flash-btn--warning:hover {
                background: linear-gradient(135deg, #d97706, #b45309) !important;
                box-shadow: 0 6px 20px rgba(245,158,11,.40) !important;
            }

            .flash-btn--error {
                background: linear-gradient(135deg, #ef4444, #dc2626) !important;
                box-shadow: 0 4px 14px rgba(239,68,68,.30) !important;
            }
            .flash-btn--error:hover {
                background: linear-gradient(135deg, #dc2626, #b91c1c) !important;
                box-shadow: 0 6px 20px rgba(239,68,68,.40) !important;
            }

            /* ── Timer progress bar ── */
            .swal2-timer-progress-bar-container {
                border-radius: 0 !important;
            }
            .swal2-timer-progress-bar {
                border-radius: 0 !important;
                height: 3px !important;
                opacity: .7 !important;
            }
            .flash-popup--success .swal2-timer-progress-bar { background: #10b981 !important; }
            .flash-popup--info    .swal2-timer-progress-bar { background: #3b82f6 !important; }
            .flash-popup--warning .swal2-timer-progress-bar { background: #f59e0b !important; }
            .flash-popup--error   .swal2-timer-progress-bar { background: #ef4444 !important; }

            /* ── Entrance / exit animations ── */
            .swal2-show { animation: flashPop .28s cubic-bezier(.34,1.56,.64,1) both !important; }
            .swal2-hide { animation: flashFade .18s ease both !important; }

            @keyframes flashPop {
                from { opacity: 0; transform: scale(.88) translateY(16px); }
                to   { opacity: 1; transform: scale(1)   translateY(0); }
            }
            @keyframes flashFade {
                from { opacity: 1; transform: scale(1); }
                to   { opacity: 0; transform: scale(.94); }
            }
        `;
        document.head.appendChild(style);
    };

    watch(
        () => page.props.flash,
        (flash) => {
            injectStyles();

            if (flash?.success) {
                Swal.fire({
                    ...baseConfig,
                    ...configs.success,
                    text: flash.success,
                    timer: 4000,
                    timerProgressBar: true,
                }).then(redirect);

            } else if (flash?.info) {
                Swal.fire({
                    ...baseConfig,
                    ...configs.info,
                    text: flash.info,
                    timer: 4000,
                    timerProgressBar: true,
                }).then(redirect);

            } else if (flash?.warning) {
                Swal.fire({
                    ...baseConfig,
                    ...configs.warning,
                    text: flash.warning,
                }).then(redirect);

            } else if (flash?.error) {
                Swal.fire({
                    ...baseConfig,
                    ...configs.error,
                    text: flash.error,
                });
            }
        },
        { immediate: true }
    );

    return { injectStyles, baseConfig, configs };
}
