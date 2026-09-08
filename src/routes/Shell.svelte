<script lang="ts">
	import type { Snippet } from 'svelte'
	import { route, navigate } from '../lib/router.svelte'
	import { cerrarSesion } from '../lib/stores/session.svelte'
	import { estadoConfig } from '../lib/stores/configapp.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import Thing from '../lib/ui/Thing.svelte'
	import type { NombreIcono } from '../lib/ui/iconos'
	import type { NombreThing } from '../lib/ui/Thing.svelte'

	let { children }: { children?: Snippet } = $props()

	const secciones: Array<{ nombre: string; etiqueta: string; icono: NombreIcono; thing: NombreThing; path: string }> = [
		{ nombre: 'inicio', etiqueta: 'Inicio', icono: 'inicio', thing: 'stethoscope', path: '/inicio' },
		{ nombre: 'pacientes', etiqueta: 'Pacientes', icono: 'pacientes', thing: 'patient', path: '/pacientes' },
		{ nombre: 'resultados', etiqueta: 'Resultados', icono: 'resultados', thing: 'medical-report', path: '/resultados' },
		{ nombre: 'configuracion', etiqueta: 'Configuración', icono: 'configuracion', thing: 'building', path: '/configuracion' },
		{ nombre: 'panel', etiqueta: 'Panel', icono: 'panel', thing: 'microscope', path: '/panel' },
		{ nombre: 'admin', etiqueta: 'Admin', icono: 'escudo', thing: 'shield', path: '/admin' }
	]

	const activa = $derived(secciones.find((s) => s.nombre === route.name)?.nombre ?? 'inicio')

	let menuAbierto = $state(false)

	function ir(path: string) {
		menuAbierto = false
		navigate(path)
	}

	function salir() {
		menuAbierto = false
		cerrarSesion()
		navigate('/login')
	}
</script>

