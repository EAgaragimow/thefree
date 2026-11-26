<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\FreelancerApplications\FreelancerApplicationResource;
use App\Models\FreelancerApplication;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The first applications in the moderation queue: the oldest ones still waiting for a decision.
 */
class PendingApplicationsWidget extends TableWidget
{
    public const LISTED = 3;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Freelancer Applications')
            ->description('The first '.self::LISTED.' applications waiting for a decision.')
            ->query(fn () => FreelancerApplication::query()
                ->where('status', ApplicationStatus::Pending)
                ->oldest()
                ->limit(self::LISTED))
            ->columns([
                TextColumn::make('full_name')->label('Name'),
                TextColumn::make('email'),
                TextColumn::make('specialty')->label('Main role'),
                TextColumn::make('created_at')->label('Submitted')->dateTime(),
            ])
            ->recordUrl(fn (FreelancerApplication $record): string => FreelancerApplicationResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('viewAll')
                    ->label('View all')
                    ->url(FreelancerApplicationResource::getUrl('index'))
                    ->link(),
            ])
            ->emptyStateHeading('No applications are waiting')
            ->paginated(false);
    }
}
