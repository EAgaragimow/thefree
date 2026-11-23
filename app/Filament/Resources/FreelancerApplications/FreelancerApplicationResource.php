<?php

namespace App\Filament\Resources\FreelancerApplications;

use App\Actions\Applications\ApproveApplication;
use App\Actions\Applications\RejectApplication;
use App\Actions\Applications\ResendInvitation;
use App\Enums\ApplicationStatus;
use App\Filament\Resources\FreelancerApplications\Pages\ListFreelancerApplications;
use App\Filament\Resources\FreelancerApplications\Pages\ViewFreelancerApplication;
use App\Filament\Support\RunsAdminAction;
use App\Models\FreelancerApplication;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class FreelancerApplicationResource extends Resource
{
    protected static ?string $model = FreelancerApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('full_name'),
                TextEntry::make('email'),
                TextEntry::make('phone')->placeholder('—'),
                TextEntry::make('location'),
                TextEntry::make('specialty'),
                TextEntry::make('years_experience')->placeholder('—'),
                TextEntry::make('skills')->badge(),
                TextEntry::make('about')->placeholder('—')->columnSpanFull(),
                TextEntry::make('experience_summary')->columnSpanFull(),
                TextEntry::make('portfolio_links')->listWithLineBreaks(),
                TextEntry::make('social_links')->listWithLineBreaks()->placeholder('—'),
                TextEntry::make('status')->badge(),
                TextEntry::make('reviewer.name')->label('Reviewer')->placeholder('—'),
                TextEntry::make('reviewed_at')->dateTime()->placeholder('—'),
                TextEntry::make('rejection_reason')->placeholder('—')->columnSpanFull(),
                TextEntry::make('invitation_expires_at')->dateTime()->placeholder('—'),
                TextEntry::make('activated_at')->dateTime()->placeholder('Not activated'),
                TextEntry::make('created_at')->label('Submitted')->dateTime(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('full_name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('specialty')->searchable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ApplicationStatus::cases())->mapWithKeys(fn (ApplicationStatus $status) => [$status->value => ucfirst($status->value)])->all())
                    ->default(ApplicationStatus::Pending->value),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approveAction(),
                self::rejectAction(),
                self::resendAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFreelancerApplications::route('/'),
            'view' => ViewFreelancerApplication::route('/{record}'),
        ];
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->color('success')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->requiresConfirmation()
            ->modalDescription('The applicant will receive an email invitation to activate their account. Their profile is not published until it passes a separate review.')
            ->schema([
                Textarea::make('note')->label('Internal note (optional)')->maxLength(1000),
            ])
            ->visible(fn (FreelancerApplication $record): bool => $record->status === ApplicationStatus::Pending)
            ->action(fn (FreelancerApplication $record, array $data) => RunsAdminAction::run(
                fn () => app(ApproveApplication::class)($record, auth()->user(), $data['note'] ?? null),
                'Application approved and invitation sent.',
            ));
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->color('danger')
            ->icon(Heroicon::OutlinedXCircle)
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')->label('Reason (shared with the applicant)')->required()->maxLength(1000),
            ])
            ->visible(fn (FreelancerApplication $record): bool => $record->status === ApplicationStatus::Pending)
            ->action(fn (FreelancerApplication $record, array $data) => RunsAdminAction::run(
                fn () => app(RejectApplication::class)($record, auth()->user(), $data['reason']),
                'Application rejected.',
            ));
    }

    public static function resendAction(): Action
    {
        return Action::make('resendInvitation')
            ->label('Resend invitation')
            ->color('gray')
            ->icon(Heroicon::OutlinedEnvelope)
            ->requiresConfirmation()
            ->visible(fn (FreelancerApplication $record): bool => $record->status === ApplicationStatus::Approved && $record->activated_at === null)
            ->action(fn (FreelancerApplication $record) => RunsAdminAction::run(
                fn () => app(ResendInvitation::class)($record, auth()->user()),
                'A new invitation has been sent.',
            ));
    }
}
