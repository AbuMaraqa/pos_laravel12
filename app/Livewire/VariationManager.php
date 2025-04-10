<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use App\Services\WooCommerceService;

class VariationManager extends Component
{
    public $productAttributes = [];
    public $attributeTerms = [];
    public $selectedAttributes = [];
    public $variations = [];
    public $attributeMap = [];

    public function mount()
    {
        $woo = new WooCommerceService();

        $this->productAttributes = $woo->getAttributes();

        foreach ($this->productAttributes as $attribute) {
            $this->attributeTerms[$attribute['id']] = $woo->getTerms($attribute['id']);
        }
    }

    public function generateVariations()
    {
        $this->variations = [];
        $attributeOptions = [];
        $wooService = new WooCommerceService();
        $this->attributeMap = [];

        foreach ($this->selectedAttributes as $attributeId => $termMap) {
            $termIds = array_keys(array_filter($termMap));

            if (!empty($termIds)) {
                $attribute = collect($this->productAttributes)->firstWhere('id', $attributeId);
                $this->attributeMap[] = [
                    'id' => $attributeId,
                    'name' => $attribute['name'] ?? 'خاصية',
                ];

                $terms = $wooService->getTermsForAttribute($attributeId);
                $selectedNames = [];

                foreach ($termIds as $id) {
                    $term = collect($terms)->firstWhere('id', $id);
                    if ($term) {
                        $selectedNames[] = $term['name'];
                    }
                }

                $attributeOptions[] = $selectedNames;
            }
        }

        $this->variations = collect($this->cartesian($attributeOptions))->map(function ($combo) {
            return [
                'options' => $combo,
                'sku' => '',
                'regular_price' => '',
                'sale_price' => '',
                'stock_quantity' => '',
                'active' => true,
                'length' => '',
                'width' => '',
                'height' => '',
                'description' => '',
            ];
        })->toArray();

        $this->emitData(); // 👈 إرسال البيانات تلقائيًا بعد التوليد
    }

    public function updatedVariations()
    {
        // 👈 أي تعديل على جدول المتغيرات يرسل البيانات
        $this->emitData();
    }

    public function emitData()
    {
        $this->dispatch('variationsGenerated', [
            'variations' => $this->variations,
            'attributeMap' => $this->attributeMap,
        ])->to('pages.product.add');
    }

    protected function cartesian($arrays)
    {
        if (empty($arrays)) return [];

        $result = [[]];
        foreach ($arrays as $values) {
            $tmp = [];
            foreach ($result as $combo) {
                foreach ($values as $value) {
                    $tmp[] = array_merge($combo, [$value]);
                }
            }
            $result = $tmp;
        }
        return $result;
    }

    #[On('requestLatestVariations')]
    public function sendLatestToParent()
    {
        $this->emitData();
    }


    public function render()
    {
        return view('livewire.variation-manager');
    }
}
