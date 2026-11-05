<?php

/**
 * Sorts config override example.
 *
 * Copy to config/sorts.php in the host site to override Settings values
 * per environment. This file is a template only — it is NOT loaded by Craft
 * and ships purely as documentation; delete or keep it as you like.
 *
 * Anything set here overrides the plugin's saved Settings (project config) for
 * the current environment, which is how you keep dev-owned values (like
 * secrets) out of project config entirely, or vary them by environment
 * without touching the CP.
 *
 * @see \iceboxind\sorts\models\Settings
 */

return [
    // Global overrides, applied in every environment unless overridden below.
    '*' => [
        // Relabels the plugin in the CP (sidebar, Plugins screen, settings
        // breadcrumb). Blank keeps "Sorts".
        'pluginName' => '',

        // Example env-aware credential. Prefix with `$` to resolve from an
        // environment variable server-side (never rendered to the browser).
        'exampleApiKey' => '$EXAMPLE_API_KEY',
    ],

    // Dev-only overrides — this branch is skipped in staging/production. Handy
    // for things you want available locally without touching the shared
    // config above (e.g. a verbose/debug toggle, a local sandbox key).
    'dev' => [
        // 'exampleApiKey' => 'dev-sandbox-key',
    ],
];
