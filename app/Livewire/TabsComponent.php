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
    public bool $isStockManagementEnabled = false;
    public $stockQuantity;
    public $allowBackorders = 'no';
    public $stockStatus = 'instock';
    public $soldIndividually = false;
    public $lowStockThreshold;
    public $terms;
    public $mrbpData = [];
    public $productId;

    // ✅ إضافة البيانات المطلوبة للمتغيرات
    public $variations = [];
    public $attributeMap = [];
    public $selectedAttributes = [];

    protected WooCommerceService $wooService;

    public function mount($productType, $regularPrice = null, $salePrice = null, $sku = null, $productId = null, $variations = [], $attributeMap = [], $selectedAttributes = [])
    {
        $this->productType = $productType;
        $this->localRegularPrice = $regularPrice;
        $this->localSalePrice = $salePrice;
        $this->localSku = $sku;
        $this->showAttributesTab = ($productType === 'variable');
        $this->productId = $productId;

        // ✅ استقبال بيانات المتغيرات
        $this->variations = $variations;
        $this->attributeMap = $attributeMap;
        $this->selectedAttributes = $selectedAttributes;

        \Illuminate\Support\Facades\Log::info('TabsComponent mounted with variation data', [
            'productType' => $productType,
            'regularPrice' => $regularPrice,
            'salePrice' => $salePrice,
            'sku' => $sku,
            'productId' => $productId,
            'variations_count' => count($variations),
            'attributeMap_count' => count($attributeMap),
            'selectedAttributes_count' => count($selectedAttributes)
        ]);

        // إذا كان المنتج موجود، نجلب البيانات من Edit Component
        if ($this->productId) {
            $this->dispatch('getProductStockSettings')->to('pages.product.edit');
        }
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

        // إذا تم اختيار منتج متغير، انتقل تلقائيًا إلى تبويب الصفات
        if ($type === 'variable') {
            $this->activeTab = 4; // تبويب الصفات
        } else if ($this->activeTab === 4 && $type === 'simple') {
            $this->activeTab = 1;
        }
    }

    public function updatedLocalRegularPrice($value)
    {
        $this->localRegularPrice = $value;
        $this->sendUpdatedData();
    }

    public function updatedLocalSalePrice($value)
    {
        $this->localSalePrice = $value;
        $this->sendUpdatedData();
    }

    public function updatedLocalSku($value)
    {
        $this->localSku = $value;
        $this->sendUpdatedData();
    }

    public function sendUpdatedData()
    {
        $data = [
            'regularPrice' => $this->localRegularPrice ?? '',
            'salePrice' => $this->localSalePrice ?? '',
            'sku' => $this->localSku ?? '',
            'productId' => $this->productId ?? '',
            'productType' => $this->productType ?? '',
            'isStockManagementEnabled' => $this->isStockManagementEnabled,
            'stockQuantity' => $this->stockQuantity ?? '',
            'stockStatus' => $this->stockStatus ?? '',
            'soldIndividually' => $this->soldIndividually ?? '',
            'allowBackorders' => $this->allowBackorders ?? '',
            'lowStockThreshold' => $this->lowStockThreshold ?? '',
            'terms' => $this->terms ?? '',
        ];

        \Illuminate\Support\Facades\Log::info('Sending updated data from tabs', $data);

        $this->dispatch('updateMultipleFieldsFromTabs', $data)->to('pages.product.add');
        $this->dispatch('updateMultipleFieldsFromTabs', $data)->to('pages.product.edit');
    }

    public function updatedMrbpData($name, $value)
    {
        $this->dispatch('updateMrbpPrice', ['data' => $this->mrbpData])->to('pages.product.edit');
    }

    #[On('updateStockSettings')]
    public function updateStockSettings($data)
    {
        $this->isStockManagementEnabled = $data['isStockManagementEnabled'] ?? false;
        $this->stockQuantity = $data['stockQuantity'] ?? null;
        $this->stockStatus = $data['stockStatus'] ?? null;
        $this->soldIndividually = $data['soldIndividually'] ?? null;
        $this->allowBackorders = $data['allowBackorders'] ?? null;
        $this->lowStockThreshold = $data['lowStockThreshold'] ?? null;
    }

    // ✅ استقبال تحديثات المتغيرات من VariationManager
    #[On('variationsUpdated')]
    public function handleVariationsUpdated($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];

        // إرسال التحديثات للمكون الرئيسي
        $this->dispatch('variationsUpdated', $data)->to('pages.product.edit');
        $this->dispatch('variationsUpdated', $data)->to('pages.product.add');
    }

    #[Computed()]
    public function getRoles(){
        return $this->wooService->getRoles();
    }

    public function render()
    {
        return view('livewire.tabs-component', [
            'showAttributesTab' => $this->showAttributesTab,
            'variations' => $this->variations,
            'attributeMap' => $this->attributeMap,
            'selectedAttributes' => $this->selectedAttributes
        ]);
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

        // لا نرسل الحقول التي لديها وظائف محددة
        if (!in_array($field, ['localRegularPrice', 'localSalePrice', 'localSku'])) {
            $this->sendUpdatedData();
        }
    }

    #[On('updatePricesFromEdit')]
    public function handlePricesUpdate($data)
    {
        \Illuminate\Support\Facades\Log::info('TabsComponent received price update', $data);

        $this->localRegularPrice = $data['regularPrice'] ?? $this->localRegularPrice;
        $this->localSalePrice = $data['salePrice'] ?? $this->localSalePrice;
        $this->localSku = $data['sku'] ?? $this->localSku;

        \Illuminate\Support\Facades\Log::info('TabsComponent after update', [
            'localRegularPrice' => $this->localRegularPrice,
            'localSalePrice' => $this->localSalePrice,
            'localSku' => $this->localSku
        ]);
    }
}
