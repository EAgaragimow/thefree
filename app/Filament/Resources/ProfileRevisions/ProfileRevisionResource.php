<?php

namespace App\Filament\Resources\ProfileRevisions;

use App\Actions\Profiles\ApproveProfile;
use App\Actions\Profiles\ConsultationSchedule;
use App\Actions\Profiles\ProfileContent;
use App\Actions\Profiles\RequestProfileChanges;
use App\Enums\ConsultationType;
use App\Enums\PricingMode;
use App\Enums\ProfileStatus;
use App\Filament\Resources\ProfileRevisions\Pages\ListProfileRevisions;
use App\Filament\Resources\ProfileRevisions\Pages\ViewProfileRevision;
use App\Filament\Support\RunsAdminAction;
use App\Models\Category;
use App\Models\FreelancerProfileRevision;
use App\Models\Skill;
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
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The profile review queue: freelancers' proposed profile content, waiting for approval.
 */
class ProfileRevisionResource extends Resource
{
    protected static ?string $model = FreelancerProfileRevision::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $modelLabel = 'profile revision';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('profile.user.name')->label('Freelancer'),
                TextEntry::make('profile.user.email')->label('Email'),
                TextEntry::make('status')->badge(),
                TextEntry::make('changes')
                    ->label('Changed compared to the live profile')
                    ->getStateUsing(fn (FreelancerProfileRevision $record): array => self::changedFieldLabels($record))
                    ->badge()
                    ->placeholder('Nothing is live yet — this is the first version.'),
                TextEntry::make('payload.professional_title')->label('Professional title')->placeholder('—'),
                TextEntry::make('payload.location')->label('Location')->placeholder('—'),
                TextEntry::make('payload.timezone')->label('Time zone'),
                TextEntry::make('payload.website')->label('Website')->placeholder('—'),
                TextEntry::make('payload.about')->label('About')->placeholder('—')->columnSpanFull(),
                TextEntry::make('categories')
                    ->getStateUsing(fn (FreelancerProfileRevision $record): array => Category::query()->whereIn('id', $record->content()->categoryIds())->pluck('name')->all())
                    ->badge()
                    ->placeholder('—'),
                TextEntry::make('skills')
                    ->getStateUsing(fn (FreelancerProfileRevision $record): array => Skill::query()->whereIn('id', $record->content()->skillIds())->pluck('name')->all())
                    ->badge()
                    ->placeholder('—'),
                TextEntry::make('experiences')
                    ->label('Work history')
                    ->getStateUsing(fn (FreelancerProfileRevision $record): array => collect($record->content()->experiences())
                        ->map(fn (array $experience): string => "{$experience['role']} at {$experience['company']} ({$experience['started_at']} – ".($experience['ended_at'] ?? 'present').')')
                        ->all())
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('consultations')
                    ->getStateUsing(fn (FreelancerProfileRevision $record): array => self::consultationLines($record->content()))
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('services')
                    ->getStateUsing(fn (FreelancerProfileRevision $record): array => self::serviceLines($record->content()))
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('highlights')
                    ->getStateUsing(fn (FreelancerProfileRevision $record): array => self::highlightLines($record->content()))
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('last_change_request')->placeholder('—')->columnSpanFull(),
                TextEntry::make('submitted_at')->dateTime()->placeholder('—'),
                TextEntry::make('applied_at')->label('Published at')->dateTime()->placeholder('—'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('profile.user'))
            ->columns([
                TextColumn::make('profile.user.name')->label('Freelancer')->searchable(),
                TextColumn::make('payload.professional_title')->label('Professional title'),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('submitted_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ProfileStatus::cases())->mapWithKeys(fn (ProfileStatus $status) => [$status->value => ucfirst(str_replace('-', ' ', $status->value))])->all())
                    ->default(ProfileStatus::PendingReview->value),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approveAction(),
                self::requestChangesAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfileRevisions::route('/'),
            'view' => ViewProfileRevision::route('/{record}'),
        ];
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve and publish')
            ->color('success')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->requiresConfirmation()
            ->schema([
                Textarea::make('comment')->label('Note (optional)')->maxLength(1000),
            ])
            ->visible(fn (FreelancerProfileRevision $record): bool => $record->status === ProfileStatus::PendingReview)
            ->action(fn (FreelancerProfileRevision $record, array $data) => RunsAdminAction::run(
                fn () => app(ApproveProfile::class)($record, auth()->user(), $data['comment'] ?? null),
                'Profile approved and published.',
            ));
    }

    public static function requestChangesAction(): Action
    {
        return Action::make('requestChanges')
            ->label('Request changes')
            ->color('warning')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->requiresConfirmation()
            ->schema([
                Textarea::make('comment')->label('What should the freelancer change?')->required()->maxLength(2000),
            ])
            ->visible(fn (FreelancerProfileRevision $record): bool => $record->status === ProfileStatus::PendingReview)
            ->action(fn (FreelancerProfileRevision $record, array $data) => RunsAdminAction::run(
                fn () => app(RequestProfileChanges::class)($record, auth()->user(), $data['comment']),
                'Changes requested.',
            ));
    }

    /**
     * @return list<string>
     */
    public static function consultationLines(ProfileContent $content): array
    {
        return collect($content->consultationOffers())
            ->filter(fn (array $offer): bool => $offer['enabled'])
            ->map(fn (array $offer): string => collect([
                ConsultationType::from($offer['type'])->label(),
                "{$offer['duration_minutes']} min",
                $offer['price_cents'] > 0 ? '$'.number_format($offer['price_cents'] / 100, 2) : 'Free',
                implode(', ', ConsultationSchedule::summary($offer['availability'])).' ('.$content->get('timezone').')',
                $offer['description'],
            ])->filter()->implode(' · '))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function serviceLines(ProfileContent $content): array
    {
        return collect($content->services())
            ->map(function (array $service): string {
                $mode = PricingMode::from($service['pricing_mode']);
                $price = $mode->requiresPrice() ? ' $'.number_format(($service['price_cents'] ?? 0) / 100, 2) : '';

                return "{$service['title']} · {$mode->label()}{$price}";
            })
            ->all();
    }

    /**
     * Highlights with their media counts, video links and external links, so a moderator can
     * see everything the freelancer wants to publish.
     *
     * @return list<string>
     */
    public static function highlightLines(ProfileContent $content): array
    {
        return collect($content->highlights())
            ->map(function (array $highlight): string {
                $parts = [$highlight['title']];

                $images = count($highlight['gallery']) + ($highlight['cover_path'] ? 1 : 0);

                if ($images > 0) {
                    $parts[] = $images.' '.Str::plural('image', $images);
                }

                if ($highlight['video_urls'] !== []) {
                    $parts[] = 'videos: '.implode(', ', $highlight['video_urls']);
                }

                if ($highlight['links'] !== []) {
                    $parts[] = 'links: '.collect($highlight['links'])->map(fn (array $link): string => ($link['label'] ?: 'link')." ({$link['url']})")->implode(', ');
                }

                return implode(' · ', $parts);
            })
            ->all();
    }

    /**
     * Human-readable names of the fields a revision changes relative to what is live.
     *
     * @return list<string>
     */
    private static function changedFieldLabels(FreelancerProfileRevision $revision): array
    {
        $profile = $revision->profile;

        if (! $profile->isPublished() || $revision->status === ProfileStatus::Published) {
            return [];
        }

        $labels = [
            'photo_path' => 'Photo',
            'professional_title' => 'Professional title',
            'about' => 'About',
            'location' => 'Location',
            'timezone' => 'Time zone',
            'website' => 'Website',
            'social_links' => 'Social links',
            'category_ids' => 'Categories',
            'skill_ids' => 'Skills',
            'experiences' => 'Work history',
            'highlights' => 'Highlights',
            'services' => 'Services',
            'consultation_offers' => 'Consultations',
        ];

        return collect($revision->content()->changedKeys($profile->liveContent()))
            ->map(fn (string $key): string => $labels[$key] ?? $key)
            ->values()
            ->all();
    }
}
