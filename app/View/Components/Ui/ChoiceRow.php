<?php

namespace App\View\Components\Ui;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ChoiceRow extends Component
{
    public string $value;
    public ?string $selected;
    public ?string $left;
    public ?string $right;

    /**
     * @param string $value   - значение, по которому проверяется selected
     * @param string $selected - текущее выбранное значение
     * @param string $left     - HTML для левой части строки
     * @param string $right    - HTML для правой части строки
     */
    public function __construct(string $value, ?string $selected = null, string $left= null, string $right= null)
    {
        $this->value = $value;
        $this->selected = $selected;
        $this->left = $left;
        $this->right = $right;
    }

    public function render()
    {
        return view('components.ui.choice-row');
    }
}
