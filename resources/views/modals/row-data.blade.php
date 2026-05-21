<div class="divide-y divide-gray-100 dark:divide-white/10">
    @forelse ($data as $key => $value)
        <div class="grid grid-cols-5 gap-x-4 py-2 text-sm">
            <dt class="col-span-2 font-medium text-gray-500 dark:text-gray-400 break-all">{{ $key }}</dt>
            <dd class="col-span-3 text-gray-900 dark:text-white break-all">{{ $value !== null && $value !== '' ? $value : '—' }}</dd>
        </div>
    @empty
        <p class="py-4 text-sm text-gray-500 dark:text-gray-400">無資料</p>
    @endforelse
</div>
