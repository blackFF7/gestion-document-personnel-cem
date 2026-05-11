// assets/bootstrap.js
import './styles/scroll.css'
import { startStimulusApp } from '@symfony/stimulus-bundle';
import DashboardController from './controllers/dashboard_controller.js';


const app = startStimulusApp();
app.register('dashboard', DashboardController);