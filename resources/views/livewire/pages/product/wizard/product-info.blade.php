<div>
    <nav class="-mb-px flex space-x-2 mb-6" aria-label="Tabs">
        @foreach($steps as $step)
            <div class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm
        {{ $step->isCurrent() ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500' }}"
                 @if ($step->isPrevious())
                     wire:click="{{ $step->show() }}"
                @endif
            >
                <span>{{ $step->label }}</span>
            </div>
        @endforeach
    </nav>

    <flux:input wire:model="name" label="Username" description="This will be publicly displayed." />

    <div wire:click="previousStep">
        Go to the previous step
    </div>

    <div wire:click="submit">
        Go to the next step
    </div>
</div>
