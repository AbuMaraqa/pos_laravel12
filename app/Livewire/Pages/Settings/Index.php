<?php

namespace App\Livewire\Pages\Settings;

use App\Models\Setting;
use App\Settings\GeneralSettings;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    public array $settings = [];
    public $logo;

    public function mount(GeneralSettings $settings){
        $this->settings = [
            'site_name' => $settings->site_name
        ];
    }

    public function save(GeneralSettings $settings){
        $settings->site_name = $this->settings['site_name'];

        if ($this->logo) {
            $model = Setting::singleton();
            $model->clearMediaCollection('logo');

            $media = $model
                ->addMedia($this->logo->getRealPath())
                ->usingFileName('logo-' . time() . '.' . $this->logo->getClientOriginalExtension())
                ->toMediaCollection('logo');

            $settings->logo = $media->uuid;
        }

        $settings->save();
        Toaster::success('Updated');
    }

    public function render()
    {
        return view('livewire.pages.settings.index');
    }
}
