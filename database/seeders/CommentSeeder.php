<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\CommentMention;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    /**
     * Seed comments with nested replies and @mentions.
     * Requirements 5: comment management.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@blog.com')->firstOrFail();

        // Create a few visitor users for seeding comments
        $visitors = [];
        $visitorData = [
            ['name' => 'Alice Martin',  'email' => 'alice@example.com'],
            ['name' => 'Bob Dupont',    'email' => 'bob@example.com'],
            ['name' => 'Claire Morel',  'email' => 'claire@example.com'],
        ];

        foreach ($visitorData as $data) {
            $visitors[] = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'     => $data['name'],
                    'password' => bcrypt('password'),
                    'role'     => 'visitor',
                ]
            );
        }

        $posts = Post::all();

        foreach ($posts as $post) {
            // Create 3 root comments per post
            $rootComments = [];

            // Root comment 1 — by visitor 0
            $comment1 = Comment::create([
                'post_id' => $post->id,
                'user_id' => $visitors[0]->id,
                'content' => "Super article sur \"{$post->title}\" ! Très bien expliqué, merci pour ce partage.",
            ]);
            $rootComments[] = $comment1;

            // Root comment 2 — by visitor 1
            $comment2 = Comment::create([
                'post_id' => $post->id,
                'user_id' => $visitors[1]->id,
                'content' => "J'ai une question à propos de cet article. Est-ce que vous pourriez approfondir la partie sur la configuration ?",
            ]);
            $rootComments[] = $comment2;

            // Root comment 3 — by admin, with @mention of alice
            $comment3Content = "@{$visitors[0]->name} Merci pour votre enthousiasme ! Je prépare justement une suite à cet article.";
            $comment3 = Comment::create([
                'post_id' => $post->id,
                'user_id' => $admin->id,
                'content' => $comment3Content,
            ]);
            // Create mention record for alice
            CommentMention::create([
                'comment_id'          => $comment3->id,
                'mentioned_user_id'   => $visitors[0]->id,
            ]);
            $rootComments[] = $comment3;

            // 2 replies per root comment
            foreach ($rootComments as $index => $rootComment) {
                // Reply 1 — by visitor 2
                $reply1Content = "@{$rootComment->user->name} Tout à fait d'accord avec toi ! Très utile cet article.";
                $reply1 = Comment::create([
                    'post_id'   => $post->id,
                    'user_id'   => $visitors[2]->id,
                    'parent_id' => $rootComment->id,
                    'content'   => $reply1Content,
                ]);
                // Create mention for the root comment author
                CommentMention::create([
                    'comment_id'        => $reply1->id,
                    'mentioned_user_id' => $rootComment->user_id,
                ]);

                // Reply 2 — by admin
                $reply2Content = "Merci pour vos retours ! N'hésitez pas à poser d'autres questions.";
                Comment::create([
                    'post_id'   => $post->id,
                    'user_id'   => $admin->id,
                    'parent_id' => $rootComment->id,
                    'content'   => $reply2Content,
                ]);
            }
        }

        $this->command->info('CommentSeeder: comments with nested replies and @mentions created.');
    }
}
