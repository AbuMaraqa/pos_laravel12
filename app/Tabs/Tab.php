<?php

namespace App\Tabs;

use Vildanbina\LivewireTabs\Components\Tab;
use Illuminate\Validation\Rule;

class General extends Tab
{
    // Tab view located at resources/views/tabs/general.blade.php
    protected string $view = 'tabs.general';

    /*
     * Initialize tab fields
     */
    public function mount()
    {
        $this->mergeState([
            'name'                  => $this->model->name,
            'email'                 => $this->model->email,
        ]);
    }

    /*
    * Tab icon
    */
    public function icon()
    {
        return view('icons.home');
    }

    /*
     * When Tabs Form has submitted
     */
    public function save($state)
    {
        $user = $this->model;

        $user->name     = $state['name'];
        $user->email    = $state['email'];

        $user->save();
    }

    /*
     * Tab Validation
     */
    public function validate()
    {
        return [
            [
                'state.name'     => ['required', Rule::unique('users', 'name')->ignoreModel($this->model)],
                'state.email'    => ['required', Rule::unique('users', 'email')->ignoreModel($this->model)],
            ],
            [
                'state.name'     => __('Name'),
                'state.email'    => __('Email'),
            ],
        ];
    }

    /*
     * Tab Title
     */
    public function title(): string
    {
        return __('General');
    }
}
