<?php

namespace App\Livewire;

use Livewire\Component;

class VariationManager extends Component
{
    public $attribute = [];
    public $variations = [];

    // إضافة خاصية جديدة
    public function addAttribute()
    {
        $this->attribute[] = [
            'name' => '',
            'options' => ['']
        ];
    }

    // إضافة خيار جديد للخاصية
    public function addOption($index)
    {
        $this->attribute[$index]['options'][] = '';
    }

    // إزالة الخيار
    public function removeOption($index, $optIndex)
    {
        // تأكد من أنه يوجد أكثر من خيار واحد لكي لا نترك المجموعة فارغة
        if (count($this->attribute[$index]['options']) > 1) {
            // إزالة الخيار باستخدام array_splice
            array_splice($this->attribute[$index]['options'], $optIndex, 1);

            // إعادة ترتيب الفهارس بعد الحذف
            $this->attribute[$index]['options'] = array_values($this->attribute[$index]['options']);
        }
    }

    // إزالة خاصية كاملة
    public function removeAttribute($index)
    {
        unset($this->attribute[$index]);
        // إعادة ترتيب الفهارس بعد الحذف
        $this->attribute = array_values($this->attribute);
    }

    // توليد المتغيرات بناءً على الخصائص
    public function generateVariations()
    {

//        foreach ($this->attribute as $attribute) {
//            if (empty($attribute['options']) || in_array('', $attribute['options'])) {
//                $this->addError('variations', 'كل خاصية يجب أن تحتوي على خيارات كاملة.');
//                return;
//            }
//        }

        // توليد التراكيب (variations) بناءً على الخيارات
        $attributeOptions = array_map(fn($attr) => $attr['options'], $this->attribute);
        $combinations = $this->cartesian($attributeOptions);

        $this->variations = array_map(fn($combo) => ['options' => $combo], $combinations);

//        dd($this->attribute);

    }

    // دالة حساب التراكيب (التركيب الكارتيزي)
    protected function cartesian($arrays)
    {
        if (empty($arrays)) return [];

        $result = [[]];

        foreach ($arrays as $property_values) {
            $tmp = [];
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, [$property_value]);
                }
            }
            $result = $tmp;
        }

        return $result;
    }

    // عرض المكون
    public function render()
    {
        return view('livewire.variation-manager');
    }
}
