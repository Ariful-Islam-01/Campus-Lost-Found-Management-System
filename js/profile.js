/**
 * Campus Lost & Found Management System
 * ST003-1: Profile Edit Client-side Script
 */

(function () {
  'use strict';

  /* ── DOM References ── */
  const form           = document.getElementById('profileForm');
  const nameInput      = document.getElementById('fullName');
  const phoneInput     = document.getElementById('phone');
  const fileInput      = document.getElementById('profilePhoto');
  const fileLabel      = document.getElementById('fileNamePreview');
  const submitBtn      = document.getElementById('submitBtn');
  const successToast   = document.getElementById('successToast');
  
  // Left summary details
  const summaryName    = document.getElementById('summaryUserName');
  const headerName     = document.getElementById('headerUserName');
  const avatarContainer = document.getElementById('avatarPreviewContainer');

  /* ── Validation State ── */
  const state = { name: true, phone: true, photo: true };

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

  /* ── Name Validation ── */
  function validateName(live) {
    const val = nameInput.value.trim();
    if (!val && !live) {
      setFieldState('fieldGroupName', 'statusIconName', false, 'Full name is required.');
      state.name = false;
    } else if (!val) {
      clearField('fieldGroupName', 'statusIconName');
      state.name = false;
    } else if (val.length < 2) {
      setFieldState('fieldGroupName', 'statusIconName', false, 'Name must be at least 2 characters.');
      state.name = false;
    } else if (!/^[a-zA-Z\s'.'-]+$/.test(val)) {
      setFieldState('fieldGroupName', 'statusIconName', false, 'Name should contain only letters and spaces.');
      state.name = false;
    } else {
      setFieldState('fieldGroupName', 'statusIconName', true, 'Name format is correct.');
      state.name = true;
    }
  }

  /* ── Phone Validation ── */
  function validatePhone(live) {
    const val = phoneInput.value.trim();
    if (!val) {
      clearField('fieldGroupPhone', 'statusIconPhone');
      state.phone = true; // Optional field
      return;
    }
    
    if (!/^[0-9\s\-\+\(\)]+$/.test(val)) {
      setFieldState('fieldGroupPhone', 'statusIconPhone', false, 'Please enter a valid phone number.');
      state.phone = false;
    } else {
      setFieldState('fieldGroupPhone', 'statusIconPhone', true, 'Phone number format is correct.');
      state.phone = true;
    }
  }

  /* ── Photo Preview & Size check ── */
  fileInput.addEventListener('change', function () {
    const file = this.files[0];
    const msgEl = document.getElementById('profilePhotoMsg');
    
    if (!file) {
      fileLabel.textContent = 'No file chosen (JPG, PNG, WebP. Max 2MB)';
      state.photo = true;
      return;
    }

    fileLabel.textContent = file.name;
    
    // Validate file size (2MB)
    const maxSize = 2 * 1024 * 1024;
    if (file.size > maxSize) {
      msgEl.textContent = 'Profile photo must be smaller than 2MB.';
      msgEl.className = 'field-message msg-error';
      state.photo = false;
      return;
    }

    // Validate type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
      msgEl.textContent = 'Only JPG, PNG, and WebP formats are allowed.';
      msgEl.className = 'field-message msg-error';
      state.photo = false;
      return;
    }

    // Clear validation error if all checks pass
    msgEl.textContent = '';
    msgEl.className = 'field-message';
    state.photo = true;

    // Render Preview
    const reader = new FileReader();
    reader.onload = function (e) {
      avatarContainer.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" class="avatar-preview-img" id="avatarPreviewImg" />`;
    };
    reader.readAsDataURL(file);
  });

  /* ── Event Listeners ── */
  nameInput.addEventListener('input',  () => validateName(true));
  nameInput.addEventListener('blur',   () => validateName(false));
  phoneInput.addEventListener('input', () => validatePhone(true));
  phoneInput.addEventListener('blur',  () => validatePhone(false));

  /* ── Form Submit ── */
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    // Re-run validations
    validateName(false);
    validatePhone(false);
    
    // Check if photo is valid
    if (!state.photo) {
      const photoField = document.getElementById('profilePhoto');
      if (photoField) photoField.focus();
      return;
    }

    const allValid = state.name && state.phone && state.photo;
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

    // Compile multipart Form Data
    const formData = new FormData(form);

    fetch('profile.php', {
      method: 'POST',
      body: formData
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
      
      // Update names on page layout
      summaryName.textContent = data.user.name;
      if (headerName) headerName.textContent = data.user.name;
      
      // Update photo preview path on summary sidebar if photo was uploaded
      if (data.user.profile_photo) {
        avatarContainer.innerHTML = `<img src="${data.user.profile_photo}" alt="Profile Picture" class="avatar-preview-img" id="avatarPreviewImg" />`;
      }
      
      // Clear form file inputs
      fileInput.value = '';
      fileLabel.textContent = 'No file chosen (JPG, PNG, WebP. Max 2MB)';
      
      // Show success toast
      successToast.style.display = 'flex';
      successToast.setAttribute('aria-hidden', 'false');
      
      // Clear field feedback classes
      document.querySelectorAll('.field-group').forEach(g => g.classList.remove('valid','error'));
      document.querySelectorAll('.input-status-icon').forEach(i => i.classList.remove('ok','bad','show'));
      document.querySelectorAll('[role="alert"]').forEach(m => { m.textContent = ''; m.className = 'field-message'; });
      
      // Scroll up to summary
      document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth' });
    })
    .catch(error => {
      submitBtn.classList.remove('loading');
      submitBtn.disabled = false;
      
      if (error.data && error.data.status === 'validation_error') {
        const errs = error.data.errors;
        Object.keys(errs).forEach(field => {
          if (field === 'fullName') {
            setFieldState('fieldGroupName', 'statusIconName', false, errs.fullName);
          } else if (field === 'phone') {
            setFieldState('fieldGroupPhone', 'statusIconPhone', false, errs.phone);
          } else if (field === 'profilePhoto') {
            const photoMsg = document.getElementById('profilePhotoMsg');
            photoMsg.textContent = errs.profilePhoto;
            photoMsg.className = 'field-message msg-error';
            document.getElementById('fieldGroupPhoto').classList.add('error');
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

})();
