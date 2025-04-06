<div>
    Mohamad Maraqa
    <ul>
        @foreach($steps as $step)
            <li
                class="{{ $step->isCurrent() ? 'text-bold' : '' }}"
                @if ($step->isPrevious())
                    wire:click="{{ $step->show() }}"
                @endif
            >{{ $step->label }}</li>
        @endforeach
    </ul>
</div>
