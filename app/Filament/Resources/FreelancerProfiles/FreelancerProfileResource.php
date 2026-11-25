<?php

namespace App\Filament\Resources\FreelancerProfiles;

use App\Actions\Profiles\ProfileContent;
use App\Filament\Resources\FreelancerProfiles\Pages\ListFreelancerProfiles;
use App\Filament\Resources\FreelancerProfiles\Pages\ViewFreelancerProfile;
use App\Filament\Resources\ProfileRevisions\ProfileRevisionResource;
use App\Models\FreelancerProfile;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only view of live freelancer profiles. Reviewing pending edits happens in
 * {@see ProfileRevisionResource}.
 */
class FreelancerProfileResource extends Resource
{
    protected static ?string $model = FreelancerProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $modelLabel = 'freelancer profile';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')->label('Freelancer'),
                TextEntry::make('user.email')->label('Email'),
                TextEntry::make('created_at')->label('Created')->dateTime(),
                TextEntry::make('published_at')->label('First published')->dateTime()->placeholder('Never published'),
                TextEntry::make('content_updated_at')->label('Content updated')->dateTime()->placeholder('—'),
                TextEntry::make('score'),
                TextEntry::make('professional_title')->placeholder('—'),
                TextEntry::make('location')->placeholder('—'),
                TextEntry::make('timezone'),
                TextEntry::make('website')->placeholder('—'),
                TextEntry::make('about')->placeholder('—')->columnSpanFull(),
                ...ProfileRevisionResource::detailEntries(fn (FreelancerProfile $record): ProfileContent => $record->liveContent()),
                TextEntry::make('consultations')
                    ->getStateUsing(fn (FreelancerProfile $record): array => ProfileRevisionResource::consultationLines($record->liveContent()))
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('services')
                    ->getStateUsing(fn (FreelancerProfile $record): array => ProfileRevisionResource::serviceLines($record->liveContent()))
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('highlights')
                    ->getStateUsing(fn (FreelancerProfile $record): array => ProfileRevisionResource::highlightLines($record->liveContent()))
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('experiences')
                    ->label('Work history')
                    ->getStateUsing(fn (FreelancerProfile $record): array => $record->experiences
                        ->map(fn ($experience): string => "{$experience->role} at {$experience->company} ({$experience->started_at->format('M Y')} – ".($experience->ended_at?->format('M Y') ?? 'present').')')
                        ->all())
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('user.name')->label('Freelancer')->searchable(),
                TextColumn::make('professional_title')->searchable(),
                IconColumn::make('published')
                    ->boolean()
                    ->getStateUsing(fn (FreelancerProfile $record): bool => $record->isPublished()),
                TextColumn::make('score')->sortable(),
                TextColumn::make('published_at')->label('First published')->dateTime()->sortable(),
                TextColumn::make('content_updated_at')->label('Content updated')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('published')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('published_at'),
                        false: fn ($query) => $query->whereNull('published_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFreelancerProfiles::route('/'),
            'view' => ViewFreelancerProfile::route('/{record}'),
        ];
    }
}
