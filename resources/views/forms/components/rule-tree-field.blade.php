<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        $componentKey = $getKey();
    @endphp

    @vite(['packages/builder-ui-core/src/filament-rule-tree.ts'])

    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            previewResult: '',
            previewLoading: false,
            get valueJson() {
                return typeof this.state === 'string' ? this.state : JSON.stringify(this.state ?? {op:'AND',children:[]});
            },
            onTreeChange(e) {
                const v = e.detail[0] ?? e.detail ?? e;
                this.state = typeof v === 'string' ? JSON.parse(v) : v;
                this.previewResult = '';
            },
            async onTreePreview(e) {
                if (this.previewLoading) return;

                const detail = e.detail[0] ?? e.detail ?? {};
                const path = Array.isArray(detail.path) ? detail.path : [];
                this.previewLoading = true;
                this.previewResult = JSON.stringify({ path, loading: true });

                try {
                    const result = await $wire.callSchemaComponentMethod(
                        @js($componentKey),
                        'previewNode',
                        { ruleTree: this.state, nodePath: path },
                    );
                    this.previewResult = JSON.stringify({ path, result });
                } catch (error) {
                    const message = error?.response?.data?.message ?? error?.message ?? '預覽失敗，請稍後再試。';
                    this.previewResult = JSON.stringify({ path, error: message });
                } finally {
                    this.previewLoading = false;
                }
            },
        }"
        x-init="
            $nextTick(() => {
                const el = $el.querySelector('rule-tree-builder');
                if (el) {
                    el.addEventListener('change', (e) => onTreeChange(e));
                    el.addEventListener('preview', (e) => onTreePreview(e));
                }
            });
        "
    >
        <div
            wire:ignore
            wire:key="rule-tree-builder-{{ md5($field->getAvailableFieldsJson()) }}"
        >
            <rule-tree-builder
                available-fields="{{ $field->getAvailableFieldsJson() }}"
                x-bind:value="valueJson"
                x-bind:preview-enabled="@js($field->hasPreview())"
                x-bind:preview-result="previewResult"
            ></rule-tree-builder>
        </div>
    </div>
</x-dynamic-component>
