// assets/bootstrap.js
import { startStimulusApp } from '@symfony/stimulus-bundle';
import DocumentReferenceController from './controllers/document_reference_controller.js';

const app = startStimulusApp();
app.register('document-reference', DocumentReferenceController);