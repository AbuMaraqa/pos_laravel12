<?php

namespace App\Support;

use Spatie\LivewireWizard\Support\State;

class ProductWizardState extends State
{
    public function productVariation(): array
    {
        $productVariationStepState = $this->forStep('product-variation');

        return [
            'name' => $productVariationStepState['name'],
            'street' => $productVariationStepState['street'],
            'zip' => $productVariationStepState['zip'],
            'city' => $productVariationStepState['city'],
        ];
    }

    public function amount(): int
    {
        return $this->forStep('product-info')['amount'];
    }
}
