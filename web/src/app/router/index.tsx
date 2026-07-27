import { lazy } from 'react'
import { createBrowserRouter, Navigate } from 'react-router'
import { AuthGuard } from '@/app/guards/AuthGuard'
import { GuestGuard } from '@/app/guards/GuestGuard'
import { OnboardingGuard } from '@/app/guards/OnboardingGuard'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { AppLayout } from '@/app/layouts/AppLayout'
import { AuthLayout } from '@/app/layouts/AuthLayout'
import { Permission } from '@/shared/constants/permissions'
import { NotFoundPage } from './NotFoundPage'

const LoginPage = lazy(() => import('@/modules/auth/pages/LoginPage'))
const RegisterPage = lazy(() => import('@/modules/auth/pages/RegisterPage'))
const PaymentPendingPage = lazy(() => import('@/modules/auth/pages/PaymentPendingPage'))
const DashboardPage = lazy(() => import('@/modules/dashboard/pages/DashboardPage'))
const UsersListPage = lazy(() => import('@/modules/users/pages/UsersListPage'))
const UserCreatePage = lazy(() => import('@/modules/users/pages/UserCreatePage'))
const UserEditPage = lazy(() => import('@/modules/users/pages/UserEditPage'))
const RolesListPage = lazy(() => import('@/modules/roles/pages/RolesListPage'))
const RoleCreatePage = lazy(() => import('@/modules/roles/pages/RoleCreatePage'))
const RoleEditPage = lazy(() => import('@/modules/roles/pages/RoleEditPage'))
const ApiTokensListPage = lazy(() => import('@/modules/api-tokens/pages/ApiTokensListPage'))
const ApiTokenCreatePage = lazy(() => import('@/modules/api-tokens/pages/ApiTokenCreatePage'))
const WebhooksListPage = lazy(() => import('@/modules/webhooks/pages/WebhooksListPage'))
const WebhookCreatePage = lazy(() => import('@/modules/webhooks/pages/WebhookCreatePage'))
const WebhookEditPage = lazy(() => import('@/modules/webhooks/pages/WebhookEditPage'))
const WhatsAppConnectionPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppConnectionPage'))
const WhatsAppChatPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppChatPage'))
const WhatsAppConversationPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppConversationPage'))
const WhatsAppKanbanPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppKanbanPage'))
const WhatsAppKanbanStagesPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppKanbanStagesPage'))
const WhatsAppTagsListPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppTagsListPage'))
const WhatsAppTemplatesListPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppTemplatesListPage'))
const WhatsAppDashboardPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppDashboardPage'))
const OnboardingPage = lazy(() => import('@/modules/onboarding/pages/OnboardingPage'))
const PlansListPage = lazy(() => import('@/modules/billing/pages/PlansListPage'))
const PlanCreatePage = lazy(() => import('@/modules/billing/pages/PlanCreatePage'))
const PlanEditPage = lazy(() => import('@/modules/billing/pages/PlanEditPage'))
const SubscriptionPage = lazy(() => import('@/modules/billing/pages/SubscriptionPage'))
const InvoicesListPage = lazy(() => import('@/modules/billing/pages/InvoicesListPage'))
export const router = createBrowserRouter([
  {
    element: <GuestGuard />,
    children: [
      {
        element: <AuthLayout />,
        children: [
          { path: '/auth/login', element: <LoginPage /> },
          { path: '/auth/register', element: <RegisterPage /> },
        ],
      },
    ],
  },
  {
    element: <AuthGuard />,
    children: [
      {
        path: '/onboarding',
        element: <OnboardingPage />,
      },
      {
        path: '/pagamento',
        element: <PaymentPendingPage />,
      },
      {
        element: <OnboardingGuard />,
        children: [
          {
            element: <AppLayout />,
            children: [
          { path: '/', element: <Navigate to="/dashboard" replace /> },
          { path: '/dashboard', element: <DashboardPage /> },
          {
            path: '/users',
            element: (
              <PermissionGuard permission={Permission.USER_READ}>
                <UsersListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/users/create',
            element: (
              <PermissionGuard permission={Permission.USER_CREATE}>
                <UserCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/users/:id/edit',
            element: (
              <PermissionGuard permission={Permission.USER_UPDATE}>
                <UserEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/roles',
            element: (
              <PermissionGuard permission={Permission.ROLE_READ}>
                <RolesListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/roles/create',
            element: (
              <PermissionGuard permission={Permission.ROLE_CREATE}>
                <RoleCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/roles/:id/edit',
            element: (
              <PermissionGuard permission={Permission.ROLE_UPDATE}>
                <RoleEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/api-tokens',
            element: (
              <PermissionGuard permission={Permission.API_TOKEN_READ}>
                <ApiTokensListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/api-tokens/create',
            element: (
              <PermissionGuard permission={Permission.API_TOKEN_CREATE}>
                <ApiTokenCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/webhooks',
            element: (
              <PermissionGuard permission={Permission.WEBHOOK_READ}>
                <WebhooksListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/webhooks/create',
            element: (
              <PermissionGuard permission={Permission.WEBHOOK_CREATE}>
                <WebhookCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/webhooks/:id/edit',
            element: (
              <PermissionGuard permission={Permission.WEBHOOK_UPDATE}>
                <WebhookEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_CONVERSATION_READ} requiresChildTenant>
                <WhatsAppDashboardPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/inbox',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_CONVERSATION_READ} requiresChildTenant>
                <WhatsAppChatPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/conversations/:id',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_CONVERSATION_READ} requiresChildTenant>
                <WhatsAppConversationPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/kanban',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_KANBAN_READ} requiresChildTenant>
                <WhatsAppKanbanPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/kanban/stages',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_KANBAN_UPDATE} requiresChildTenant>
                <WhatsAppKanbanStagesPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/tags',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_TAG_READ} requiresChildTenant>
                <WhatsAppTagsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/templates',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_TEMPLATE_READ} requiresChildTenant>
                <WhatsAppTemplatesListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/connection',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_CONFIG_READ} requiresChildTenant>
                <WhatsAppConnectionPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/billing/plans',
            element: (
              <PermissionGuard permission={Permission.PLAN_READ} requiresUmbrella>
                <PlansListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/billing/plans/create',
            element: (
              <PermissionGuard permission={Permission.PLAN_CREATE} requiresUmbrella>
                <PlanCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/billing/plans/:id/edit',
            element: (
              <PermissionGuard permission={Permission.PLAN_UPDATE} requiresUmbrella>
                <PlanEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/billing/subscription',
            element: (
              <PermissionGuard permission={Permission.SUBSCRIPTION_READ} requiresChildTenant>
                <SubscriptionPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/billing/invoices',
            element: (
              <PermissionGuard permission={Permission.INVOICE_READ} requiresChildTenant>
                <InvoicesListPage />
              </PermissionGuard>
            ),
          },
            ],
          },
        ],
      },
    ],
  },
  { path: '*', element: <NotFoundPage /> },
])
