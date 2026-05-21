<x-filament-panels::page>
    <form wire:submit="import">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                匯入名單
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
