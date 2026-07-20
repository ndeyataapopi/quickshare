<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'provider' => env('SMS_PROVIDER', 'log'), // log | twilio | aws_sns | africas_talking

        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from_number' => env('TWILIO_FROM_NUMBER'),
        ],

        'aws_sns' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'sender_id' => env('AWS_SNS_SENDER_ID', 'QuickShare'),
        ],

        'africas_talking' => [
            'api_key' => env('AFRICAS_TALKING_API_KEY'),
            'username' => env('AFRICAS_TALKING_USERNAME'),
            'from' => env('AFRICAS_TALKING_FROM', 'QuickShare'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Configuration
    |--------------------------------------------------------------------------
    */
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'log'), // log | twilio | meta | message_bird

        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from_number' => env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'),
            'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        ],

        'meta' => [
            'access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),
            'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
            'business_account_id' => env('META_WHATSAPP_BUSINESS_ACCOUNT_ID'),
            'webhook_verify_token' => env('META_WHATSAPP_WEBHOOK_TOKEN'),
        ],

        'message_bird' => [
            'access_key' => env('MESSAGE_BIRD_ACCESS_KEY'),
            'channel_id' => env('MESSAGE_BIRD_WHATSAPP_CHANNEL_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'default' => env('NOTIFICATION_QUEUE', 'notifications'),
        'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'redis'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Channels Per Notification Type
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'welcome' => ['email', 'database'],
        'kyc_approved' => ['email', 'database'],
        'kyc_rejected' => ['email', 'database'],
        'loan_submitted' => ['email', 'database'],
        'loan_approved' => ['email', 'database'],
        'loan_rejected' => ['email', 'database'],
        'loan_funded' => ['email', 'sms', 'database'],
        'loan_disbursed' => ['email', 'sms', 'whatsapp', 'database'],
        'repayment_reminder' => ['email', 'sms', 'database'],
        'repayment_overdue' => ['email', 'sms', 'whatsapp', 'database'],
        'repayment_received' => ['email', 'database'],
        'password_reset' => ['email', 'database'],
        'fraud_alert' => ['email'],
        'kyc_submitted' => ['email', 'database'],
        'funding_payment_submitted' => ['email', 'database'],
        'funding_payment_approved' => ['email', 'database'],
        'funding_payment_rejected' => ['email', 'database'],
        'funding_payment_info_requested' => ['email', 'database'],
    ],

];
