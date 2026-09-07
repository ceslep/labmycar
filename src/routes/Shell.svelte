<script lang="ts">
	import type { Snippet } from 'svelte'
	import { route, navigate } from '../lib/router.svelte'
	import { cerrarSesion } from '../lib/stores/session.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import type { NombreIcono } from '../lib/ui/iconos'

	let { children }: { children?: Snippet } = $props()

	const secciones: Array<{ nombre: string; etiqueta: string; icono: NombreIcono; path: string }> = [
		{ nombre: 'inicio', etiqueta: 'Inicio', icono: 'inicio', path: '/inicio' },
		{ nombre: 'pacientes', etiqueta: 'Pacientes', icono: 'pacientes', path: '/pacientes' },
		{ nombre: 'resultados', etiqueta: 'Resultados', icono: 'resultados', path: '/resultados' },
		{ nombre: 'configuracion', etiqueta: 'Configuración', icono: 'configuracion', path: '/configuracion' },
		{ nombre: 'panel', etiqueta: 'Panel', icono: 'panel', path: '/panel' }
	]

	const activa = $derived(secciones.find((s) => s.nombre === route.name)?.nombre ?? 'inicio')

	function salir() {
		cerrarSesion()
		navigate('/login')
	}
</script>

<div class="flex min-h-dvh">
	<!-- Barra lateral (escritorio) -->
	<aside class="hidden w-60 shrink-0 flex-col border-r border-neutral-200 bg-white lg:flex">
		<div class="flex items-center gap-3 px-5 py-5">
			<div
				class="flex h-10 w-10 items-center justify-center rounded-xl text-white"
				style="background: linear-gradient(135deg, #161569, #0e7490)"
			>
				<Icon nombre="lab" tam={21} />
			</div>
			<div class="min-w-0">
				<p class="truncate text-[15px] font-extrabold tracking-tight text-neutral-900">Laboratorio</p>
				<p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">MyCar IPS</p>
			</div>
		</div>
		<nav class="flex-1 space-y-1 px-3 py-2">
			{#each secciones as s (s.nombre)}
				<button
					class="flex w-full items-center gap-3 rounded-xl px-3.5 py-2.5 text-left text-sm font-semibold transition {activa ===
					s.nombre
						? 'bg-brand text-white shadow'
						: 'text-neutral-600 hover:bg-neutral-100'}"
					onclick={() => navigate(s.path)}
				>
					<Icon nombre={s.icono} tam={18} />
					{s.etiqueta}
				</button>
			{/each}
		</nav>
		<div class="p-3">
			<button
				class="flex w-full items-center gap-3 rounded-xl px-3.5 py-2.5 text-left text-sm font-semibold text-neutral-500 transition hover:bg-red-50 hover:text-red-600"
				onclick={salir}
			>
				<Icon nombre="salir" tam={18} />
				Salir
			</button>
		</div>
	</aside>

	<!-- Contenido -->
	<div class="flex min-w-0 flex-1 flex-col">
		<!-- Cabecera móvil -->
		<header class="flex items-center justify-between border-b border-neutral-200 bg-white px-4 py-3 lg:hidden">
			<div class="flex items-center gap-2.5">
				<div class="flex h-8 w-8 items-center justify-center rounded-lg text-white" style="background: linear-gradient(135deg,#161569,#0e7490)">
					<Icon nombre="lab" tam={17} />
				</div>
				<span class="text-[15px] font-extrabold text-neutral-900">Laboratorio</span>
			</div>
			<button
				class="rounded-lg p-2 text-neutral-500 hover:bg-neutral-100"
				onclick={salir}
				title="Salir"
			>
				<Icon nombre="salir" tam={19} />
			</button>
		</header>

		<main class="min-w-0 flex-1 pb-24 lg:pb-0">
			{@render children?.()}
		</main>
	</div>

	<!-- Navegación inferior (móvil) -->
	<nav class="fixed inset-x-0 bottom-0 z-40 flex border-t border-neutral-200 bg-white/95 backdrop-blur lg:hidden">
		{#each secciones as s (s.nombre)}
			<button
				class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[10px] font-bold {activa === s.nombre
					? 'text-brand'
					: 'text-neutral-400'}"
				onclick={() => navigate(s.path)}
			>
				<Icon nombre={s.icono} tam={20} />
				{s.etiqueta}
			</button>
		{/each}
	</nav>
</div>
