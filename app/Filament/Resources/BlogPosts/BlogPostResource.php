<?php

namespace App\Filament\Resources\BlogPosts;

use App\Actions\Blog\PublishBlogPost;
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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
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
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?string $old): void {
                        if (blank($get('slug')) || $get('slug') === Str::slug((string) $old)) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                Textarea::make('excerpt')
                    ->required()
                    ->maxLength(500)
                    ->rows(3)
                    ->helperText('Shown to every client. Free clients see only this.')
                    ->columnSpanFull(),
                FileUpload::make('cover_path')
                    ->label('Cover image')
                    ->image()
                    ->disk('public')
                    ->directory('blog-covers')
                    ->maxSize(4096)
                    ->columnSpanFull(),
                RichEditor::make('body')
                    ->required()
                    ->helperText('Shown to Paid clients only.')
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
                    ->color(fn (BlogPostStatus $state): string => $state === BlogPostStatus::Published ? 'success' : 'gray')
                    ->formatStateUsing(fn (BlogPostStatus $state): string => ucfirst($state->value)),
                TextColumn::make('author.name')->label('Author')->toggleable(),
                TextColumn::make('published_at')->label('Published')->dateTime()->sortable()->placeholder('—'),
                TextColumn::make('created_at')->label('Created')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BlogPostStatus::cases())->mapWithKeys(fn (BlogPostStatus $status) => [$status->value => ucfirst($status->value)])->all()),
            ])
            ->recordActions([
                self::publishAction(),
                self::unpublishAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
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
