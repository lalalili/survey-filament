<x-filament-panels::page>
    @php
        $totals = $analytics['totals'] ?? [];
        $collectors = $analytics['collectors'] ?? [];
        $questions = $analytics['questions'] ?? [];
        $daily = $analytics['daily'] ?? [];
        $totalResponses = $totals['responses'] ?? 0;
    @endphp

    {{-- 總覽數字 --}}
    <div class="grid gap-4 md:grid-cols-4">
        @foreach([
            ['label' => '總回應', 'value' => $totals['responses'] ?? 0, 'icon' => '📥'],
            ['label' => '開始填寫', 'value' => $totals['started'] ?? 0, 'icon' => '✏️'],
            ['label' => '已提交', 'value' => $totals['submitted'] ?? 0, 'icon' => '✅'],
            ['label' => '完成率', 'value' => ($totals['completion_rate'] ?? 0) . '%', 'icon' => '📊'],
        ] as $stat)
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-2">
                    <span class="text-lg">{{ $stat['icon'] }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</span>
                </div>
                <div class="mt-2 text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{{ $stat['value'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Collector 成效 + 每日趨勢 --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Collector 成效</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="pb-2 pr-4 font-medium">名稱</th>
                            <th class="pb-2 pr-4 font-medium">類型</th>
                            <th class="pb-2 pr-3 text-right font-medium">開始</th>
                            <th class="pb-2 pr-3 text-right font-medium">提交</th>
                            <th class="pb-2 text-right font-medium">完成率</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($collectors as $collector)
                            <tr>
                                <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ $collector['name'] }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $collector['type'] }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ $collector['started'] }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ $collector['submitted'] }}</td>
                                <td class="py-2 text-right tabular-nums font-semibold">{{ $collector['completion_rate'] }}%</td>
                            </tr>
                        @empty
                            <tr><td class="py-8 text-center text-sm text-gray-400" colspan="5">尚未建立 Collector。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">每日趨勢</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="pb-2 pr-4 font-medium">日期</th>
                            <th class="pb-2 pr-4 text-right font-medium">開始</th>
                            <th class="pb-2 text-right font-medium">提交</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse(array_reverse($daily) as $day)
                            <tr>
                                <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">{{ $day['date'] }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ $day['started'] }}</td>
                                <td class="py-2 text-right tabular-nums font-semibold">{{ $day['submitted'] }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-8 text-center text-sm text-gray-400" colspan="3">尚無趨勢資料。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- 單題統計 --}}
    <section>
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">單題統計</h2>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse($questions as $question)
                <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    {{-- 題目標頭 --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-medium text-gray-900 dark:text-white">{{ $question['label'] }}</h3>
                            <p class="mt-0.5 text-xs text-gray-400">{{ $question['field_key'] }} · {{ $question['type'] }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ $question['answered'] }} 答
                        </span>
                    </div>

                    @php $answered = max($question['answered'], 1); @endphp

                    {{-- 選擇題：選項分布條形 --}}
                    @if(!empty($question['distribution']) && isset($question['distribution'][0]['label']))
                        @php $maxCount = max(collect($question['distribution'])->max('count'), 1); @endphp
                        <dl class="mt-4 space-y-2.5">
                            @foreach($question['distribution'] as $item)
                                @php
                                    $pct = round($item['count'] / $answered * 100, 1);
                                    $barPct = $item['count'] / $maxCount * 100;
                                @endphp
                                <div>
                                    <div class="mb-1 flex items-center justify-between text-xs">
                                        <dt class="truncate text-gray-700 dark:text-gray-300">{{ $item['label'] }}</dt>
                                        <dd class="ml-3 shrink-0 tabular-nums text-gray-500">{{ $item['count'] }} ({{ $pct }}%)</dd>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                        <div class="h-full rounded-full bg-primary-500 transition-all" style="width: {{ $barPct }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </dl>

                    {{-- 數值分布（NPS / Rating / LinearScale）：平均值 + 條形 --}}
                    @elseif(!empty($question['distribution']) && isset($question['distribution'][0]['value']))
                        @if(isset($question['average']))
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                平均：<span class="ml-1 text-xl font-bold text-gray-900 dark:text-white">{{ $question['average'] ?? '-' }}</span>
                            </p>
                        @endif
                        @php $maxCount = max(collect($question['distribution'])->max('count'), 1); @endphp
                        <dl class="mt-3 grid grid-cols-2 gap-1.5">
                            @foreach($question['distribution'] as $item)
                                @php $barPct = $item['count'] / $maxCount * 100; @endphp
                                <div class="flex items-center gap-2 text-xs">
                                    <dt class="w-6 shrink-0 text-right tabular-nums text-gray-500">{{ $item['value'] }}</dt>
                                    <div class="flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700" style="height:8px">
                                        <div class="h-full rounded-full bg-primary-400" style="width: {{ $barPct }}%"></div>
                                    </div>
                                    <dd class="w-6 shrink-0 tabular-nums text-gray-500">{{ $item['count'] }}</dd>
                                </div>
                            @endforeach
                        </dl>

                    {{-- 數字題：平均 / 最小 / 最大 --}}
                    @elseif(isset($question['min']) || isset($question['average']))
                        <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                            @foreach([
                                ['label' => '平均', 'value' => $question['average'] ?? '-'],
                                ['label' => '最小', 'value' => $question['min'] ?? '-'],
                                ['label' => '最大', 'value' => $question['max'] ?? '-'],
                            ] as $stat)
                                <div class="rounded-lg bg-gray-50 px-2 py-3 dark:bg-gray-800">
                                    <dt class="text-xs text-gray-400">{{ $stat['label'] }}</dt>
                                    <dd class="mt-1 text-lg font-bold tabular-nums text-gray-900 dark:text-white">{{ $stat['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>

                    {{-- 矩陣題 --}}
                    @elseif(!empty($question['matrix']))
                        @php
                            $matrix = $question['matrix'];
                            $rows = $matrix['rows'] ?? [];
                            $cols = $matrix['cols'] ?? [];
                            $counts = $matrix['counts'] ?? [];
                        @endphp
                        @if(!empty($rows) && !empty($cols))
                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr>
                                            <th class="pb-2 pr-3 text-left text-gray-400"></th>
                                            @foreach($cols as $colVal => $colLabel)
                                                <th class="pb-2 pr-2 text-center font-medium text-gray-500">{{ $colLabel }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach($rows as $rowVal => $rowLabel)
                                            <tr>
                                                <td class="py-1.5 pr-3 font-medium text-gray-700 dark:text-gray-300">{{ $rowLabel }}</td>
                                                @foreach($cols as $colVal => $colLabel)
                                                    <td class="py-1.5 pr-2 text-center tabular-nums text-gray-600 dark:text-gray-400">
                                                        {{ $counts[$rowVal][$colVal] ?? 0 }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                    {{-- 排序題：平均名次排行 --}}
                    @elseif(!empty($question['ranking']))
                        <ol class="mt-4 space-y-1.5">
                            @foreach($question['ranking'] as $i => $item)
                                <li class="flex items-center gap-3 text-sm">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                                        {{ $i + 1 }}
                                    </span>
                                    <span class="flex-1 truncate text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="tabular-nums text-xs text-gray-400">平均 {{ $item['avg_rank'] ?? '-' }} 名</span>
                                </li>
                            @endforeach
                        </ol>

                    {{-- 總計題：各項平均分配 --}}
                    @elseif(!empty($question['constant_sum']))
                        @php $maxAvg = max(collect($question['constant_sum'])->max('avg') ?? 1, 1); @endphp
                        <dl class="mt-4 space-y-2">
                            @foreach($question['constant_sum'] as $item)
                                @php $barPct = $item['avg'] !== null ? ($item['avg'] / $maxAvg * 100) : 0; @endphp
                                <div>
                                    <div class="mb-1 flex justify-between text-xs">
                                        <dt class="text-gray-700 dark:text-gray-300">{{ $item['label'] }}</dt>
                                        <dd class="tabular-nums text-gray-500">平均 {{ $item['avg'] ?? '-' }}</dd>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                        <div class="h-full rounded-full bg-amber-400" style="width: {{ $barPct }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </dl>

                    {{-- 文字題：樣本答案列表 --}}
                    @elseif(!empty($question['sample']))
                        <ul class="mt-4 space-y-1.5">
                            @foreach($question['sample'] as $text)
                                <li class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {{ $text }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @empty
                <p class="col-span-2 py-12 text-center text-sm text-gray-400">尚無可統計題目。</p>
            @endforelse
        </div>
    </section>
</x-filament-panels::page>
