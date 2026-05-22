<?php

namespace Tests;

use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Default roles always exist in test DB.
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');

        // Mark the app as installed so middleware doesn't bounce to /install.
        $general = app(GeneralSettings::class);
        $general->installed = true;
        $general->save();
    }
}
