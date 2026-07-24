<x-filament-panels::page>
    @php
        $totals = $analytics['totals'] ?? [];
        $questions = $analytics['questions'] ?? [];
        $daily = $analytics['daily'] ?? [];
    @endphp

    {{-- 收集器篩選 --}}
    @if(count($collectorOptions) > 0)
        <div class="flex items-center gap-3">
            <label for="analytics-collector-filter" class="text-sm font-medium text-gray-700 dark:text-gray-300">收集器</label>
            <select
                id="analytics-collector-filter"
                wire:model.live="collectorId"
                class="rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            >
                <option value="">全部</option>
                @foreach($collectorOptions as $option)
                    <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                @endforeach
            </select>
            <span wire:loading wire:target="collectorId" class="text-xs text-gray-400">統計中…</span>
        </div>
    @endif

    {{-- 左：總覽數字（已提交／完成率）｜右：每日趨勢，各佔 50% --}}
    <div class="grid items-start gap-6 lg:grid-cols-2">
        {{-- 總覽數字 --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
            @foreach([
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

        {{-- 每日趨勢（wire:key 讓切換 collector 後 Alpine 以新資料重建） --}}
        <section
            wire:key="daily-trend-{{ $collectorId === '' ? 'all' : $collectorId }}"
            x-data="{
                page: 1,
                perPage: 10,
                rows: @js(array_values(array_reverse($daily))),
                get totalPages() { return Math.max(1, Math.ceil(this.rows.length / this.perPage)); },
                get paged() {
                    const start = (this.page - 1) * this.perPage;
                    return this.rows.slice(start, start + this.perPage);
                },
                get rangeLabel() {
                    if (this.rows.length === 0) { return '0'; }
                    const start = (this.page - 1) * this.perPage + 1;
                    const end = Math.min(this.page * this.perPage, this.rows.length);
                    return start + '–' + end;
                },
            }"
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
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
                        <template x-for="day in paged" :key="day.date">
                            <tr>
                                <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white" x-text="day.date"></td>
                                <td class="py-2 pr-4 text-right tabular-nums" x-text="day.started"></td>
                                <td class="py-2 text-right tabular-nums font-semibold" x-text="day.submitted"></td>
                            </tr>
                        </template>
                        <tr x-show="rows.length === 0">
                            <td class="py-8 text-center text-sm text-gray-400" colspan="3">尚無趨勢資料。</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- 分頁控制：每頁 10 筆，僅在超過 1 頁時顯示 --}}
            <div x-show="totalPages > 1" class="mt-4 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>顯示 <span class="tabular-nums" x-text="rangeLabel"></span> / 共 <span class="tabular-nums" x-text="rows.length"></span> 天</span>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="page = Math.max(1, page - 1)"
                        :disabled="page === 1"
                        class="rounded-md border border-gray-200 px-2.5 py-1 font-medium transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:hover:bg-gray-800"
                    >上一頁</button>
                    <span class="tabular-nums">第 <span x-text="page"></span> / <span x-text="totalPages"></span> 頁</span>
                    <button
                        type="button"
                        @click="page = Math.min(totalPages, page + 1)"
                        :disabled="page === totalPages"
                        class="rounded-md border border-gray-200 px-2.5 py-1 font-medium transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:hover:bg-gray-800"
                    >下一頁</button>
                </div>
            </div>
        </section>
    </div>

    {{-- 填答流程漏斗（逐頁流失） --}}
    @php
        $funnel = $analytics['funnel']['steps'] ?? [];
        $funnelMax = collect($funnel)->max('count') ?: 1;
    @endphp
    @if(collect($funnel)->sum('count') > 0)
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">填答流程漏斗</h2>
            <p class="mb-4 text-xs text-gray-400 dark:text-gray-500">依頁面順序的瀏覽量（括號為相對前一階段的留存率）。</p>
            <div class="space-y-3">
                @foreach($funnel as $index => $step)
                    @php
                        $width = $funnelMax > 0 ? round(($step['count'] / $funnelMax) * 100, 1) : 0;
                        $previous = $index > 0 ? ($funnel[$index - 1]['count'] ?? 0) : null;
                        $retention = ($previous !== null && $previous > 0) ? round(($step['count'] / $previous) * 100) : null;
                    @endphp
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-3 text-xs text-gray-600 dark:text-gray-300">
                            <span class="truncate">{{ $step['label'] }}</span>
                            <span class="shrink-0 tabular-nums">
                                {{ number_format($step['count']) }}
                                @if($retention !== null)
                                    <span class="text-gray-400 dark:text-gray-500">（{{ $retention }}%）</span>
                                @endif
                            </span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-full rounded-full bg-primary-500 transition-all" style="width: {{ $width }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

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

                    {{-- NPS：淨推薦值、三族群、0–10 分布與每日趨勢 --}}
                    @if($question['type'] === 'nps' && isset($question['nps']))
                        @php
                            $nps = $question['nps'];
                            $npsScore = $nps['score'];
                            $npsRespondents = $nps['respondents'];
                            $npsDistributionMax = max(collect($question['distribution'] ?? [])->max('count') ?? 0, 1);
                            $npsSegments = [
                                ['key' => 'detractors', 'label' => '貶損者', 'range' => '0–6', 'classes' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300'],
                                ['key' => 'passives', 'label' => '中立者', 'range' => '7–8', 'classes' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'],
                                ['key' => 'promoters', 'label' => '推薦者', 'range' => '9–10', 'classes' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'],
                            ];
                        @endphp

                        <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                            <div class="flex min-h-32 flex-col justify-center rounded-xl bg-gray-950 px-4 py-5 text-white dark:bg-gray-800">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">NPS 分數</p>
                                @if($npsScore !== null)
                                    <p class="mt-1 text-4xl font-bold tabular-nums">
                                        {{ $npsScore > 0 ? '+' : '' }}{{ number_format($npsScore, 1) }}
                                    </p>
                                    <p class="mt-2 text-xs text-gray-400">{{ $npsRespondents }} 位有效填答者</p>
                                @else
                                    <p class="mt-2 text-3xl font-bold text-gray-500">—</p>
                                    <p class="mt-2 text-sm text-gray-400">尚無 NPS 資料</p>
                                @endif
                            </div>

                            <dl class="grid grid-cols-3 gap-2">
                                @foreach($npsSegments as $segment)
                                    <div class="rounded-xl px-2 py-3 text-center {{ $segment['classes'] }}">
                                        <dt class="text-xs font-medium">{{ $segment['label'] }}</dt>
                                        <dd class="mt-1 text-lg font-bold tabular-nums">{{ number_format($nps[$segment['key']]['percentage'], 1) }}%</dd>
                                        <dd class="text-[11px] tabular-nums opacity-75">{{ $nps[$segment['key']]['count'] }} 人 · {{ $segment['range'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>

                        @if($npsRespondents > 0)
                            <div class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" aria-label="NPS 族群比例">
                                <div class="bg-red-500" style="width: {{ $nps['detractors']['percentage'] }}%" title="貶損者 {{ number_format($nps['detractors']['percentage'], 1) }}%"></div>
                                <div class="bg-amber-400" style="width: {{ $nps['passives']['percentage'] }}%" title="中立者 {{ number_format($nps['passives']['percentage'], 1) }}%"></div>
                                <div class="bg-emerald-500" style="width: {{ $nps['promoters']['percentage'] }}%" title="推薦者 {{ number_format($nps['promoters']['percentage'], 1) }}%"></div>
                            </div>
                        @endif

                        <div class="mt-4">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-xs font-medium text-gray-600 dark:text-gray-300">分數分布</p>
                                <p class="text-xs text-gray-400">平均 {{ $question['average'] ?? '—' }}</p>
                            </div>
                            <dl class="grid grid-cols-[repeat(11,minmax(0,1fr))] gap-1">
                                @foreach($question['distribution'] ?? [] as $item)
                                    @php
                                        $barHeight = $item['count'] / $npsDistributionMax * 100;
                                        $barColor = $item['value'] <= 6
                                            ? 'bg-red-400'
                                            : ($item['value'] <= 8 ? 'bg-amber-400' : 'bg-emerald-500');
                                    @endphp
                                    <div class="min-w-0 text-center">
                                        <div class="flex h-12 items-end overflow-hidden rounded-sm bg-gray-100 dark:bg-gray-800">
                                            <div class="w-full {{ $barColor }}" style="height: {{ $barHeight }}%"></div>
                                        </div>
                                        <dt class="mt-1 text-[10px] font-medium tabular-nums text-gray-500">{{ $item['value'] }}</dt>
                                        <dd class="text-[10px] tabular-nums text-gray-400">{{ $item['count'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>

                        @if(!empty($nps['daily']))
                            <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                                <div class="border-b border-gray-200 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-600 dark:border-gray-800 dark:bg-gray-800/60 dark:text-gray-300">
                                    每日趨勢
                                </div>
                                <div class="max-h-44 overflow-y-auto">
                                    <table class="w-full text-xs">
                                        <thead class="sr-only">
                                            <tr>
                                                <th>日期</th>
                                                <th>NPS 分數</th>
                                                <th>有效填答者</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                            @foreach(array_reverse($nps['daily']) as $day)
                                                <tr>
                                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $day['date'] }}</td>
                                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-gray-900 dark:text-white">
                                                        {{ $day['score'] > 0 ? '+' : '' }}{{ number_format($day['score'], 1) }}
                                                    </td>
                                                    <td class="px-3 py-2 text-right tabular-nums text-gray-400">{{ $day['respondents'] }} 人</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                    {{-- 選擇題：選項分布條形 --}}
                    @elseif(!empty($question['distribution']) && isset($question['distribution'][0]['label']))
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
