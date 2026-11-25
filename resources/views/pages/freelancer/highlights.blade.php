<?php

use App\Actions\Profiles\ProfileContent;
use App\Actions\Profiles\UpdateProfileContent;
use App\Actions\Profiles\VideoEmbed;
use App\Enums\ProfileStatus;
use App\Models\FreelancerProfile;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Highlights')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public int $profileId = 0;

    /** The uid of the highlight being edited, or null when adding a new one. */
    public ?string $editingUid = null;

    public string $title = '';

    public string $description = '';

    public ?string $cover_path = null;

    public $coverUpload = null;

    /** @var list<string> Paths of gallery images that are already stored. */
    public array $gallery = [];

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $galleryUploads = [];

    /** @var list<string> */
    public array $video_urls = [];

    /** @var list<array{label: string, url: string}> */
    public array $links = [];

    public function mount(): void
    {
        $profile = auth()->user()->freelancerProfile;

        abort_if($profile === null, 404);

        $this->profileId = $profile->id;
    }

    public function profile(): FreelancerProfile
    {
        return FreelancerProfile::query()
            ->with('openRevision')
            ->where('user_id', auth()->id())
            ->findOrFail($this->profileId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function highlights(): array
    {
        return $this->profile()->workingContent()->highlights();
    }

    public function isLocked(): bool
    {
        return $this->profile()->openRevision?->status === ProfileStatus::PendingReview;
    }

    #[On('profile-revision-changed')]
    public function resetForm(): void
    {
        $this->reset('editingUid', 'title', 'description', 'cover_path', 'coverUpload', 'gallery', 'galleryUploads', 'video_urls', 'links');
        $this->resetValidation();
    }

    public function startCreating(): void
    {
        $this->resetForm();

        Flux::modal('highlight-form')->show();
    }

    public function startEditing(string $uid): void
    {
        $highlight = collect($this->highlights())->firstWhere('uid', $uid);

        abort_if($highlight === null, 404);

        $this->resetForm();
        $this->editingUid = $uid;
        $this->title = $highlight['title'];
        $this->description = (string) $highlight['description'];
        $this->cover_path = $highlight['cover_path'];
        $this->gallery = $highlight['gallery'];
        $this->video_urls = $highlight['video_urls'];
        $this->links = $highlight['links'];

        Flux::modal('highlight-form')->show();
    }

    public function removeCover(): void
    {
        $this->cover_path = null;
        $this->coverUpload = null;
    }

    public function removeGalleryImage(int $index): void
    {
        unset($this->gallery[$index]);
        $this->gallery = array_values($this->gallery);
    }

    public function removeGalleryUpload(int $index): void
    {
        unset($this->galleryUploads[$index]);
        $this->galleryUploads = array_values($this->galleryUploads);
    }

    public function addVideo(): void
    {
        if (count($this->video_urls) < ProfileContent::MAX_VIDEOS) {
            $this->video_urls[] = '';
        }
    }

    public function removeVideo(int $index): void
    {
        unset($this->video_urls[$index]);
        $this->video_urls = array_values($this->video_urls);
    }

    public function addLink(): void
    {
        if (count($this->links) < ProfileContent::MAX_LINKS) {
            $this->links[] = ['label' => '', 'url' => ''];
        }
    }

    public function removeLink(int $index): void
    {
        unset($this->links[$index]);
        $this->links = array_values($this->links);
    }

    public function save(UpdateProfileContent $update): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'coverUpload' => ['nullable', 'image', 'max:4096'],
            'galleryUploads' => ['array'],
            'galleryUploads.*' => ['image', 'max:4096'],
            'video_urls' => ['array', 'max:'.ProfileContent::MAX_VIDEOS],
            'video_urls.*' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && ! VideoEmbed::isSupported($value)) {
                        $fail('Use a link from YouTube, Vimeo or Loom.');
                    }
                },
            ],
            'links' => ['array', 'max:'.ProfileContent::MAX_LINKS],
            'links.*.label' => ['nullable', 'string', 'max:60'],
            'links.*.url' => ['nullable', 'url:http,https', 'max:255'],
        ], [
            'galleryUploads.*.image' => 'Gallery files must be images.',
            'galleryUploads.*.max' => 'Each gallery image must be at most 4 MB.',
            'links.*.url.url' => 'Enter a full link starting with http:// or https://.',
        ]);

        $highlights = $this->highlights();

        if ($this->editingUid === null && count($highlights) >= ProfileContent::MAX_HIGHLIGHTS) {
            $this->addError('title', 'You can add at most '.ProfileContent::MAX_HIGHLIGHTS.' highlights.');

            return;
        }

        if (count($this->gallery) + count($this->galleryUploads) > ProfileContent::MAX_GALLERY_IMAGES) {
            $this->addError('galleryUploads', 'A highlight can have at most '.ProfileContent::MAX_GALLERY_IMAGES.' gallery images.');

            return;
        }

        $stored = [];

        $cover = $this->cover_path;

        if ($this->coverUpload) {
            $cover = $stored[] = $this->coverUpload->store('highlights', 'public');
        }

        $gallery = $this->gallery;

        foreach ($this->galleryUploads as $upload) {
            $gallery[] = $stored[] = $upload->store('highlights', 'public');
        }

        $values = [
            'title' => trim($this->title),
            'description' => $this->description,
            'cover_path' => $cover,
            'gallery' => $gallery,
            'video_urls' => array_values(array_filter(array_map('trim', $this->video_urls))),
            'links' => $this->links,
        ];

        $found = false;

        $highlights = array_map(function (array $highlight) use ($values, &$found): array {
            if ($highlight['uid'] !== $this->editingUid) {
                return $highlight;
            }

            $found = true;

            return [...$highlight, ...$values];
        }, $highlights);

        if ($this->editingUid !== null && ! $found) {
            Storage::disk('public')->delete($stored);

            abort(404);
        }

        if ($this->editingUid === null) {
            $highlights[] = $values;
        }

        if (! $this->persist($update, $highlights)) {
            Storage::disk('public')->delete($stored);

            return;
        }

        Flux::modal('highlight-form')->close();
        $this->resetForm();
    }

    public function delete(string $uid, UpdateProfileContent $update): void
    {
        $this->persist($update, array_values(array_filter(
            $this->highlights(),
            fn (array $highlight): bool => $highlight['uid'] !== $uid,
        )));
    }

    public function sort(string $uid, int $position, UpdateProfileContent $update): void
    {
        $highlights = $this->highlights();
        $moved = collect($highlights)->firstWhere('uid', $uid);

        abort_if($moved === null, 404);

        $others = array_values(array_filter($highlights, fn (array $highlight): bool => $highlight['uid'] !== $uid));
        array_splice($others, max(0, min($position, count($others))), 0, [$moved]);

        $this->persist($update, $others);
    }

    /**
     * @param  list<array<string, mixed>>  $highlights
     */
    private function persist(UpdateProfileContent $update, array $highlights): bool
    {
        try {
            $update($this->profile(), ['highlights' => $highlights]);
        } catch (\DomainException $exception) {
            $this->addError('title', $exception->getMessage());

            return false;
        }

        $this->dispatch('profile-content-saved');

        return true;
    }

    public function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
};
?>

