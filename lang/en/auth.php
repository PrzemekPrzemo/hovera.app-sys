<?php

declare(strict_types=1);

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'login' => [
        'title' => 'Sign in — hovera',
        'heading' => 'Sign in',
        'email' => 'Email',
        'password' => 'Password',
        'remember' => 'Remember me',
        'submit' => 'Sign in',
        'forgot_password' => 'Forgot password?',
        'no_account' => "Don't have an account?",
        'register' => 'Register',
    ],

    'logout' => 'Sign out',

    'two_factor' => [
        'setup_title' => '2FA setup — hovera',
        'setup_heading' => 'Enable two-factor authentication (2FA)',
        'setup_intro' => 'Scan the QR code with an authenticator app (Google Authenticator, Authy, 1Password) and enter the generated six-digit code to confirm.',
        'manual_entry' => 'Or enter the secret manually:',
        'code_label' => '2FA code',
        'confirm' => 'Confirm and enable',
        'challenge_title' => '2FA verification — hovera',
        'challenge_heading' => 'Enter your 2FA code',
        'challenge_intro' => 'Enter the six-digit code from your authenticator app, or one of your one-time recovery codes.',
        'remember_device' => 'Remember this device for 14 days',
        'submit_challenge' => 'Sign in',
        'invalid_code' => 'Invalid code.',
        'recovery_codes_title' => 'Recovery codes — hovera',
        'recovery_codes_heading' => 'Your recovery codes',
        'recovery_codes_intro' => 'Save these codes somewhere safe. Each one works only once — you can use them if you lose access to your authenticator app.',
        'recovery_codes_continue' => 'I saved the codes, continue',
    ],

    'password_reset' => [
        'request_title' => 'Password reset',
        'email_sent' => 'We sent a password reset link to your email.',
        'reset_title' => 'Set a new password',
        'reset_button' => 'Reset password',
    ],

    'tenant_select' => [
        'title' => 'Pick a stable — Hovera',
        'heading' => 'Pick a stable',
        'intro' => 'Your account has access to :count stables. Pick which one to sign in to.',
        'role_label' => ':slug · role: :role',
        'submit' => 'Continue to stable',
    ],

    'no_tenants' => [
        'title' => 'No stables available — Hovera',
        'heading' => 'No stables available',
        'intro' => 'Your account is not yet assigned to any stable, or your access has been revoked. Contact the stable administrator to get access.',
        'logout' => 'Sign out',
    ],

    'invitation_accept' => [
        'title' => 'Activate account — Hovera',
        'heading' => 'Set a password',
        'intro_with_tenant' => "You're joining <strong>:tenant</strong>.",
        'intro_account' => 'Account: <strong>:email</strong>.',
        'intro_pwd' => 'Choose a password (min. 12 characters) to activate your account.',
        'password' => 'New password',
        'password_confirmation' => 'Confirm password',
        'submit' => 'Activate account and sign in',
    ],
];
