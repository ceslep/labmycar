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

	function salir() {
		cerrarSesion()
		navigate('/login')
	}
</script>

<div class="flex min-h-dvh">
	<!-- Barra lateral (escritorio) -->
	<aside class="hidden w-60 shrink-0 flex-col border-r border-black/5 bg-white/85 backdrop-blur-xl lg:flex">
		<div class="flex items-center gap-3 px-5 py-5">
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
		<nav class="flex-1 space-y-0.5 px-3 py-2">
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
		<div class="p-3">
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

	<!-- Contenido -->
	<div class="flex min-w-0 flex-1 flex-col">
		<!-- Cabecera móvil -->
		<header class="flex items-center justify-between gap-2 border-b border-black/5 bg-white/85 px-4 py-3 backdrop-blur-xl lg:hidden">
			<div class="flex min-w-0 items-center gap-2.5">
				{#if estadoConfig.logoData}
					<img src={estadoConfig.logoData} alt="Logo" class="h-8 w-8 rounded-lg object-contain" />
				{:else}
					<Thing nombre="laboratory" tam={30} />
				{/if}
				<span class="truncate text-[15px] font-bold text-neutral-900">{estadoConfig.nombreLab}</span>
			</div>
			<button
				class="flex shrink-0 items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-3 py-1.5 text-[12px] font-bold text-red-600 transition active:scale-95"
				onclick={salir}
				title="Cerrar sesión y volver a la pantalla de acceso"
			>
				<Icon nombre="salir" tam={15} />
				Cerrar sesión
			</button>
		</header>

		<main class="min-w-0 flex-1 pb-24 lg:pb-0">
			{@render children?.()}
		</main>
	</div>

	<!-- Navegación inferior (móvil) -->
	<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-black/5 bg-white/90 backdrop-blur-xl lg:hidden">
		{#each secciones as s (s.nombre)}
			<button
				class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-semibold {activa === s.nombre
					? 'text-brand'
					: 'text-neutral-400'}"
				onclick={() => navigate(s.path)}
			>
				<Thing
					nombre={s.thing}
					tam={20}
					clase={activa === s.nombre ? 'drop-shadow-sm' : 'opacity-70 saturate-[0.55]'}
				/>
				<span class="truncate">{s.etiqueta}</span>
			</button>
		{/each}
	</nav>
</div>
