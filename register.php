<?php
// register.php
require_once __DIR__ . '/db.php';

// Handle POST request (registration form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Support both JSON payload and standard POST body
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $fullName        = isset($input['fullName']) ? trim($input['fullName']) : '';
    $emailAddress    = isset($input['emailAddress']) ? trim($input['emailAddress']) : '';
    $password        = isset($input['password']) ? $input['password'] : '';
    $confirmPassword = isset($input['confirmPassword']) ? $input['confirmPassword'] : '';
    $agreeTerms      = isset($input['agreeTerms']) ? $input['agreeTerms'] : false;

    $errors = [];

    // 1. Validate Full Name
    if (empty($fullName)) {
        $errors['fullName'] = 'Full name is required.';
    } elseif (strlen($fullName) < 2) {
        $errors['fullName'] = 'Name must be at least 2 characters.';
    } elseif (!preg_match("/^[a-zA-Z\s'.'-]+$/", $fullName)) {
        $errors['fullName'] = 'Name should contain only letters and spaces.';
    }

    // 2. Validate Email Address
    if (empty($emailAddress)) {
        $errors['emailAddress'] = 'Email address is required.';
    } elseif (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
        $errors['emailAddress'] = 'Please enter a valid email address.';
    }

    // 3. Validate Password
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } else {
        $hasUpper   = preg_match('/[A-Z]/', $password);
        $hasLower   = preg_match('/[a-z]/', $password);
        $hasNumber  = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[^A-Za-z0-9]/', $password);
        
        if (!$hasUpper || !$hasLower || !$hasNumber || !$hasSpecial) {
            $errors['password'] = 'Password must include uppercase, lowercase, number, and special character.';
        }
    }

    // 4. Validate Confirm Password
    if (empty($confirmPassword)) {
        $errors['confirmPassword'] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match.';
    }

    // 5. Validate Terms Agreement
    if (!$agreeTerms || $agreeTerms === 'false') {
        $errors['agreeTerms'] = 'You must agree to the terms to continue.';
    }

    // Return validation errors if any
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'validation_error',
            'errors' => $errors
        ]);
        exit;
    }

    try {
        $db = getDBConnection();
        
        // Check for duplicate account
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $emailAddress]);
        if ($stmt->fetch()) {
            http_response_code(409); // Conflict
            echo json_encode([
                'status' => 'validation_error',
                'errors' => [
                    'emailAddress' => 'Email address is already registered.'
                ]
            ]);
            exit;
        }
        
        // Hash password and generate verification code
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $verificationCode = (string)mt_rand(100000, 999999);
        
        // Save new user
        $insert = $db->prepare("INSERT INTO users (name, email, password_hash, verification_code, is_verified) VALUES (:name, :email, :password_hash, :verification_code, 0)");
        $insert->execute([
            ':name' => $fullName,
            ':email' => $emailAddress,
            ':password_hash' => $passwordHash,
            ':verification_code' => $verificationCode
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'User registered successfully. Please verify your email.',
            'user' => [
                'name' => $fullName,
                'email' => $emailAddress,
                'verification_code' => $verificationCode 
            ]
        ]);
        exit;
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to save user to the database: ' . $e->getMessage()
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Register for Campus Lost and Found Management System - Report, search, and reclaim lost items on campus." />
  <title>Register | Campus Lost &amp; Found</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/register.css" />
