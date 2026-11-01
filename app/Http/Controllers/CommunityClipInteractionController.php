<?php

namespace App\Http\Controllers;

use App\Models\CommunityClip;
use App\Models\CommunityClipComment;
use App\Models\CommunityClipLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunityClipInteractionController extends Controller
{
    private function publicClip(CommunityClip $clip): void
    {
        abort_unless($clip->is_visible && ! $clip->trashed(), 404);
    }

    public function comments(Request $request, CommunityClip $clip): JsonResponse
    {
        $this->publicClip($clip);
        $comments = $clip->comments()->with('user:id,nick')->orderByDesc('id')->paginate(20);

        return response()->json([
            'items' => $comments->getCollection()->map(fn (CommunityClipComment $comment) => $this->formatComment($comment, $request))->all(),
            'next_page' => $comments->hasMorePages() ? $comments->currentPage() + 1 : null,
            'count' => $clip->comments()->count(),
        ]);
    }

    public function storeComment(Request $request, CommunityClip $clip): JsonResponse
    {
        $this->publicClip($clip);
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:1000']]);
        $body = trim($data['body']);
        if ($body === '') return response()->json(['message' => 'Escribe un comentario.'], 422);
        $comment = $clip->comments()->create(['user_id' => $request->user()->id, 'body' => $body]);
        $comment->load('user:id,nick');
        return response()->json(['item' => $this->formatComment($comment, $request), 'count' => $clip->comments()->count()], 201);
    }

    public function destroyComment(Request $request, CommunityClip $clip, CommunityClipComment $comment): JsonResponse
    {
        $this->publicClip($clip);
        abort_unless($comment->community_clip_id === $clip->id, 404);
        abort_unless((int) $comment->user_id === (int) $request->user()->id
            || $request->user()->hasRole('admin') || $request->user()->can('community-clips.delete'), 403);
        $comment->delete();
        return response()->json(['count' => $clip->comments()->count()]);
    }

    public function like(Request $request, CommunityClip $clip): JsonResponse
    {
        $this->publicClip($clip);
        $liked = DB::transaction(function () use ($clip, $request): bool {
            $where = ['community_clip_id' => $clip->id, 'user_id' => $request->user()->id];
            $existing = CommunityClipLike::query()->where($where)->lockForUpdate()->first();
            if ($existing) {
                $existing->delete();
                return false;
            }
            CommunityClipLike::query()->firstOrCreate($where);
            return true;
        });
        return response()->json(['liked' => $liked, 'count' => $clip->likes()->count()]);
    }

    private function formatComment(CommunityClipComment $comment, Request $request): array
    {
        $user = $request->user();
        return [
            'id' => $comment->id,
            'member' => $comment->user?->nick ?? 'Miembro',
            'body' => $comment->body,
            'date' => $comment->created_at?->format('d/m/Y H:i'),
            'can_delete' => $user && ((int) $comment->user_id === (int) $user->id || $user->hasRole('admin') || $user->can('community-clips.delete')),
        ];
    }
}
