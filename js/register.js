/**
 * Campus Lost & Found Management System
 * ST001-1 & ST001-2: Registration Form Real-time Validation & Backend Fetch
 */

(function () {
  'use strict';

  /* ── DOM References ── */
  const form            = document.getElementById('registrationForm');
  const fullNameInput   = document.getElementById('fullName');
  const emailInput      = document.getElementById('emailAddress');
  const passwordInput   = document.getElementById('password');
  const confirmInput    = document.getElementById('confirmPassword');
  const termsInput      = document.getElementById('agreeTerms');
  const submitBtn       = document.getElementById('submitBtn');
  const progressFill    = document.getElementById('progressFill');
  const progressLabel   = document.getElementById('progressLabel');
  const strengthText    = document.getElementById('strengthText');
  const successToast    = document.getElementById('successToast');
  const btnLoader       = document.getElementById('btnLoader');

  /* ── Validation State ── */
  const state = { name: false, email: false, password: false, confirm: false, terms: false };

  /* ── Helpers ── */
  function setFieldState(groupId, statusIconId, isValid, message, messageType) {
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

  /* ── Progress Bar ── */
  function updateProgress() {
    const filled = Object.values(state).filter(Boolean).length;
    const pct    = Math.round((filled / Object.keys(state).length) * 100);
    progressFill.style.width = pct + '%';
    progressLabel.textContent = pct + '% complete';
    const track = progressFill.closest('[role="progressbar"]');
    if (track) track.setAttribute('aria-valuenow', pct);
  }

  /* ── Full Name Validation ── */
  function validateName(live) {
    const val = fullNameInput.value.trim();
    if (!val && !live) {
      setFieldState('fieldGroupName', 'statusIconName', false, 'Full name is required.', 'error');
      state.name = false;
    } else if (!val) {
      clearField('fieldGroupName', 'statusIconName');
      state.name = false;
    } else if (val.length < 2) {
      setFieldState('fieldGroupName', 'statusIconName', false, 'Name must be at least 2 characters.', 'error');
      state.name = false;
    } else if (!/^[a-zA-Z\s'.'-]+$/.test(val)) {
      setFieldState('fieldGroupName', 'statusIconName', false, 'Name should contain only letters and spaces.', 'error');
      state.name = false;
    } else {
      setFieldState('fieldGroupName', 'statusIconName', true, 'Looks great!', 'success');
      state.name = true;
    }
    updateProgress();
  }

  /* ── Email Validation ── */
  function validateEmail(live) {
    const val = emailInput.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    if (!val && !live) {
      setFieldState('fieldGroupEmail', 'statusIconEmail', false, 'Email address is required.', 'error');
      state.email = false;
    } else if (!val) {
      clearField('fieldGroupEmail', 'statusIconEmail');
      state.email = false;
    } else if (!emailRegex.test(val)) {
      setFieldState('fieldGroupEmail', 'statusIconEmail', false, 'Please enter a valid email address.', 'error');
      state.email = false;
    } else {
      setFieldState('fieldGroupEmail', 'statusIconEmail', true, 'Valid email format.', 'success');
      state.email = true;
    }
    updateProgress();
  }

  /* ── Password Strength & Rules ── */
  const rules = {
    length:  { id: 'ruleLength',  fn: v => v.length >= 8 },
    upper:   { id: 'ruleUpper',   fn: v => /[A-Z]/.test(v) },
    lower:   { id: 'ruleLower',   fn: v => /[a-z]/.test(v) },
    number:  { id: 'ruleNumber',  fn: v => /[0-9]/.test(v) },
    special: { id: 'ruleSpecial', fn: v => /[^A-Za-z0-9]/.test(v) },
  };

  function calcStrength(val) {
    let score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    return score; // 0–5
  }

  const strengthLevels = [
    { label: '',       cls: '' },
    { label: 'Weak',   cls: 'weak' },
    { label: 'Weak',   cls: 'weak' },
    { label: 'Fair',   cls: 'fair' },
    { label: 'Good',   cls: 'good' },
    { label: 'Strong', cls: 'strong' },
  ];

  function updateStrengthUI(val) {
    const score = calcStrength(val);
    const bars  = document.querySelectorAll('.strength-bar');

    bars.forEach((b, i) => {
      b.className = 'strength-bar';
      if (val.length && i < Math.ceil(score * bars.length / 5)) {
        b.classList.add(strengthLevels[score].cls, 'active');
      }
    });

    const lvl = val.length ? strengthLevels[score] : { label: '', cls: '' };
    strengthText.textContent = lvl.label;
    strengthText.className   = 'strength-text ' + lvl.cls;
  }

  function updateRules(val) {
    Object.entries(rules).forEach(([key, rule]) => {
      const li = document.getElementById(rule.id);
      if (!li) return;
      const met = rule.fn(val);
      li.classList.toggle('met',   met);
      li.classList.toggle('unmet', !met);
    });
  }

  function validatePassword(live) {
    const val = passwordInput.value;
    updateStrengthUI(val);
    updateRules(val);

    const msgEl = document.getElementById('passwordMsg');

    if (!val && !live) {
      document.getElementById('fieldGroupPassword').classList.add('error');
      msgEl.textContent = 'Password is required.';
      msgEl.className   = 'field-message msg-error';
      state.password = false;
    } else if (!val) {
      document.getElementById('fieldGroupPassword').classList.remove('valid','error');
      msgEl.textContent = '';
      msgEl.className = 'field-message';
      state.password = false;
    } else if (val.length < 8) {
      document.getElementById('fieldGroupPassword').classList.remove('valid');
      document.getElementById('fieldGroupPassword').classList.add('error');
      msgEl.textContent = 'Password must be at least 8 characters.';
      msgEl.className   = 'field-message msg-error';
      state.password = false;
    } else if (calcStrength(val) < 3) {
      document.getElementById('fieldGroupPassword').classList.remove('valid');
      document.getElementById('fieldGroupPassword').classList.add('error');
      msgEl.textContent = 'Please create a stronger password.';
      msgEl.className   = 'field-message msg-error';
      state.password = false;
    } else {
      document.getElementById('fieldGroupPassword').classList.remove('error');
      document.getElementById('fieldGroupPassword').classList.add('valid');
      msgEl.textContent = 'Strong password!';
      msgEl.className   = 'field-message msg-success';
      state.password = true;
    }

    // Re-validate confirm if it has a value
    if (confirmInput.value) validateConfirm(true);
    updateProgress();
  }

  /* ── Confirm Password ── */
  function validateConfirm(live) {
    const val     = confirmInput.value;
    const passVal = passwordInput.value;

    if (!val && !live) {
      setFieldState('fieldGroupConfirmPassword', 'statusIconConfirm', false, 'Please confirm your password.', 'error');
      state.confirm = false;
    } else if (!val) {
      clearField('fieldGroupConfirmPassword', 'statusIconConfirm');
      state.confirm = false;
    } else if (val !== passVal) {
      setFieldState('fieldGroupConfirmPassword', 'statusIconConfirm', false, 'Passwords do not match.', 'error');
      state.confirm = false;
    } else {
      setFieldState('fieldGroupConfirmPassword', 'statusIconConfirm', true, 'Passwords match!', 'success');
      state.confirm = true;
    }
    updateProgress();
  }

  /* ── Terms ── */
  function validateTerms() {
    const msgEl = document.getElementById('termsMsg');
    if (!termsInput.checked) {
      msgEl.textContent = 'You must agree to the terms to continue.';
      msgEl.className   = 'field-message msg-error';
      state.terms = false;
    } else {
      msgEl.textContent = '';
      msgEl.className   = 'field-message';
      state.terms = true;
    }
    updateProgress();
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
  fullNameInput.addEventListener('input',  () => validateName(true));
  fullNameInput.addEventListener('blur',   () => validateName(false));
  emailInput.addEventListener('input',     () => validateEmail(true));
  emailInput.addEventListener('blur',      () => validateEmail(false));
  passwordInput.addEventListener('input',  () => validatePassword(true));
  passwordInput.addEventListener('blur',   () => validatePassword(false));
  confirmInput.addEventListener('input',   () => validateConfirm(true));
  confirmInput.addEventListener('blur',    () => validateConfirm(false));
  termsInput.addEventListener('change',    () => validateTerms());

  setupToggle('togglePassword', passwordInput);
  setupToggle('toggleConfirm',  confirmInput);

  /* ── Form Submit ── */
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    // Run all validations on submit
    validateName(false);
    validateEmail(false);
    validatePassword(false);
    validateConfirm(false);
    validateTerms();

    const allValid = Object.values(state).every(Boolean);
    if (!allValid) {
      const firstError = form.querySelector('.error .form-input');
      if (firstError) firstError.focus();
      return;
    }

    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    successToast.setAttribute('aria-hidden', 'true');
    successToast.style.display = 'none';

    // Clear validation error highlights that might be stale
    document.querySelectorAll('.field-group').forEach(g => {
      if (!g.classList.contains('valid')) {
        g.classList.remove('error');
      }
    });

    const formData = {
      fullName: fullNameInput.value.trim(),
      emailAddress: emailInput.value.trim(),
      password: passwordInput.value,
      confirmPassword: confirmInput.value,
      agreeTerms: termsInput.checked
    };

    fetch('register.php', {
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
      submitBtn.classList.remove('loading');
      submitBtn.disabled = false;
      
      // Update toast description with verification info (code printed for demo purposes)
      const toastDesc = successToast.querySelector('p');
      if (toastDesc) {
        toastDesc.textContent = `Please verify your email address. Verification Code: ${data.user.verification_code}`;
      }
      
      successToast.style.display = 'flex';
      successToast.setAttribute('aria-hidden', 'false');
      form.reset();
      
      // Reset state
      Object.keys(state).forEach(k => state[k] = false);
      document.querySelectorAll('.field-group').forEach(g => g.classList.remove('valid','error'));
      document.querySelectorAll('.input-status-icon').forEach(i => i.classList.remove('ok','bad','show'));
      document.querySelectorAll('[role="alert"]').forEach(m => { m.textContent = ''; m.className = 'field-message'; });
      document.querySelectorAll('.strength-bar').forEach(b => b.className = 'strength-bar');
      document.querySelectorAll('.pwd-rule').forEach(r => r.classList.remove('met','unmet'));
      strengthText.textContent = ''; 
      strengthText.className = 'strength-text';
      updateProgress();
      
      // Smooth scroll to card
      document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth' });
    })
    .catch(error => {
      submitBtn.classList.remove('loading');
      submitBtn.disabled = false;
      
      if (error.data && error.data.status === 'validation_error') {
        const errs = error.data.errors;
        Object.keys(errs).forEach(field => {
          if (field === 'fullName') {
            setFieldState('fieldGroupName', 'statusIconName', false, errs.fullName, 'error');
          } else if (field === 'emailAddress') {
            setFieldState('fieldGroupEmail', 'statusIconEmail', false, errs.emailAddress, 'error');
          } else if (field === 'password') {
            document.getElementById('fieldGroupPassword').classList.remove('valid');
            document.getElementById('fieldGroupPassword').classList.add('error');
            const msgEl = document.getElementById('passwordMsg');
            msgEl.textContent = errs.password;
            msgEl.className = 'field-message msg-error';
          } else if (field === 'confirmPassword') {
            setFieldState('fieldGroupConfirmPassword', 'statusIconConfirm', false, errs.confirmPassword, 'error');
          } else if (field === 'agreeTerms') {
            const msgEl = document.getElementById('termsMsg');
            msgEl.textContent = errs.agreeTerms;
            msgEl.className = 'field-message msg-error';
          }
        });
        
        const firstError = form.querySelector('.error .form-input');
        if (firstError) firstError.focus();
      } else {
        const msg = error.data && error.data.message ? error.data.message : 'An unexpected server error occurred. Please try again.';
        alert(msg);
      }
    });
  });

  /* ── Initialize ── */
  updateProgress();

})();
