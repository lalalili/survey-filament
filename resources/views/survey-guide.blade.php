<x-filament-panels::page>
    <div class="space-y-8">
        <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="space-y-5 p-6">
                <header class="space-y-1">
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">建議操作流程</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">第一次建立問卷時，建議依序完成以下步驟；需要調整時可回到 Builder 修改草稿，確認後再發佈。</p>
                </header>

                <div class="grid gap-4 md:grid-cols-5">
                    @foreach ($this->guideQuickSteps() as $step)
                        <div class="space-y-2 rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-gray-950/40 dark:ring-white/10">
                            <div class="flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-600 text-xs font-semibold text-white dark:bg-primary-500">{{ $step['label'] }}</span>
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $step['title'] }}</h3>
                            </div>
                            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $step['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @foreach ($this->guideSections() as $section)
            <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="space-y-4 p-6">
                    <header class="space-y-1">
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $section['title'] }}</h2>
                        @if (! empty($section['intro']))
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $section['intro'] }}</p>
                        @endif
                    </header>

                    <div class="grid gap-5 md:grid-cols-2">
                        @foreach ($section['blocks'] as $block)
                            <div class="space-y-2">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $block['heading'] }}</h3>
                                <ul class="list-disc space-y-1 ps-5 text-sm text-gray-600 dark:text-gray-300">
                                    @foreach ($block['items'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
