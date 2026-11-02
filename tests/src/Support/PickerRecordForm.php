<?php

namespace TomatoPHP\FilamentMediaManager\Tests\Support;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use TomatoPHP\FilamentMediaManager\Form\MediaManagerPicker;
use TomatoPHP\FilamentMediaManager\Tests\Models\Product;

/**
 * A picker on a record, which keeps its media in media_has_models
 */
class PickerRecordForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?array $data = [];

    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([MediaManagerPicker::make('media')->multiple()])
            ->statePath('data')
            ->model($this->product);
    }

    public function save(): void
    {
        $this->form->getState();
        $this->form->saveRelationships();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}<x-filament-actions::modals /></div>';
    }
}
