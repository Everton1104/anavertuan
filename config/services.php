<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'mercadopago' => [
        // PUBLIC_KEY vai para o front (SDK MercadoPago.js); ACCESS_TOKEN é sigiloso
        // e só deve ser usado no backend. WEBHOOK_SECRET valida a assinatura do
        // webhook enviado pelo MP (header x-signature).
        'public_key'         => env('MP_PUBLIC_KEY'),
        'access_token'       => env('MP_ACCESS_TOKEN'),
        'webhook_secret'     => env('MP_WEBHOOK_SECRET'),
        'statement_descriptor' => env('MP_STATEMENT_DESCRIPTOR', config('app.name')),
        // Taxa estimada (em %) de custos do MP no pior caso da ordem — cartão em
        // 6x sem juros (Parcelado Vendedor: taxa base + % por parcela). Usada SÓ
        // como estimativa visual na criação da ordem; o valor cobrado do paciente
        // continua sendo o integral. Ajuste MP_TAXA_CREDITO_6X ao seu plano real.
        // 14,94 = taxa real confirmada em 26-07 (R$3.500 em 6x → líquido R$2.977).
        'taxa_credito_6x'    => (float) env('MP_TAXA_CREDITO_6X', 14.94),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
