<?php

namespace App\Filament\Resources\ProdOrderGroupResource\RelationManagers;

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\RoleType;
use App\Events\ProdOrderChanged;
use App\Filament\Resources\ProdOrderGroupResource;
use App\Models\ProdOrder\ProdOrder;
use App\Models\ProdOrder\ProdOrderGroup;
use App\Models\ProdOrder\ProdOrderStep;
use App\Models\Product;
use App\Services\ProdOrderService;
use Closure;
use Exception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RyanChandler\FilamentProgressColumn\ProgressColumn;
use Throwable;

class ProdOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'prodOrders';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('product_id')
                        ->relationship('product', 'name', fn($query) => $query->where('type', ProductType::ReadyProduct))
                        ->getOptionLabelFromRecordUsing(function($record) {
                            /** @var Product $record */
                            return $record->ready_product_id ? $record->name : $record->catName;
                        })
                        ->rules([
                            fn(Forms\Get $get): Closure => function (string $attribute, $value, $fail) use ($get) {
                                /** @var ProdOrderGroup $poGroup */
                                $poGroup = $this->getOwnerRecord();
                                if (
                                    $poGroup->prodOrders()
                                        ->when(
                                            $get('id'),
                                            fn($query) => $query->whereNot('id', $get('id'))
                                        )
                                        ->where('group_id', $poGroup->id)
                                        ->where('product_id', $get('product_id'))
                                        ->exists()
                                ) {
                                    $fail('This product is already added to the order.');
                                }
                            }
                        ])
                        ->required()
                        ->reactive(),

                    Forms\Components\TextInput::make('quantity')
                        ->required()
                        ->suffix(function ($get) {
                            /** @var Product|null $product */
                            $product = $get('product_id') ? Product::query()->find(
                                $get('product_id')
                            ) : null;
                            return $product?->category?->measure_unit?->getLabel();
                        })
                        ->numeric(),

                    Forms\Components\TextInput::make('offer_price')
                        ->required()
                        ->numeric(),
                ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_id')
            ->modifyQueryUsing(function (Builder $query) {
                $query->with([
                    'product' => fn($query) => $query->with('category'),
                ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('number'),
                Tables\Columns\TextColumn::make('product.catName'),
                Tables\Columns\TextColumn::make('quantity')
                    ->formatStateUsing(function ($record) {
                        return $record->quantity . ' ' . $record->product->category?->measure_unit?->getLabel();
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('offer_price'),
                ProgressColumn::make('progress')
                    ->width('150px')
                    ->progress(fn (ProdOrder $record) => $record->getProgress()),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('confirmed_at')
                    ->getStateUsing(function ($record) {
                        if ($record->confirmed_at) {
                            return '<span class="text-green-500">✔️</span>';
                        }
                        return '<span class="text-red-500">❌</span>';
                    })
                    ->html()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var ProdOrderGroup $poGroup */
                        $poGroup = $this->getOwnerRecord();

                        $data['status'] = OrderStatus::Pending;

                        $data['total_cost'] = app(ProdOrderService::class)->calculateTotalCost(
                            $data['product_id'],
                            $poGroup->warehouse_id
                        );
                        $data['deadline'] = app(ProdOrderService::class)->calculateDeadline(
                            $data['product_id']
                        );
                        return $data;
                    })
                    ->after(function () {
                        /** @var ProdOrderGroup $poGroup */
                        $poGroup = $this->getOwnerRecord();

                        ProdOrderChanged::dispatch($poGroup, false);
                    }),
            ])
            ->recordUrl(function($record) {
                if (!$record->isStarted()) {
                    return null;
                }
                return ProdOrderGroupResource::getUrl('details', [
                    'record' => $this->getOwnerRecord(),
                    'id' => $record->id,
                ]);
            })
            ->actions([
                Tables\Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->visible(fn($record) => !$record->confirmed_at)
                    ->action(function (ProdOrder $record, $livewire, $action) {
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

                Tables\Actions\Action::make('startProduction')
                    ->label('Start')
                    ->visible(fn($record) => $record->confirmed_at && !$record->started_at)
                    ->action(function ($record, $livewire) {
                        /** @var ProdOrderService $prodOrderService */
                        $prodOrderService = app(ProdOrderService::class);

                        try {
                            $insufficientAssets = $prodOrderService->checkStart($record);
                            if (!empty($insufficientAssets)) {
                                $livewire->dispatch(
                                    'openModal',
                                    $record,
                                    $insufficientAssets,
                                    'startProdOrder'
                                );
                            } else {
                                $prodOrderService->start($record);
                                showSuccess('Production order started successfully');
                            }

                            $livewire->dispatch('$refresh');
                        } catch (Exception $e) {
                            showError($e->getMessage());
                        }
                    })
                    ->requiresConfirmation(),

                Tables\Actions\Action::make('details')
                    ->label('Details')
                    ->hidden(fn($record) => $record->status == OrderStatus::Pending)
                    ->url(fn($record) => ProdOrderGroupResource::getUrl('details', [
                        'record' => $this->getOwnerRecord(),
                        'id' => $record->id,
                    ]))
                    ->requiresConfirmation(),

                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->status == OrderStatus::Pending)
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var ProdOrderGroup $poGroup */
                        $poGroup = $this->getOwnerRecord();

                        $data['total_cost'] = app(ProdOrderService::class)->calculateTotalCost(
                            $data['product_id'],
                            $poGroup->warehouse_id
                        );
                        $data['deadline'] = app(ProdOrderService::class)->calculateDeadline(
                            $data['product_id']
                        );
                        return $data;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
