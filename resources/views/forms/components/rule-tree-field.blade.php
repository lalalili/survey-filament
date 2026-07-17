<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @vite(['packages/builder-ui-core/src/filament-rule-tree.ts'])

    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            get valueJson() {
                return typeof this.state === 'string' ? this.state : JSON.stringify(this.state ?? {op:'AND',children:[]});
            },
            onTreeChange(e) {
                const v = e.detail[0] ?? e.detail ?? e;
                this.state = typeof v === 'string' ? JSON.parse(v) : v;
            },
        }"
        x-init="
            $nextTick(() => {
                const el = $el.querySelector('rule-tree-builder');
                if (el) {
                    el.addEventListener('change', (e) => onTreeChange(e));
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
            ></rule-tree-builder>
        </div>
    </div>
</x-dynamic-component>
