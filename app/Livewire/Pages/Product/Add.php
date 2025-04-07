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



    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
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
