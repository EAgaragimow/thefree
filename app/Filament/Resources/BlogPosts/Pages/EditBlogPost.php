<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Actions\Blog\PublishBlogPostChanges;
use App\Actions\Blog\SaveBlogPostDraft;
use App\Enums\BlogPostStatus;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Models\BlogPost;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * @property BlogPost $record
 */
class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return match ($this->record->statusLabel()) {
            'In Draft' => 'In Draft: your changes are saved but clients still see the published version until you publish them.',
            'Published' => 'Published. Any change you make is kept as a draft until you publish it.',
            default => 'Draft: not visible to clients.',
        };
    }

    /**
     * The form shows the pending edits when there are any, not the published version.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...$this->record->editableContent()];
    }

    /**
     * Called as the editor changes a field: edits to a published article are kept as a draft, so
     * the article becomes In Draft right away and clients keep seeing the published version.
     * Content that does not validate yet is not saved.
     */
    public function autosaveDraft(): void
    {
        if ($this->record->status !== BlogPostStatus::Published) {
            return;
        }

        try {
            $content = $this->form->getState();
        } catch (ValidationException) {
            return;
        }

        app(SaveBlogPostDraft::class)($this->record, $content);
    }

    /**
     * Saving a published article publishes the edits; a draft article is simply saved.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($record->status === BlogPostStatus::Published) {
            app(PublishBlogPostChanges::class)($record, $data);

            return $record;
        }

        return parent::handleRecordUpdate($record, $data);
    }

    protected function getSaveFormAction(): Action
    {
        $action = parent::getSaveFormAction();

        return $this->record->status === BlogPostStatus::Published
            ? $action->label('Publish changes')
            : $action;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->record->status === BlogPostStatus::Published
            ? 'Changes published.'
            : parent::getSavedNotificationTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            BlogPostResource::publishAction(),
            BlogPostResource::discardChangesAction()->after(fn () => $this->refreshFormData(BlogPost::CONTENT_FIELDS)),
            BlogPostResource::unpublishAction(),
            DeleteAction::make(),
        ];
    }
}
