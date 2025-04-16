<?php

namespace App\Livewire\Pages\Category;

use Livewire\Component;
use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Masmerise\Toaster\Toaster;

class Index extends Component
{
    public $name;
    public $parentId;
    public $description;

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    #[Computed]
    public function getCategories(): array
    {
        $categories = $this->wooService->getCategories(['per_page' => 100]);
        $grouped = [];
        foreach ($categories as $cat) {
            $grouped[$cat['parent']][] = $cat;
        }

        $buildTree = function ($parentId = 0) use (&$buildTree, $grouped) {
            $tree = [];
            if (isset($grouped[$parentId])) {
                foreach ($grouped[$parentId] as $cat) {
                    $cat['children'] = $buildTree($cat['id']);
                    $tree[] = $cat;
                }
            }
            return $tree;
        };

        return $buildTree();
    }

    #[Computed]
    public function listCategories(){
        return $this->wooService->getCategories();
    }

    public function addCategory()
    {
        $this->validate([
            'name' => 'required',
            'parentId' => 'required',
        ]);

        $response = $this->wooService->addCategory($this->name, $this->parentId, $this->description);

        if ($response) {
            Toaster::success(__('Category added successfully'));
        } else {
            Toaster::error(__('Failed to add category'));
        }

    }

    public function render()
    {
        return view('livewire.pages.category.index');
    }
}
