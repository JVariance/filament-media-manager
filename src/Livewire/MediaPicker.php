<?php

namespace TomatoPHP\FilamentMediaManager\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Models\Media;
use TomatoPHP\FilamentMediaManager\Resources\Actions\EditCurrentFolderAction;
use TomatoPHP\FilamentMediaManager\Resources\Schemas\FolderForm;
use TomatoPHP\FilamentMediaManager\Services\FolderBreadcrumbs;
use TomatoPHP\FilamentMediaManager\Services\FolderLocks;
use TomatoPHP\FilamentMediaManager\Services\WebSafeFileName;

class MediaPicker extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?int $currentFolderId = null;

    public bool $isMultiple = true;

    public array $selectedMedia = [];

    public ?string $collectionName = null;

    public string $pickerKey = '';

    public string $search = '';

    public ?int $pendingFolderId = null;

    /**
     * Protected folders opened with their password in this picker, whose media the search may show. Locked, so that
     * the browser can't add any.
     *
     * @var list<int>
     */
    #[Locked]
    public array $unlockedFolderIds = [];

    /**
     * All folders, for the search, once per request
     *
     * @var Collection<int, Folder>|null
     */
    protected ?Collection $allFolders = null;

    public ?int $maxItems = null;

    public ?int $minItems = null;

    public function mount(
        string $pickerKey,
        bool $isMultiple = true,
        ?string $collectionName = null,
        ?int $maxItems = null,
        ?int $minItems = null,
        $initialState = null
    ): void {
        $this->pickerKey = $pickerKey;
        $this->isMultiple = $isMultiple;
        $this->collectionName = $collectionName;
        $this->maxItems = $maxItems;
        $this->minItems = $minItems;

        // Initialize selectedMedia with current state
        if ($initialState) {
            if ($isMultiple && is_array($initialState)) {
                $this->selectedMedia = $initialState;
            } elseif (! $isMultiple && ! is_array($initialState)) {
                $this->selectedMedia = [$initialState];
            }
        }
    }

    public function openFolder(int $folderId): void
    {
        $folder = Folder::find($folderId);

        if (! $folder) {
            return;
        }

        // Check if folder is protected; once opened with its password, it opens without it in this picker
        if ($folder->is_protected && ! in_array($folder->id, $this->unlockedFolderIds, true)) {
            $this->pendingFolderId = $folderId;
            $this->mountAction('verifyPassword', ['folder' => $folder]);

            return;
        }

        // Don't reset selections when navigating
        $this->currentFolderId = $folderId;

        // Opened from the search results, the folder shows its contents
        $this->search = '';
    }

    /**
     * The open folder is a public property, which the browser can set (the first breadcrumb sets it to null). A
     * protected folder that is still locked, or a folder below it, must not open that way, without its password.
     */
    public function updatedCurrentFolderId(): void
    {
        if (! $this->currentFolderIsOpen()) {
            $this->currentFolderId = null;
        }
    }

    protected function currentFolderIsOpen(): bool
    {
        if (! $this->currentFolderId) {
            return true;
        }

        // Fresh, as a folder created in this request is not among allFolders() yet
        $folder = Folder::withoutGlobalScope('user')->find($this->currentFolderId);

        return $folder && $this->isOpen($folder);
    }

    public function verifyPassword(): Action
    {
        return Action::make('verifyPassword')
            ->modalHeading(fn (array $arguments) => __('Enter Password for') . ' ' . $arguments['folder']->name)
            ->modalIcon('heroicon-o-lock-closed')
            ->schema([
                TextInput::make('password')
                    ->label(__('Password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->id('folder-password-' . uniqid()),
            ])
            ->action(function (array $arguments, array $data) {
                $folder = $arguments['folder'];

                if ($folder->password !== $data['password']) {
                    Notification::make()
                        ->title(__('Password is incorrect'))
                        ->danger()
                        ->send();

                    $this->pendingFolderId = null;

                    return;
                }

                // Password correct, open folder
                $this->currentFolderId = $this->pendingFolderId;
                $this->unlockedFolderIds[] = $this->pendingFolderId;
                $this->pendingFolderId = null;
                $this->search = '';

                Notification::make()
                    ->title(__('Access granted'))
                    ->success()
                    ->send();
            })
            ->modalSubmitActionLabel(__('Confirm'));
    }

    public function uploadMedia(): Action
    {
        return Action::make('uploadMedia')
            ->label(__('Upload Media'))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->visible(fn () => $this->currentFolderId !== null)
            ->schema([
                FileUpload::make('files')
                    ->label(__('Files'))
                    ->multiple()
                    ->required()
                    ->maxSize(10240)
                    ->storeFiles(false),
            ])
            ->action(function (array $data) {
                $folder = Folder::find($this->currentFolderId);

                if (! $folder) {
                    return;
                }

                $uploadedMedia = [];

                foreach ($data['files'] as $file) {
                    // Determine which model to attach media to
                    if ($folder->model_type && $folder->model_id) {
                        // Attach to folder's specific model instance
                        $model = app($folder->model_type)->find($folder->model_id);
                    } elseif ($folder->model) {
                        // Use folder's model if available
                        $model = $folder->model;
                    } else {
                        // Attach to folder itself for collection folders
                        $model = $folder;
                    }

                    if (! $model) {
                        continue;
                    }

                    // Pass file directly like CreateMediaAction does
                    $media = WebSafeFileName::apply($model->addMedia($file), $model, $folder->collection ?? 'default')
                        ->toMediaCollection($folder->collection ?? 'default');

                    $uploadedMedia[] = $media->uuid;
                }

                Notification::make()
                    ->title(__('Media uploaded successfully'))
                    ->success()
                    ->send();

                // Auto-select uploaded media
                if ($this->isMultiple) {
                    foreach ($uploadedMedia as $uuid) {
                        if ($this->maxItems && count($this->selectedMedia) >= $this->maxItems) {
                            break;
                        }
                        if (! in_array($uuid, $this->selectedMedia)) {
                            $this->selectedMedia[] = $uuid;
                        }
                    }
                } else {
                    $this->selectedMedia = [$uploadedMedia[0] ?? null];
                    if ($uploadedMedia) {
                        $this->selectMedia();
                    }
                }
            });
    }

    /**
     * A folder at the top level, or a sub folder of the open folder, so that media can be uploaded right where it
     * belongs without leaving the picker
     */
    public function createFolder(): Action
    {
        return Action::make('createFolder')
            ->label(trans('filament-media-manager::messages.picker.create_folder'))
            ->icon('heroicon-o-folder-plus')
            ->color('gray')
            ->visible(fn (): bool => $this->canCreateFolder())
            ->schema(FolderForm::components())
            ->action(function (array $data): void {
                $parent = $this->currentFolder;

                $folder = Folder::query()->create([
                    ...Arr::except($data, ['password_confirmation']),
                    'user_id' => auth()->id(),
                    'user_type' => auth()->user() ? get_class(auth()->user()) : null,
                    'model_type' => $parent ? Folder::class : null,
                    'model_id' => $parent?->id,
                ]);

                Notification::make()
                    ->title(trans('filament-media-manager::messages.picker.folder_created'))
                    ->success()
                    ->send();

                // Open it to upload right away; a protected folder asks for its password like any other
                if (! $folder->is_protected) {
                    $this->currentFolderId = $folder->id;

                    // Livewire keeps computed properties for the request, the header would still show the parent
                    unset($this->currentFolder);
                }
            });
    }

    protected function canCreateFolder(): bool
    {
        // Collections are unique, so a picker limited to one would never show another folder
        if ($this->collectionName) {
            return false;
        }

        $folder = $this->currentFolder;

        if (! $folder) {
            return true;
        }

        // Folders of a model type, a collection or a record are managed by their model, not by hand
        if (! in_array($folder->model_type, [null, Folder::class], true)) {
            return false;
        }

        $panel = filament()->getCurrentOrDefaultPanel();

        return $panel?->hasPlugin('filament-media-manager') && filament('filament-media-manager')->allowSubFolders;
    }

    /**
     * Rename or change the open folder, with the form of the folder page
     */
    public function editFolder(): Action
    {
        return EditCurrentFolderAction::make((int) $this->currentFolderId)
            ->name('editFolder')
            ->visible(fn (): bool => $this->canEditFolder())
            ->after(function (): void {
                // Livewire keeps computed properties for the request, the header would still show the old name
                unset($this->currentFolder);
            });
    }

    protected function canEditFolder(): bool
    {
        $folder = $this->currentFolder;

        if (! $folder || ! filament()->getCurrentOrDefaultPanel()?->hasPlugin('filament-media-manager')) {
            return false;
        }

        // Like the folder page: with user access, a folder of a user can only be changed by that user
        if (filament('filament-media-manager')->allowUserAccess && filled($folder->user_id)) {
            $user = auth()->user();

            return $user && $folder->user_id === $user->getAuthIdentifier() && $folder->user_type === get_class($user);
        }

        return true;
    }

    public function goBack(): void
    {
        if ($this->currentFolderId) {
            $currentFolder = Folder::find($this->currentFolderId);

            if ($currentFolder) {
                // A sub folder points to its parent through model_id; it has a collection too, so check it first
                if ($currentFolder->model_type === Folder::class) {
                    // Through openFolder(), so a protected parent asks for its password
                    if ($currentFolder->model_id) {
                        $this->openFolder($currentFolder->model_id);
                    } else {
                        $this->currentFolderId = null;
                    }
                }
                // If in an instance folder, go back to collection folder
                elseif ($currentFolder->model_type && $currentFolder->model_id && $currentFolder->collection) {
                    // Find the collection folder
                    $collectionFolder = Folder::where('model_type', $currentFolder->model_type)
                        ->whereNull('model_id')
                        ->where('collection', $currentFolder->collection)
                        ->first();
                    $this->currentFolderId = $collectionFolder?->id;
                }
                // If in a collection folder, go back to model type folder
                elseif ($currentFolder->model_type && ! $currentFolder->model_id && $currentFolder->collection) {
                    // Find the model type folder
                    $modelTypeFolder = Folder::where('model_type', $currentFolder->model_type)
                        ->whereNull('model_id')
                        ->whereNull('collection')
                        ->first();
                    $this->currentFolderId = $modelTypeFolder?->id;
                }
                // Otherwise use model_id or go to root
                else {
                    $this->currentFolderId = $currentFolder->model_id;
                }
            } else {
                $this->currentFolderId = null;
            }
        }
        // Keep selections when going back
    }

    public function toggleMediaSelection(string $mediaUuid): void
    {
        if ($this->isMultiple) {
            if (in_array($mediaUuid, $this->selectedMedia)) {
                $this->selectedMedia = array_values(array_diff($this->selectedMedia, [$mediaUuid]));
            } else {
                // Check max items limit
                if ($this->maxItems && count($this->selectedMedia) >= $this->maxItems) {
                    Notification::make()
                        ->title(__('Maximum :count items allowed', ['count' => $this->maxItems]))
                        ->warning()
                        ->send();

                    return;
                }
                $this->selectedMedia[] = $mediaUuid;
            }
        } else {
            $this->selectedMedia = [$mediaUuid];
            // Auto-select and close for single selection
            $this->selectMedia();
        }
    }

    public function removeSelection(string $mediaUuid): void
    {
        $this->selectedMedia = array_values(array_diff($this->selectedMedia, [$mediaUuid]));
    }

    public function selectMedia(): void
    {
        if (empty($this->selectedMedia)) {
            Notification::make()
                ->title(__('No media selected'))
                ->warning()
                ->send();

            return;
        }

        // Check minimum items
        if ($this->minItems && count($this->selectedMedia) < $this->minItems) {
            Notification::make()
                ->title(__('Minimum :count items required', ['count' => $this->minItems]))
                ->warning()
                ->send();

            return;
        }

        $count = count($this->selectedMedia);
        $selectedData = $this->isMultiple ? $this->selectedMedia : $this->selectedMedia[0];

        // Show success notification
        Notification::make()
            ->title(__('Successfully selected :count item(s)', ['count' => $count]))
            ->success()
            ->send();

        // Dispatch window event with consistent structure wrapped in 'media' property
        $this->js(
            "window.dispatchEvent(new CustomEvent('media-selected-{$this->pickerKey}', {
                detail: { media: " . json_encode($selectedData) . ' }
            }));'
        );

        // Reset state but don't close the modal - user can click "Close" button
        $this->selectedMedia = [];
        $this->currentFolderId = null;
        $this->search = '';
    }

    public function getFoldersProperty()
    {
        if (filled($this->search)) {
            return $this->searchFolders();
        }

        // Remove user access global scope to show all folders
        $query = Folder::withoutGlobalScope('user');

        if ($this->currentFolderId) {
            $currentFolder = Folder::find($this->currentFolderId);

            if ($currentFolder) {
                // If current folder has model_type but no collection, show collection folders
                if ($currentFolder->model_type && ! $currentFolder->collection) {
                    $query->where('model_type', $currentFolder->model_type)
                        ->whereNull('model_id')
                        ->whereNotNull('collection');
                }
                // If current folder has model_type and collection, show instance folders
                elseif ($currentFolder->model_type && $currentFolder->collection && ! $currentFolder->model_id) {
                    $query->where('model_type', $currentFolder->model_type)
                        ->whereNotNull('model_id')
                        ->where('collection', $currentFolder->collection);
                }
                // Otherwise show subfolders using model_id
                else {
                    $query->where('model_id', $this->currentFolderId);
                }
            }
        } else {
            // Root level: match FolderResource query logic exactly
            // Wrap in closure to properly group with other conditions
            $query->where(function ($q) {
                $q->where('model_id', null)
                    ->where('collection', null)
                    ->orWhere('model_type', null);
            });
        }

        if ($this->collectionName) {
            $query->where('collection', $this->collectionName);
        }

        return $query->get();
    }

    public function getMediaProperty()
    {
        if (filled($this->search)) {
            return $this->searchMedia();
        }

        // No media at root level or model type level
        if (! $this->currentFolderId) {
            return collect();
        }

        $folder = Folder::find($this->currentFolderId);

        // If no collection, don't show any media
        if (! $folder?->collection) {
            return collect();
        }

        // Remove the global scope temporarily to get media
        $query = Media::withoutGlobalScope('folder');
        $this->scopeToFolder($query, $folder);

        return $query->get();
    }

    /**
     * Filter media based on folder structure (same logic as Media model's global scope)
     */
    protected function scopeToFolder(Builder $query, Folder $folder): void
    {
        if ($folder->model_type && $folder->model_id) {
            // Instance folder - show only media belonging to this specific model instance
            $query->where('model_type', $folder->model_type)
                ->where('model_id', $folder->model_id)
                ->where('collection_name', $folder->collection);
        } elseif ($folder->model_type && ! $folder->model_id) {
            // Collection folder - show media without model_id (not belonging to specific instances)
            $query->where('collection_name', $folder->collection)
                ->whereNull('model_id');
        } else {
            // Regular folder - show all media in collection
            $query->where('collection_name', $folder->collection);
        }
    }

    /**
     * Whether a medium is in the folder, like scopeToFolder() decides it
     */
    protected function folderHolds(Folder $folder, Media $medium): bool
    {
        if ($folder->collection !== $medium->collection_name) {
            return false;
        }

        if ($folder->model_type && $folder->model_id) {
            return $medium->model_type === $folder->model_type && (string) $medium->model_id === (string) $folder->model_id;
        }

        return ! $folder->model_type || $medium->model_id === null;
    }

    /**
     * The folders of all levels whose name matches the search. A protected folder that is still locked shows, so
     * that it can be opened with its password, but none of the folders below it. Each knows the path of the folder
     * it is in (picker_location), if any.
     */
    protected function searchFolders(): Collection
    {
        $folders = $this->allFolders();

        return $folders
            ->filter(fn (Folder $folder): bool => Str::contains($folder->name, $this->search, ignoreCase: true))
            ->filter(fn (Folder $folder): bool => ! ($parent = $this->parentOf($folder)) || $this->isOpen($parent))
            ->when($this->collectionName, fn (Collection $folders) => $folders->where('collection', $this->collectionName))
            ->each(fn (Folder $folder) => $folder->setAttribute('picker_location', $this->folderPath($this->parentOf($folder))))
            ->values();
    }

    /**
     * The media of all folders whose name or file name matches the search, except the media of protected folders
     * that are still locked and of the folders below them. Each knows the path of its folder (picker_location).
     */
    protected function searchMedia(): Collection
    {
        $folders = $this->allFolders()
            ->filter(fn (Folder $folder): bool => filled($folder->collection) && $this->isOpen($folder))
            ->when($this->collectionName, fn (Collection $folders) => $folders->where('collection', $this->collectionName));

        if ($folders->isEmpty()) {
            return collect();
        }

        $media = Media::withoutGlobalScope('folder')
            ->where(function (Builder $query) use ($folders): void {
                foreach ($folders as $folder) {
                    $query->orWhere(fn (Builder $query) => $this->scopeToFolder($query, $folder));
                }
            })
            ->where(function (Builder $query): void {
                $query->where('file_name', 'like', '%' . $this->search . '%')
                    ->orWhere('name', 'like', '%' . $this->search . '%');
            })
            ->get();

        return $media->each(fn (Media $medium) => $medium->setAttribute(
            'picker_location',
            $this->folderPath($folders->first(fn (Folder $folder): bool => $this->folderHolds($folder, $medium))),
        ));
    }

    /**
     * The path of the folder in the search results, e.g. "Floor plans › Water tower"
     */
    protected function folderPath(?Folder $folder): ?string
    {
        return FolderLocks::path($folder, $this->allFolders());
    }

    /**
     * Whether the folder and all folders above it are unprotected or opened with their password in this picker
     */
    protected function isOpen(Folder $folder): bool
    {
        return FolderLocks::isOpen($folder, $this->unlockedFolderIds, $this->allFolders());
    }

    protected function parentOf(Folder $folder): ?Folder
    {
        return FolderLocks::parentOf($folder, $this->allFolders());
    }

    /**
     * @return Collection<int, Folder>
     */
    protected function allFolders(): Collection
    {
        return $this->allFolders ??= FolderLocks::folders();
    }

    public function getCurrentFolderProperty(): ?Folder
    {
        return $this->currentFolderId ? Folder::find($this->currentFolderId) : null;
    }

    /**
     * @return list<array{id: ?int, label: string}>
     */
    public function getBreadcrumbsProperty(): array
    {
        return $this->currentFolder ? FolderBreadcrumbs::forPicker($this->currentFolder) : [];
    }

    public function render()
    {
        return view('filament-media-manager::livewire.media-picker', [
            'folders' => $this->folders,
            'media' => $this->media,
            'currentFolder' => $this->currentFolder,
        ]);
    }
}
