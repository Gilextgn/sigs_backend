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

    'demo_mode' => filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN),
];
