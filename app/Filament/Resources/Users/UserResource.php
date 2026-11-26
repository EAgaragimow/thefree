<?php

namespace App\Filament\Resources\Users;

use App\Actions\Users\ChangeUserStatus;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\RestoreUser;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Support\RunsAdminAction;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('email'),
                TextEntry::make('role')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('subscription_type')->badge(),
                TextEntry::make('billing_state')->badge(),
                TextEntry::make('timezone'),
                TextEntry::make('email_verified_at')->dateTime()->placeholder('Not verified'),
                TextEntry::make('deactivated_at')->dateTime()->placeholder('Active'),
                TextEntry::make('deletion_requested_at')->dateTime()->placeholder('No request'),
                TextEntry::make('deleted_at')->dateTime()->placeholder('Not deleted'),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('role')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('subscription_type')->badge()->toggleable(),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->hasVerifiedEmail()),
                IconColumn::make('deletion_requested_at')
                    ->label('Deletion requested')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->deletion_requested_at !== null),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->options(collect(UserRole::cases())->mapWithKeys(fn (UserRole $role) => [$role->value => ucfirst($role->value)])->all()),
                SelectFilter::make('status')->options(collect(UserStatus::cases())->mapWithKeys(fn (UserStatus $status) => [$status->value => ucfirst($status->value)])->all()),
                Filter::make('deletion_requested')
                    ->label('Deletion requested')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('deletion_requested_at')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                self::statusAction('restrict', 'Restrict', UserStatus::Restricted, 'warning', Heroicon::OutlinedNoSymbol),
                self::statusAction('suspend', 'Suspend', UserStatus::Suspended, 'danger', Heroicon::OutlinedLockClosed),
                self::statusAction('reinstate', 'Reinstate', UserStatus::Active, 'success', Heroicon::OutlinedCheckCircle),
                self::deleteAction(),
                self::restoreAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function statusAction(string $name, string $label, UserStatus $status, string $color, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->icon($icon)
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')->required()->maxLength(1000),
            ])
            ->visible(fn (User $record): bool => ! $record->trashed()
                && $record->status !== $status
                && ! $record->is(auth()->user()))
            ->action(fn (User $record, array $data) => RunsAdminAction::run(
                fn () => app(ChangeUserStatus::class)($record, $status, auth()->user(), $data['reason']),
                "Account {$status->value}.",
            ));
    }

    public static function deleteAction(): Action
    {
        return Action::make('deleteAccount')
            ->label('Delete')
            ->color('danger')
            ->icon(Heroicon::OutlinedTrash)
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')->required()->maxLength(1000),
            ])
            ->visible(fn (User $record): bool => ! $record->trashed() && ! $record->is(auth()->user()))
            ->action(fn (User $record, array $data) => RunsAdminAction::run(
                fn () => app(DeleteUser::class)($record, auth()->user(), $data['reason']),
                'Account deleted.',
            ));
    }

    public static function restoreAction(): Action
    {
        return Action::make('restoreAccount')
            ->label('Restore')
            ->color('success')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')->required()->maxLength(1000),
            ])
            ->visible(fn (User $record): bool => $record->trashed())
            ->action(fn (User $record, array $data) => RunsAdminAction::run(
                fn () => app(RestoreUser::class)($record, auth()->user(), $data['reason']),
                'Account restored.',
            ));
    }
}
