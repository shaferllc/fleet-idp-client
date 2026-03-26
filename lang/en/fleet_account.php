<?php

return [
    'profile_title' => 'Fleet account',
    'profile_intro' => 'Link this profile to central Fleet sign-in so you can use “Continue with Fleet”, shared password resets, and the same account across apps.',
    'oauth_section_title' => 'Link this session (recommended)',
    'oauth_section_body' => 'Sign in once with Fleet from this browser. We match your email and link Fleet to this profile.',
    'sync_section_title' => 'Sync your password to Fleet',
    'sync_section_body' => 'Creates your account on Fleet if it is missing, or leaves it unchanged if that email already exists. Then use the button above to finish linking this browser session.',
    'current_password_label' => 'Current password',
    'sync_submit' => 'Sync to Fleet',
    'sync_success_created' => 'Your account is on Fleet. Use “Continue with Fleet” once to finish linking this profile.',
    'sync_success_exists' => 'That email already exists on Fleet. Use “Continue with Fleet” to link this app.',
    'sync_error_missing_provisioning_token' => 'Fleet sync is not configured on this app.',
    'sync_error_missing_idp_url' => 'Fleet URL is not configured.',
    'sync_error_missing_password' => 'Enter your current password.',
    'sync_error_unauthorized' => 'Fleet rejected the sync request. Check the provisioning token.',
    'sync_error_http' => 'Fleet returned an error (HTTP :status). Try again or contact support.',
    'sync_error_bad_response' => 'Fleet returned an unexpected response. Try again or contact support.',
    'sync_error_exception' => 'Could not reach Fleet. Try again later.',
];
