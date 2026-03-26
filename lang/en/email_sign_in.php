<?php

return [
    'profile_confirm_magic_subject' => 'Confirm magic sign-in for :app',
    'profile_confirm_magic_intro' => 'You asked to turn on magic sign-in links for this app. Click the button below to confirm you own this email address. Magic link sign-in stays off until you do.',
    'profile_confirm_magic_action' => 'Confirm magic sign-in',

    'profile_confirm_code_subject' => 'Confirm email code sign-in for :app',
    'profile_confirm_code_intro' => 'You asked to turn on one-time email codes for this app. Click the button below to confirm you own this email address. Code sign-in stays off until you do.',
    'profile_confirm_code_action' => 'Confirm email code sign-in',

    'profile_confirm_invalid' => 'This confirmation link is invalid.',
    'profile_confirm_used' => 'This confirmation link is invalid or has already been used.',
    'profile_confirm_expired_magic' => 'This confirmation link has expired. Turn magic sign-in on again from your profile to get a new email.',
    'profile_confirm_expired_code' => 'This confirmation link has expired. Turn email code sign-in on again from your profile to get a new email.',
    'profile_confirm_wrong_account' => 'That confirmation link belongs to a different account. Sign out first, or open the link in a private window.',
    'profile_confirm_magic_success' => 'Magic sign-in link is now enabled. You can use the email sign-in page with a link next time you log in.',
    'profile_confirm_code_success' => 'One-time email code sign-in is now enabled. You can use the email sign-in page with a code next time you log in.',

    /*
     * GET shows this page so email link scanners (Safe Links, etc.) cannot consume the token.
     * The user confirms with POST + CSRF.
     */
    'profile_confirm_page_title_magic' => 'Confirm magic sign-in',
    'profile_confirm_page_title_code' => 'Confirm email code sign-in',
    'profile_confirm_page_lead_magic' => 'Email programs sometimes open links automatically. Click the button below to turn on magic sign-in links for your account.',
    'profile_confirm_page_lead_code' => 'Email programs sometimes open links automatically. Click the button below to turn on one-time email codes for your account.',
    'profile_confirm_page_button_magic' => 'Turn on magic sign-in',
    'profile_confirm_page_button_code' => 'Turn on email codes',

    'confirm_password_modal_password_label' => 'Current password',
    'confirm_password_modal_confirm' => 'Confirm',
    'confirm_password_modal_cancel' => 'Cancel',

    'mail_code_subject' => 'Your :app sign-in code',
    'mail_code_intro' => 'Use this code to sign in to :app. It expires in :minutes minutes.',
    'mail_code_value' => 'Your code is: :code',
    'mail_magic_subject' => 'Your :app sign-in link',
    'mail_magic_intro' => 'Tap the button below to sign in to :app. The link expires in :minutes minutes.',
    'mail_magic_action' => 'Sign in',
    'mail_footer_ignore' => 'If you did not request this, you can ignore this email.',
];
