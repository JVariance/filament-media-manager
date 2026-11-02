<?php

namespace TomatoPHP\FilamentMediaManager\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use TomatoPHP\FilamentMediaManager\Services\FolderAccess;
use TomatoPHP\FilamentMediaManager\Services\FolderLocks;

class FolderComponent extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public $item;

    /**
     * The path of the folder it is in, shown in search results instead of its date
     */
    public ?string $location = null;

    public function mount($item, ?string $location = null): void
    {
        $this->item = $item;
        $this->location = $location;
    }

    public function getFolderAction(): Action
    {
        return Action::make('getFolderAction')
            ->color('danger')
            ->view('filament-media-manager::components.folder-action-view', fn (array $arguments) => ['item' => $this->resolveFolder($arguments), 'location' => $this->location])
            ->requiresConfirmation(fn (array $arguments): bool => $this->needsPassword($this->resolveFolder($arguments)))
            ->schema(function (array $arguments) {
                if (! $this->needsPassword($this->resolveFolder($arguments))) {
                    return null;
                }

                return [
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->maxLength(255),
                ];
            })
            ->action(function (array $arguments, array $data) {
                $folder = $this->resolveFolder($arguments);

                if (! $folder) {
                    return;
                }

                if ($this->needsPassword($folder)) {
                    // Check against the stored password, never against a value sent by the browser.
                    if (! FolderAccess::passwordMatches($folder, $data['password'] ?? null)) {
                        Notification::make()
                            ->title('Password is incorrect')
                            ->danger()
                            ->send();

                        return;
                    }

                    // This folder only, for the session; the other protected folders keep their passwords
                    FolderLocks::unlockInSession($folder);
                }

                if (! $folder->model_type) {
                    if (filament()->getTenant()) {
                        return redirect()->to(url(filament()->getCurrentPanel()->getId() . '/' . filament()->getTenant()->id . '/media?folder_id=' . $folder->id));
                    } else {
                        return redirect()->route('filament.' . filament()->getCurrentPanel()->getId() . '.resources.media.index', ['folder_id' => $folder->id]);
                    }
                }
                if (! $folder->model_id && ! $folder->collection) {
                    if (filament()->getTenant()) {
                        return redirect()->to(url(filament()->getCurrentPanel()->getId() . '/' . filament()->getTenant()->id . '/folders?model_type=' . $folder->model_type));
                    } else {
                        return redirect()->route('filament.' . filament()->getCurrentPanel()->getId() . '.resources.folders.index', ['model_type' => $folder->model_type]);
                    }
                } elseif (! $folder->model_id) {
                    if (filament()->getTenant()) {
                        return redirect()->to(url(filament()->getCurrentPanel()->getId() . '/' . filament()->getTenant()->id . '/folders?model_type=' . $folder->model_type . '&collection=' . $folder->collection));
                    } else {
                        return redirect()->route('filament.' . filament()->getCurrentPanel()->getId() . '.resources.folders.index', ['model_type' => $folder->model_type, 'collection' => $folder->collection]);
                    }
                } else {
                    if (filament()->getTenant()) {
                        return redirect()->to(url(filament()->getCurrentPanel()->getId() . '/' . filament()->getTenant()->id . '/media?folder_id=' . $folder->id));
                    } else {
                        return redirect()->route('filament.' . filament()->getCurrentPanel()->getId() . '.resources.media.index', ['folder_id' => $folder->id]);
                    }
                }
            });
    }

    /**
     * Load the folder from the database; the browser only sends its id.
     */
    /**
     * A protected folder asks for its password until it was opened with it in this session
     */
    protected function needsPassword(?Model $folder): bool
    {
        return $folder && $folder->is_protected && ! in_array((int) $folder->getKey(), FolderLocks::unlockedInSession(), true);
    }

    protected function resolveFolder(array $arguments): ?Model
    {
        $item = $arguments['item'] ?? null;

        if ($item instanceof Model) {
            return $item;
        }

        $id = is_array($item) ? ($item['id'] ?? null) : $item;

        return filled($id) ? config('filament-media-manager.model.folder')::find($id) : null;
    }

    public function render(): mixed
    {
        return view('filament-media-manager::livewire.folder-component');
    }
}
