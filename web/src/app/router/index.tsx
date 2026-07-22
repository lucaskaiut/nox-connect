import { lazy } from 'react'
import { createBrowserRouter, Navigate } from 'react-router'
import { AuthGuard } from '@/app/guards/AuthGuard'
import { GuestGuard } from '@/app/guards/GuestGuard'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { AppLayout } from '@/app/layouts/AppLayout'
import { AuthLayout } from '@/app/layouts/AuthLayout'
import { Permission } from '@/shared/constants/permissions'
import { NotFoundPage } from './NotFoundPage'

const LoginPage = lazy(() => import('@/modules/auth/pages/LoginPage'))
const RegisterPage = lazy(() => import('@/modules/auth/pages/RegisterPage'))
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
const WhatsAppConfigsListPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppConfigListPage'))
const WhatsAppConfigCreatePage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppConfigCreatePage'))
const WhatsAppConfigEditPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppConfigEditPage'))
const WhatsAppInboxPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppInboxPage'))
const WhatsAppConversationPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppConversationPage'))
const WhatsAppKanbanPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppKanbanPage'))
const WhatsAppKanbanStagesPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppKanbanStagesPage'))
const WhatsAppDashboardPage = lazy(() => import('@/modules/whatsapp/pages/WhatsAppDashboardPage'))
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
              <PermissionGuard permission={Permission.WHATSAPP_CONVERSATION_READ}>
                <WhatsAppDashboardPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/inbox',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_CONVERSATION_READ}>
                <WhatsAppInboxPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/conversations/:id',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_CONVERSATION_READ}>
                <WhatsAppConversationPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/kanban',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_KANBAN_READ}>
                <WhatsAppKanbanPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/kanban/stages',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_KANBAN_UPDATE}>
                <WhatsAppKanbanStagesPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/configs',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_CONFIG_READ}>
                <WhatsAppConfigsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/configs/create',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_CONFIG_CREATE}>
                <WhatsAppConfigCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/whatsapp/configs/:id/edit',
            element: (
              <PermissionGuard permission={Permission.WHATSAPP_CONFIG_UPDATE}>
                <WhatsAppConfigEditPage />
              </PermissionGuard>
            ),
          },
        ],
      },
    ],
  },
  { path: '*', element: <NotFoundPage /> },
])