<div class="flex h-dvh flex-col overflow-hidden lg:flex-row">
	<!-- Barra lateral (escritorio) -->
	<aside class="hidden w-60 shrink-0 flex-col border-r border-black/5 bg-white/85 backdrop-blur-xl lg:flex">
		<div class="flex shrink-0 items-center gap-3 px-5 py-5">
			{#if estadoConfig.logoData}
				<img src={estadoConfig.logoData} alt="Logo" class="h-10 w-10 rounded-xl object-contain" />
			{:else}
				<Thing nombre="laboratory" tam={38} clase="drop-shadow-sm" />
			{/if}
			<div class="min-w-0">
				<p class="truncate text-[15px] font-bold tracking-[-0.01em] text-neutral-900">{estadoConfig.nombreLab}</p>
				<p class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">
					{estadoConfig.nombreCorto || 'Laboratorio clínico'}
				</p>
			</div>
		</div>
		<nav class="min-h-0 flex-1 space-y-0.5 overflow-y-auto px-3 py-2">
			{#each secciones as s (s.nombre)}
				<button
					class="flex w-full items-center gap-3 rounded-xl px-3.5 py-2 text-[13px] font-semibold transition {activa ===
					s.nombre
						? 'bg-gradient-to-r from-brand/15 via-brand/10 to-accent/10 text-brand shadow-[inset_0_0_0_1px_rgba(10,124,255,0.12)]'
						: 'text-neutral-600 hover:bg-neutral-100/80 hover:text-neutral-900'}"
					onclick={() => navigate(s.path)}
				>
					<Thing
						nombre={s.thing}
						tam={20}
						clase={activa === s.nombre ? 'drop-shadow-sm' : 'opacity-70 saturate-[0.55]'}
					/>
					{s.etiqueta}
				</button>
			{/each}
		</nav>
		<div class="shrink-0 border-t border-black/5 p-3">
			<button
				class="flex w-full items-center gap-3 rounded-xl px-3.5 py-2.5 text-[13px] font-semibold text-red-600 transition hover:bg-red-50"
				onclick={salir}
				title="Cerrar sesión y volver a la pantalla de acceso"
			>
				<Icon nombre="salir" tam={18} />
				Cerrar sesión
			</button>
		</div>
	</aside>

	<!-- Cabecera móvil con hamburguesa -->
	<header
		class="flex shrink-0 items-center justify-between gap-2 border-b border-black/5 bg-white/85 px-3 py-2.5 backdrop-blur-xl lg:hidden"
	>
		<button
			class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-neutral-700 transition hover:bg-neutral-100 active:scale-95"
			onclick={() => (menuAbierto = true)}
			title="Abrir menú"
			aria-label="Abrir menú"
		>
			<Icon nombre="menu" tam={22} />
		</button>
		<div class="flex min-w-0 flex-1 items-center justify-center gap-2">
			{#if estadoConfig.logoData}
				<img src={estadoConfig.logoData} alt="Logo" class="h-7 w-7 rounded-lg object-contain" />
			{:else}
				<Thing nombre="laboratory" tam={26} />
			{/if}
			<span class="truncate text-[15px] font-bold text-neutral-900">{estadoConfig.nombreLab}</span>
		</div>
		<button
			class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-red-600 transition hover:bg-red-50 active:scale-95"
			onclick={salir}
			title="Cerrar sesión"
			aria-label="Cerrar sesión"
		>
			<Icon nombre="salir" tam={20} />
		</button>
	</header>

	<!-- Contenido -->
	<div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
		<main class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
			{@render children?.()}
		</main>
	</div>
</div>

<!-- Menú lateral (móvil/tablet) -->
{#if menuAbierto}
	<div class="fixed inset-0 z-50 lg:hidden">
		<button
			class="anim-overlay absolute inset-0 h-full w-full cursor-default bg-black/45 backdrop-blur-[2px]"
			onclick={() => (menuAbierto = false)}
			aria-label="Cerrar menú"
		></button>
		<div class="anim-drawer absolute inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col border-r border-black/5 bg-white shadow-2xl">
			<div class="flex shrink-0 items-center justify-between gap-2 border-b border-black/5 px-4 py-4">
				<div class="flex min-w-0 items-center gap-2.5">
					{#if estadoConfig.logoData}
						<img src={estadoConfig.logoData} alt="Logo" class="h-9 w-9 rounded-lg object-contain" />
					{:else}
						<Thing nombre="laboratory" tam={34} />
					{/if}
					<div class="min-w-0">
						<p class="truncate text-[15px] font-bold text-neutral-900">{estadoConfig.nombreLab}</p>
						<p class="truncate text-[11px] font-medium uppercase tracking-wide text-neutral-400">
							{estadoConfig.nombreCorto || 'Laboratorio clínico'}
						</p>
					</div>
				</div>
				<button
					class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-neutral-500 transition hover:bg-neutral-100 active:scale-95"
					onclick={() => (menuAbierto = false)}
					title="Cerrar"
					aria-label="Cerrar menú"
				>
					<Icon nombre="cerrar" tam={20} />
				</button>
			</div>
			<nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-3">
				{#each secciones as s (s.nombre)}
					<button
						class="flex w-full items-center gap-3 rounded-xl px-3.5 py-2.5 text-left text-[14px] font-semibold transition {activa ===
						s.nombre
							? 'bg-gradient-to-r from-brand/15 via-brand/10 to-accent/10 text-brand shadow-[inset_0_0_0_1px_rgba(10,124,255,0.12)]'
							: 'text-neutral-700 hover:bg-neutral-100/80'}"
						onclick={() => ir(s.path)}
					>
						<Thing
							nombre={s.thing}
							tam={22}
							clase={activa === s.nombre ? 'drop-shadow-sm' : 'opacity-70 saturate-[0.55]'}
						/>
						<span class="flex-1">{s.etiqueta}</span>
						{#if activa === s.nombre}
							<Icon nombre="check" tam={16} clase="text-brand" />
						{/if}
					</button>
				{/each}
			</nav>
			<div class="shrink-0 border-t border-black/5 p-3">
				<button
					class="flex w-full items-center gap-3 rounded-xl px-3.5 py-2.5 text-[14px] font-semibold text-red-600 transition hover:bg-red-50"
					onclick={salir}
				>
					<Icon nombre="salir" tam={18} />
					Cerrar sesión
				</button>
			</div>
		</div>
	</div>
{/if}
