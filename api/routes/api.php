<?php

use App\Modules\ACL\Http\Controllers\RoleController;
use App\Modules\ApiToken\Http\Controllers\ApiTokenController;
use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Billing\Http\Controllers\InvoiceController;
use App\Modules\Billing\Http\Controllers\PaymentMethodController;
use App\Modules\Billing\Http\Controllers\PlanController;
use App\Modules\Billing\Http\Controllers\SubscriptionController;
use App\Modules\Shared\Http\Controllers\FileUploadController;
use App\Modules\Tenant\Http\Controllers\TenantController;
use App\Modules\User\Http\Controllers\UserController;
use App\Modules\Webhook\Http\Controllers\WebhookController;
use App\Modules\WhatsApp\Http\Controllers\ConversationController;
use App\Modules\WhatsApp\Http\Controllers\KanbanController;
use App\Modules\WhatsApp\Http\Controllers\MessageTemplateController;
use App\Modules\WhatsApp\Http\Controllers\TagController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppConnectionController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppWebhookController;
use App\Modules\Onboarding\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('select-tenant', [AuthController::class, 'selectTenant']);
    });
});

Route::get('billing/plans/catalog', [PlanController::class, 'catalog']);
Route::get('plans/public', [PlanController::class, 'catalog']);
Route::get('billing/gateways', [SubscriptionController::class, 'gateways']);
Route::get('payment-methods', [PaymentMethodController::class, 'index']);

/*
 * Pagamento e regularização ficam acessíveis mesmo com assinatura PAST_DUE/SUSPENDED.
 * Onboarding e leitura de cobranças não exigem subscription.active.
 */
