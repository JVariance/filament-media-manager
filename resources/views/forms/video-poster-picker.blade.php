@php
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        {{-- Blade swallows the line break after @js(), so the statements after it end with a semicolon --}}
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            error: null,
            preview: null,
            isUploading: false,

            // The frame on screen; the video comes from the panel's origin, so it can be drawn on a canvas
            capture() {
                const video = this.$refs.video

                if (! video.videoWidth) {
                    this.error = @js(trans('filament-media-manager::messages.media.poster.not_ready'));

                    return
                }

                try {
                    this.send(this.draw(video, video.videoWidth, video.videoHeight))
                } catch (error) {
                    this.error = @js(trans('filament-media-manager::messages.media.poster.capture_failed'));
                }
            },

            upload(event) {
                const file = event.target.files[0]
                event.target.value = ''

                if (! file) {
                    return
                }

                const image = new Image()

                image.onload = () => {
                    this.send(this.draw(image, image.naturalWidth, image.naturalHeight))
                    URL.revokeObjectURL(image.src)
                }

                image.onerror = () => {
                    this.error = @js(trans('filament-media-manager::messages.media.poster.invalid'));
                    URL.revokeObjectURL(image.src)
                }

                image.src = URL.createObjectURL(file)
            },

            // At most 1920 pixels wide
            draw(source, width, height) {
                const scale = Math.min(1, 1920 / width)
                const canvas = document.createElement('canvas')

                canvas.width = Math.round(width * scale)
                canvas.height = Math.round(height * scale)
                canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height)

                // A canvas with a video from another origin can't be read; this throws before anything is sent
                canvas.toDataURL('image/jpeg', 0.1)

                return canvas
            },

            // As a file upload: a frame can be larger than a Livewire request may be
            send(canvas) {
                canvas.toBlob((blob) => {
                    this.error = null
                    this.preview = URL.createObjectURL(blob)
                    this.isUploading = true

                    $wire.upload(
                        @js("componentFileAttachments.{$statePath}"),
                        new File([blob], 'poster.jpg', { type: 'image/jpeg' }),
                        () => {
                            this.isUploading = false
                            this.state = @js(\TomatoPHP\FilamentMediaManager\Form\VideoPosterPicker::UPLOADED);
                        },
                        () => {
                            this.isUploading = false
                            this.preview = null
                            this.error = @js(trans('filament-media-manager::messages.media.poster.upload_failed'));
                        },
                    )
                }, 'image/jpeg', 0.85)
            },

            // The image shown: a new one, or the current poster
            shown() {
                return this.preview ?? ((this.state !== @js(\TomatoPHP\FilamentMediaManager\Form\VideoPosterPicker::UPLOADED)) ? this.state : null)
            },

            remove() {
                this.state = null
                this.preview = null
            },
        }"
        style="display: grid; gap: 0.75rem;"
    >
        <video
            x-ref="video"
            src="{{ $getSourceUrl() }}"
            controls
            playsinline
            preload="metadata"
            style="width: 100%; max-height: 16rem; border-radius: 0.5rem; background-color: rgb(0 0 0);"
        ></video>

        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
            <x-filament::button type="button" size="sm" color="gray" icon="heroicon-m-camera" x-on:click="capture()">
                {{ trans('filament-media-manager::messages.media.poster.capture') }}
            </x-filament::button>

            <x-filament::button type="button" size="sm" color="gray" icon="heroicon-m-arrow-up-tray" x-on:click="$refs.file.click()">
                {{ trans('filament-media-manager::messages.media.poster.upload') }}
            </x-filament::button>

            <input x-ref="file" type="file" accept="image/jpeg,image/png,image/webp" x-on:change="upload($event)" style="display: none;" />

            <x-filament::link tag="button" type="button" size="sm" color="danger" x-show="shown()" x-cloak x-on:click="remove()">
                {{ trans('filament-media-manager::messages.media.poster.remove') }}
            </x-filament::link>
        </div>

        <p x-show="error" x-cloak x-text="error" style="font-size: 0.875rem; color: var(--danger-600);"></p>

        <template x-if="shown()">
            <img x-bind:src="shown()" x-bind:style="isUploading && 'opacity: 0.5'" alt="" style="max-height: 10rem; max-width: 100%; justify-self: start; border-radius: 0.5rem;" />
        </template>

        <p x-show="! shown()" style="font-size: 0.875rem; color: var(--gray-500);">
            {{ trans('filament-media-manager::messages.media.poster.none') }}
        </p>
    </div>
</x-dynamic-component>
