<?php

namespace App\Livewire\Pages\Product;

use App\Services\WooCommerceService;
use Livewire\Component;
use Spatie\LivewireFilepond\WithFilePond;

class Add extends Component
{
    use WithFilePond;

    public $file;

    public $isStockManagementEnabled = false;
    public $stockQuantity = null;
    public $allowBackorders = false;
    public $lowStockThreshold = null;
    public $stockStatus = null;
    public $soldIndividually = false;

    public $productId;
    public $variations = [];
    public $attribute = [];
    public $selectedAttributes = []; // ['Color' => 'Red', 'Size' => 'M']
    public $selectedVariation = null;

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount($productId = null)
    {
        if ($productId) {
            $this->productId = $productId;
            $this->loadVariations();
        }
    }

    public function loadVariations()
    {
        $response = $this->wooService->get("products/{$this->productId}/variations");

        $this->variations = $response;

        foreach ($this->variations as $variation) {
            foreach ($variation['attributes'] as $attr) {
                $name = $attr['name'];
                $option = $attr['option'];
                $this->attribute[$name][] = $option;
            }
        }

        // إزالة التكرارات
        foreach ($this->attribute as $key => $values) {
            $this->attribute[$key] = array_unique($values);
        }
    }

    public function updatedSelectedAttributes()
    {
        $this->getMatchingVariation();
    }

    public function getMatchingVariation()
    {
        $matched = collect($this->variations)->first(function ($variation) {
            foreach ($variation['attributes'] as $attr) {
                if (!isset($this->selectedAttributes[$attr['name']]) || $this->selectedAttributes[$attr['name']] !== $attr['option']) {
                    return false;
                }
            }
            return true;
        });

        $this->selectedVariation = $matched;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|mimetypes:image/jpg,image/jpeg,image/png|max:3000',
        ];
    }

    public function validateUploadedFile()
    {
        $this->validate();

        return true;
    }

    public function uploadImage(): array
    {
        $this->validate();
        $realPath = $this->file->getRealPath();

        $response = $this->wooService->post('media', [
            'headers' => [
                'Content-Disposition' => 'attachment; filename="image.jpg"',
                'Content-Type' => 'image/jpeg',
            ],
            'body' => file_get_contents($realPath),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function render()
    {
        return view('livewire.pages.product.add');
    }
}
