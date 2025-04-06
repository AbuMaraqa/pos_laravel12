<?php

namespace App\Livewire\Pages\Product\Wizard;

use Livewire\Component;
use Spatie\LivewireWizard\Components\StepComponent;

class ProductVariation extends StepComponent
{
    public function stepInfo(): array
    {
        return [
            'label' => 'Variations',
            'icon' => 'fa-shopping-cart',
        ];
    }

    public function render()
    {
        return view('livewire.pages.product.wizard.product-variation');
    }
}
