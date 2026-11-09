{{--
    Notifications. From a Livewire component:  $this->dispatch('toast', message: 'Saved.');
                                                $this->dispatch('toast', type: 'error', message: 'Failed.');
    Before a redirect:                           session()->flash('toast', 'Saved.');
--}}
<div
    x-data="{
        toasts: [],
        add(detail) {
            const toast = { id: Date.now() + Math.random(), type: detail.type ?? 'success', message: detail.message ?? detail };
            this.toasts.push(toast);
            setTimeout(() => this.remove(toast.id), toast.type === 'error' ? 6000 : 3500);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id) },
    }"
    x-init="@if (session('toast')) add({ message: @js(session('toast')) }) @endif"
    x-on:toast.window="add($event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg bg-raised p-3 text-sm shadow-lg ring-1 ring-line-strong/40"
        >
            <span x-show="toast.type !== 'error'" class="text-success"><x-icon name="check" class="size-5" /></span>
            <span x-show="toast.type === 'error'" class="text-danger"><x-icon name="exclamation" class="size-5" /></span>
            <p class="flex-1 text-fg" x-text="toast.message"></p>
            <button type="button" x-on:click="remove(toast.id)" class="text-subtle hover:text-fg">
                <x-icon name="x" class="size-4" /><span class="sr-only">Dismiss</span>
            </button>
        </div>
    </template>
</div>
