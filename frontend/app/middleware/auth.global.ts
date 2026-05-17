export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()

  const publicRoutes = ['/login', '/register', '/verify-email', '/forgot-password', '/reset-password']
  const isPublic = publicRoutes.some(r => to.path.startsWith(r))

  if (!auth.isLoggedIn && !isPublic && to.path !== '/') {
    return navigateTo('/login')
  }

  if (!auth.isLoggedIn && to.path === '/') {
    return navigateTo('/login')
  }

  if (auth.isLoggedIn && !auth.isVerified && !isPublic && to.path !== '/') {
    return navigateTo('/verify-email')
  }

  if (auth.isLoggedIn && isPublic && to.path !== '/verify-email') {
    return navigateTo(auth.isVerified ? '/' : '/verify-email')
  }
})
