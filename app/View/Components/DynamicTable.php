<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DynamicTable extends Component
{
    public $columns;
    public $data;
    public $actions;

    /**
     * Constructor to initialize the columns, data, and actions.
     */
    public function __construct($columns, $data, $actions = [])
    {
        $this->columns = $columns;  // الأعمدة
        $this->data = $data;        // البيانات (مثل المنتجات أو أي محتوى آخر)
        $this->actions = $actions;  // الأزرار التي يمكن تخصيصها (مثل View, Edit, Delete)
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('components.dynamic-table');
    }

}
