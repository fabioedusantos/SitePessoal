import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../lib/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { public: true } },
    {
      path: '/',
      component: () => import('../layouts/AdminLayout.vue'),
      children: [
        { path: '', name: 'dashboard', component: () => import('../views/DashboardView.vue') },
        { path: 'analytics', name: 'analytics', component: () => import('../views/AnalyticsView.vue') },
        { path: 'emails', name: 'mail-usage', component: () => import('../views/MailUsageView.vue') },
        { path: 'apresentacao', name: 'profile', component: () => import('../views/ProfileView.vue') },
        { path: 'configuracoes-site', name: 'site-settings', component: () => import('../views/SiteSettingsView.vue') },
        { path: 'projects/novo', name: 'project-new', component: () => import('../views/ProjectEditView.vue') },
        { path: 'projects/:id', name: 'project-edit', component: () => import('../views/ProjectEditView.vue') },
        { path: 'publications/novo', name: 'publication-new', component: () => import('../views/PublicationEditView.vue') },
        { path: 'publications/:id', name: 'publication-edit', component: () => import('../views/PublicationEditView.vue') },
        { path: 'pagina/:slug', name: 'page-edit-by-slug', component: () => import('../views/PageEditView.vue') },
        { path: 'pages/novo', name: 'page-new', component: () => import('../views/ResourceEditView.vue') },
        { path: 'pages/:id', name: 'page-edit', component: () => import('../views/PageEditView.vue') },
        { path: ':resource', name: 'resource-list', component: () => import('../views/ResourceListView.vue') },
        { path: ':resource/novo', name: 'resource-new', component: () => import('../views/ResourceEditView.vue') },
        { path: ':resource/:id', name: 'resource-edit', component: () => import('../views/ResourceEditView.vue') }
      ]
    }
  ]
})

router.beforeEach(async (to) => {
  const { authenticated, checkSession } = useAuth()

  if (!to.meta.public) {
    const ok = authenticated.value || await checkSession()
    if (!ok) return { name: 'login' }
  }

  if (to.name === 'login' && authenticated.value) {
    return { name: 'dashboard' }
  }
})

export default router
