<?php

use App\Modules\ACL\Http\Controllers\RoleController;
use App\Modules\ApiToken\Http\Controllers\ApiTokenController;
use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Shared\Http\Controllers\FileUploadController;
use App\Modules\Tenant\Http\Controllers\TenantController;
use App\Modules\User\Http\Controllers\UserController;
use App\Modules\Webhook\Http\Controllers\WebhookController;
use App\Modules\WhatsApp\Http\Controllers\ConversationController;
use App\Modules\WhatsApp\Http\Controllers\KanbanController;
use App\Modules\WhatsApp\Http\Controllers\MetaWebhookController;
use App\Modules\WhatsApp\Http\Controllers\TagController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppConfigController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth.multi:sanctum', 'tenant'])->group(function (): void {
    Route::get('tenant', [TenantController::class, 'show'])->middleware('permission:tenant.read');
    Route::match(['put', 'patch'], 'tenant', [TenantController::class, 'update'])->middleware('permission:tenant.update');

    Route::get('users', [UserController::class, 'index'])->middleware('permission:user.read');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:user.read');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:user.delete');

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:role.read');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:role.create');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:role.read');
    Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role.delete');

    Route::get('api-tokens', [ApiTokenController::class, 'index'])->middleware('permission:api-token.read');
    Route::post('api-tokens', [ApiTokenController::class, 'store'])->middleware('permission:api-token.create');
    Route::delete('api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->middleware('permission:api-token.delete');

    Route::get('webhooks', [WebhookController::class, 'index'])->middleware('permission:webhook.read');
    Route::get('webhooks/events', [WebhookController::class, 'events'])->middleware('permission:webhook.read');
    Route::post('webhooks', [WebhookController::class, 'store'])->middleware('permission:webhook.create');
    Route::get('webhooks/{webhook}', [WebhookController::class, 'show'])->middleware('permission:webhook.read');
    Route::match(['put', 'patch'], 'webhooks/{webhook}', [WebhookController::class, 'update'])->middleware('permission:webhook.update');
    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->middleware('permission:webhook.delete');
    Route::get('webhooks/{webhook}/logs', [WebhookController::class, 'logs'])->middleware('permission:webhook.read');

    Route::post('uploads', FileUploadController::class);

    Route::get('whatsapp/conversations/stats', [ConversationController::class, 'stats'])->middleware('permission:whatsapp.conversation.read');

    Route::get('whatsapp-configs', [WhatsAppConfigController::class, 'index'])->middleware('permission:whatsapp-config.read');
    Route::post('whatsapp-configs', [WhatsAppConfigController::class, 'store'])->middleware('permission:whatsapp-config.create');
    Route::get('whatsapp-configs/{config}', [WhatsAppConfigController::class, 'show'])->middleware('permission:whatsapp-config.read');
    Route::match(['put', 'patch'], 'whatsapp-configs/{config}', [WhatsAppConfigController::class, 'update'])->middleware('permission:whatsapp-config.update');
    Route::delete('whatsapp-configs/{config}', [WhatsAppConfigController::class, 'destroy'])->middleware('permission:whatsapp-config.delete');
    Route::post('whatsapp-configs/{config}/test-connection', [WhatsAppConfigController::class, 'testConnection'])->middleware('permission:whatsapp-config.update');
    Route::post('whatsapp-configs/{config}/toggle', [WhatsAppConfigController::class, 'toggle'])->middleware('permission:whatsapp-config.update');

    Route::get('whatsapp/conversations', [ConversationController::class, 'index'])->middleware('permission:whatsapp.conversation.read');
    Route::get('whatsapp/conversations/{conversation}', [ConversationController::class, 'show'])->middleware('permission:whatsapp.conversation.read');
    Route::post('whatsapp/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])->middleware('permission:whatsapp.conversation.update');
    Route::post('whatsapp/conversations/{conversation}/assign', [ConversationController::class, 'assign'])->middleware('permission:whatsapp.conversation.update');
    Route::post('whatsapp/conversations/{conversation}/transfer', [ConversationController::class, 'transfer'])->middleware('permission:whatsapp.conversation.update');
    Route::post('whatsapp/conversations/{conversation}/remove-assignment', [ConversationController::class, 'removeAssignment'])->middleware('permission:whatsapp.conversation.update');
    Route::post('whatsapp/conversations/{conversation}/close', [ConversationController::class, 'close'])->middleware('permission:whatsapp.conversation.update');
    Route::post('whatsapp/conversations/{conversation}/reopen', [ConversationController::class, 'reopen'])->middleware('permission:whatsapp.conversation.update');
    Route::get('whatsapp/conversations/{conversation}/notes', [ConversationController::class, 'notes'])->middleware('permission:whatsapp.conversation.read');
    Route::post('whatsapp/conversations/{conversation}/notes', [ConversationController::class, 'storeNote'])->middleware('permission:whatsapp.conversation.update');
    Route::get('whatsapp/conversations/{conversation}/tags', [ConversationController::class, 'tags'])->middleware('permission:whatsapp.conversation.read');
    Route::post('whatsapp/conversations/{conversation}/tags', [ConversationController::class, 'syncTags'])->middleware('permission:whatsapp.conversation.update');

    Route::get('whatsapp/tags', [TagController::class, 'index'])->middleware('permission:whatsapp.tag.read');
    Route::post('whatsapp/tags', [TagController::class, 'store'])->middleware('permission:whatsapp.tag.create');
    Route::get('whatsapp/tags/{tag}', [TagController::class, 'show'])->middleware('permission:whatsapp.tag.read');
    Route::match(['put', 'patch'], 'whatsapp/tags/{tag}', [TagController::class, 'update'])->middleware('permission:whatsapp.tag.update');
    Route::delete('whatsapp/tags/{tag}', [TagController::class, 'destroy'])->middleware('permission:whatsapp.tag.delete');

    Route::get('whatsapp/kanban/board', [KanbanController::class, 'board'])->middleware('permission:whatsapp.kanban.read');
    Route::get('whatsapp/kanban/stages', [KanbanController::class, 'stages'])->middleware('permission:whatsapp.kanban.read');
    Route::post('whatsapp/kanban/stages', [KanbanController::class, 'storeStage'])->middleware('permission:whatsapp.kanban.update');
    Route::match(['put', 'patch'], 'whatsapp/kanban/stages/{stage}', [KanbanController::class, 'updateStage'])->middleware('permission:whatsapp.kanban.update');
    Route::delete('whatsapp/kanban/stages/{stage}', [KanbanController::class, 'deleteStage'])->middleware('permission:whatsapp.kanban.update');
    Route::post('whatsapp/kanban/conversations/{conversation}/move', [KanbanController::class, 'moveConversation'])->middleware('permission:whatsapp.kanban.update');
    Route::get('whatsapp/kanban/conversations/{conversation}/history', [KanbanController::class, 'conversationHistory'])->middleware('permission:whatsapp.kanban.read');
    Route::post('whatsapp/kanban/seed-defaults', [KanbanController::class, 'seedDefaults'])->middleware('permission:whatsapp.kanban.update');
});

Route::match(['get', 'post'], 'webhooks/whatsapp/{config}', [MetaWebhookController::class, 'receive']);
