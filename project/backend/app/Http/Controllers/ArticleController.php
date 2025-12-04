<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticleController extends Controller
{
    /**
     * Display a listing of articles.
     */
    public function index(Request $request)
    {
        $articles = Article::all();

        $articles = $articles->map(function ($article) use ($request) {
            if ($request->has('performance_test')) {
                usleep(30000); // 30ms par article pour simuler le coût du N+1
            }

            return [
                'id' => $article->id,
                'title' => $article->title,
                'content' => substr($article->content, 0, 200) . '...',
                'author' => $article->author->name,
                'comments_count' => $article->comments->count(),
                'published_at' => $article->published_at,
                'created_at' => $article->created_at,
            ];
        });

        return response()->json($articles);
    }

    /**
     * Display the specified article.
     */
    public function show($id)
    {
        $article = Article::with(['author', 'comments.user'])->findOrFail($id);

        return response()->json([
            'id' => $article->id,
            'title' => $article->title,
            'content' => $article->content,
            'author' => $article->author->name,
            'author_id' => $article->author->id,
            'image_path' => $article->image_path,
            'published_at' => $article->published_at,
            'created_at' => $article->created_at,
            'comments' => $article->comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'user' => $comment->user->name,
                    'created_at' => $comment->created_at,
                ];
            }),
        ]);
    }

    /**
     * Search articles using Eloquent with prepared statements.
     * Protects against SQL injection by using query builder.
     */
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json([]);
        }

        // Normalize query (remove accents)
        $normalizedQuery = $this->normalizeString(strtolower($query));
        $searchPattern = '%' . $normalizedQuery . '%';

        // Use Eloquent query builder to prevent SQL injection
        // whereRaw with bindings is safe, but using where() with JSON search is even better
        $articles = Article::where(function ($q) use ($normalizedQuery, $searchPattern) {
            // Search in title with accent normalization using COLLATE
            $q->whereRaw(
                "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(title, 'é', 'e'), 'è', 'e'), 'ê', 'e'), 'à', 'a'), 'ç', 'c')) LIKE ?",
                [$searchPattern]
            )->orWhereRaw(
                "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(content, 'é', 'e'), 'è', 'e'), 'ê', 'e'), 'à', 'a'), 'ç', 'c')) LIKE ?",
                [$searchPattern]
            );
        })->get();

        $results = $articles->map(function ($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'content' => substr($article->content, 0, 200),
                'published_at' => $article->published_at,
            ];
        });

        return response()->json($results);
    }

    /**
     * Normalise une chaîne en supprimant uniquement les accents
     * tout en conservant les caractères spéciaux.
     * Exemple : "élève #1" → "eleve #1"
     *
     * @param string $string
     * @return string
     */
    private function normalizeString(string $string): string
    {
        // Décomposer les caractères Unicode (NFD)
        $normalized = \Normalizer::normalize($string, \Normalizer::FORM_D);

        // Supprimer uniquement les accents (marques diacritiques)
        $normalized = preg_replace('/\p{Mn}/u', '', $normalized);

        return $normalized;
    }


    /**
     * Store a newly created article.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'author_id' => 'required|exists:users,id',
            'image_path' => 'nullable|string',
        ]);

        $article = Article::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'author_id' => $validated['author_id'],
            'image_path' => $validated['image_path'] ?? null,
            'published_at' => now(),
        ]);

        return response()->json($article, 201);
    }

    /**
     * Update the specified article.
     */
    public function update(Request $request, $id)
    {
        $article = Article::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|max:255',
            'content' => 'sometimes|required',
        ]);

        $article->update($validated);

        return response()->json($article);
    }

    /**
     * Remove the specified article.
     */
    public function destroy($id)
    {
        $article = Article::findOrFail($id);
        $article->delete();

        return response()->json(['message' => 'Article deleted successfully']);
    }
}
