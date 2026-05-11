// ═══════════════════════════════════════════════════════════════
//  DIGITDOC — Floating Field Stimulus Controller
//  app/assets/controllers/floating_field_controller.js
//
//  Usage Twig :
//  <div class="ff-wrap ff-group" data-controller="floating-field">
//    <input class="ff-input" ...>
//    <label class="ff-label">Mon label</label>
//  </div>
// ═══════════════════════════════════════════════════════════════

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

  // ── Cibles ────────────────────────────────────────────────────
  static targets = ['input', 'label', 'feedback', 'count'];

  // ── Valeurs configurables ──────────────────────────────────────
  static values = {
    maxLength:    { type: Number,  default: 0     }, // 0 = pas de limite
    warnAt:       { type: Number,  default: 80    }, // % restant pour avertissement
    validateOn:   { type: String,  default: 'blur' }, // 'blur' | 'input' | 'none'
    required:     { type: Boolean, default: false },
    minLength:    { type: Number,  default: 0     },
    pattern:      { type: String,  default: ''    },
    patternMsg:   { type: String,  default: 'Format invalide' },
    requiredMsg:  { type: String,  default: 'Ce champ est requis' },
    minLengthMsg: { type: String,  default: 'Trop court' },
  };

  // ── Cycle de vie ──────────────────────────────────────────────
  connect() {
    this._field = this._resolveField();
    if (!this._field) return;

    // État initial
    this._syncFilled();
    this._syncCount();
    this._addSelectClass();

    // Listeners
    this._field.addEventListener('focus', this._onFocus);
    this._field.addEventListener('blur',  this._onBlur);
    this._field.addEventListener('input', this._onInput);

    // Support des Live Components (les valeurs peuvent changer côté serveur)
    this.element.addEventListener('live:update', () => {
      this._syncFilled();
      this._syncCount();
    });
  }

  disconnect() {
    if (!this._field) return;
    this._field.removeEventListener('focus', this._onFocus);
    this._field.removeEventListener('blur',  this._onBlur);
    this._field.removeEventListener('input', this._onInput);
  }

  // ── Résolution du champ ────────────────────────────────────────
  _resolveField() {
    // Priorité : target explicite, sinon premier input/select/textarea
    if (this.hasInputTarget) return this.inputTarget;
    return (
      this.element.querySelector('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"])') ||
      this.element.querySelector('select') ||
      this.element.querySelector('textarea')
    );
  }

  // ── Handlers (arrow functions pour préserver `this`) ──────────
  _onFocus = () => {
    this.element.classList.add('is-focused');
    // Retire les classes d'état validation au focus
    this.element.classList.remove('has-error', 'is-valid', 'shake');
    this._clearFeedback();
  };

  _onBlur = () => {
    this.element.classList.remove('is-focused');
    this._syncFilled();
    if (this.validateOnValue !== 'none') {
      this._validate();
    }
  };

  _onInput = () => {
    this._syncFilled();
    this._syncCount();
    if (this.validateOnValue === 'input') {
      this._validate();
    }
  };

  // ── État "rempli" ──────────────────────────────────────────────
  _syncFilled() {
    const val = this._field.value;
    const filled = val !== null && val !== undefined && String(val).trim() !== '';
    this.element.classList.toggle('is-filled', filled);
  }

  // ── Classe select ──────────────────────────────────────────────
  _addSelectClass() {
    if (this._field.tagName === 'SELECT') {
      this.element.classList.add('ff-select-wrap');
    }
    if (this._field.tagName === 'TEXTAREA') {
      this.element.classList.add('ff-wrap--textarea');
    }
  }

  // ── Compteur de caractères ─────────────────────────────────────
  _syncCount() {
    if (!this.hasCountTarget) return;
    if (!this.maxLengthValue) return;

    const len   = this._field.value.length;
    const max   = this.maxLengthValue;
    const pct   = (len / max) * 100;
    const left  = max - len;

    this.countTarget.textContent = `${len} / ${max}`;
    this.countTarget.classList.toggle('is-near-limit', pct >= this.warnAtValue && pct < 100);
    this.countTarget.classList.toggle('is-at-limit',   pct >= 100);

    // Bloquer la saisie au max (renforce maxlength natif)
    if (len > max) {
      this._field.value = this._field.value.slice(0, max);
    }
  }

  // ── Validation ────────────────────────────────────────────────
  _validate() {
    const val  = this._field.value.trim();
    const msgs = [];

    // Requis
    if (this.requiredValue && val === '') {
      msgs.push(this.requiredMsgValue);
    }

    // Longueur min
    if (this.minLengthValue > 0 && val.length > 0 && val.length < this.minLengthValue) {
      msgs.push(this.minLengthMsgValue + ` (min ${this.minLengthValue})`);
    }

    // Pattern
    if (this.patternValue && val.length > 0) {
      try {
        const rx = new RegExp(this.patternValue);
        if (!rx.test(val)) msgs.push(this.patternMsgValue);
      } catch (_) { /* pattern invalide ignoré */ }
    }

    // Validation HTML5 native (email, number, etc.)
    if (!this._field.validity.valid && val.length > 0) {
      const nativeMsg = this._field.validationMessage;
      if (nativeMsg && !msgs.includes(nativeMsg)) {
        msgs.push(nativeMsg);
      }
    }

    if (msgs.length > 0) {
      this._setError(msgs[0]);
    } else if (val.length > 0) {
      this._setValid();
    } else {
      this._clearFeedback();
      this.element.classList.remove('has-error', 'is-valid');
    }
  }

  // ── API publique ───────────────────────────────────────────────

  /**
   * Afficher une erreur (appelable depuis l'extérieur)
   * ex: this.application.getControllerForElementAndIdentifier(el, 'floating-field').setError('Message')
   */
  setError(message) {
    this._setError(message);
  }

  /**
   * Forcer l'état valide
   */
  setValid(message = '') {
    this._setValid(message);
  }

  /**
   * Réinitialiser l'état
   */
  reset() {
    this.element.classList.remove('has-error', 'is-valid', 'is-filled', 'is-focused');
    this._clearFeedback();
    if (this.hasCountTarget) this.countTarget.textContent = `0 / ${this.maxLengthValue}`;
  }

  // ── Helpers privés ────────────────────────────────────────────
  _setError(message) {
    this.element.classList.add('has-error', 'shake');
    this.element.classList.remove('is-valid');

    // Retirer l'animation shake après qu'elle soit terminée
    this.element.addEventListener('animationend', () => {
      this.element.classList.remove('shake');
    }, { once: true });

    if (this.hasFeedbackTarget) {
      this.feedbackTarget.textContent = '';
      this.feedbackTarget.className = 'ff-feedback is-error';
      this.feedbackTarget.innerHTML = `
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" aria-hidden="true">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span>${this._escape(message)}</span>`;
    }
  }

  _setValid(message = '') {
    this.element.classList.remove('has-error');
    this.element.classList.add('is-valid');

    if (this.hasFeedbackTarget && message) {
      this.feedbackTarget.className = 'ff-feedback is-valid';
      this.feedbackTarget.innerHTML = `
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" aria-hidden="true">
          <path d="M20 6L9 17l-5-5"/>
        </svg>
        <span>${this._escape(message)}</span>`;
    } else if (this.hasFeedbackTarget) {
      this._clearFeedback();
    }
  }

  _clearFeedback() {
    if (this.hasFeedbackTarget) {
      this.feedbackTarget.textContent = '';
      this.feedbackTarget.className = 'ff-feedback';
    }
  }

  _escape(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
}