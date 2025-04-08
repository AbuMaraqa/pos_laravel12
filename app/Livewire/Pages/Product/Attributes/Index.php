<?php

namespace App\Livewire\Pages\Product\Attributes;

use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class Index extends Component
{
    public ?array $data = [];
    public $attribute = [];
    public ?array $terms = [];
    public ?int $selectedAttributeId = null;

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;

        // إضافة قاعدة التحقق المخصصة هنا
//        Validator::extend('unique_slug', function ($attribute, $value, $parameters, $validator) {
//            return $this->isSlugUnique($value);
//        });
    }

    public function mount(): void
    {
        $this->loadAttributes();
    }

    public function loadAttributes(array $query = []): void
    {
        $attributes = $this->wooService->getAttributes($query);

        foreach ($attributes as &$attribute) {
            $attribute['terms'] = $this->wooService->getTermsForAttribute($attribute['id']);
        }

        $this->attribute = $attributes;
    }

    public function generateSlug(): void
    {
        // تأكد من أن الاسم ليس فارغًا قبل إنشاء الـ slug
        $this->data['slug'] = Str::slug($this->data['name']);

        // تقليص الـ slug إذا كان أكبر من 255 حرفًا
        $this->data['slug'] = Str::limit($this->data['slug'], 255);
    }

    #[Computed]
    public function loadTerms($attributeId = null)
    {
        $attributeId = $attributeId ?? $this->selectedAttributeId;

        if (!$attributeId) {
            return [];
        }

        return $this->wooService->getTermsByAttributeId($attributeId);
    }

    public function editAttribute($attributeId)
    {
        $attribute = $this->wooService->getAttributeById($attributeId);

        // تحقق من وجود البيانات قبل المتابعة
        if (!$attribute) {
            return; // أو يمكنك إرسال رسالة خطأ
        }

        $this->modal('edit-term')->show();

        $this->data['id'] = $attribute['id'];
        $this->data['name'] = $attribute['name'];
        $this->data['slug'] = $attribute['slug'];

        // خزّن الـ attributeId حتى تستخدمه في loadTerms
        $this->selectedAttributeId = $attributeId;
    }


    public function saveAttribute(): void
    {
        $this->validate([
            'data.name' => 'required|string|max:255',
            'data.slug' => 'required|string|max:255',
        ]);

        // إعداد البيانات وإرسالها
        $response = $this->wooService->post('products/attributes', [
            'name' => $this->data['name'],
            'slug' => $this->data['slug'],
        ]);

        // إعادة تحميل السمات بعد الحفظ
        $this->loadAttributes();
    }

    public function saveTerm(){
        $this->validate([
            'terms.name' => 'required|string|max:255',
        ]);

        $data = [
            'name' => $this->terms['name'],
        ];

        if (!empty($this->terms['slug'])) {
            $data['slug'] = $this->terms['slug'];
        }

        $this->wooService->post("products/attributes/{$this->selectedAttributeId}/terms", $data);

        $this->loadAttributes();

        $this->dispatch('$refresh');

    }

    public function delete($id){
        $response = $this->wooService->deleteAttribute($id);
        $this->loadAttributes();
        return $response;
    }

    public function deleteTerm($termId)
    {
        try {
            $this->wooService->deleteTerm($this->selectedAttributeId, $termId , ['force' => true]);

            session()->flash('success', 'تم حذف التيرم بنجاح.');

            // تحديث التيرمات بعد الحذف
            $this->loadAttributes(); // أو $this->loadTerms($attributeId) حسب استخدامك
        } catch (\Exception $e) {
            session()->flash('error', 'حدث خطأ أثناء حذف التيرم: ' . $e->getMessage());
        }
    }



    public function render()
    {
        return view('livewire.pages.product.attributes.index');
    }
}
