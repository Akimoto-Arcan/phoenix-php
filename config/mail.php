<?php
/**
 * Email Configuration for PhoenixPHP
 *
 * Configure your Microsoft 365 / Exchange Online SMTP settings below.
 * For Office 365, you'll need a shared mailbox or service account.
 *
 * Steps to set up:
 * 1. Create a shared mailbox in Microsoft 365 Admin Center (e.g., reports@yourcompany.com)
 * 2. Enable SMTP AUTH for the mailbox (may need admin to enable org-wide first)
 * 3. Generate an app password if MFA is enabled, or use the mailbox password
 * 4. Fill in the credentials below
 */

return [
    'smtp' => [
        // Microsoft 365 / Exchange Online settings
        'host'       => 'smtp.office365.com',
        'port'       => 587,
        'encryption' => 'tls',  // Use 'tls' for port 587, 'ssl' for port 465

        // SMTP credentials — set in your .env file
        'username'   => env('MAIL_USERNAME', ''),
        'password'   => env('MAIL_PASSWORD', ''),

        // From address (usually same as username for Office 365)
        'from_email' => env('MAIL_FROM_EMAIL', 'noreply@example.com'),
        'from_name'  => 'Phoenix System',  // Can be overridden per email
    ],

    'defaults' => [
        'reply_to'           => null,  // Optional: reply-to address
        'max_attachment_mb'  => 25,    // Office 365 limit is 25MB per message
        'timeout'            => 30,    // Connection timeout in seconds
    ],

    // Debug mode - set to true to see SMTP conversation (for troubleshooting)
    'debug' => false,
];
