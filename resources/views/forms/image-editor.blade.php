@php
    $statePath = $getStatePath();
    $media = $getMedia();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    {{-- Blade swallows the line break after @js(), so the statements after it end with a semicolon --}}
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            cropper: null,
            type: @js($media?->mime_type),
            ratio: null,
            rotation: 0,
            straighten: 0,
            scaleX: 1,
            scaleY: 1,
            dragMode: 'crop',
            size: null,
            error: null,
            isSaving: false,
            isDraggingStraighten: false,
            observer: null,

            async init() {
                if (! document.querySelector('link[data-media-manager-cropper]')) {
                    const link = document.createElement('link')
                    link.rel = 'stylesheet'
                    link.href = @js($getCropperStyleHref());
                    link.dataset.mediaManagerCropper = ''
                    document.head.appendChild(link)
                }

                if (! window.Cropper) {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script')
                        script.src = @js($getCropperScriptSrc());
                        script.onload = resolve
                        script.onerror = reject
                        document.head.appendChild(script)
                    })
                }

                // Cropper.js measures its frame once; while the modal opens, the frame may have no size yet
                await new Promise((resolve) => {
                    const wait = () => this.$refs.frame.offsetWidth ? resolve() : requestAnimationFrame(wait)

                    wait()
                })

                this.cropper = new window.Cropper(this.$refs.image, {
                    viewMode: 1,
                    autoCropArea: 1,
                    dragMode: 'crop',
                    crop: (event) => this.size = { width: Math.round(event.detail.width), height: Math.round(event.detail.height) },
                })

                // It only follows the window by itself, not the modal
                this.observer = new ResizeObserver(() => this.cropper?.resize())
                this.observer.observe(this.$refs.frame)
            },

            destroy() {
                this.observer?.disconnect()
                this.cropper?.destroy()
            },

            // Dragged with the pointer, the slider snaps to 0° near it; with the arrow keys, every step counts
            setStraighten(value) {
                value = Number(value)

                if (this.isDraggingStraighten && (Math.abs(value) < 1)) {
                    value = 0
                }

                this.straighten = value
                this.cropper.rotateTo(this.rotation + value)
            },

            setRatio(ratio) {
                this.ratio = ratio

                const image = this.cropper.getImageData()

                this.cropper.setAspectRatio(ratio === 'original' ? (image.naturalWidth / image.naturalHeight) : (ratio ?? NaN))
            },

            rotate(degrees) {
                this.rotation = (this.rotation + degrees) % 360
                this.cropper.rotateTo(this.rotation + Number(this.straighten))
            },

            flip(axis) {
                if (axis === 'x') {
                    this.scaleX = -this.scaleX
                    this.cropper.scaleX(this.scaleX)
                } else {
                    this.scaleY = -this.scaleY
                    this.cropper.scaleY(this.scaleY)
                }
            },

            setDragMode(mode) {
                this.dragMode = mode
                this.cropper.setDragMode(mode)
            },

            reset() {
                Object.assign(this, { ratio: null, rotation: 0, straighten: 0, scaleX: 1, scaleY: 1 })
                this.cropper.reset()
                this.cropper.setAspectRatio(NaN)
                this.setDragMode('crop')
            },

            // The browser draws the result in the image's type, uploads it and then saves it with the action
            save(mode) {
                if (! this.cropper || this.isSaving) {
                    return
                }

                this.isSaving = true
                this.error = null

                const canvas = this.cropper.getCroppedCanvas({
                    maxWidth: 8192,
                    maxHeight: 8192,
                    fillColor: this.type === 'image/jpeg' ? '#fff' : 'transparent',
                    imageSmoothingQuality: 'high',
                })

                canvas.toBlob((blob) => {
                    if (! blob) {
                        this.fail(@js(trans('filament-media-manager::messages.media.editor.failed')));

                        return
                    }

                    $wire.upload(
                        @js("componentFileAttachments.{$statePath}"),
                        new File([blob], `image.${blob.type.split('/')[1].replace('jpeg', 'jpg')}`, { type: blob.type }),
                        () => {
                            this.state = @js(\TomatoPHP\FilamentMediaManager\Form\ImageEditorField::UPLOADED);
                            $wire.callMountedAction({ mode }).finally(() => this.isSaving = false)
                        },
                        () => this.fail(@js(trans('filament-media-manager::messages.media.editor.upload_failed'))),
                    )
                }, this.type, 0.92)
            },

            fail(message) {
                this.error = message
                this.isSaving = false
            },
        }"
        style="display: grid; gap: 0.75rem;"
    >
        {{-- Cropper.js replaces the image with its own elements, which Livewire must leave alone --}}
        <div x-ref="frame" wire:ignore style="height: 60vh; overflow: hidden; border-radius: 0.5rem; background-color: var(--gray-100);">
            <img x-ref="image" src="{{ $getSourceUrl() }}" alt="" style="display: block; max-width: 100%;" />
        </div>

        {{-- The active ratio and tool get an outline and the primary color; the gaps leave room for the outline --}}
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1rem;">
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                {{-- @js() is not compiled in the attributes of a Blade component, {{ }} is --}}
                @foreach ($getAspectRatios() as $label => $ratio)
                    <x-filament::button
                        type="button"
                        size="xs"
                        color="gray"
                        x-on:click="setRatio({{ \Illuminate\Support\Js::from($ratio) }})"
                        x-bind:style="(ratio === {{ \Illuminate\Support\Js::from($ratio) }}) && 'outline: 2px solid var(--primary-500); color: var(--primary-500)'"
                    >
                        {{ $label }}
                    </x-filament::button>
                @endforeach
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <x-filament::icon-button type="button" color="gray" icon="heroicon-m-arrow-uturn-left" :label="trans('filament-media-manager::messages.media.editor.rotate_left')" :tooltip="trans('filament-media-manager::messages.media.editor.rotate_left')" x-on:click="rotate(-90)" />
                <x-filament::icon-button type="button" color="gray" icon="heroicon-m-arrow-uturn-right" :label="trans('filament-media-manager::messages.media.editor.rotate_right')" :tooltip="trans('filament-media-manager::messages.media.editor.rotate_right')" x-on:click="rotate(90)" />
                <x-filament::icon-button type="button" color="gray" icon="heroicon-m-arrows-right-left" :label="trans('filament-media-manager::messages.media.editor.flip_horizontal')" :tooltip="trans('filament-media-manager::messages.media.editor.flip_horizontal')" x-on:click="flip('x')" />
                <x-filament::icon-button type="button" color="gray" icon="heroicon-m-arrows-up-down" :label="trans('filament-media-manager::messages.media.editor.flip_vertical')" :tooltip="trans('filament-media-manager::messages.media.editor.flip_vertical')" x-on:click="flip('y')" />
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <x-filament::icon-button type="button" color="gray" icon="heroicon-m-magnifying-glass-plus" :label="trans('filament-media-manager::messages.media.editor.zoom_in')" :tooltip="trans('filament-media-manager::messages.media.editor.zoom_in')" x-on:click="cropper.zoom(0.1)" />
                <x-filament::icon-button type="button" color="gray" icon="heroicon-m-magnifying-glass-minus" :label="trans('filament-media-manager::messages.media.editor.zoom_out')" :tooltip="trans('filament-media-manager::messages.media.editor.zoom_out')" x-on:click="cropper.zoom(-0.1)" />
                <x-filament::icon-button type="button" color="gray" icon="heroicon-m-scissors" :label="trans('filament-media-manager::messages.media.editor.crop')" :tooltip="trans('filament-media-manager::messages.media.editor.crop')" x-on:click="setDragMode('crop')" x-bind:style="(dragMode === 'crop') && 'outline: 2px solid var(--primary-500); color: var(--primary-500)'" />
                <x-filament::icon-button type="button" color="gray" icon="heroicon-m-hand-raised" :label="trans('filament-media-manager::messages.media.editor.move')" :tooltip="trans('filament-media-manager::messages.media.editor.move')" x-on:click="setDragMode('move')" x-bind:style="(dragMode === 'move') && 'outline: 2px solid var(--primary-500); color: var(--primary-500)'" />
                <x-filament::icon-button type="button" color="gray" icon="heroicon-m-arrow-path" :label="trans('filament-media-manager::messages.media.editor.reset')" :tooltip="trans('filament-media-manager::messages.media.editor.reset')" x-on:click="reset()" />
            </div>

            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: var(--gray-500);">
                {{ trans('filament-media-manager::messages.media.editor.straighten') }}
                <input
                    type="range"
                    min="-45"
                    max="45"
                    step="0.5"
                    x-bind:value="straighten"
                    x-on:pointerdown="isDraggingStraighten = true"
                    x-on:pointerup.window="isDraggingStraighten = false"
                    x-on:input="setStraighten($event.target.value)"
                    style="width: 10rem; accent-color: var(--primary-500);"
                />
                <button
                    type="button"
                    x-text="`${straighten}°`"
                    x-on:click="setStraighten(0)"
                    title="0°"
                    style="min-width: 3rem; text-align: start; cursor: pointer;"
                ></button>
            </label>

            <span x-show="size" x-text="size && `${size.width} × ${size.height} px`" style="font-size: 0.875rem; color: var(--gray-500);"></span>
        </div>

        <p x-show="error" x-cloak x-text="error" style="font-size: 0.875rem; color: var(--danger-600);"></p>

        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
            <x-filament::button type="button" x-on:click="save('replace')" x-bind:disabled="isSaving">
                {{ trans('filament-media-manager::messages.media.editor.save') }}
            </x-filament::button>

            <x-filament::button type="button" color="gray" x-on:click="save('copy')" x-bind:disabled="isSaving">
                {{ trans('filament-media-manager::messages.media.editor.save_copy') }}
            </x-filament::button>

            <x-filament::loading-indicator x-show="isSaving" x-cloak style="width: 1.25rem; height: 1.25rem;" />
        </div>
    </div>
</x-dynamic-component>
