// assets/controllers/document_reference_controller.js
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    static targets = [
        "reference",
        "typeDocument", 
        "personnel",
        "titulaire",
        "date",
        "titre",
    ];

    connect() {
        this.typeData      = {};
        this.personnelData = {};

        this.waitForTomSelect(this.typeDocumentTarget, (ts) => {
            ts.on("change", (value) => this.loadTypeDoc(value));
            if (ts.getValue()) {
                this.loadTypeDoc(ts.getValue());
            }
        });

        this.waitForTomSelect(this.personnelTarget, (ts) => {
            ts.on("change", (value) => this.loadPersonnel(value));
            if (ts.getValue()) {
                this.loadPersonnel(ts.getValue());
            }
        });

        this.titulaireTarget.addEventListener("input",  () => this.generate());
        this.dateTarget.addEventListener("change",      () => this.generate());
        this.dateTarget.addEventListener("input",       () => this.generate());
    }

    waitForTomSelect(element, callback) {
        if (element.tomselect) {
            callback(element.tomselect);
            return;
        }

        let attempts = 0;
        const interval = setInterval(() => {
            attempts++;
            if (element.tomselect) {
                clearInterval(interval);
                callback(element.tomselect);
            } else if (attempts > 50) {
                clearInterval(interval);
                console.warn("TomSelect non trouvé sur", element, "— fallback natif");
                element.addEventListener("change", () => {
                    const val = element.value;
                    if (element === this.typeDocumentTarget) this.loadTypeDoc(val);
                    if (element === this.personnelTarget)    this.loadPersonnel(val);
                });
            }
        }, 100);
    }

    async loadTypeDoc(value) {
        if (!value) {
            this.typeData = {};
            this.generate();
            return;
        }

        try {
            const res = await fetch(`/api/type-document/${value}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            this.typeData = await res.json();
        } catch (e) {
            console.error("Erreur loadTypeDoc:", e);
            this.typeData = {};
        }

        this.generate();
    }

    async loadPersonnel(value) {
        if (!value) {
            this.personnelData = {};
            this.generate();
            return;
        }

        try {
            const res = await fetch(`/api/personnel/${value}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            this.personnelData = await res.json();
        } catch (e) {
            console.error("Erreur loadPersonnel:", e);
            this.personnelData = {};
        }

        this.generate();
    }

    generate() {
        const nomTypeDoc   = this.typeData.nom          || "";
        const nomenclature = this.typeData.nomenclature  || "";
        const idDossier    = this.typeData.dossier       || "";
        const im           = this.personnelData.im        || "";

        const titulaire = this.cleanTitulaire(this.titulaireTarget.value);
        const date      = this.dateTarget.value
            ? this.formatDate(this.dateTarget.value)
            : "";

        if (nomTypeDoc) {
            this.titreTarget.value = nomTypeDoc;
        }

        const parts = [];
        if (im)           parts.push(im);
        if (idDossier)    parts.push(idDossier);
        if (nomenclature) parts.push(nomenclature);
        if (titulaire)    parts.push(titulaire);
        if (nomTypeDoc)   parts.push(nomTypeDoc);

        let reference = parts.join("_");

        if (date) {
            reference += "_du " + date;
        }

        this.referenceTarget.value = reference;
    }

    formatDate(dateStr) {
        const d     = new Date(dateStr + "T00:00:00");
        const day   = String(d.getDate()).padStart(2, "0");
        const month = String(d.getMonth() + 1).padStart(2, "0");
        const year  = d.getFullYear();
        return `${day}.${month}.${year}`;
    }

    cleanTitulaire(val) {
        if (!val) return "";
        return val
            .trim()
            .replace(/\s+/g, " ")
            .replace(/[^a-zA-ZÀ-ÿ0-9\s'\-]/g, "");
    }
}