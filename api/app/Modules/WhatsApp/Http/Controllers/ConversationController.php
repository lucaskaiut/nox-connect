<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\WhatsApp\Http\Requests\AssignConversationRequest;
use App\Modules\WhatsApp\Http\Requests\SendMessageRequest;
use App\Modules\WhatsApp\Http\Requests\StoreNoteRequest;
use App\Modules\WhatsApp\Http\Resources\ConversationResource;
use App\Modules\WhatsApp\Http\Resources\MessageResource;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppNote;
use App\Modules\WhatsApp\Services\ConversationService;
use App\Modules\WhatsApp\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends ApiController
{
    public function __construct(
        private readonly ConversationService $service,
        private readonly MessageService $messageService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WhatsAppConversation::class);

        $filters = $request->only([
            'status', 'assigned_to', 'stage_id', 'tag_id', 'search', 'per_page',
        ]);

        $filters['unassigned'] = $request->boolean('unassigned');

        return $this->paginated(
            ConversationResource::collection($this->service->list($filters))
        );
    }

    public function show(WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $this->service->markAsRead($conversation);

        return $this->success(ConversationResource::make($this->service->find($conversation->id)));
    }

    public function sendMessage(SendMessageRequest $request, WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $message = $this->messageService->sendText($conversation, $request->validated('content'));

        if ($message->status === 'failed') {
            $error = $message->metadata['error_message'] ?? 'Erro ao enviar mensagem.';

            return $this->success(MessageResource::make($message), $error, 500);
        }

        return $this->created(MessageResource::make($message), 'Mensagem enviada.');
    }

    public function assign(AssignConversationRequest $request, WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $this->service->assign($conversation, $request->validated('user_id'));

        return $this->success(null, 'Atendimento atribuído.');
    }

    public function transfer(AssignConversationRequest $request, WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $this->service->transfer($conversation, $request->validated('user_id'));

        return $this->success(null, 'Atendimento transferido.');
    }

    public function removeAssignment(WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $this->service->removeAssignment($conversation);

        return $this->success(null, 'Responsável removido.');
    }

    public function close(WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $this->service->close($conversation);

        return $this->success(null, 'Conversa finalizada.');
    }

    public function reopen(WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $this->service->reopen($conversation);

        return $this->success(null, 'Conversa reaberta.');
    }

    public function notes(WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $notes = $conversation->notes()->with('user')->get();

        return $this->success($notes);
    }

    public function storeNote(StoreNoteRequest $request, WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $note = WhatsAppNote::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->getKey(),
            'content' => $request->validated('content'),
        ]);

        return $this->created($note->load('user'), 'Nota adicionada.');
    }

    public function tags(WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return $this->success($conversation->tags);
    }

    public function syncTags(Request $request, WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $conversation->tags()->sync($request->input('tag_ids', []));

        return $this->success($conversation->tags()->get(), 'Tags atualizadas.');
    }

    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', WhatsAppConversation::class);

        return $this->success($this->service->getStats());
    }
}
