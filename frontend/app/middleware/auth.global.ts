export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()

  const publicRoutes = ['/login', '/register', '/verify-email', '/forgot-password', '/reset-password']
  const isPublic = publicRoutes.some(r => to.path.startsWith(r))

  if (!auth.isLoggedIn && !isPublic) {
    return navigateTo('/login')
  }

  if (auth.isLoggedIn && !auth.isVerified && !isPublic) {
    return navigateTo('/verify-email')
  }

  if (auth.isLoggedIn && isPublic && to.path !== '/verify-email') {
    return navigateTo(auth.isVerified ? `/${useUiStore().activeVariant}/day` : '/verify-email')
  }
})
