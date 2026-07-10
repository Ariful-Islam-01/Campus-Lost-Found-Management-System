/**
 * Campus Lost & Found Management System
 * ST004-1: Lost Item Report Form Client-side Script
 */

(function () {
  'use strict';

  /* ── DOM References ── */
  const form           = document.getElementById('lostItemForm');
  const nameInput      = document.getElementById('itemName');
  const categoryInput  = document.getElementById('category');
  const descInput      = document.getElementById('description');
  const locationInput  = document.getElementById('lastSeenLocation');
  const dateInput      = document.getElementById('dateLost');
  const fileInput      = document.getElementById('itemPhoto');
  const fileLabel      = document.getElementById('fileNamePreview');
  const previewCont    = document.getElementById('itemPhotoPreviewContainer');
  const previewImg     = document.getElementById('itemPhotoPreviewImg');
  const removePreview  = document.getElementById('btnRemovePreview');
  const submitBtn      = document.getElementById('submitBtn');
  const successToast   = document.getElementById('successToast');

  /* ── Validation State ── */
  const state = { name: false, category: false, description: false, location: false, date: false, photo: true };

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
      if (message) {
        msg.classList.add(isValid ? 'msg-success' : 'msg-error');
      }
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

  /* ── Item Name Validation ── */
  function validateName(live) {
    const val = nameInput.value.trim();
    if (!val && !live) {
      setFieldState('fieldGroupName', 'statusIconName', false, 'Item name is required.');
      state.name = false;
    } else if (!val) {
      clearField('fieldGroupName', 'statusIconName');
      state.name = false;
    } else if (val.length < 2) {
      setFieldState('fieldGroupName', 'statusIconName', false, 'Item name must be at least 2 characters.');
      state.name = false;
    } else if (val.length > 100) {
      setFieldState('fieldGroupName', 'statusIconName', false, 'Item name cannot exceed 100 characters.');
      state.name = false;
    } else {
      setFieldState('fieldGroupName', 'statusIconName', true, 'Item name format is correct.');
      state.name = true;
    }
  }

  /* ── Category Validation ── */
  function validateCategory(live) {
    const val = categoryInput.value;
    if (!val && !live) {
      setFieldState('fieldGroupCategory', 'statusIconCategory', false, 'Please select a category.');
      state.category = false;
    } else if (!val) {
      clearField('fieldGroupCategory', 'statusIconCategory');
      state.category = false;
    } else {
      setFieldState('fieldGroupCategory', 'statusIconCategory', true, 'Category selected.');
      state.category = true;
    }
  }

  /* ── Description Validation ── */
  function validateDescription(live) {
    const val = descInput.value.trim();
    if (!val && !live) {
      setFieldState('fieldGroupDescription', 'statusIconDescription', false, 'Description is required.');
      state.description = false;
    } else if (!val) {
      clearField('fieldGroupDescription', 'statusIconDescription');
      state.description = false;
    } else if (val.length < 10) {
      setFieldState('fieldGroupDescription', 'statusIconDescription', false, 'Description must be at least 10 characters.');
      state.description = false;
    } else if (val.length > 1000) {
      setFieldState('fieldGroupDescription', 'statusIconDescription', false, 'Description cannot exceed 1000 characters.');
      state.description = false;
    } else {
      setFieldState('fieldGroupDescription', 'statusIconDescription', true, 'Description looks good.');
      state.description = true;
    }
  }

  /* ── Location Validation ── */
  function validateLocation(live) {
    const val = locationInput.value.trim();
    if (!val && !live) {
      setFieldState('fieldGroupLocation', 'statusIconLocation', false, 'Last seen location is required.');
      state.location = false;
    } else if (!val) {
      clearField('fieldGroupLocation', 'statusIconLocation');
      state.location = false;
    } else if (val.length < 3) {
      setFieldState('fieldGroupLocation', 'statusIconLocation', false, 'Location must be at least 3 characters.');
      state.location = false;
    } else if (val.length > 100) {
      setFieldState('fieldGroupLocation', 'statusIconLocation', false, 'Location cannot exceed 100 characters.');
      state.location = false;
    } else {
      setFieldState('fieldGroupLocation', 'statusIconLocation', true, 'Location format is correct.');
      state.location = true;
    }
  }

  /* ── Date Validation ── */
  function validateDate(live) {
    const val = dateInput.value;
    if (!val && !live) {
      setFieldState('fieldGroupDate', 'statusIconDate', false, 'Date lost is required.');
      state.date = false;
    } else if (!val) {
      clearField('fieldGroupDate', 'statusIconDate');
      state.date = false;
    } else {
      const selectedDate = new Date(val);
      // Strip time from selected date and today's date for comparison
      selectedDate.setHours(0,0,0,0);
      const today = new Date();
      today.setHours(0,0,0,0);

      if (selectedDate > today) {
        setFieldState('fieldGroupDate', 'statusIconDate', false, 'Date lost cannot be in the future.');
        state.date = false;
      } else {
        setFieldState('fieldGroupDate', 'statusIconDate', true, 'Date is valid.');
        state.date = true;
      }
    }
  }

  /* ── Photo Preview & Size Check ── */
  fileInput.addEventListener('change', function () {
    const file = this.files[0];
    const msgEl = document.getElementById('itemPhotoMsg');
    
    if (!file) {
      resetFilePreview();
      return;
    }

    fileLabel.textContent = file.name;
    
    // Validate file size (2MB)
    const maxSize = 2 * 1024 * 1024;
    if (file.size > maxSize) {
      msgEl.textContent = 'Item photo must be smaller than 2MB.';
      msgEl.className = 'field-message msg-error';
      document.getElementById('fieldGroupPhoto').classList.add('error');
      state.photo = false;
      previewCont.style.display = 'none';
      return;
    }

    // Validate type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
      msgEl.textContent = 'Only JPG, PNG, and WebP formats are allowed.';
      msgEl.className = 'field-message msg-error';
      document.getElementById('fieldGroupPhoto').classList.add('error');
      state.photo = false;
      previewCont.style.display = 'none';
      return;
    }

    // Clear validation error if all checks pass
    msgEl.textContent = '';
    msgEl.className = 'field-message';
    document.getElementById('fieldGroupPhoto').classList.remove('error');
    state.photo = true;

    // Render Preview
    const reader = new FileReader();
    reader.onload = function (e) {
      previewImg.src = e.target.result;
      previewCont.style.display = 'block';
    };
    reader.readAsDataURL(file);
  });

  /* ── Remove Preview Action ── */
  removePreview.addEventListener('click', function() {
    resetFilePreview();
  });

  function resetFilePreview() {
    fileInput.value = '';
    fileLabel.textContent = 'No file chosen (JPG, PNG, WebP. Max 2MB)';
    previewCont.style.display = 'none';
    previewImg.src = '';
    
    const msgEl = document.getElementById('itemPhotoMsg');
    msgEl.textContent = '';
    msgEl.className = 'field-message';
    document.getElementById('fieldGroupPhoto').classList.remove('error');
    state.photo = true;
  }

  /* ── Input Event Listeners ── */
  nameInput.addEventListener('input',     () => validateName(true));
  nameInput.addEventListener('blur',      () => validateName(false));
  categoryInput.addEventListener('change', () => validateCategory(false));
  descInput.addEventListener('input',     () => validateDescription(true));
  descInput.addEventListener('blur',      () => validateDescription(false));
  locationInput.addEventListener('input', () => validateLocation(true));
  locationInput.addEventListener('blur',  () => validateLocation(false));
  dateInput.addEventListener('change',    () => validateDate(false));

  /* ── Form Submit ── */
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    // Re-run all validations
    validateName(false);
    validateCategory(false);
    validateDescription(false);
    validateLocation(false);
    validateDate(false);
    
    const allValid = state.name && state.category && state.description && state.location && state.date && state.photo;
    
    if (!allValid) {
      const firstError = form.querySelector('.error .form-input, .error .form-textarea, .error .form-select');
      if (firstError) firstError.focus();
      return;
    }

    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    successToast.setAttribute('aria-hidden', 'true');
    successToast.style.display = 'none';

    // Clear validation validation highlights that might be stale
    document.querySelectorAll('.field-group').forEach(g => {
      if (!g.classList.contains('valid')) {
        g.classList.remove('error');
      }
    });

    const formData = new FormData(form);

    fetch('report-lost.php', {
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
      
      // Reset form controls
      form.reset();
      resetFilePreview();
      
      // Show success toast
      successToast.style.display = 'flex';
      successToast.setAttribute('aria-hidden', 'false');
      
      // Clear field feedback classes
      document.querySelectorAll('.field-group').forEach(g => g.classList.remove('valid','error'));
      document.querySelectorAll('.input-status-icon').forEach(i => i.classList.remove('ok','bad','show'));
      document.querySelectorAll('[role="alert"]').forEach(m => { m.textContent = ''; m.className = 'field-message'; });
      
      // Scroll to form header
      document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth' });
    })
    .catch(error => {
      submitBtn.classList.remove('loading');
      submitBtn.disabled = false;
      
      if (error.data && error.data.status === 'validation_error') {
        const errs = error.data.errors;
        Object.keys(errs).forEach(field => {
          if (field === 'itemName') {
            setFieldState('fieldGroupName', 'statusIconName', false, errs.itemName);
          } else if (field === 'category') {
            setFieldState('fieldGroupCategory', 'statusIconCategory', false, errs.category);
          } else if (field === 'description') {
            setFieldState('fieldGroupDescription', 'statusIconDescription', false, errs.description);
          } else if (field === 'lastSeenLocation') {
            setFieldState('fieldGroupLocation', 'statusIconLocation', false, errs.lastSeenLocation);
          } else if (field === 'dateLost') {
            setFieldState('fieldGroupDate', 'statusIconDate', false, errs.dateLost);
          } else if (field === 'itemPhoto') {
            const photoMsg = document.getElementById('itemPhotoMsg');
            photoMsg.textContent = errs.itemPhoto;
            photoMsg.className = 'field-message msg-error';
            document.getElementById('fieldGroupPhoto').classList.add('error');
          }
        });
        
        const firstError = form.querySelector('.error .form-input, .error .form-textarea, .error .form-select');
        if (firstError) firstError.focus();
      } else {
        const msg = error.data && error.data.message ? error.data.message : 'An unexpected server error occurred. Please try again.';
        alert(msg);
      }
    });
  });

})();
