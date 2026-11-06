<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\WorkOrders\AddComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group('Work orders')]
#[Authenticated]
final class WorkOrderCommentController extends Controller
{
    /**
     * Add a comment
     *
     * Anyone involved in the work order. Only staff may post internal comments.
     */
    #[ResponseFromApiResource(CommentResource::class, WorkOrderComment::class, status: 201, with: ['user'])]
    public function store(StoreCommentRequest $request, WorkOrder $workOrder, AddComment $addComment): JsonResponse
    {
        /** @var User $author */
        $author = $request->user();

        $comment = $addComment->handle($workOrder, $author, $request->string('body')->trim()->toString(), $request->boolean('is_internal'));

        return (new CommentResource($comment->load('user')))->response()->setStatusCode(201);
    }
}
