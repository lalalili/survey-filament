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
                        <div class="survey-guide-step-card space-y-2 rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/15">
                            <div class="flex items-center gap-2">
                                <span class="survey-guide-step-badge flex h-7 w-7 items-center justify-center rounded-full bg-primary-600 text-xs font-semibold text-white dark:bg-primary-500 dark:text-white">{{ $step['label'] }}</span>
                                <h3 class="survey-guide-step-title text-sm font-semibold text-gray-950 dark:text-gray-50">{{ $step['title'] }}</h3>
                            </div>
                            <p class="survey-guide-step-body text-sm leading-6 text-gray-600 dark:text-gray-200">{{ $step['body'] }}</p>
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
                            @php($type = $block['type'] ?? 'list')

                            @if ($type === 'table')
                                <div class="space-y-3 md:col-span-2">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $block['heading'] }}</h3>
                                    <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/10 dark:ring-white/10">
                                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                                            <thead class="bg-gray-50 dark:bg-white/5">
                                                <tr>
                                                    @foreach ($block['headers'] ?? [] as $header)
                                                        <th scope="col" class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">{{ $header }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/10 dark:bg-gray-900">
                                                @foreach ($block['rows'] ?? [] as $row)
                                                    <tr>
                                                        @foreach ($row as $cell)
                                                            <td class="px-4 py-3 align-top text-gray-600 dark:text-gray-300">{{ $cell }}</td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @elseif ($type === 'screenshot')
                                <div class="survey-guide-screenshot-block overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/15 md:col-span-2">
                                    <div class="grid gap-0 lg:grid-cols-[minmax(0,1.35fr)_minmax(280px,0.65fr)]">
                                        <div class="survey-guide-screenshot-stage bg-white p-4 dark:bg-gray-950">
                                            @php($variant = $block['variant'] ?? 'generic')

                                            <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-100 shadow-sm dark:border-white/15 dark:bg-gray-900">
                                                @if ($variant === 'cascade-data')
                                                    <div class="border-b border-gray-200 bg-white px-4 py-3 dark:border-white/15 dark:bg-gray-800">
                                                        <div class="flex items-center justify-between gap-3">
                                                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">編輯巢狀選擇題資料</div>
                                                            <div class="h-5 w-5 rounded-full border border-gray-300 dark:border-gray-500"></div>
                                                        </div>
                                                    </div>
                                                    <div class="space-y-3 p-4">
                                                        <div class="flex flex-wrap gap-2">
                                                            <span class="rounded-full bg-primary-100 px-3 py-1 text-xs font-medium text-primary-700 dark:bg-primary-500/15 dark:text-primary-200">1 縣市</span>
                                                            <span class="rounded-full bg-primary-100 px-3 py-1 text-xs font-medium text-primary-700 dark:bg-primary-500/15 dark:text-primary-200">2 鄉鎮區</span>
                                                            <span class="rounded-md bg-white px-3 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/15">+ 層級</span>
                                                        </div>
                                                        <div class="survey-guide-note rounded-lg bg-primary-50 p-3 text-xs text-primary-700 ring-1 ring-primary-200 dark:bg-gray-800 dark:text-primary-100 dark:ring-primary-300/30">
                                                            快速套用：臺灣縣市鄉鎮區
                                                        </div>
                                                        <div class="rounded-lg bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-white/15">
                                                            <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                                <span>XLSX 格式，每列代表一條完整路徑</span>
                                                                <span class="rounded-md bg-white px-2 py-1 text-gray-700 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/15">下載範例檔</span>
                                                                <span class="rounded-md bg-white px-2 py-1 text-gray-700 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/15">上傳資料</span>
                                                            </div>
                                                            <div class="space-y-2">
                                                                <div class="rounded-md bg-gray-50 p-2 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/15">
                                                                    <div class="text-xs font-medium text-gray-900 dark:text-gray-100">台北市</div>
                                                                    <div class="ms-4 mt-2 grid gap-1 text-xs text-gray-600 dark:text-gray-300">
                                                                        <span class="rounded bg-white px-2 py-1 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-white/15">中正區</span>
                                                                        <span class="rounded bg-white px-2 py-1 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-white/15">大安區</span>
                                                                    </div>
                                                                </div>
                                                                <div class="rounded-md bg-gray-50 p-2 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/15">
                                                                    <div class="text-xs font-medium text-gray-900 dark:text-gray-100">新北市</div>
                                                                    <div class="ms-4 mt-2 grid gap-1 text-xs text-gray-600 dark:text-gray-300">
                                                                        <span class="rounded bg-white px-2 py-1 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-white/15">板橋區</span>
                                                                        <span class="rounded bg-white px-2 py-1 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-white/15">新莊區</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @elseif ($variant === 'calculation-score')
                                                    <div class="grid gap-3 p-4 md:grid-cols-[0.95fr_1.05fr]">
                                                        <div class="space-y-3 rounded-lg bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-white/15">
                                                            <div class="text-xs font-semibold text-gray-900 dark:text-gray-100">頁面屬性</div>
                                                            <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-900">
                                                                <div class="text-xs font-medium text-gray-700 dark:text-gray-300">問卷計算變數</div>
                                                                <div class="mt-2 flex items-center gap-2 rounded-md bg-white px-3 py-2 text-xs text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/15">
                                                                    <span class="font-mono">total_score</span>
                                                                    <span class="ms-auto text-gray-400 dark:text-gray-500">總分</span>
                                                                </div>
                                                                <div class="mt-2 text-xs text-primary-700 dark:text-primary-200">+ 新增計算變數</div>
                                                            </div>
                                                        </div>
                                                        <div class="space-y-3 rounded-lg bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-white/15">
                                                            <div class="text-xs font-semibold text-gray-900 dark:text-gray-100">選項分數設定</div>
                                                            <div class="space-y-2">
                                                                @foreach ([['選項 1', '+10'], ['選項 2', '0'], ['選項 3', '-5']] as $scoreRow)
                                                                    <div class="grid grid-cols-[1fr_90px] items-center gap-2">
                                                                        <div class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-700 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/15">{{ $scoreRow[0] }}</div>
                                                                        <div class="rounded-md bg-white px-3 py-2 text-right text-xs font-semibold text-gray-900 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-100 dark:ring-white/15">{{ $scoreRow[1] }}</div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                            <div class="survey-guide-note rounded-md bg-primary-50 px-3 py-2 text-xs text-primary-700 ring-1 ring-primary-200 dark:bg-gray-800 dark:text-primary-100 dark:ring-primary-300/30">填答者選到選項後，送出時寫入計算結果。</div>
                                                        </div>
                                                    </div>
                                                @elseif ($variant === 'logic-rules')
                                                    <div class="grid gap-3 p-4 md:grid-cols-2">
                                                        <div class="space-y-3 rounded-lg bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-white/15">
                                                            <div class="text-xs font-semibold text-gray-900 dark:text-gray-100">顯示條件</div>
                                                            <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-900">
                                                                <div class="mb-2 text-xs text-gray-500 dark:text-gray-400">符合以下條件才顯示此題</div>
                                                                <div class="space-y-2">
                                                                    <div class="rounded-md bg-white px-3 py-2 text-xs text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/15">第 1 題 等於 選項 1</div>
                                                                    <div class="rounded-md bg-white px-3 py-2 text-xs text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/15">AND 第 2 題 包含 服務</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="space-y-3 rounded-lg bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-white/15">
                                                            <div class="text-xs font-semibold text-gray-900 dark:text-gray-100">跳題邏輯</div>
                                                            <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-900">
                                                                <div class="mb-2 text-xs text-gray-500 dark:text-gray-400">填答後決定下一步</div>
                                                                <div class="space-y-2">
                                                                    <div class="rounded-md bg-white px-3 py-2 text-xs text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/15">選項 1 → 前往 P02</div>
                                                                    <div class="rounded-md bg-white px-3 py-2 text-xs text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/15">選項 2 → 顯示感謝頁</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @elseif ($variant === 'publish-errors')
                                                    <div class="space-y-3 p-4">
                                                        <div class="survey-guide-error-box rounded-lg bg-red-50 p-3 ring-1 ring-red-200 dark:bg-red-950 dark:ring-red-300/30">
                                                            <div class="survey-guide-error-title text-xs font-semibold text-red-700 dark:text-red-100">發佈失敗，請修正以下 2 個問題</div>
                                                            <div class="mt-2 space-y-2">
                                                                <div class="survey-guide-error-row rounded-md bg-white px-3 py-2 text-xs text-red-700 ring-1 ring-red-200 dark:bg-gray-900 dark:text-red-100 dark:ring-red-300/30">
                                                                    <span class="font-semibold">第 1 頁</span>
                                                                    <span class="survey-guide-error-tag ms-2 rounded bg-red-100 px-2 py-0.5 dark:bg-red-300/15">層級設定</span>
                                                                    巢狀選擇題至少需要一個層級。
                                                                </div>
                                                                <div class="survey-guide-error-row rounded-md bg-white px-3 py-2 text-xs text-red-700 ring-1 ring-red-200 dark:bg-gray-900 dark:text-red-100 dark:ring-red-300/30">
                                                                    <span class="font-semibold">第 1 頁</span>
                                                                    <span class="survey-guide-error-tag ms-2 rounded bg-red-100 px-2 py-0.5 dark:bg-red-300/15">選項資料</span>
                                                                    巢狀選擇題至少需要一個第一層選項。
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="grid gap-2 md:grid-cols-3">
                                                            <span class="rounded-md bg-white px-3 py-2 text-xs text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/15">定位頁面</span>
                                                            <span class="rounded-md bg-white px-3 py-2 text-xs text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/15">修正屬性</span>
                                                            <span class="rounded-md bg-white px-3 py-2 text-xs text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/15">重新發佈</span>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="p-4 text-sm text-gray-500 dark:text-gray-400">此操作截圖尚未設定。</div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="survey-guide-screenshot-copy space-y-3 bg-white p-4 dark:bg-gray-800">
                                            <h3 class="survey-guide-screenshot-heading text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $block['heading'] }}</h3>
                                            @if (! empty($block['body']))
                                                <p class="survey-guide-screenshot-body text-sm leading-6 text-gray-600 dark:text-gray-200">{{ $block['body'] }}</p>
                                            @endif
                                            <ol class="survey-guide-screenshot-steps space-y-2 text-sm leading-6 text-gray-600 dark:text-gray-200">
                                                @foreach ($block['steps'] ?? [] as $index => $step)
                                                    <li class="flex gap-2">
                                                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-semibold text-white dark:bg-primary-500">{{ $index + 1 }}</span>
                                                        <span>{{ $step }}</span>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            @elseif ($type === 'callout')
                                <div class="survey-guide-callout space-y-2 rounded-lg bg-primary-50 p-4 ring-1 ring-primary-500/20 dark:bg-gray-800 dark:ring-white/15 md:col-span-2">
                                    <h3 class="survey-guide-callout-heading text-sm font-semibold text-primary-900 dark:text-gray-50">{{ $block['heading'] }}</h3>
                                    <ul class="survey-guide-callout-list list-disc space-y-1 ps-5 text-sm leading-6 text-primary-800 dark:text-gray-200">
                                        @foreach ($block['items'] ?? [] as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @else
                                <div class="space-y-2">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $block['heading'] }}</h3>
                                    <ul class="list-disc space-y-1 ps-5 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                        @foreach ($block['items'] ?? [] as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
