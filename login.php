<?php
// login.php
session_start();

// Include database connection helper
require_once __DIR__ . '/db.php';

// Redirect to the appropriate dashboard if already logged in
if (isset($_SESSION['user_id'])) {
  if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: admin-dashboard.php');
  } else {
    header('Location: dashboard.php');
  }
    exit;
}

// Handle login request (POST API)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set headers
    header('Content-Type: application/json; charset=UTF-8');
    
    // Retrieve input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // Fallback to standard $_POST if JSON decode failed
    if (!$input) {
        $input = $_POST;
    }
    
    $email = isset($input['emailAddress']) ? trim($input['emailAddress']) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    
    $errors = [];
    
    // Server-Side Input Validation
    if (empty($email)) {
        $errors['emailAddress'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['emailAddress'] = 'Please enter a valid email address.';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    }
    
    // If validation fails
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'validation_error',
            'errors' => $errors
        ]);
        exit;
    }
    
    // Verify credentials
    try {
        $user = getUserByEmail($email);
        
        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'status' => 'validation_error',
                'errors' => [
                    'emailAddress' => 'Email address is not registered.'
                ]
            ]);
            exit;
        }
        
        // Match password hashes
        if (!password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode([
                'status' => 'validation_error',
                'errors' => [
                    'password' => 'Incorrect password.'
                ]
            ]);
            exit;
        }

        // Block login for deactivated accounts.
        if (isset($user['status']) && strtolower($user['status']) === 'inactive') {
            http_response_code(403);
            echo json_encode([
                'status' => 'validation_error',
                'errors' => [
                    'account' => 'Your account has been deactivated. Please contact the administrator.'
                ]
            ]);
            exit;
        }
        
        // Start User Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'] ?? 'user';

        $redirect = ($_SESSION['user_role'] === 'admin') ? 'admin-dashboard.php' : 'dashboard.php';
        
        // Return Success Response
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful! Redirecting...',
          'redirect' => $redirect
        ]);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'An unexpected database error occurred. Please try again.'
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sign in to your Campus Lost & Found Management System account.">
  <title>Login | Campus Lost & Found</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4r3x4m4h+2wO1G75kmOe9w+8Xwwk6oFVi+6Fv7eJmYB4jzBXGww9j5gIMB8DQG7B" crossorigin="anonymous">
  
  <!-- Stylesheets -->
  <link rel="stylesheet" href="css/login.css">
</head>
<body>

  <!-- Floating Background Particles -->
  <div class="bg-particles" aria-hidden="true">
    <span class="particle" style="--x: 10vw; --y: 15vh; --s: 1.2; --d: 6s;"></span>
    <span class="particle" style="--x: 80vw; --y: 20vh; --s: 0.8; --d: 8s;"></span>
    <span class="particle" style="--x: 25vw; --y: 75vh; --s: 1.5; --d: 10s;"></span>
    <span class="particle" style="--x: 85vw; --y: 80vh; --s: 0.6; --d: 5s;"></span>
    <span class="particle" style="--x: 50vw; --y: 40vh; --s: 1.0; --d: 7s;"></span>
  </div>

  <div class="page-wrapper">
    <!-- Left Panel (Branding / Platform Highlights) -->
    <section class="brand-panel" aria-label="Branding panel">
      <div class="brand-inner">
        <div class="brand-logo" aria-hidden="true">
          <!-- SVG Logo representing Search/Finder Concept -->
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
        <p class="brand-tagline">Reuniting students with their lost items. Quick, reliable, and secure.</p>

        <ul class="feature-list" aria-label="Platform highlights">
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
        </ul>

        <div class="brand-stats" aria-label="Platform stats">
          <div class="stat">
            <span class="stat-value">1.2K+</span>
            <span class="stat-label">Items Returned</span>
          </div>
          <div class="stat-divider" aria-hidden="true"></div>
          <div class="stat">
            <span class="stat-value">98%</span>
            <span class="stat-label">Success Rate</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Right Panel (Login Form Card) -->
    <section class="form-panel" aria-label="Login form panel">
      <div class="form-card">
        <div class="form-header">
          <h2 class="form-title">Welcome back</h2>
          <p class="form-subtitle">Enter your credentials to access your account.</p>
        </div>

        <div id="formAlert" class="alert alert-danger d-none" role="alert"></div>

        <form id="loginForm" class="login-form" novalidate aria-label="Account login form">

          <!-- Email Address Input -->
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

          <!-- Password Input -->
          <div class="field-group" id="fieldGroupPassword">
            <label class="field-label" for="password">
              Password <span class="required-star" aria-hidden="true">*</span>
            </label>
            <div class="input-wrapper">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
              </span>
              <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" autocomplete="current-password" aria-required="true" aria-describedby="passwordMsg" />
              <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                <svg class="eye-icon eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-icon eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
              <span class="input-status-icon" id="statusIconPassword" aria-hidden="true"></span>
            </div>
            <div class="field-message" id="passwordMsg" role="alert" aria-live="polite"></div>
          </div>

          <!-- Submit Button -->
          <button type="submit" id="submitBtn" class="btn-submit">
            <span class="btn-text">Sign In</span>
            <span class="btn-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </span>
            <span class="btn-loader" id="btnLoader" aria-label="Loading">
              <!-- Inline loading spinner SVG -->
              <svg class="spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.2)"></circle>
                <circle cx="12" cy="12" r="10" stroke="white" stroke-width="3"></circle>
              </svg>
            </span>
          </button>

          <!-- Register Redirect Link -->
          <div class="register-redirect">
            <span>Don't have an account? </span>
            <a href="register.php" class="register-link">Register here</a>
          </div>

        </form>
      </div>
    </section>
  </div>

  <!-- Validation Javascript -->
  <script src="js/login.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-U1h5x0G3Sj2+hN6tDf1DE4BFeN4eQcA/pv3eCG7Q6u0xH7p81Md7UkrhQZHzueU6" crossorigin="anonymous"></script>
</body>
</html>
