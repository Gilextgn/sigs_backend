<?php

return [
    'matricule_prefix' => env('MATRICULE_PREFIX', 'ELV'),
    'currency' => env('SCHOOL_CURRENCY', 'XOF'),

    /*
     * Instance de démonstration publique : données fictives et comptes
     * neutralisés. Doit rester faux en production.
     */
    /* Numéro WhatsApp affiché à une école suspendue (format international, sans +). */
    'support_whatsapp' => env('SUPPORT_WHATSAPP', '2290191489743'),

    /* Adresse du frontend : lien de vérification du reçu envoyé aux parents. */
    'public_app_url' => rtrim(env('PUBLIC_APP_URL', explode(',', env('FRONTEND_URLS', 'http://localhost:5173'))[0]), '/'),

    /* Indicatif ajouté aux numéros WhatsApp saisis sans indicatif (229 = Bénin). */
    'phone_country_code' => env('PHONE_COUNTRY_CODE', '229'),

    /*
     * Envoi automatique du reçu par WhatsApp (API WhatsApp Cloud de Meta).
     * Sans jeton, rien ne part automatiquement : la secrétaire dispose du
     * bouton « Envoyer sur WhatsApp » qui ouvre la conversation pré-remplie.
     * Le modèle (template) doit être approuvé par Meta avec 5 variables :
     * {{1}} parent, {{2}} élève, {{3}} montant, {{4}} référence, {{5}} lien de vérification.
     */
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'template' => env('WHATSAPP_RECEIPT_TEMPLATE', 'recu_paiement'),
        'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'fr'),
    ],

    'demo_mode' => filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN),
];
