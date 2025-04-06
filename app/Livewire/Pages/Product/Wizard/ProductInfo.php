<?php

namespace App\Livewire\Pages\Product\Wizard;

use Livewire\Component;
use Spatie\LivewireWizard\Components\StepComponent;
use Spatie\LivewireWizard\Support\Step;

class ProductInfo extends StepComponent
{
    public $name = '';
    public function stepInfo(): array
    {
        return [
            'label' => 'Product Info',
            'icon' => 'fa-shopping-cart',
        ];
    }

    public array $rules = [
        'name' => 'required',
    ];


    public function submit()
    {
        $this->nextStep();
    }

    public function render()
    {
        return view('livewire.pages.product.wizard.product-info');
    }
}
