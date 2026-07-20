/**
 * Campus Lost & Found Management System
 * ST002-1: Login Form Real-time Validation & Backend Fetch
 */

(function () {
  'use strict';

  /* ── DOM References ── */
  const form          = document.getElementById('loginForm');
  const emailInput    = document.getElementById('emailAddress');
  const passwordInput = document.getElementById('password');
  const submitBtn     = document.getElementById('submitBtn');
  const btnLoader     = document.getElementById('btnLoader');
  const formAlert     = document.getElementById('formAlert');

  /* ── Validation State ── */
  const state = { email: false, password: false };

  /* ── Helpers ── */
  function setFieldState(groupId, statusIconId, isValid, message) {
    const group = document.getElementById(groupId);
    const icon  = document.getElementById(statusIconId);
    const msg   = group ? group.querySelector('[role="alert"]') : null;

    if (!group) return;

    group.classList.toggle('valid', isValid);
    group.classList.toggle('error', !isValid && message !== '');

    if (icon) {
      icon.classList.remove('ok', 'bad', 'show');
      if (message !== '' || isValid) {
        icon.classList.add(isValid ? 'ok' : 'bad', 'show');
      }
    }

    if (msg) {
      msg.textContent = message;
      msg.className = 'field-message';
      if (message) msg.classList.add(isValid ? 'msg-success' : 'msg-error');
    }
  }

  function clearField(groupId, statusIconId) {
    const group = document.getElementById(groupId);
    const icon  = document.getElementById(statusIconId);
    const msg   = group ? group.querySelector('[role="alert"]') : null;
    if (!group) return;
    group.classList.remove('valid', 'error');
    if (icon) icon.classList.remove('ok', 'bad', 'show');
    if (msg)  { msg.textContent = ''; msg.className = 'field-message'; }
  }

  function showFormAlert(message, type = 'danger') {
    if (!formAlert) return;
    formAlert.textContent = message;
    formAlert.className = `alert alert-${type}`;
    formAlert.classList.remove('d-none');
  }

  function hideFormAlert() {
    if (!formAlert) return;
    formAlert.textContent = '';
    formAlert.classList.add('d-none');
  }

  /* ── Email Validation ── */
  function validateEmail(live) {
    const val = emailInput.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    if (!val && !live) {
      setFieldState('fieldGroupEmail', 'statusIconEmail', false, 'Email address is required.');
      state.email = false;
    } else if (!val) {
      clearField('fieldGroupEmail', 'statusIconEmail');
      state.email = false;
    } else if (!emailRegex.test(val)) {
      setFieldState('fieldGroupEmail', 'statusIconEmail', false, 'Please enter a valid email address.');
      state.email = false;
    } else {
      setFieldState('fieldGroupEmail', 'statusIconEmail', true, 'Valid email format.');
      state.email = true;
    }
  }

  /* ── Password Validation ── */
  function validatePassword(live) {
    const val = passwordInput.value;
    if (!val && !live) {
      setFieldState('fieldGroupPassword', 'statusIconPassword', false, 'Password is required.');
      state.password = false;
    } else if (!val) {
      clearField('fieldGroupPassword', 'statusIconPassword');
      state.password = false;
    } else {
      setFieldState('fieldGroupPassword', 'statusIconPassword', true, '');
      state.password = true;
    }
  }

  /* ── Password Toggle ── */
  function setupToggle(btnId, inputEl) {
    const btn = document.getElementById(btnId);
    if (!btn || !inputEl) return;
    btn.addEventListener('click', () => {
      const isText = inputEl.type === 'text';
      inputEl.type = isText ? 'password' : 'text';
      const open   = btn.querySelector('.eye-open');
      const closed = btn.querySelector('.eye-closed');
      if (open)   open.style.display   = isText ? '' : 'none';
      if (closed) closed.style.display = isText ? 'none' : '';
      btn.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');
    });
  }

  /* ── Event Listeners ── */
  emailInput.addEventListener('input',     () => validateEmail(true));
  emailInput.addEventListener('blur',      () => validateEmail(false));
  passwordInput.addEventListener('input',  () => validatePassword(true));
  passwordInput.addEventListener('blur',   () => validatePassword(false));

  setupToggle('togglePassword', passwordInput);

  /* ── Form Submit ── */
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    // Run validations
    validateEmail(false);
    validatePassword(false);

    const allValid = Object.values(state).every(Boolean);
    if (!allValid) {
      const firstError = form.querySelector('.error .form-input');
      if (firstError) firstError.focus();
      return;
    }

    hideFormAlert();
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;

    // Clear stale field error highlights
    document.querySelectorAll('.field-group').forEach(g => {
      if (!g.classList.contains('valid')) {
        g.classList.remove('error');
      }
    });

    const formData = {
      emailAddress: emailInput.value.trim(),
      password: passwordInput.value
    };

    fetch('login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(formData)
    })
    .then(response => {
      return response.json().then(data => {
        if (!response.ok) {
          return Promise.reject({ status: response.status, data: data });
        }
        return data;
      });
    })
    .then(data => {
      // Redirect on success
      window.location.href = data.redirect;
    })
    .catch(error => {
      submitBtn.classList.remove('loading');
      submitBtn.disabled = false;
      
      if (error.data && error.data.status === 'validation_error') {
        const errs = error.data.errors;
        Object.keys(errs).forEach(field => {
          if (field === 'emailAddress') {
            setFieldState('fieldGroupEmail', 'statusIconEmail', false, errs.emailAddress);
          } else if (field === 'password') {
            setFieldState('fieldGroupPassword', 'statusIconPassword', false, errs.password);
          } else if (field === 'account') {
            showFormAlert(errs.account, 'danger');
          }
        });
        
        const firstError = form.querySelector('.error .form-input');
        if (firstError) firstError.focus();
      } else {
        const msg = error.data && error.data.message ? error.data.message : 'An unexpected server error occurred. Please try again.';
        showFormAlert(msg, 'danger');
      }
    });
  });

})();
