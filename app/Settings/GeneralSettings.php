<?php

namespace App\Settings;

use App\Models\Setting;
use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name = '';

    public ?string $logo = null;

    public static function group(): string
    {
        return 'general';
    }

    public function getMediaUrl(string $collection, ?string $uuid): ?string
    {
        if (!$uuid) return null;

        $model = Setting::singleton();
        return optional($model->getMedia($collection)->firstWhere('uuid', $uuid))->getFullUrl();
    }

    public function getLogoUrl(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        $model = Setting::singleton();
        return optional($model->getMedia('logo')->firstWhere('uuid', $this->logo))->getFullUrl();
    }

    public function getFaviconUrl(): ?string
    {
        return $this->getMediaUrl('favicon', $this->favicon_uuid);
    }
}