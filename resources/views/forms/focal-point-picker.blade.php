@php
    $statePath = $getStatePath();
    $imageUrl = $getImageUrl();
    $videoUrl = $getVideoUrl();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},

            set(event) {
                const rect = this.$refs.picture.getBoundingClientRect()
                const clamp = (value) => Math.min(100, Math.max(0, Math.round(value)))

                this.state = {
                    x: clamp(((event.clientX - rect.left) / rect.width) * 100),
                    y: clamp(((event.clientY - rect.top) / rect.height) * 100),
                }
            },
        }"
        style="display: grid; gap: 0.5rem;"
    >
        <div
            x-on:click="set($event)"
            style="position: relative; justify-self: start; max-width: 100%; overflow: hidden; border-radius: 0.5rem; cursor: crosshair;"
        >
            @if ($imageUrl)
                <img x-ref="picture" src="{{ $imageUrl }}" alt="" draggable="false" style="display: block; max-width: 100%; max-height: 20rem; user-select: none;" />
            @elseif ($videoUrl)
                <video x-ref="picture" src="{{ $videoUrl }}" muted playsinline preload="metadata" style="display: block; max-width: 100%; max-height: 20rem;"></video>
            @endif

            {{-- Without a focal point, the center counts --}}
            <span
                x-bind:style="`left: ${state?.x ?? 50}%; top: ${state?.y ?? 50}%; background-color: ${state ? 'var(--primary-500)' : 'rgb(255 255 255 / 0.4)'}`"
                style="pointer-events: none; position: absolute; width: 1.25rem; height: 1.25rem; transform: translate(-50%, -50%); border: 2px solid white; border-radius: 9999px; box-shadow: 0 0 0 1px rgb(0 0 0 / 0.5), 0 1px 4px rgb(0 0 0 / 0.5);"
            ></span>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.875rem; color: var(--gray-500);">
            <span x-text="state ? `${state.x} % / ${state.y} %` : @js(trans('filament-media-manager::messages.media.focal.center'))"></span>

            <x-filament::link tag="button" type="button" size="sm" color="gray" x-show="state" x-cloak x-on:click="state = null">
                {{ trans('filament-media-manager::messages.media.focal.reset') }}
            </x-filament::link>
        </div>
    </div>
</x-dynamic-component>
