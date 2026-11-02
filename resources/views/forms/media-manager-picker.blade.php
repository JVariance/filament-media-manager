@php
    $statePath = $getStatePath();
    $isMultiple = $isMultiple();
    $state = $getState();
    $mediaItems = [];

    if ($state) {
        $uuids = is_array($state) ? $state : [$state];
        // Without the folder of the media page in the session, which would hide media of other folders
        $media = \TomatoPHP\FilamentMediaManager\Models\Media::withoutGlobalScope('folder')->whereIn('uuid', $uuids)->get()->keyBy('uuid');

        // Order media items according to state array order. A medium deleted from the library since it was picked
        // stays as null, so that it can be seen and removed, and the items keep the positions of the state for sorting
        foreach ($uuids as $uuid) {
            $mediaItems[$uuid] = $media->get($uuid);
        }
    }
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <style>
        /* Tiles in as many columns as fit, so that a gallery doesn't take a row per image */
        .fi-picker-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr));
            gap: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgb(0 0 0 / 0.1);
            background-color: white;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            margin-top: 1rem;
            padding: 0.75rem;
        }

        .fi-picker-grid:where(.dark,.dark *) {
            border-color: rgb(255 255 255 / 0.1);
            background-color: var(--gray-950);
        }

        .fi-picker-grid-item {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
            border-radius: 0.5rem;
            border: 1px solid rgb(0 0 0 / 0.1);
            background-color: white;
            transition: box-shadow 0.15s;
        }

        .fi-picker-grid-item:where(.dark,.dark *) {
            border-color: rgb(255 255 255 / 0.1);
            background-color: var(--gray-900);
        }

        .fi-picker-grid-item:hover {
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .fi-picker-preview-thumb {
            display: flex;
            aspect-ratio: 1;
            width: 100%;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: rgb(249 250 251);
        }

        .fi-picker-preview-thumb:where(.dark,.dark *) {
            background-color: var(--gray-800);
        }

        .fi-picker-preview-thumb img {
            height: 100%;
            width: 100%;
            object-fit: cover;
        }

        .fi-picker-drag-handle {
            cursor: grab;
        }

        .fi-picker-icon {
            height: 2.5rem;
            width: 2.5rem;
            color: rgb(107 114 128);
        }

        .fi-picker-icon:where(.dark,.dark *) {
            color: rgb(156 163 175);
        }

        .fi-picker-icon-danger {
            height: 2.5rem;
            width: 2.5rem;
            color: rgb(239 68 68);
        }

        .fi-picker-file-info {
            min-width: 0;
            padding: 0.5rem;
        }

        .fi-picker-file-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.75rem;
            font-weight: 600;
            color: rgb(17 24 39);
        }

        .fi-picker-file-name:where(.dark,.dark *) {
            color: white;
        }

        .fi-picker-file-meta {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.75rem;
            color: rgb(107 114 128);
        }

        .fi-picker-file-meta:where(.dark,.dark *) {
            color: rgb(156 163 175);
        }

        /* On the image, with a background so that it stands out on light and dark images alike */
        .fi-picker-remove {
            position: absolute;
            top: 0.25rem;
            inset-inline-end: 0.25rem;
            border-radius: 9999px;
            background-color: rgb(255 255 255 / 0.9);
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.15);
        }

        .fi-picker-remove:where(.dark,.dark *) {
            background-color: rgb(17 24 39 / 0.85);
        }

        .fi-picker-empty-state-container {
            overflow: hidden;
            border-radius: 0.5rem;
            border: 1px solid rgb(0 0 0 / 0.1);
            background-color: white;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            margin-top: 1rem;
        }

        .fi-picker-empty-state-container:where(.dark,.dark *) {
            border-color: rgb(255 255 255 / 0.1);
            background-color: var(--gray-950);
        }

        .sortable-ghost {
            opacity: 0.4;
        }
    </style>

    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            pickerKey: '{{ $getId() }}',
            isMultiple: {{ $isMultiple ? 'true' : 'false' }},
            sortableInstance: null,

            initSortable() {
                if (!this.isMultiple) return;

                // Destroy existing instance
                if (this.sortableInstance) {
                    this.sortableInstance.destroy();
                    this.sortableInstance = null;
                }

                const container = this.$refs.mediaList;
                if (!container || !window.Sortable) return;

                // Only init if we have items
                const items = container.querySelectorAll('.fi-picker-grid-item');
                if (items.length <= 1) return;

                this.sortableInstance = window.Sortable.create(container, {
                    animation: 150,
                    handle: '.fi-picker-drag-handle',
                    ghostClass: 'sortable-ghost',
                    onEnd: (evt) => {
                        // Get current state
                        const items = Array.isArray(this.state) ? [...this.state] : [this.state];

                        // Move the item
                        const [movedItem] = items.splice(evt.oldIndex, 1);
                        items.splice(evt.newIndex, 0, movedItem);

                        // Update state - this will trigger Livewire to re-render
                        this.state = items;
                    }
                });
            },

            destroySortable() {
                if (this.sortableInstance) {
                    this.sortableInstance.destroy();
                    this.sortableInstance = null;
                }
            }
        }"
        x-init="
            let isProcessing = false;

            // Listen for media selection events
            window.addEventListener('media-selected-' + pickerKey, (event) => {
                if (isProcessing) return;
                isProcessing = true;

                const newState = event.detail?.media || event.detail;
                state = newState;

                $wire.set('{{ $statePath }}', newState).then(() => {
                    $wire.call('unmountAction', 'getBrowseAction');

                    setTimeout(() => {
                        isProcessing = false;
                    }, 300);
                });
            });

            // Watch state changes and reinit sortable
            $watch('state', () => {
                $nextTick(() => initSortable());
            });

            // Initialize on mount
            $nextTick(() => initSortable());
        "
        x-on:destroy="destroySortable()"
        class="space-y-3"
    >
        {{-- Preview Section --}}
        @if(!empty($mediaItems) && count($mediaItems) > 0)
            <div class="fi-picker-grid" x-ref="mediaList">
                @foreach($mediaItems as $uuid => $media)
                    <div class="fi-picker-grid-item" data-uuid="{{ $uuid }}" wire:key="media-{{ $uuid }}">
                        {{-- Preview, by which the tile is dragged when there are several --}}
                        <div @class([
                            'fi-picker-preview-thumb',
                            'fi-picker-drag-handle' => $isMultiple && count($mediaItems) > 1,
                        ])>
                            @if(! $media)
                                <x-filament::icon
                                    icon="heroicon-o-exclamation-triangle"
                                    class="fi-picker-icon-danger"
                                />
                            @elseif(str_starts_with($media->mime_type, 'image/'))
                                <img
                                    src="{{ $media->getUrl() }}"
                                    alt="{{ $media->name }}"
                                    loading="lazy"
                                    draggable="false"
                                />
                            @elseif(str($media->mime_type)->contains('video'))
                                <x-filament::icon
                                    icon="heroicon-o-video-camera"
                                    class="fi-picker-icon"
                                />
                            @elseif(str($media->mime_type)->contains('audio'))
                                <x-filament::icon
                                    icon="heroicon-o-musical-note"
                                    class="fi-picker-icon"
                                />
                            @elseif(str($media->mime_type)->contains('pdf'))
                                <x-filament::icon
                                    icon="heroicon-o-document-text"
                                    class="fi-picker-icon-danger"
                                />
                            @else
                                <x-filament::icon
                                    icon="heroicon-o-document"
                                    class="fi-picker-icon"
                                />
                            @endif
                        </div>

                        {{-- File Info, in full in the titles --}}
                        <div class="fi-picker-file-info">
                            @if(! $media)
                                <p class="fi-picker-file-name">
                                    {{ trans('filament-media-manager::messages.picker.missing') }}
                                </p>
                                <p class="fi-picker-file-meta">
                                    {{ trans('filament-media-manager::messages.picker.missing_description') }}
                                </p>
                            @else
                                <p class="fi-picker-file-name" title="{{ $media->file_name }}">
                                    {{ $media->name }}
                                </p>
                                <p class="fi-picker-file-meta" title="{{ $media->getCustomProperty('description') }}">
                                    {{ number_format($media->size / 1024, 2) }} KB
                                </p>
                            @endif
                        </div>

                        {{-- Remove Button --}}
                        <div class="fi-picker-remove">
                            {{ $getAction('removeMediaItem')->arguments(['uuid' => $uuid]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="fi-picker-empty-state-container">
                <div class="fi-ta-empty-state px-6 py-12">
                    <div class="fi-ta-empty-state-content mx-auto grid max-w-lg justify-items-center text-center">
                        <div class="fi-ta-empty-state-icon-ctn mb-4 rounded-full bg-gray-100 p-3 dark:bg-gray-500/20">
                            <x-filament::icon
                                icon="heroicon-o-photo"
                                class="fi-ta-empty-state-icon h-6 w-6 text-gray-500 dark:text-gray-400"
                            />
                        </div>
                        <h4 class="fi-ta-empty-state-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            {{ trans('filament-media-manager::messages.picker.no_media_selected') }}
                        </h4>
                        <p class="fi-ta-empty-state-description mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Click "Browse Media" to select files') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Actions --}}
        <div class="flex flex-wrap items-center gap-2" style="margin-top: 1rem;">
            {{ $getAction('getBrowseAction') }}

            @if(!empty($mediaItems) && count($mediaItems) > 0)
                {{ $getAction('getRemoveAction') }}
            @endif
        </div>
    </div>
</x-dynamic-component>
