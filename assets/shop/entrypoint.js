import { Application } from '@hotwired/stimulus';
import ChartController from './controllers/chart_controller';
import DonutController from './controllers/donut_controller';

const application = Application.start();
application.register('tmonier-insights-chart', ChartController);
application.register('tmonier-insights-donut', DonutController);
