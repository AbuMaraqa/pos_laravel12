<?php

namespace Database\Seeders;

use App\Settings\GeneralSettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = app(GeneralSettings::class);
        if ($settings->site_name) {
            return;
        }

        $settings->site_name = 'Site Name';
        $settings->logo_uuid = null;

        $settings->save();
    }
}
