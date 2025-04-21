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
    public $productId;
    // protected $listeners  = ['productTypeChanged'];

    protected WooCommerceService $wooService;

    public function mount($productType, $regularPrice = null, $productId = null)
    {
        $this->productType = $productType;
        $this->localRegularPrice = $regularPrice;
        $this->showAttributesTab = ($productType === 'variable');
        $this->productId = $productId;
    }

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    #[On('productTypeChanged')]
    public function handleProductTypeChange($type)
    {
        $this->productType = $type;
        $this->showAttributesTab = ($type === 'variable');

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
        if ($field === 'productType') {
            $this->showAttributesTab = ($value === 'variable');
        }

        $data = [
            'regularPrice' => $this->localRegularPrice ?? '',
            'salePrice' => $this->localSalePrice ?? '',
            'sku' => $this->localSku ?? '',
            'productId' => $this->productId ?? '',
            'productType' => $this->productType ?? '',
            'isStockManagementEnabled' => $this->isStockManagementEnabled ?? '',
            'stockQuantity' => $this->stockQuantity ?? '',
            'stockStatus' => $this->stockStatus ?? '',
            'soldIndividually' => $this->soldIndividually ?? '',
            'allowBackorders' => $this->allowBackorders ?? '',
            'lowStockThreshold' => $this->lowStockThreshold ?? '',
            'terms' => $this->terms ?? '',
        ];

        dd($data);
        $this->dispatch('updateMultipleFieldsFromTabs', $data)->to('pages.product.edit');
    }

    public function updatedMrbpData($name, $value)
    {
        $this->dispatch('updateMrbpPrice', ['data' => $this->mrbpData])->to('pages.product.edit');
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
