import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';

// Gebruik de ZiggyVue van de vendor directory direct, zoals Laravel Breeze dat doet
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.mjs';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// CRUCIAAL: Voeg een unieke string toe die we kunnen vinden in de bundle
window.DOCS_APP_VERSION = 'PEST-V100-REAL';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        console.log('Mounting Docs App PEST-V100-REAL...');
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
