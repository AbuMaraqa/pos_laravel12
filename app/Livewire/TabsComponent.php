<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;

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
    public $mrbpData = [];
    // protected $listeners  = ['productTypeChanged'];

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

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


    public function updatedMrbpData($name, $value)
    {
        // if (is_string($name) && str_starts_with($name, 'mrbpData')) {
            $this->dispatch('updateMrbpPrice', ['data' => $this->mrbpData])->to('pages.product.add');
        // }
    }

    #[Computed()]
    public function getRoles(){
        return $this->wooService->getRoles();
    }

    public function render()
    {
        return view('livewire.tabs-component', [
            'showAttributesTab' => $this->showAttributesTab
        ]);
    }
}
