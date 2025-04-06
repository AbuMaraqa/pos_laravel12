<?php

namespace App\Livewire\Pages\Product;

use App\Livewire\Pages\Product\Wizard\ProductInfo;
use App\Livewire\Pages\Product\Wizard\ProductVariation;
use Spatie\LivewireWizard\Components\WizardComponent;

class WizardRegistration extends WizardComponent
{
    public function steps() : array
    {
        return [
            ProductInfo::class,
            ProductVariation::class
        ];
    }
}
