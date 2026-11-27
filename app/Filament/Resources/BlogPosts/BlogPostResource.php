<?php

namespace App\Filament\Resources\BlogPosts;

use App\Actions\Blog\DiscardBlogPostChanges;
use App\Actions\Blog\PublishBlogPost;
use App\Actions\Blog\PublishBlogPostChanges;
use App\Actions\Blog\UnpublishBlogPost;
use App\Enums\BlogPostStatus;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Filament\Support\RunsAdminAction;
use App\Models\BlogPost;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;
use UnitEnum;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $modelLabel = 'article';

    protected static ?string $navigationLabel = 'Blog / Resources';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?string $old, Component $livewire): void {
                        if (blank($get('slug')) || $get('slug') === Str::slug((string) $old)) {
                            $set('slug', Str::slug((string) $state));
                        }

                        self::autosave($livewire);
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Component $livewire) => self::autosave($livewire)),
                Textarea::make('excerpt')
                    ->required()
                    ->maxLength(500)
                    ->rows(3)
                    ->helperText('Shown to every client. Free clients see only this.')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Component $livewire) => self::autosave($livewire))
                    ->columnSpanFull(),
                FileUpload::make('cover_path')
                    ->label('Cover image')
                    ->image()
                    ->disk('public')
                    ->directory('blog-covers')
                    ->maxSize(4096)
                    ->live()
                    ->afterStateUpdated(fn (Component $livewire) => self::autosave($livewire))
                    ->columnSpanFull(),
                RichEditor::make('body')
                    ->required()
                    ->helperText('Shown to Paid clients only.')
                    ->live(debounce: 1500)
                    ->afterStateUpdated(fn (Component $livewire) => self::autosave($livewire))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('author'))
            ->columns([
                ImageColumn::make('cover_path')->label('Cover')->disk('public')->toggleable(),
                TextColumn::make('title')->searchable()->sortable()->limit(60),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (BlogPost $record): string => $record->statusLabel())
                    ->color(fn (BlogPost $record): string|array => $record->statusColor() === 'orange' ? Color::Orange : $record->statusColor()),
                TextColumn::make('author.name')->label('Author')->toggleable(),
                TextColumn::make('published_at')->label('Published')->dateTime()->sortable()->placeholder('—'),
                TextColumn::make('created_at')->label('Created')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['draft' => 'Draft', 'published' => 'Published', 'in_draft' => 'In Draft'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'draft' => $query->where('status', BlogPostStatus::Draft),
                        'published' => $query->where('status', BlogPostStatus::Published)->whereNull('draft'),
                        'in_draft' => $query->where('status', BlogPostStatus::Published)->whereNotNull('draft'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                self::publishAction(),
                self::publishChangesAction(),
                self::discardChangesAction(),
                self::unpublishAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Keep an editor's changes to a published article as a draft the moment they are made, so
     * nothing half-edited reaches clients. Only the edit page of a published article does this.
     */
    private static function autosave(Component $livewire): void
    {
        if ($livewire instanceof EditBlogPost) {
            $livewire->autosaveDraft();
        }
    }

    public static function publishChangesAction(): Action
    {
        return Action::make('publishChanges')
            ->label('Publish changes')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (BlogPost $record): bool => $record->hasUnpublishedChanges())
            ->action(fn (BlogPost $record) => RunsAdminAction::run(
                fn () => app(PublishBlogPostChanges::class)($record),
                'Changes published.',
            ));
    }

    public static function discardChangesAction(): Action
    {
        return Action::make('discardChanges')
            ->label('Discard changes')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('The article goes back to its published version and the unpublished edits are lost.')
            ->visible(fn (BlogPost $record): bool => $record->hasUnpublishedChanges())
            ->action(fn (BlogPost $record) => RunsAdminAction::run(
                fn () => app(DiscardBlogPostChanges::class)($record),
                'Changes discarded.',
            ));
    }

    public static function publishAction(): Action
    {
        return Action::make('publish')
            ->icon(Heroicon::OutlinedEye)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (BlogPost $record): bool => $record->status === BlogPostStatus::Draft)
            ->action(fn (BlogPost $record) => RunsAdminAction::run(
                fn () => app(PublishBlogPost::class)($record),
                'Article published.',
            ));
    }

    public static function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (BlogPost $record): bool => $record->status === BlogPostStatus::Published)
            ->action(fn (BlogPost $record) => RunsAdminAction::run(
                fn () => app(UnpublishBlogPost::class)($record),
                'Article unpublished.',
            ));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogPosts::route('/'),
            'create' => CreateBlogPost::route('/create'),
            'edit' => EditBlogPost::route('/{record}/edit'),
        ];
    }
}