Route::middleware(['auth.multi:sanctum', 'tenant'])->group(function (): void {
    Route::middleware('tenant.child')->group(function (): void {
        Route::get('billing/subscription', [SubscriptionController::class, 'show'])->middleware('permission:subscription.read');
        Route::get('billing/invoices', [InvoiceController::class, 'index'])->middleware('permission:invoice.read');
        Route::get('billing/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoice.read');
        Route::post('billing/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->middleware('permission:invoice.read');
    });

    Route::get('onboarding', [OnboardingController::class, 'show']);
    Route::post('onboarding/company', [OnboardingController::class, 'completeCompany'])->middleware('permission:tenant.update');
    Route::get('onboarding/whatsapp/initialize', [OnboardingController::class, 'initializeWhatsApp'])->middleware('permission:whatsapp-config.create|tenant.update');
    Route::post('onboarding/whatsapp/complete', [OnboardingController::class, 'completeWhatsApp'])->middleware('permission:whatsapp-config.create|tenant.update');
    Route::post('onboarding/finish', [OnboardingController::class, 'finish'])->middleware('permission:tenant.update');
});

Route::middleware(['auth.multi:sanctum', 'tenant', 'onboarding.completed'])->group(function (): void {
    Route::get('billing/plans', [PlanController::class, 'index'])->middleware('permission:plan.read');
    Route::post('billing/plans', [PlanController::class, 'store'])->middleware('permission:plan.create');
    Route::get('billing/plans/{plan}', [PlanController::class, 'show'])->middleware('permission:plan.read');
    Route::match(['put', 'patch'], 'billing/plans/{plan}', [PlanController::class, 'update'])->middleware('permission:plan.update');
    Route::delete('billing/plans/{plan}', [PlanController::class, 'destroy'])->middleware('permission:plan.delete');

    Route::middleware('tenant.child')->group(function (): void {
        Route::post('billing/subscription', [SubscriptionController::class, 'store'])->middleware('permission:subscription.update');
        Route::post('billing/subscription/change-plan', [SubscriptionController::class, 'changePlan'])->middleware('permission:subscription.update');
        Route::post('billing/subscription/cancel', [SubscriptionController::class, 'cancel'])->middleware('permission:subscription.update');
        Route::post('billing/subscription/reactivate', [SubscriptionController::class, 'reactivate'])->middleware('permission:subscription.update');
    });
});

Route::middleware(['auth.multi:sanctum', 'tenant', 'onboarding.completed', 'subscription.active'])->group(function (): void {
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
    Route::post('api-tokens', [ApiTokenController::class, 'store'])->middleware(['permission:api-token.create', 'throttle:api-tokens-create']);
    Route::delete('api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->middleware('permission:api-token.delete');

    Route::get('webhooks', [WebhookController::class, 'index'])->middleware('permission:webhook.read');
    Route::get('webhooks/events', [WebhookController::class, 'events'])->middleware('permission:webhook.read');
    Route::post('webhooks', [WebhookController::class, 'store'])->middleware('permission:webhook.create');
    Route::get('webhooks/{webhook}', [WebhookController::class, 'show'])->middleware('permission:webhook.read');
    Route::match(['put', 'patch'], 'webhooks/{webhook}', [WebhookController::class, 'update'])->middleware('permission:webhook.update');
    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->middleware('permission:webhook.delete');
    Route::get('webhooks/{webhook}/logs', [WebhookController::class, 'logs'])->middleware('permission:webhook.read');

    Route::post('uploads', FileUploadController::class)->middleware(['permission:media.upload', 'throttle:uploads']);

    Route::middleware('tenant.child')->group(function (): void {
        Route::get('whatsapp/conversations/stats', [ConversationController::class, 'stats'])->middleware('permission:whatsapp.conversation.read');

        Route::get('whatsapp/connection', [WhatsAppConnectionController::class, 'show'])->middleware('permission:whatsapp-config.read');
        Route::post('whatsapp/connection', [WhatsAppConnectionController::class, 'connect'])->middleware('permission:whatsapp-config.create');
        Route::delete('whatsapp/connection', [WhatsAppConnectionController::class, 'disconnect'])->middleware('permission:whatsapp-config.delete');
        Route::post('whatsapp/connection/test', [WhatsAppConnectionController::class, 'test'])->middleware('permission:whatsapp-config.update');
        Route::get('whatsapp/connection/webhook-logs', [WhatsAppConnectionController::class, 'webhookLogs'])->middleware('permission:whatsapp-config.read');

        Route::get('whatsapp/conversations', [ConversationController::class, 'index'])->middleware('permission:whatsapp.conversation.read');
        Route::get('whatsapp/conversations/{conversation}', [ConversationController::class, 'show'])->middleware('permission:whatsapp.conversation.read');
        Route::get('whatsapp/conversations/{conversation}/messages', [ConversationController::class, 'messages'])->middleware('permission:whatsapp.conversation.read');
        Route::post('whatsapp/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])->middleware(['permission:whatsapp.conversation.update', 'throttle:whatsapp-send']);
        Route::post('whatsapp/conversations/{conversation}/assign', [ConversationController::class, 'assign'])->middleware('permission:whatsapp.conversation.update');
        Route::post('whatsapp/conversations/{conversation}/transfer', [ConversationController::class, 'transfer'])->middleware('permission:whatsapp.conversation.update');
        Route::post('whatsapp/conversations/{conversation}/remove-assignment', [ConversationController::class, 'removeAssignment'])->middleware('permission:whatsapp.conversation.update');
        Route::post('whatsapp/conversations/{conversation}/close', [ConversationController::class, 'close'])->middleware('permission:whatsapp.conversation.update');
        Route::post('whatsapp/conversations/{conversation}/reopen', [ConversationController::class, 'reopen'])->middleware('permission:whatsapp.conversation.update');
        Route::get('whatsapp/conversations/{conversation}/window', [ConversationController::class, 'windowStatus'])->middleware('permission:whatsapp.conversation.read');
        Route::post('whatsapp/conversations/{conversation}/template', [ConversationController::class, 'sendTemplate'])->middleware('permission:whatsapp.conversation.update');
        Route::get('whatsapp/conversations/{conversation}/notes', [ConversationController::class, 'notes'])->middleware('permission:whatsapp.conversation.read');
        Route::post('whatsapp/conversations/{conversation}/notes', [ConversationController::class, 'storeNote'])->middleware('permission:whatsapp.conversation.update');
        Route::get('whatsapp/conversations/{conversation}/tags', [ConversationController::class, 'tags'])->middleware('permission:whatsapp.conversation.read');
        Route::post('whatsapp/conversations/{conversation}/tags', [ConversationController::class, 'syncTags'])->middleware('permission:whatsapp.conversation.update');

        Route::get('whatsapp/tags', [TagController::class, 'index'])->middleware('permission:whatsapp.tag.read');
        Route::post('whatsapp/tags', [TagController::class, 'store'])->middleware('permission:whatsapp.tag.create');
        Route::get('whatsapp/tags/{tag}', [TagController::class, 'show'])->middleware('permission:whatsapp.tag.read');
        Route::match(['put', 'patch'], 'whatsapp/tags/{tag}', [TagController::class, 'update'])->middleware('permission:whatsapp.tag.update');
        Route::delete('whatsapp/tags/{tag}', [TagController::class, 'destroy'])->middleware('permission:whatsapp.tag.delete');

        Route::get('whatsapp/templates', [MessageTemplateController::class, 'index'])->middleware('permission:whatsapp.template.read');
        Route::post('whatsapp/templates', [MessageTemplateController::class, 'store'])->middleware('permission:whatsapp.template.create');
        Route::get('whatsapp/templates/{templateId}', [MessageTemplateController::class, 'show'])->middleware('permission:whatsapp.template.read');
        Route::match(['put', 'patch'], 'whatsapp/templates/{templateId}', [MessageTemplateController::class, 'update'])->middleware('permission:whatsapp.template.update');
        Route::delete('whatsapp/templates', [MessageTemplateController::class, 'destroy'])->middleware('permission:whatsapp.template.delete');

        Route::get('whatsapp/kanban/board', [KanbanController::class, 'board'])->middleware('permission:whatsapp.kanban.read');
        Route::get('whatsapp/kanban/stages', [KanbanController::class, 'stages'])->middleware('permission:whatsapp.kanban.read');
        Route::get('whatsapp/kanban/stages/{stage}/conversations', [KanbanController::class, 'stageConversations'])->middleware('permission:whatsapp.kanban.read');
        Route::post('whatsapp/kanban/stages', [KanbanController::class, 'storeStage'])->middleware('permission:whatsapp.kanban.update');
        Route::match(['put', 'patch'], 'whatsapp/kanban/stages/{stage}', [KanbanController::class, 'updateStage'])->middleware('permission:whatsapp.kanban.update');
        Route::delete('whatsapp/kanban/stages/{stage}', [KanbanController::class, 'deleteStage'])->middleware('permission:whatsapp.kanban.update');
        Route::post('whatsapp/kanban/conversations/{conversation}/move', [KanbanController::class, 'moveConversation'])->middleware('permission:whatsapp.kanban.update');
        Route::get('whatsapp/kanban/conversations/{conversation}/history', [KanbanController::class, 'conversationHistory'])->middleware('permission:whatsapp.kanban.read');
        Route::post('whatsapp/kanban/seed-defaults', [KanbanController::class, 'seedDefaults'])->middleware('permission:whatsapp.kanban.update');
    });
});

Route::match(['get', 'post'], 'webhooks/whatsapp/{tenantUuid}', [WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:webhook-inbound');
