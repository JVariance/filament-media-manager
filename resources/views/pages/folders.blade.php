@include('filament-media-manager::partials.drag-and-drop-styles')

<div class="fi-section-content" style="padding: 32px;">
    {{-- Folders at the top level can be dragged into each other --}}
    <div
        style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 1rem;"
        data-top-level-url="{{ \TomatoPHP\FilamentMediaManager\Resources\FolderResource::getUrl('index') }}"
        x-data="@include('filament-media-manager::partials.drag-and-drop')"
    >
        @foreach($records as $item)
            @php
                $folderTargets = $item->model_type ? [] : \TomatoPHP\FilamentMediaManager\Services\FolderMover::targetIds($item);
            @endphp
            <div
                data-media-drop-folder="{{ $item->id }}"
                @if($folderTargets)
                    draggable="true"
                    data-folder-targets="{{ json_encode($folderTargets) }}"
                    x-on:dragstart="startFolder($event, {{ $item->id }})"
                    x-on:dragend="end()"
                @endif
            >
                @livewire(\TomatoPHP\FilamentMediaManager\Livewire\FolderComponent::class, ['item' => $item], key('folder-' . $item->id))
            </div>
        @endforeach
    </div>
</div>