</head>
<body>
  <div class="bg-particles" aria-hidden="true">
    <span class="particle" style="--x:10%;--y:20%;--d:6s;--s:1.2"></span>
    <span class="particle" style="--x:85%;--y:10%;--d:8s;--s:0.8"></span>
    <span class="particle" style="--x:25%;--y:75%;--d:5s;--s:1.5"></span>
    <span class="particle" style="--x:70%;--y:60%;--d:7s;--s:0.6"></span>
    <span class="particle" style="--x:50%;--y:40%;--d:9s;--s:1.0"></span>
    <span class="particle" style="--x:90%;--y:85%;--d:6.5s;--s:0.9"></span>
    <span class="particle" style="--x:5%;--y:55%;--d:11s;--s:1.3"></span>
    <span class="particle" style="--x:40%;--y:90%;--d:4.5s;--s:0.7"></span>
  </div>

  <main class="page-wrapper">
    <section class="brand-panel" aria-label="Campus Lost and Found branding">
      <div class="brand-inner">
        <div class="brand-logo" aria-hidden="true">
          <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="32" cy="32" r="32" fill="url(#lg1)"/>
            <circle cx="28" cy="27" r="10" stroke="white" stroke-width="3" fill="none"/>
            <line x1="35.5" y1="34.5" x2="45" y2="44" stroke="white" stroke-width="3" stroke-linecap="round"/>
            <path d="M32 38 Q32 48 32 52" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
            <defs>
              <linearGradient id="lg1" x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse">
                <stop stop-color="#0D9488"/>
                <stop offset="1" stop-color="#F59E0B"/>
              </linearGradient>
            </defs>
          </svg>
        </div>
        <h1 class="brand-title">Campus Lost &amp; Found</h1>
        <p class="brand-tagline">Your campus companion for reuniting people with their belongings.</p>

        <ul class="feature-list" aria-label="Platform features">
          <li class="feature-item">
            <span class="feature-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </span>
            <span>Report lost &amp; found items instantly</span>
          </li>
          <li class="feature-item">
            <span class="feature-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </span>
            <span>Search &amp; filter by category or location</span>
          </li>
          <li class="feature-item">
            <span class="feature-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </span>
            <span>Track item status from Lost to Returned</span>
          </li>
          <li class="feature-item">
            <span class="feature-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            </span>
            <span>Admin-verified claim system</span>
          </li>
        </ul>

        <div class="brand-stats" aria-label="Platform statistics">
          <div class="stat">
            <span class="stat-value">1.2K+</span>
            <span class="stat-label">Items Returned</span>
          </div>
          <div class="stat-divider" aria-hidden="true"></div>
          <div class="stat">
            <span class="stat-value">500+</span>
            <span class="stat-label">Active Users</span>
          </div>
          <div class="stat-divider" aria-hidden="true"></div>
          <div class="stat">
            <span class="stat-value">98%</span>
            <span class="stat-label">Success Rate</span>
          </div>
        </div>
      </div>
    </section>

    <section class="form-panel" aria-label="Registration form">
      <div class="form-card">
        <div class="form-header">
          <h2 class="form-title">Create your account</h2>
          <p class="form-subtitle">Join thousands of students managing lost &amp; found items on campus.</p>
        </div>

        <form id="registrationForm" class="reg-form" novalidate aria-label="Student registration form">

          <div class="form-progress" aria-label="Form completion progress">
            <div class="progress-bar-track" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="Registration progress">
              <div class="progress-bar-fill" id="progressFill"></div>
            </div>
            <span class="progress-label" id="progressLabel">0% complete</span>
          </div>

          <div class="field-group" id="fieldGroupName">
            <label class="field-label" for="fullName">
              Full Name <span class="required-star" aria-hidden="true">*</span>
            </label>
            <div class="input-wrapper">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </span>
              <input type="text" id="fullName" name="fullName" class="form-input" placeholder="e.g. Ariful Islam" autocomplete="name" maxlength="80" aria-required="true" aria-describedby="fullNameMsg" />
              <span class="input-status-icon" id="statusIconName" aria-hidden="true"></span>
            </div>
            <div class="field-message" id="fullNameMsg" role="alert" aria-live="polite"></div>
          </div>

          <div class="field-group" id="fieldGroupEmail">
            <label class="field-label" for="emailAddress">
              Email Address <span class="required-star" aria-hidden="true">*</span>
            </label>
            <div class="input-wrapper">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              </span>
              <input type="email" id="emailAddress" name="emailAddress" class="form-input" placeholder="you@university.edu" autocomplete="email" aria-required="true" aria-describedby="emailAddressMsg" />
              <span class="input-status-icon" id="statusIconEmail" aria-hidden="true"></span>
            </div>
            <div class="field-message" id="emailAddressMsg" role="alert" aria-live="polite"></div>
          </div>

          <div class="field-group" id="fieldGroupPassword">
            <label class="field-label" for="password">
              Password <span class="required-star" aria-hidden="true">*</span>
            </label>
            <div class="input-wrapper">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
              </span>
              <input type="password" id="password" name="password" class="form-input" placeholder="Create a strong password" autocomplete="new-password" aria-required="true" aria-describedby="passwordMsg" />
              <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                <svg class="eye-icon eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-icon eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>

            <div class="strength-meter" id="strengthMeter" aria-live="polite" aria-label="Password strength indicator">
              <div class="strength-bars">
                <span class="strength-bar" id="sbar1"></span>
                <span class="strength-bar" id="sbar2"></span>
                <span class="strength-bar" id="sbar3"></span>
                <span class="strength-bar" id="sbar4"></span>
              </div>
              <span class="strength-text" id="strengthText"></span>
            </div>

            <ul class="pwd-checklist" id="pwdChecklist" aria-label="Password requirements checklist">
              <li class="pwd-rule" id="ruleLength"><span class="rule-icon" aria-hidden="true"></span><span>At least 8 characters</span></li>
              <li class="pwd-rule" id="ruleUpper"><span class="rule-icon" aria-hidden="true"></span><span>One uppercase letter (A-Z)</span></li>
              <li class="pwd-rule" id="ruleLower"><span class="rule-icon" aria-hidden="true"></span><span>One lowercase letter (a-z)</span></li>
              <li class="pwd-rule" id="ruleNumber"><span class="rule-icon" aria-hidden="true"></span><span>One number (0-9)</span></li>
              <li class="pwd-rule" id="ruleSpecial"><span class="rule-icon" aria-hidden="true"></span><span>One special character (!@#$...)</span></li>
            </ul>
            <div class="field-message" id="passwordMsg" role="alert" aria-live="polite"></div>
          </div>

          <div class="field-group" id="fieldGroupConfirmPassword">
            <label class="field-label" for="confirmPassword">
              Confirm Password <span class="required-star" aria-hidden="true">*</span>
            </label>
            <div class="input-wrapper">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </span>
              <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" placeholder="Re-enter your password" autocomplete="new-password" aria-required="true" aria-describedby="confirmPasswordMsg" />
              <button type="button" class="toggle-password" id="toggleConfirm" aria-label="Toggle confirm password visibility">
                <svg class="eye-icon eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-icon eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
              <span class="input-status-icon" id="statusIconConfirm" aria-hidden="true"></span>
            </div>
            <div class="field-message" id="confirmPasswordMsg" role="alert" aria-live="polite"></div>
          </div>

          <div class="terms-group">
            <label class="checkbox-label" for="agreeTerms">
              <input type="checkbox" id="agreeTerms" name="agreeTerms" class="checkbox-input" aria-required="true" />
              <span class="checkbox-custom" aria-hidden="true"></span>
              <span class="checkbox-text">
                I agree to the <a href="#" class="terms-link">Terms of Service</a> and
                <a href="#" class="terms-link">Privacy Policy</a>
              </span>
            </label>
            <div class="field-message" id="termsMsg" role="alert" aria-live="polite"></div>
          </div>

          <button type="submit" class="btn-submit" id="submitBtn" aria-label="Create your account">
            <span class="btn-text">Create Account</span>
            <span class="btn-loader" id="btnLoader" aria-hidden="true">
              <svg class="spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-dasharray="31.4" stroke-dashoffset="31.4"/></svg>
            </span>
            <span class="btn-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </span>
          </button>

          <div class="success-toast" id="successToast" role="status" aria-live="polite" aria-hidden="true">
            <span class="toast-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </span>
            <div>
              <strong>Account created!</strong>
              <p>Please check your email to verify your account.</p>
            </div>
          </div>

          <p class="login-redirect">
            Already have an account? <a href="login.php" class="login-link" id="loginLink">Sign in here</a>
          </p>

        </form>
      </div>
    </section>
  </main>

  <script src="js/register.js"></script>
</body>
</html>
