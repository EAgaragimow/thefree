<?php

namespace App\Filament\Widgets;

use App\Enums\ProfileStatus;
use App\Filament\Resources\ProfileRevisions\ProfileRevisionResource;
use App\Models\FreelancerProfileRevision;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The first profile revisions in the review queue: the oldest ones waiting for approval.
 */
class PendingProfileRevisionsWidget extends TableWidget
{
    public const LISTED = 3;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Profile Revisions')
            ->description('The first '.self::LISTED.' profile revisions waiting for review.')
            ->query(fn () => FreelancerProfileRevision::query()
                ->where('status', ProfileStatus::PendingReview)
                ->with('profile.user')
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->limit(self::LISTED))
            ->columns([
                TextColumn::make('profile.user.name')->label('Freelancer'),
                TextColumn::make('payload.professional_title')->label('Professional title'),
                TextColumn::make('submitted_at')->label('Submitted')->dateTime(),
            ])
            ->recordUrl(fn (FreelancerProfileRevision $record): string => ProfileRevisionResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('viewAll')
                    ->label('View all')
                    ->url(ProfileRevisionResource::getUrl('index'))
                    ->link(),
            ])
            ->emptyStateHeading('No revisions are waiting for review')
            ->paginated(false);
    }
}
