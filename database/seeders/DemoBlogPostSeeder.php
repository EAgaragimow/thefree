<?php

namespace Database\Seeders;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A handful of demo Blog / Resources articles (five published, one draft) for trying out the blog.
 * Local environment only. Running it again updates the same articles instead of adding more. It is
 * not part of DatabaseSeeder: `php artisan db:seed --class=DemoBlogPostSeeder`.
 */
class DemoBlogPostSeeder extends Seeder
{
    /**
     * @var list<array{title: string, excerpt: string, paragraphs: list<string>, draft?: bool}>
     */
    private const ARTICLES = [
        [
            'title' => 'How to brief a freelancer so the first draft is nearly right',
            'excerpt' => 'A good brief saves a round of revisions. Here is what to include before you send the first message.',
            'paragraphs' => [
                'Start with the outcome, not the task. "We need a landing page that explains pricing to first-time buyers" tells a designer far more than "design a page".',
                'Share the constraints early: budget range, deadline, brand guidelines and anything that has already been tried and rejected.',
                'Add two or three examples you like and one you do not, and say why. Taste is hard to describe, easy to point at.',
                'Finally, name the person who gives the final approval. Feedback from five people at once is the most common reason a project drifts.',
            ],
        ],
        [
            'title' => 'Hourly or fixed price: choosing the right pricing mode',
            'excerpt' => 'Both models work, but they fit different kinds of work. A quick guide to picking one without regret.',
            'paragraphs' => [
                'Fixed price suits work with a clear scope and a clear end: a logo, a one-off audit, a migration with a checklist.',
                'Hourly suits work where the scope will move as you learn: ongoing support, exploratory research, retained advisory.',
                'If you are unsure, start with a short paid discovery step at an hourly rate and fix the price for everything after it.',
            ],
        ],
        [
            'title' => 'Five questions to ask in a first consultation',
            'excerpt' => 'A thirty-minute call can tell you most of what you need to know. Use it well with these five questions.',
            'paragraphs' => [
                'Ask how they would approach the problem, not whether they can do it. The reasoning matters more than the yes.',
                'Ask what they would need from you in the first week, and what usually slows projects like yours down.',
                'Ask for a similar project they are proud of, and what they would do differently today.',
                'Ask how they prefer to communicate and how often they will update you.',
                'Ask what would make them say no to this project. A freelancer who can answer honestly is one you can trust.',
            ],
        ],
        [
            'title' => 'Working across time zones without losing a day',
            'excerpt' => 'Distributed teams ship fine when handoffs are explicit. A few habits that make the difference.',
            'paragraphs' => [
                'Agree on a small overlap window, even one hour, for questions that cannot wait.',
                'Write handoffs as if the reader cannot reply: what is done, what is next, what is blocked.',
                'Keep decisions in writing, in one place, so nobody has to ask what was agreed on the call.',
            ],
        ],
        [
            'title' => 'Reviewing work: feedback that moves a project forward',
            'excerpt' => 'Specific, prioritised feedback gets better results in fewer rounds. How to give it.',
            'paragraphs' => [
                'Separate must-fix from nice-to-have, and say which is which.',
                'Describe the problem you see rather than the solution you imagine; the freelancer may know a better one.',
                'Review in one batch instead of a stream of messages, so the work can be planned.',
            ],
        ],
        [
            'title' => 'Planning a content calendar with an external team',
            'excerpt' => 'A draft for the next release of the blog: how to plan a quarter of content with freelancers.',
            'paragraphs' => [
                'Pick three themes for the quarter and map every article to one of them.',
                'Book writers and editors for the whole quarter so deadlines are agreed once.',
            ],
            'draft' => true,
        ],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('The demo articles can only be seeded in the local environment.');
        }

        $author = User::query()->where('role', 'admin')->orderBy('id')->first();

        foreach (self::ARTICLES as $index => $article) {
            $isDraft = $article['draft'] ?? false;

            BlogPost::query()->updateOrCreate(
                ['slug' => Str::slug($article['title'])],
                [
                    'title' => $article['title'],
                    'excerpt' => $article['excerpt'],
                    'body' => '<p>'.implode('</p><p>', $article['paragraphs']).'</p>',
                    'status' => $isDraft ? BlogPostStatus::Draft : BlogPostStatus::Published,
                    'published_at' => $isDraft ? null : now()->subDays(($index + 1) * 3)->startOfDay(),
                    'author_id' => $author?->id,
                ],
            );
        }
    }
}
