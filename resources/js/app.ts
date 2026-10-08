import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { installFspEnhancements } from '@/lib/fspEnhancements';
import { installFspInteractions } from '@/lib/fspInteractions';
import { installFspProcessActions } from '@/lib/fspProcessActions';
import '../css/fsp-enhancements.css';
import '../css/fsp-v6-fixes.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Apply the saved light/dark preference before custom FSP pages are mounted.
initializeTheme();

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name === 'Dashboard':
            case name === 'FspWorkspace':
            case name === 'CandidateCenter':
            case name === 'AssessmentSession':
            case name.startsWith('auth/'):
                return null;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#6d28d9',
    },
});

initializeFlashToast();
installFspEnhancements();
installFspInteractions();
installFspProcessActions();
