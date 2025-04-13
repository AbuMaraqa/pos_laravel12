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
        $attributesWithTerms = $woo->getAttributesWithTerms();

        $this->productAttributes = $attributesWithTerms;

        foreach ($attributesWithTerms as $attribute) {
            $this->attributeTerms[$attribute['id']] = $attribute['terms'];
        }
    }

    public function generateVariations()
    {
        $this->variations = [];
        $attributeOptions = [];
        $this->attributeMap = [];

        foreach ($this->selectedAttributes as $attributeId => $termMap) {
            $termIds = array_keys(array_filter($termMap));

            if (!empty($termIds)) {
                $terms = array_map(function ($termId) use ($attributeId) {
                    return [
                        'id' => $termId,
                        'name' => $this->getTermName($attributeId, $termId)
                    ];
                }, $termIds);

                $attribute = $this->getAttributeById($attributeId);

                $attributeOptions[] = array_column($terms, 'name');

                $this->attributeMap[] = [
                    'id' => $attributeId,
                    'name' => $attribute['name'] ?? ''
                ];
            }
        }

        if (!empty($attributeOptions)) {
            $combinations = $this->generateCombinations($attributeOptions);

            foreach ($combinations as $combo) {
                $this->variations[] = [
                    'sku' => '',
                    'regular_price' => '',
                    'sale_price' => '',
                    'stock_quantity' => 0,
                    'active' => true,
                    'length' => '',
                    'width' => '',
                    'height' => '',
                    'description' => '',
                    'options' => $combo,
                ];
            }
        }
    }

    #[On('requestLatestVariations')]
    public function sendLatestToParent()
    {
        $this->dispatch('latestVariationsSent', [
            'variations' => array_map(fn($v) => (array) $v, $this->variations),
            'attributeMap' => array_map(fn($m) => (array) $m, $this->attributeMap),
        ])->to('pages.product.add');
    }

    protected function getAttributeById($id)
    {
        foreach ($this->productAttributes as $attribute) {
            if ($attribute['id'] == $id) {
                return $attribute;
            }
        }
        return null;
    }

    protected function getTermName($attributeId, $termId)
    {
        foreach ($this->attributeTerms[$attributeId] ?? [] as $term) {
            if ($term['id'] == $termId) {
                return $term['name'];
            }
        }
        return '';
    }

    protected function generateCombinations($arrays)
    {
        $result = [[]];
        foreach ($arrays as $array) {
            $append = [];
            foreach ($result as $product) {
                foreach ($array as $item) {
                    $append[] = array_merge($product, [$item]);
                }
            }
            $result = $append;
        }
        return $result;
    }

    public function render()
    {
        return view('livewire.variation-manager');
    }
}
