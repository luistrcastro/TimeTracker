export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()
  const path = to.path.replace(/\/$/, '') || '/'

  const publicRoutes = ['/login', '/register', '/verify-email', '/forgot-password', '/reset-password']
  const isPublic = publicRoutes.some(r => path.startsWith(r))

  if (!auth.isLoggedIn && !isPublic && path !== '/') {
    return navigateTo('/login')
  }

  if (!auth.isLoggedIn && path === '/') {
    return navigateTo('/login')
  }

  if (auth.isLoggedIn && !auth.isVerified && !isPublic) {
    return navigateTo('/verify-email')
  }

  if (auth.isLoggedIn && isPublic && (path !== '/verify-email' || auth.isVerified)) {
    return navigateTo(auth.isVerified ? '/' : '/verify-email')
  }
})
