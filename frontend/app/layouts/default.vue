<template>
	<v-app :theme="ui.theme">
		<v-app-bar elevation="0" color="surface" border="b">
			<v-app-bar-title>
				<NuxtLink
					to="/"
					class="title-link text-decoration-none text-high-emphasis"
				>
					<span class="font-weight-bold">Time Tracker</span>
				</NuxtLink>
				<span class="text-medium-emphasis font-weight-regular">
					| v{{ appVersion }}</span
				>
				<template v-if="pageLabel">
					<span class="text-medium-emphasis font-weight-regular"> | </span>
					<v-menu location="bottom start" min-width="180">
						<template #activator="{ props }">
							<span v-bind="props" class="text-medium-emphasis nav-label-btn">
								{{ pageLabel }}<v-icon size="x-small" class="ml-1">mdi-chevron-down</v-icon>
							</span>
						</template>
						<v-list density="compact">
							<v-list-item
								v-for="item in navItems"
								:key="item.label"
								:prepend-icon="item.icon"
								:title="item.label"
								@click="router.push(item.to)"
							>
								<template v-if="pageLabel === item.label" #append>
									<v-icon size="small" class="text-medium-emphasis"
										>mdi-check</v-icon
									>
								</template>
							</v-list-item>
						</v-list>
					</v-menu>
				</template>
			</v-app-bar-title>
			<template #append>
				<span
					class="text-mono text-body-2 text-medium-emphasis mr-2"
					style="min-width: 70px; text-align: right"
					>{{ clock }}</span
				>
				<v-btn
					:icon="
						ui.theme === 'dark' ? 'mdi-weather-sunny' : 'mdi-weather-night'
					"
					@click="toggleTheme"
					size="small"
				/>
				<v-btn
					:text="ui.use12h ? '12H' : '24H'"
					@click="ui.toggleTimeFormat()"
					variant="text"
					size="small"
				/>
				<v-btn
					icon="mdi-keyboard-outline"
					@click="ui.shortcutsDialog = true"
					size="small"
				/>
				<v-menu min-width="220" location="bottom end">
					<template #activator="{ props }">
						<v-btn
							v-if="auth.user?.avatar_url"
							v-bind="props"
							icon
							size="small"
						>
							<v-avatar size="28" :image="auth.user.avatar_url" />
						</v-btn>
						<v-btn
							v-else
							icon="mdi-account-circle"
							v-bind="props"
							size="small"
						/>
					</template>
					<v-list density="compact">
						<v-list-item>
							<v-list-item-title class="font-weight-bold">{{
								auth.user?.name
							}}</v-list-item-title>
							<v-list-item-subtitle>{{
								auth.user?.email
							}}</v-list-item-subtitle>
						</v-list-item>
						<v-divider />
						<v-list-item
							prepend-icon="mdi-account-edit-outline"
							title="Profile"
							:to="'/profile'"
						/>
						<v-list-item
							prepend-icon="mdi-logout"
							title="Sign out"
							@click="handleLogout"
						/>
					</v-list>
				</v-menu>
			</template>
		</v-app-bar>

		<v-main>
			<v-container fluid class="pa-4">
				<slot />
			</v-container>
		</v-main>
		<v-dialog v-model="ui.shortcutsDialog" max-width="420">
			<v-card rounded="lg">
				<v-card-title class="pt-4 pb-2 px-6">Keyboard shortcuts</v-card-title>
				<v-divider />
				<v-card-text class="px-6 py-4">
					<v-table density="compact">
						<tbody>
							<tr v-for="s in shortcuts" :key="s.desc">
								<td class="text-no-wrap pr-6">
									<kbd v-for="k in s.keys" :key="k" class="shortcut-key mr-1">{{
										k
									}}</kbd>
								</td>
								<td class="text-medium-emphasis">{{ s.desc }}</td>
							</tr>
						</tbody>
					</v-table>
				</v-card-text>
				<v-card-actions class="px-6 pb-4">
					<v-spacer />
					<v-btn variant="text" @click="ui.shortcutsDialog = false"
						>Close</v-btn
					>
				</v-card-actions>
			</v-card>
		</v-dialog>
	</v-app>
</template>

<script setup lang="ts">
	const ui = useUiStore();
	const auth = useAuthStore();
	const router = useRouter();
	const route = useRoute();
	const nuxtApp = useNuxtApp();
	const {
		public: { appVersion },
	} = useRuntimeConfig();

	const navItems = [
		{
			label: 'Replicon',
			icon: 'mdi-clock-time-four-outline',
			to: '/replicon/day',
		},
		{
			label: 'Contractor',
			icon: 'mdi-briefcase-outline',
			to: '/contractor/day',
		},
		{ label: 'Profile', icon: 'mdi-account-edit-outline', to: '/profile' },
	];

	const shortcuts = [
		{ keys: ['←', '→'], desc: 'Previous / next day' },
		{ keys: ['T'], desc: 'Jump to today' },
		{ keys: ['[', ']'], desc: 'Previous / next tab' },
		{ keys: ['?'], desc: 'Show this help' },
	];

	const pageLabel = computed(() => {
		if (route.meta.title) return route.meta.title as string;
		if (route.path.startsWith('/replicon')) return 'Replicon';
		if (route.path.startsWith('/contractor')) return 'Contractor';
		return '';
	});

	// Live clock
	const clock = ref('');
	let clockTimer: ReturnType<typeof setInterval> | null = null;

	function updateClock() {
		const now = new Date();
		if (ui.use12h) {
			clock.value = now.toLocaleTimeString('en-US', {
				hour: 'numeric',
				minute: '2-digit',
				second: '2-digit',
				hour12: true,
			});
		} else {
			const h = String(now.getHours()).padStart(2, '0');
			const m = String(now.getMinutes()).padStart(2, '0');
			const s = String(now.getSeconds()).padStart(2, '0');
			clock.value = `${h}:${m}:${s}`;
		}
	}

	watch(() => ui.use12h, updateClock);

	onMounted(() => {
		updateClock();
		clockTimer = setInterval(updateClock, 1000);
		ui.initTheme();
		const vuetify = (nuxtApp as any).$vuetify;
		if (vuetify) vuetify.theme.global.name.value = ui.theme;
	});

	onUnmounted(() => {
		if (clockTimer) clearInterval(clockTimer);
	});

	function toggleTheme() {
		ui.toggleTheme();
		const vuetify = (nuxtApp as any).$vuetify;
		if (vuetify) vuetify.theme.global.name.value = ui.theme;
	}

	async function handleLogout() {
		await auth.logout();
		router.push('/login');
	}
</script>

<style scoped>
	.title-link:hover {
		opacity: 0.75;
	}
	.nav-label-btn {
		cursor: pointer;
		user-select: none;
	}
	.nav-label-btn:hover {
		opacity: 0.75;
	}
	.text-mono {
		font-family: monospace;
	}
	.shortcut-key {
		display: inline-block;
		padding: 1px 6px;
		border: 1px solid rgba(128, 128, 128, 0.4);
		border-radius: 4px;
		font-family: monospace;
		font-size: 0.8rem;
	}
</style>
