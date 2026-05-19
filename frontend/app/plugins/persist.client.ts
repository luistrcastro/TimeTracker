export default defineNuxtPlugin(async () => {
  const auth = useAuthStore()

  if (auth.sessionExpiry && Date.now() > auth.sessionExpiry) {
    await auth.logout()
  }

  await auth.me()

  if (auth.isLoggedIn && auth.isVerified) {
    const ui         = useUiStore()
    const replicon   = useRepliconStore()
    const contractor = useContractorStore()
    const { load }   = useUserCustomization()

    const data = await load()
    ui.loadFromServer(data)
    replicon.loadCustomization(data)
    contractor.loadCustomization(data)

    const nuxtApp = useNuxtApp()
    const vuetify = (nuxtApp as any).$vuetify
    if (vuetify) vuetify.theme.global.name.value = ui.theme
  }
})