<div class="mx-auto w-full max-w-3xl space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Highlights</flux:heading>
            <flux:text class="mt-1">Showcase your best work. Clients open each highlight in a pop-up with its full description, images, videos and links.</flux:text>
        </div>

        @unless ($this->isLocked())
            <flux:button variant="primary" icon="plus" wire:click="startCreating">Add highlight</flux:button>
        @endunless
    </div>

    <livewire:profile-revision-bar />

    @php($highlights = $this->highlights())

    @if ($highlights === [])
        <flux:card>
            <flux:heading>No highlights yet</flux:heading>
            <flux:text class="mt-1">Add a highlight to show clients what you have done.</flux:text>
        </flux:card>
    @else
        <ul wire:sort="sort" class="space-y-3">
            @foreach ($highlights as $highlight)
                <li wire:key="highlight-{{ $highlight['uid'] }}" wire:sort:item="{{ $highlight['uid'] }}">
                    <flux:card class="flex items-start gap-4">
                        <div wire:sort:handle class="mt-1 cursor-grab text-zinc-400" title="Drag to reorder" aria-hidden="true">
                            <flux:icon.bars-3 class="size-5" />
                        </div>

                        @if ($highlight['cover_path'])
                            <img src="{{ $this->url($highlight['cover_path']) }}" alt="" class="size-16 shrink-0 rounded object-cover">
                        @endif

                        <div class="min-w-0 flex-1">
                            <flux:heading>{{ $highlight['title'] }}</flux:heading>
                            <flux:text class="mt-1 line-clamp-2">{{ $highlight['description'] }}</flux:text>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if (count($highlight['gallery']))
                                    <flux:badge size="sm" icon="photo">{{ count($highlight['gallery']) }}</flux:badge>
                                @endif
                                @if (count($highlight['video_urls']))
                                    <flux:badge size="sm" icon="video-camera">{{ count($highlight['video_urls']) }}</flux:badge>
                                @endif
                                @if (count($highlight['links']))
                                    <flux:badge size="sm" icon="link">{{ count($highlight['links']) }}</flux:badge>
                                @endif
                            </div>
                        </div>

                        @unless ($this->isLocked())
                            <div class="flex gap-2" wire:sort:ignore>
                                <flux:button size="sm" wire:click="startEditing('{{ $highlight['uid'] }}')">Edit</flux:button>
                                <flux:button size="sm" variant="danger" wire:click="delete('{{ $highlight['uid'] }}')" wire:confirm="Remove this highlight?">Remove</flux:button>
                            </div>
                        @endunless
                    </flux:card>
                </li>
            @endforeach
        </ul>
    @endif

    <flux:modal name="highlight-form" class="md:w-[42rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingUid === null ? 'Add highlight' : 'Edit highlight' }}</flux:heading>

            <flux:field>
                <flux:label>Title *</flux:label>
                <flux:input wire:model="title" placeholder="Rebranding for Acme" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="description" rows="6" placeholder="The brief, your role, the result." />
                <flux:error name="description" />
            </flux:field>

            <div class="space-y-2">
                <flux:label>Cover image</flux:label>

                @if ($coverUpload && $coverUpload->isPreviewable())
                    <img src="{{ $coverUpload->temporaryUrl() }}" alt="" class="h-32 rounded object-cover">
                @elseif ($cover_path)
                    <img src="{{ $this->url($cover_path) }}" alt="" class="h-32 rounded object-cover">
                @endif

                <div class="flex items-center gap-3">
                    <input type="file" wire:model="coverUpload" accept="image/*" class="text-sm">
                    @if ($coverUpload || $cover_path)
                        <flux:button type="button" size="sm" variant="ghost" wire:click="removeCover">Remove</flux:button>
                    @endif
                </div>
                <flux:error name="coverUpload" />
            </div>

            <div class="space-y-2">
                <flux:label>Gallery images</flux:label>

                <div class="flex flex-wrap gap-3">
                    @foreach ($gallery as $index => $path)
                        <div wire:key="gallery-{{ $index }}-{{ $path }}" class="relative">
                            <img src="{{ $this->url($path) }}" alt="" class="size-20 rounded object-cover">
                            <button type="button" wire:click="removeGalleryImage({{ $index }})" class="absolute -end-2 -top-2 rounded-full bg-zinc-900 px-1.5 text-xs text-white" aria-label="Remove image">&times;</button>
                        </div>
                    @endforeach

                    @foreach ($galleryUploads as $index => $upload)
                        <div wire:key="upload-{{ $index }}" class="relative">
                            @if ($upload->isPreviewable())
                                <img src="{{ $upload->temporaryUrl() }}" alt="" class="size-20 rounded object-cover">
                            @else
                                <div class="grid size-20 place-items-center rounded bg-zinc-100 text-xs dark:bg-zinc-700">Not an image</div>
                            @endif
                            <button type="button" wire:click="removeGalleryUpload({{ $index }})" class="absolute -end-2 -top-2 rounded-full bg-zinc-900 px-1.5 text-xs text-white" aria-label="Remove image">&times;</button>
                        </div>
                    @endforeach
                </div>

                <input type="file" wire:model="galleryUploads" accept="image/*" multiple class="text-sm">
                <flux:error name="galleryUploads" />
                <flux:error name="galleryUploads.*" />
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:label>Videos (YouTube, Vimeo or Loom links)</flux:label>
                    <flux:button type="button" size="sm" icon="plus" wire:click="addVideo">Add</flux:button>
                </div>

                @foreach ($video_urls as $index => $video)
                    <div wire:key="video-{{ $index }}" class="space-y-1">
                        <div class="flex gap-2">
                            <div class="min-w-0 flex-1">
                                <flux:input wire:model="video_urls.{{ $index }}" type="url" placeholder="https://www.youtube.com/watch?v=…" />
                            </div>
                            <flux:button type="button" size="sm" variant="ghost" wire:click="removeVideo({{ $index }})">Remove</flux:button>
                        </div>
                        <flux:error name="video_urls.{{ $index }}" />
                    </div>
                @endforeach
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:label>Links</flux:label>
                    <flux:button type="button" size="sm" icon="plus" wire:click="addLink">Add</flux:button>
                </div>

                @foreach ($links as $index => $link)
                    <div wire:key="link-{{ $index }}" class="space-y-1">
                        <div class="flex gap-2">
                            <div class="w-1/3 min-w-0">
                                <flux:input wire:model="links.{{ $index }}.label" placeholder="Label" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <flux:input wire:model="links.{{ $index }}.url" type="url" placeholder="https://" />
                            </div>
                            <flux:button type="button" size="sm" variant="ghost" wire:click="removeLink({{ $index }})">Remove</flux:button>
                        </div>
                        <flux:error name="links.{{ $index }}.url" />
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">
                    <span wire:loading.remove wire:target="save">Save highlight</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
