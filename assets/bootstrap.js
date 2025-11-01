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
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
