<?php

namespace App\Filament\Resources\BrandProfiles;

use App\Enums\SubscriptionType;
use App\Filament\Resources\BrandProfiles\Pages\ListBrandProfiles;
use App\Filament\Resources\BrandProfiles\Pages\ViewBrandProfile;
use App\Models\BrandProfile;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only list of brand profiles. A brand edits its own profile and an administrator manages the
 * account itself (restrict, suspend, delete) under Users.
 */
class BrandProfileResource extends Resource
{
    protected static ?string $model = BrandProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'brand profile';

    protected static ?string $recordTitleAttribute = 'company_name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('logo_path')->label('Logo')->disk('public')->circular(),
                TextEntry::make('company_name'),
                TextEntry::make('user.name')->label('Account'),
                TextEntry::make('user.email')->label('Account email'),
                TextEntry::make('user.subscription_type')->label('Plan')->badge(),
                TextEntry::make('user.billing_state')->label('Billing')->badge(),
                TextEntry::make('industry.name')->label('Industry')->placeholder('—'),
                TextEntry::make('location')->placeholder('—'),
                TextEntry::make('website')->placeholder('—'),
                TextEntry::make('description')->placeholder('—')->columnSpanFull(),
                TextEntry::make('contact_name')->label('Contact person')->placeholder('—'),
                TextEntry::make('contact_email')->label('Contact email')->placeholder('—'),
                TextEntry::make('contact_phone')->label('Contact phone')->placeholder('—'),
                TextEntry::make('social_links')
                    ->getStateUsing(fn (BrandProfile $record): array => collect($record->social_links ?? [])->filter()->values()->all())
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextEntry::make('professionalFunctions.name')->label('Functions')->badge()->placeholder('—')->columnSpanFull(),
                TextEntry::make('professionalRoles.name')->label('Roles')->badge()->placeholder('—')->columnSpanFull(),
                TextEntry::make('created_at')->label('Created')->dateTime(),
                TextEntry::make('updated_at')->label('Updated')->dateTime(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('company_name')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'industry']))
            ->columns([
                ImageColumn::make('logo_path')->label('Logo')->disk('public')->circular()->toggleable(),
                TextColumn::make('company_name')->searchable()->sortable(),
                TextColumn::make('user.name')->label('Account')->searchable(),
                TextColumn::make('user.email')->label('Account email')->searchable(),
                TextColumn::make('industry.name')->label('Industry')->sortable(),
                TextColumn::make('location')->searchable()->toggleable(),
                TextColumn::make('user.subscription_type')->label('Plan')->badge(),
                TextColumn::make('created_at')->label('Created')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('industry')->relationship('industry', 'name'),
                SelectFilter::make('plan')
                    ->options(collect(SubscriptionType::cases())->mapWithKeys(fn (SubscriptionType $type) => [$type->value => $type === SubscriptionType::PaidPlan ? 'Paid' : 'Free'])->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $plan) => $query->whereHas('user', fn (Builder $users) => $users->where('subscription_type', $plan)),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrandProfiles::route('/'),
            'view' => ViewBrandProfile::route('/{record}'),
        ];
    }
}
