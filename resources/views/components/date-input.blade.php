{{--
    Date field shown as dd-mm-yyyy, stored as yyyy-mm-dd in the Livewire property.
    <x-date-input label="Order date" wire:model="order_date" required />
    Add :live="true" to send changes immediately (filters).
--}}
@props(['label' => null, 'help' => null, 'required' => false, 'live' => false])

@php
    $model = $attributes->wire('model')->value();
    $id = $attributes->get('id') ?? ($model ? 'f-'.str_replace('.', '-', $model) : 'f-'.uniqid());
    $hasError = $model && $errors->has($model);
@endphp

<x-field :label="$label" :for="$id" :error="$model" :help="$help" :required="$required" :class="$attributes->get('class')">
    <div
        x-data="{
            value: {{ $live ? "\$wire.entangle('$model').live" : "\$wire.entangle('$model')" }},
            text: '',
            init() {
                this.text = this.toDisplay(this.value);
                this.$watch('value', (v) => { if (this.toIso(this.text) !== v) this.text = this.toDisplay(v) });
            },
            toDisplay(v) {
                if (! v || ! /^\d{4}-\d{2}-\d{2}/.test(v)) return v ?? '';
                const [y, m, d] = v.substring(0, 10).split('-');
                return `${d}-${m}-${y}`;
            },
            toIso(t) {
                const m = (t ?? '').trim().match(/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/);
                return m ? `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}` : null;
            },
            commit() {
                const iso = this.toIso(this.text);
                this.value = this.text.trim() === '' ? null : (iso ?? this.text);
            },
            today() {
                const d = new Date();
                this.text = `${String(d.getDate()).padStart(2, '0')}-${String(d.getMonth() + 1).padStart(2, '0')}-${d.getFullYear()}`;
                this.commit();
            },
        }"
        class="relative"
    >
        <input
            type="text"
            id="{{ $id }}"
            x-model="text"
            x-on:blur="commit()"
            x-on:keydown.enter="commit()"
            x-on:keydown.t.prevent="today()"
            x-mask="99-99-9999"
            placeholder="dd-mm-yyyy"
            inputmode="numeric"
            autocomplete="off"
            @if ($required) required @endif
            {{ $attributes->except(['class', 'wire:model', 'wire:model.live'])->class([
                'block w-full rounded-md border-0 bg-white px-3 py-2 text-sm tabular text-zinc-900 shadow-sm ring-1 ring-inset placeholder:text-zinc-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 dark:bg-zinc-900 dark:text-zinc-100',
                'ring-red-500' => $hasError,
                'ring-zinc-300 dark:ring-zinc-700' => ! $hasError,
            ]) }}
        />
    </div>
</x-field>
