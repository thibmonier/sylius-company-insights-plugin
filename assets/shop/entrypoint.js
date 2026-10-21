import { Application } from '@hotwired/stimulus';
import ChartController from './controllers/chart_controller';

// The application must provide `apexcharts` (already a dependency of the Sylius admin) and
// `@hotwired/stimulus`. The charts are only a drawing of the data tables of the page.
const application = Application.start();
application.register('tmonier-insights-chart', ChartController);
