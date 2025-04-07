<?php

namespace App\Livewire;

use Livewire\Component;

class TabsComponent extends Component
{
    public $activeTab = 1; // التبويب النشط بشكل افتراضي

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('livewire.tabs-component');
    }
}
