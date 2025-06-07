<?php

namespace App\Filament\Resources;

use App\Enums\ProdOrderGroupType;
use App\Enums\RoleType;
use App\Filament\Resources\ProdOrderGroupResource\Pages;
use App\Filament\Resources\ProdOrderGroupResource\RelationManagers\ProdOrdersRelationManager;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\ProdOrder\ProdOrderStep;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use RyanChandler\FilamentProgressColumn\ProgressColumn;
use Throwable;

class ProdOrderGroupResource extends Resource
{
    protected static ?string $model = ProdOrderGroup::class;
    protected static ?string $label = 'Prod order';
    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canAccess(): bool
    {
        return !empty(auth()->user()->organization_id) && in_array(auth()->user()->role, [
                RoleType::ADMIN,
                RoleType::PLANNING_MANAGER,
                RoleType::PRODUCTION_MANAGER,
                RoleType::ALLOCATION_MANAGER,
                RoleType::SENIOR_STOCK_MANAGER,
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([

                    Forms\Components\Select::make('type')
                        ->options(ProdOrderGroupType::class)
                        ->reactive()
                        ->required(),

                    Forms\Components\Select::make('warehouse_id')
                        ->relationship('warehouse', 'name')
                        ->required(),

                    Forms\Components\Select::make('organization_id')
                        ->label('Agent')
                        ->relationship('organization', 'name')
                        ->visible(fn($get) => $get('type') == ProdOrderGroupType::ByOrder->value)
                        ->required(),

                    Forms\Components\DatePicker::make('deadline')
                        ->label('Срок выполнения')
                        ->visible(fn($get) => $get('type') == ProdOrderGroupType::ByCatalog->value)
                        ->required(),

                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Agent')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('deadline')
                    ->label('Deadline')
                    ->sortable()
                    ->toggleable()
                    ->date(),

                ProgressColumn::make('progress')
                    ->width('150px')
                    ->progress(fn (ProdOrderGroup $record) => $record->getProgress()),

                Tables\Columns\TextColumn::make('confirmed_at')
                    ->getStateUsing(function (ProdOrderGroup $record) {
                        if ($record->isConfirmed()) {
                            return '<span class="text-green-500">✔️</span>';
                        }
                        return '<span class="text-red-500">❌</span>';
                    })
                    ->html()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->visible(fn($record) => !$record->isConfirmed())
                    ->action(function (ProdOrderGroup $record, $livewire, $action) {
                        try {
                            $record->confirm();
                            showSuccess('Order confirmed successfully');
                            $livewire->dispatch('$refresh');
                        } catch (Throwable $e) {
                            showError($e->getMessage());
                            $action->halt();
                        }
                    })
                    ->requiresConfirmation(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProdOrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProdOrderGroups::route('/'),
            'create' => Pages\CreateProdOrderGroup::route('/create'),
            'edit' => Pages\EditProdOrderGroup::route('/{record}/edit'),
            'details' => Pages\ProdOrderDetails::route('/{record}/orders/{id}/details'),
            'view' => Pages\ProdOrderView::route('/{record}/orders/{id}/view'),
        ];
    }
}
