<?php

namespace App\Filament\Resources\ProfessionalRoles;

use App\Filament\Resources\ProfessionalRoles\Pages\CreateProfessionalRole;
use App\Filament\Resources\ProfessionalRoles\Pages\EditProfessionalRole;
use App\Filament\Resources\ProfessionalRoles\Pages\ListProfessionalRoles;
use App\Filament\Support\CatalogDeletion;
use App\Models\ProfessionalRole;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ProfessionalRoleResource extends Resource
{
    protected static ?string $model = ProfessionalRole::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $modelLabel = 'role';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                Select::make('professional_function_id')
                    ->label('Function')
                    ->relationship('professionalFunction', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('professionalFunction.name')
                    ->label('Function')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('professional_function_id')
                    ->label('Function')
                    ->relationship('professionalFunction', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    CatalogDeletion::deleteBulkAction(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfessionalRoles::route('/'),
            'create' => CreateProfessionalRole::route('/create'),
            'edit' => EditProfessionalRole::route('/{record}/edit'),
        ];
    }
}
