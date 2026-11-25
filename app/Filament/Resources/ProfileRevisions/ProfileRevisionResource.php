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
use App\Models\FreelancerProfileRevision;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use App\Models\ProfessionalRole;
use App\Models\Skill;
use App\Models\Tool;
use BackedEnum;
use Closure;
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
                ...self::detailEntries(fn (FreelancerProfileRevision $record): ProfileContent => $record->content()),
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
     * The catalog selections and the rates and availability answers of a profile, shared by the
     * revision view and the live profile view.
     *
     * @param  Closure(mixed): ProfileContent  $content  Resolves the content to show from the record.
     * @return list<TextEntry>
     */
    public static function detailEntries(Closure $content): array
    {
        $names = fn (string $model, array $ids): array => $model::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
        $dollars = fn (?int $cents): ?string => $cents === null ? null : '$'.number_format($cents / 100, 2);
        $yesNo = fn (?bool $value): ?string => $value === null ? null : ($value ? 'Yes' : 'No');

        return [
            TextEntry::make('function')
                ->getStateUsing(fn ($record): ?string => ProfessionalFunction::query()->whereKey($content($record)->professionalFunctionId())->value('name'))
                ->placeholder('—'),
            TextEntry::make('roles')
                ->label('Roles')
                ->getStateUsing(function ($record) use ($content): array {
                    $roleIds = $content($record)->roleIds();
                    $roles = ProfessionalRole::query()->whereIn('id', $roleIds)->pluck('name', 'id');

                    return collect($roleIds)
                        ->map(fn (int $id, int $position): string => ['main-role', 'secondary-role', 'third-role'][$position].': '.$roles->get($id))
                        ->all();
                })
                ->badge()
                ->placeholder('—'),
            TextEntry::make('skills')
                ->getStateUsing(fn ($record): array => $names(Skill::class, $content($record)->skillIds()))
                ->badge()
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('tools')
                ->getStateUsing(fn ($record): array => $names(Tool::class, $content($record)->toolIds()))
                ->badge()
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('industries')
                ->getStateUsing(fn ($record): array => $names(Industry::class, $content($record)->industryIds()))
                ->badge()
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('hourly_rate')
                ->getStateUsing(fn ($record): ?string => $dollars($content($record)->get('hourly_rate_cents')))
                ->placeholder('—'),
            TextEntry::make('weekly_hours')
                ->label('Hours per week')
                ->getStateUsing(fn ($record): ?string => $content($record)->get('weekly_hours'))
                ->placeholder('—'),
            TextEntry::make('open_to_full_time')
                ->label('Open to full-time')
                ->getStateUsing(fn ($record): ?string => $yesNo($content($record)->get('open_to_full_time')))
                ->placeholder('—'),
            TextEntry::make('monthly_retainers')
                ->getStateUsing(function ($record) use ($content, $dollars, $yesNo): ?string {
                    $content = $content($record);

                    return collect([
                        $yesNo($content->get('offers_monthly_retainers')),
                        $content->get('monthly_retainers_from_cents') ? 'from '.$dollars($content->get('monthly_retainers_from_cents')).' / month' : null,
                    ])->filter()->implode(', ') ?: null;
                })
                ->placeholder('—'),
            TextEntry::make('monthly_retainers_description')
                ->label('Retainer scope')
                ->getStateUsing(fn ($record): ?string => $content($record)->get('monthly_retainers_description'))
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('best_fit')
                ->label('Best fit for')
                ->getStateUsing(fn ($record): array => $content($record)->get('best_fit'))
                ->listWithLineBreaks()
                ->placeholder('—')
                ->columnSpanFull(),
        ];
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
            'professional_function_id' => 'Function',
            'role_ids' => 'Roles',
            'skill_ids' => 'Skills',
            'tool_ids' => 'Tools',
            'industry_ids' => 'Industries',
            'hourly_rate_cents' => 'Hourly rate',
            'offers_monthly_retainers' => 'Monthly retainers',
            'monthly_retainers_description' => 'Retainer scope',
            'monthly_retainers_from_cents' => 'Retainer starting price',
            'weekly_hours' => 'Hours per week',
            'open_to_full_time' => 'Open to full-time',
            'best_fit' => 'Best fit',
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
