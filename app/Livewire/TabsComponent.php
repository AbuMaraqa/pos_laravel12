<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;

class TabsComponent extends Component
{
    public $activeTab = 1;
    public $productType = 'simple';
    public $showAttributesTab = false;
    public $localRegularPrice;
    public $localSalePrice;
    public $localSku;
    public $isStockManagementEnabled = false;
    public $stockQuantity;
    public $allowBackorders;
    public $stockStatus;
    public $soldIndividually;
    public $lowStockThreshold;
    public $terms;

    // protected $listeners  = ['productTypeChanged'];

    #[On('productTypeChanged')]
    public function handleProductTypeChange($type)
    {
        $this->productType = $type;
        $this->showAttributesTab = ($type === 'variable');

        // إذا كان التبويب النشط هو تبويب الصفات وكان نوع المنتج بسيط، نعود للتبويب الأول
        if ($this->activeTab === 4 && $type === 'simple') {
            $this->activeTab = 1;
        }
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function updated($field, $value)
    {
        $data = [
            'regularPrice' => $this->localRegularPrice ?? '',
            'salePrice' => $this->localSalePrice ?? '',
            'sku' => $this->localSku ?? '',
        ];

        $this->dispatch('updateMultipleFieldsFromTabs', $data)->to('pages.product.add');
    }

    public function render()
    {
        return view('livewire.tabs-component', [
            'showAttributesTab' => $this->showAttributesTab
        ]);
    }
}
