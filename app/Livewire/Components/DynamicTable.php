<?php

namespace App\Livewire\Components;

use Livewire\Component;
use Livewire\WithPagination;

class DynamicTable extends Component
{
    use WithPagination;

    public $model; // النموذج الذي سيتم التعامل معه (مثل Product، User، إلخ)
    public $columns; // الأعمدة التي سيتم عرضها في الجدول
    public $search = ''; // خاصية البحث
    public $selectedId = null; // لتحديد السطر الذي سيتم تعديله
    public $formData = []; // لتخزين بيانات النموذج عند إضافة أو تعديل

    public function mount($model, $columns)
    {
        $this->model = $model;
        $this->columns = $columns;
    }

    // تحميل البيانات وتطبيق البحث والصفحات
    public function render()
    {
        $query = $this->model::query();

        // تطبيق البحث إذا كان هناك
        if ($this->search) {
            foreach ($this->columns as $column) {
                $query->orWhere($column, 'like', '%' . $this->search . '%');
            }
        }

        $data = $query->paginate(10); // تقسيم البيانات على الصفحات

        return view('livewire.dynamic-table', compact('data'));
    }

    // حذف السطر
    public function delete($id)
    {
        $this->model::find($id)->delete();
        session()->flash('message', 'تم الحذف بنجاح');
    }

    // فتح النموذج للتعديل
    public function edit($id)
    {
        $this->selectedId = $id;
        $this->formData = $this->model::find($id)->toArray();
    }

    // تحديث البيانات
    public function update()
    {
        $this->model::find($this->selectedId)->update($this->formData);
        session()->flash('message', 'تم التحديث بنجاح');
        $this->resetForm();
    }

    // إضافة سجل جديد
    public function store()
    {
        $this->model::create($this->formData);
        session()->flash('message', 'تم إضافة البيانات بنجاح');
        $this->resetForm();
    }

    // إعادة تعيين النموذج بعد الإضافة أو التعديل
    public function resetForm()
    {
        $this->formData = [];
        $this->selectedId = null;
    }
}
