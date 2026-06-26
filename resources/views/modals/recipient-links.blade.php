<div class="space-y-3">
    @forelse ($links as $link)
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-medium text-gray-950 dark:text-white">
                    {{ $link['channel'] ?? '未知通道' }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    產生時間：{{ $link['created_at']?->format('Y/m/d H:i') ?? '—' }}
                </span>
            </div>

            <div class="mt-2 flex items-center gap-2" x-data="{ copied: false }">
                <input
                    type="text"
                    readonly
                    value="{{ $link['url'] }}"
                    class="w-full truncate rounded-md border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300"
                    x-on:focus="$el.select()"
                />
                <button
                    type="button"
                    x-on:click="navigator.clipboard.writeText('{{ $link['url'] }}'); copied = true; setTimeout(() => copied = false, 1500)"
                    class="shrink-0 rounded-md bg-primary-600 px-3 py-1 text-xs font-medium text-white hover:bg-primary-500"
                >
                    <span x-show="! copied">複製</span>
                    <span x-show="copied" x-cloak>已複製</span>
                </button>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">此收件人目前沒有啟用中的連結。</p>
    @endforelse
</div>
