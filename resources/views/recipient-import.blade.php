<x-filament-panels::page>
    <form wire:submit="import">
        {{ $this->form }}

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-filament::button type="button" wire:click="preview" wire:loading.attr="disabled" wire:target="preview">
                預覽名單
            </x-filament::button>

            @if ($previewData !== null)
                <x-filament::button type="submit" color="primary" wire:loading.attr="disabled" wire:target="import">
                    確認匯入
                </x-filament::button>
            @endif
        </div>

        @if ($previewData !== null)
            <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">匯入預覽</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            顯示前 {{ $previewData['displayed_rows'] }} 筆，共 {{ $previewData['total_rows'] }} 筆資料。
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                @foreach ($previewData['columns'] as $column)
                                    <th scope="col" class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">
                                        {{ $column }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @forelse ($previewData['rows'] as $row)
                                <tr>
                                    @foreach ($previewData['columns'] as $column)
                                        <td class="max-w-64 truncate px-4 py-3 text-gray-700 dark:text-gray-300" title="{{ $row[$column] ?? '' }}">
                                            {{ $row[$column] ?? '' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400" colspan="{{ max(count($previewData['columns']), 1) }}">
                                        沒有可匯入的資料列。
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </form>
</x-filament-panels::page>
