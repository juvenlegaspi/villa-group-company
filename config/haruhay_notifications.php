<?php

return [
    'email_enabled' => env('HARUHAY_BILL_EMAIL_ENABLED', true),
    'sms_enabled' => env('HARUHAY_BILL_SMS_ENABLED', true),
    'receipt_email_enabled' => env('HARUHAY_RECEIPT_EMAIL_ENABLED', true),
    'receipt_sms_enabled' => env('HARUHAY_RECEIPT_SMS_ENABLED', true),
    'due_reminder_sms_enabled' => env('HARUHAY_DUE_REMINDER_SMS_ENABLED', true),
    'due_reminder_days' => (int) env('HARUHAY_DUE_REMINDER_DAYS', 3),
    'tracking_sms_enabled' => env('HARUHAY_TRACKING_SMS_ENABLED', true),
];
