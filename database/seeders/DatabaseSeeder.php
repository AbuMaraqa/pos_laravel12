<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            SettingsSeeder::class
        ]);

        $subscription = Subscription::firstOrCreate([
            'company_name' => 'test',
        ], [
            'site_url' => 'https://veronastores.com',
            'consumer_secret' => 'cs_7f329a1eb90da3e6cae31553f680411d21f6568e',
            'consumer_key' => 'ck_cf7d448107e3ed022060e836037c789f0c16c099',
            'app_username' => 'test',
            'app_password' => 'test',
            'start_date' => now(),
            'end_date' => now()->addYear(),
            'annual_price' => 100,
            'notes' => 'test',
            'status' => 1,
        ]);

        User::firstOrCreate([
            'username' => 'test',
        ],[
            'phone' => '1234567890',
            'password' => bcrypt('123456789'),
            'subscription_id' => $subscription->id,
        ]);
    }
}
