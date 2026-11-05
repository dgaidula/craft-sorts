<?php

namespace iceboxind\sorts\models;

use craft\base\Model;
use craft\helpers\App;

/**
 * Global plugin settings (PROJECT CONFIG — dev-owned, locked on prod when
 * `allowAdminChanges` is false; that's intentional, these are deploy-time
 * values). Editor-facing content belongs in a plugin-owned service/table
 * instead.
 *
 * No secrets are stored here. Credential fields accept either a literal value
 * or an `$ENV_VAR` reference (Craft autosuggest), keeping the plugin
 * self-contained for the Plugin Store without bundling keys.
 */
class Settings extends Model
{
    /**
     * Overrides the plugin's name in the CP — the sidebar nav item, the
     * Plugins screen, and the settings breadcrumb. Blank = "Sorts".
     *
     * Set it in the site's `config/sorts.php` so it is version-controlled
     * and survives with `allowAdminChanges` off.
     */
    public string $pluginName = '';

    /**
     * Example credential setting. Accepts either a literal value or an
     * `$ENV_VAR` reference — a value beginning with `$` is resolved from the
     * environment server-side via `craft\helpers\App::parseEnv()`, so the
     * real secret never ships in page HTML or lives in project config. Swap
     * this out (or add more like it) for whatever this plugin actually needs.
     */
    public string $exampleApiKey = '$EXAMPLE_API_KEY';

    /** Pro-only example; the settings screen binds it, so it must exist here. */
    public bool $exampleProToggle = false;

    /**
     * The resolved key. Craft does NOT parse `$ENV_VAR` references in plugin
     * settings; every read of an env-capable setting goes through parseEnv().
     */
    public function getExampleApiKey(): string
    {
        return (string) App::parseEnv($this->exampleApiKey);
    }

    public function rules(): array
    {
        return [
            [['pluginName', 'exampleApiKey'], 'string'],
            [['exampleProToggle'], 'boolean'],
        ];
    }
}
