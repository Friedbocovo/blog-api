<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PostSeeder extends Seeder
{
    /**
     * Seed 10 test posts (6 published + 4 drafts, 2 pinned) with tags.
     * Requirements 2: post management.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@blog.com')->firstOrFail();

        $tags = Tag::all()->keyBy('slug');

        $postsData = [
            // Published + pinned (2)
            [
                'title'        => 'Introduction à Laravel : le framework PHP moderne',
                'content'      => '<h2>Pourquoi Laravel ?</h2><p>Laravel est un framework PHP élégant qui facilite le développement d\'applications web robustes. Il intègre nativement l\'ORM Eloquent, le moteur de templates Blade, et bien d\'autres outils.</p><p>Dans cet article, nous allons découvrir les bases de Laravel et comment démarrer un projet from scratch.</p><h3>Installation</h3><p>Commencez par installer Laravel via Composer :</p><pre><code>composer create-project laravel/laravel mon-projet</code></pre>',
                'excerpt'      => 'Découvrez pourquoi Laravel est le framework PHP le plus populaire et comment démarrer votre premier projet.',
                'status'       => 'published',
                'pinned'       => true,
                'published_at' => Carbon::now()->subDays(30),
                'views_count'  => 1240,
                'tags'         => ['laravel', 'php'],
            ],
            [
                'title'        => 'Construire une API REST avec React et TypeScript',
                'content'      => '<h2>Introduction</h2><p>Dans ce tutoriel complet, nous allons construire une application full-stack avec React côté client et une API REST Laravel côté serveur. Nous utiliserons TypeScript pour bénéficier d\'un typage statique robuste.</p><h3>Prérequis</h3><ul><li>Node.js 18+</li><li>PHP 8.2+</li><li>Composer</li></ul>',
                'excerpt'      => 'Un guide complet pour construire une application full-stack moderne avec React, TypeScript et une API Laravel.',
                'status'       => 'published',
                'pinned'       => true,
                'published_at' => Carbon::now()->subDays(20),
                'views_count'  => 890,
                'tags'         => ['react', 'javascript', 'laravel'],
            ],
            // Published, not pinned (4)
            [
                'title'        => 'Les middlewares Laravel en profondeur',
                'content'      => '<h2>Qu\'est-ce qu\'un middleware ?</h2><p>Les middlewares Laravel sont des filtres HTTP qui interceptent les requêtes entrantes. Ils sont parfaits pour l\'authentification, la journalisation, la validation, etc.</p><p>Dans cet article, nous allons créer un middleware personnalisé pour limiter l\'accès selon le rôle utilisateur.</p>',
                'excerpt'      => 'Apprenez à créer et utiliser des middlewares Laravel pour filtrer les requêtes HTTP efficacement.',
                'status'       => 'published',
                'pinned'       => false,
                'published_at' => Carbon::now()->subDays(15),
                'views_count'  => 534,
                'tags'         => ['laravel', 'php'],
            ],
            [
                'title'        => 'Déployer une application Laravel avec Docker',
                'content'      => '<h2>Dockeriser Laravel</h2><p>Docker simplifie le déploiement en encapsulant votre application et ses dépendances dans des conteneurs portables. Dans ce guide, nous allons configurer un environnement Docker complet pour Laravel.</p><h3>Structure du projet</h3><pre><code>docker-compose.yml\ndockerfile\nnginx/default.conf</code></pre>',
                'excerpt'      => 'Un guide pratique pour containeriser votre application Laravel avec Docker et Docker Compose.',
                'status'       => 'published',
                'pinned'       => false,
                'published_at' => Carbon::now()->subDays(10),
                'views_count'  => 721,
                'tags'         => ['laravel', 'devops'],
            ],
            [
                'title'        => 'Les hooks React : useState, useEffect et useContext',
                'content'      => '<h2>Introduction aux hooks</h2><p>Les hooks React ont révolutionné la façon d\'écrire des composants fonctionnels. Ils permettent d\'utiliser l\'état et d\'autres fonctionnalités React sans écrire une classe.</p><h3>useState</h3><p>useState est le hook le plus basique. Il vous permet d\'ajouter un état local à un composant fonctionnel.</p>',
                'excerpt'      => 'Maîtrisez les hooks React fondamentaux : useState, useEffect et useContext avec des exemples concrets.',
                'status'       => 'published',
                'pinned'       => false,
                'published_at' => Carbon::now()->subDays(7),
                'views_count'  => 445,
                'tags'         => ['react', 'javascript'],
            ],
            [
                'title'        => 'Sécuriser votre API avec Laravel Sanctum',
                'content'      => '<h2>Pourquoi Sanctum ?</h2><p>Laravel Sanctum offre un système d\'authentification léger basé sur des tokens API pour les SPA et les applications mobiles. Il s\'intègre parfaitement avec l\'écosystème Laravel.</p><h3>Installation</h3><pre><code>composer require laravel/sanctum\nphp artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"</code></pre>',
                'excerpt'      => 'Protégez votre API Laravel avec Sanctum : tokens, middleware et bonnes pratiques de sécurité.',
                'status'       => 'published',
                'pinned'       => false,
                'published_at' => Carbon::now()->subDays(3),
                'views_count'  => 312,
                'tags'         => ['laravel', 'php'],
            ],
            // Drafts (4)
            [
                'title'        => 'Introduction à CI/CD avec GitHub Actions',
                'content'      => '<h2>Automatiser votre workflow</h2><p>GitHub Actions permet d\'automatiser vos pipelines de CI/CD directement depuis votre dépôt GitHub. Dans cet article, nous allons configurer un pipeline complet : tests, build, et déploiement.</p>',
                'excerpt'      => 'Automatisez vos tests et déploiements avec GitHub Actions pour une livraison continue fiable.',
                'status'       => 'draft',
                'pinned'       => false,
                'published_at' => null,
                'views_count'  => 0,
                'tags'         => ['devops'],
            ],
            [
                'title'        => 'Gestion d\'état avec Zustand dans React',
                'content'      => '<h2>Pourquoi Zustand ?</h2><p>Zustand est une solution de gestion d\'état minimaliste pour React. Contrairement à Redux, sa configuration est simple et son API est intuitive, tout en restant très performant.</p>',
                'excerpt'      => 'Simplifiez la gestion d\'état de vos applications React avec Zustand, l\'alternative légère à Redux.',
                'status'       => 'draft',
                'pinned'       => false,
                'published_at' => null,
                'views_count'  => 0,
                'tags'         => ['react', 'javascript'],
            ],
            [
                'title'        => 'Optimisation des requêtes Eloquent avec le Lazy Loading',
                'content'      => '<h2>Le problème N+1</h2><p>Le problème N+1 est l\'une des erreurs de performance les plus courantes avec les ORM. Il survient quand vous chargez des relations de façon paresseuse dans une boucle, générant N requêtes supplémentaires.</p>',
                'excerpt'      => 'Découvrez comment éviter le problème N+1 et optimiser vos requêtes Eloquent pour de meilleures performances.',
                'status'       => 'draft',
                'pinned'       => false,
                'published_at' => null,
                'views_count'  => 0,
                'tags'         => ['laravel', 'php'],
            ],
            [
                'title'        => 'Créer une application desktop avec Electron et React',
                'content'      => '<h2>Electron + React</h2><p>Electron permet de créer des applications desktop multiplateformes avec des technologies web. Combiné à React, il offre une expérience de développement moderne pour les applications desktop.</p>',
                'excerpt'      => 'Construisez des applications desktop multiplateformes avec Electron et React step by step.',
                'status'       => 'draft',
                'pinned'       => false,
                'published_at' => null,
                'views_count'  => 0,
                'tags'         => ['javascript', 'react', 'devops'],
            ],
        ];

        foreach ($postsData as $data) {
            $tagSlugs = $data['tags'];
            unset($data['tags']);

            $post = Post::firstOrCreate(
                ['title' => $data['title']],
                array_merge($data, ['user_id' => $admin->id])
            );

            // Attach tags
            $tagIds = [];
            foreach ($tagSlugs as $slug) {
                if (isset($tags[$slug])) {
                    $tagIds[] = $tags[$slug]->id;
                }
            }
            $post->tags()->sync($tagIds);
        }

        $this->command->info('PostSeeder: 10 posts created (6 published, 4 drafts, 2 pinned).');
    }
}
