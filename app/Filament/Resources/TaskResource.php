<?php

namespace App\Filament\Resources;

use App\Enums\RoleType;
use App\Enums\TaskAction;
use App\Filament\Resources\TaskResource\Pages;
use App\Filament\Resources\TaskResource\RelationManagers;
use App\Models\ProdOrder;
use App\Models\ProdOrderGroup;
use App\Models\SupplyOrder;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';

    public static function canAccess(): bool
    {
        return !empty(auth()->user()->organization_id) && in_array(auth()->user()->role, [
                RoleType::ADMIN,
                RoleType::SENIOR_SUPPLY_MANAGER,
                RoleType::SUPPLY_MANAGER,
                RoleType::PRODUCTION_MANAGER,
                RoleType::SENIOR_STOCK_MANAGER,
                RoleType::STOCK_MANAGER,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        if (auth()->user()->role == RoleType::ADMIN) {
            return parent::getEloquentQuery();
        }

        return parent::getEloquentQuery()
            ->whereJsonContains('to_user_ids', auth()->user()->id)
            ->orWhereJsonContains('to_user_roles', auth()->user()->role);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('from_user_id')
                    ->relationship('fromUser', 'name')
                    ->required(),
                Forms\Components\Select::make('to_user_id')
                    ->relationship('toUser', 'name'),
                Forms\Components\TextInput::make('to_user_role')
                    ->numeric(),
                Forms\Components\TextInput::make('related_id')
                    ->numeric(),
                Forms\Components\TextInput::make('related_type')
                    ->maxLength(255),
                Forms\Components\TextInput::make('action')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('comment')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('status')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('fromUser.name')
                    ->numeric()
                    ->sortable(),
//                Tables\Columns\TextColumn::make('toUser.name')
//                    ->numeric()
//                    ->sortable(),
//                Tables\Columns\TextColumn::make('to_user_role')
//                    ->numeric()
//                    ->sortable(),
                Tables\Columns\TextColumn::make('related')
                    ->getStateUsing(function (Task $record) {
                        switch ($record->related_type) {
                            case ProdOrderGroup::class:
                                $text = "PO-$record->related_id";
                                $link = "<a href='/admin/prod-order-groups/$record->related_id/edit' target='_blank'>$text</a>";
                                break;
                            case ProdOrder::class:
                                /** @var ProdOrder $prodOrder */
                                $prodOrder = ProdOrder::query()->find($record->related_id);
                                if (!$prodOrder) {
                                    return null;
                                }
                                $text = !empty($prodOrder->number) ? $prodOrder->number : "PO-$record->related_id";
                                $link = "<a href='/admin/prod-order-groups/{$prodOrder->group_id}' target='_blank'>$text</a>";
                                break;
                            case SupplyOrder::class:
                                $supplyOrder = SupplyOrder::query()->find($record->related_id);
                                if (in_array(auth()->user()->role, [
                                    RoleType::ADMIN,
                                    RoleType::SUPPLY_MANAGER,
                                    RoleType::SENIOR_SUPPLY_MANAGER,
                                ])) {
                                    $href = "/admin/supply-orders/$record->related_id/edit";
                                } else {
                                    $href = "/admin/supply-orders/$record->related_id/view";
                                }

                                $text = !empty($supplyOrder->number) ? $supplyOrder->number : "SO-$record->related_id";
                                $link = "<a href='$href' target='_blank'>$text</a>";
                                break;
                            default:
                                $link = null;
                        }

                        return $link;
                    })
                    ->color('info')
                    ->html(),
                Tables\Columns\TextColumn::make('action')
                    ->formatStateUsing(function ($state) {
                        return "Need to " . $state->getLabel();
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->formatStateUsing(function ($state) {
                        return $state->format('d M Y H:i');
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
//                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTasks::route('/'),
        ];
    }
}
