import '@hotwired/turbo';
import { startStimulusApp } from '@symfony/stimulus-bundle';
import AlpineModule from 'alpinejs';
import ApexChartsModule from 'apexcharts';

if (typeof window !== 'undefined') {
    const Alpine = AlpineModule?.default ?? AlpineModule;
    if (!window.Alpine) {
        window.Alpine = Alpine;
        window.Alpine.start();
    }

    const ApexCharts = ApexChartsModule?.default ?? ApexChartsModule;
    if (!window.ApexCharts) {
        window.ApexCharts = ApexCharts;
    }
}

const app = startStimulusApp();

import SetupSecretController from './controllers/setup_secret_controller.js';
app.register('setup-secret', SetupSecretController);
// register any additional custom controllers below
