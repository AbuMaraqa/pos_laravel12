<?php

namespace App\Livewire;

use Livewire\Attributes\Reactive;
use Livewire\Component;

class TabsComponent extends Component
{

    public $localRegularPrice;
    public $localSalePrice;
    public $localSku;


    public function updated($field, $value)
    {
        $this->dispatch('updateMultipleFieldsFromTabs', [
            'regularPrice' => $this->localRegularPrice,
            'salePrice' => $this->localSalePrice,
            'sku' => $this->localSku,
        ])->to('pages.product.add');
    }



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
