import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <>
                <App {...props} />
                <Toaster
                    theme="system"
                    richColors
                    position="top-right"
                    closeButton
                    toastOptions={{
                        classNames: {
                            success: 'border-l-4 border-l-emerald-500',
                            error: 'border-l-4 border-l-red-500',
                            warning: 'border-l-4 border-l-amber-500',
                            info: 'border-l-4 border-l-[#135eeb]',
                        },
                    }}
                />
            </>,
        );
    },
    progress: {
        color: '#2f5233',
    },
});

// This will set light / dark mode on load...
initializeTheme();
