<?php

namespace App\Models;

use App\Settings\GeneralSettings;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Setting extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    public static function singleton(): self
    {
        return self::firstOrCreate(['id' => 1]);
    }

    public function uploadLogo($file)
    {
        $settingModel = Setting::singleton();

        $settingModel->clearMediaCollection('logo');

        $media = $settingModel
            ->addMedia($file->getRealPath())
            ->usingName('Logo')
            ->usingFileName('logo-' . time() . '.' . $file->getClientOriginalExtension())
            ->toMediaCollection('logo');

        $settings = app(GeneralSettings::class);
        $settings->logo_uuid = $media->uuid;
        $settings->save();
    }
}
