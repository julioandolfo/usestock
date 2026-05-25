<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Update the support WhatsApp on already-installed instances (the
        // add-migration only seeds fresh installs). Idempotent: just sets
        // the current value.
        $this->migrator->update(
            'general.support_whatsapp',
            fn () => '5535992697570',
        );
    }
};
