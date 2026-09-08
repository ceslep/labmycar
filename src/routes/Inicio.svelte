<script lang="ts">
	import { navigate } from '../lib/router.svelte'
	import { hoyISO, formatearFechaLarga } from '../lib/utils/format'
	import Icon from '../lib/ui/Icon.svelte'
	import Thing from '../lib/ui/Thing.svelte'
	import type { NombreThing } from '../lib/ui/Thing.svelte'

	const accesos: Array<{
		titulo: string
		desc: string
		thing: NombreThing
		chip: string
		tituloColor: string
		accion: () => void
	}> = [
		{
			titulo: 'Nuevo examen',
			desc: 'Asignar exámenes a un paciente',
			thing: 'laboratory',
			chip: 'from-sky-400 via-blue-500 to-blue-700 shadow-sky-500/40',
			tituloColor: 'text-sky-600',
			accion: () => navigate('/crear-examen')
		},
		{
			titulo: 'Nuevo paciente',
			desc: 'Registrar un paciente',
			thing: 'patient',
			chip: 'from-violet-400 via-purple-500 to-fuchsia-600 shadow-violet-500/40',
			tituloColor: 'text-violet-600',
			accion: () => navigate('/pacientes/nuevo')
		},
		{
			titulo: 'Ver pacientes',
			desc: 'Buscar y consultar pacientes',
			thing: 'stethoscope',
			chip: 'from-amber-400 via-orange-500 to-orange-600 shadow-orange-500/40',
			tituloColor: 'text-orange-600',
			accion: () => navigate('/pacientes')
		}
	]
</script>

<div class="mx-auto w-full max-w-6xl px-5 py-8 sm:px-8">
	<!-- Saludo -->
	<div
		class="relative overflow-hidden rounded-[28px] border border-black/5 bg-white/80 p-6 shadow-[0_12px_40px_-24px_rgba(10,124,255,0.45)] backdrop-blur-xl sm:p-10"
	>
		<div class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full bg-brand/15 blur-2xl anim-float"></div>
		<div class="pointer-events-none absolute -bottom-24 -left-12 h-72 w-72 rounded-full bg-accent/15 blur-2xl" style="animation:animFloat 9s ease-in-out infinite reverse"></div>
		<img
			src="/thiings/laboratory.png"
			alt=""
			aria-hidden="true"
			class="pointer-events-none absolute -bottom-8 -right-2 hidden w-40 rotate-6 opacity-80 drop-shadow-lg md:block anim-float"
		/>
		<div class="relative">
		<p class="text-[13px] font-semibold uppercase tracking-[0.08em] text-brand">Hoy es</p>
		<h1 class="mt-2 text-3xl font-bold tracking-[-0.03em] text-neutral-900 sm:text-4xl">{formatearFechaLarga(hoyISO())}</h1>
		<p class="mt-2 max-w-xl text-[15px] leading-relaxed text-neutral-500">
			Gestione el trabajo del laboratorio: asigne exámenes, registre resultados y consulte los reportes.
		</p>
		<div class="mt-6 flex flex-wrap gap-3">
			<button
				class="inline-flex items-center gap-2 rounded-2xl bg-brand px-5 py-2.5 text-sm font-semibold text-white shadow-[0_8px_20px_-8px_rgba(10,124,255,0.7)] transition hover:bg-brand-600"
				onclick={() => navigate('/crear-examen')}
			>
				<Icon nombre="mas" tam={16} />
				Examen nuevo
			</button>
			<button
				class="inline-flex items-center gap-2 rounded-2xl border border-black/10 bg-white/70 px-5 py-2.5 text-sm font-semibold text-neutral-700 transition hover:bg-white"
				onclick={() => navigate('/resultados')}
			>
				<Icon nombre="calendario" tam={16} />
				Exámenes del día
			</button>
		</div>
		</div>
	</div>

	<!-- Accesos rápidos -->
	<div class="mt-6 grid gap-4 sm:grid-cols-3">
		{#each accesos as a (a.titulo)}
			<button
				class="group flex flex-col items-center rounded-3xl border border-black/5 bg-white/80 p-6 text-center backdrop-blur transition hover:-translate-y-1 hover:shadow-[0_20px_48px_-24px_rgba(0,0,0,0.35)]"
				onclick={a.accion}
			>
				<div
					class="mb-4 grid h-[84px] w-[84px] place-items-center rounded-[26px] bg-gradient-to-br shadow-lg transition-transform duration-300 group-hover:-rotate-3 group-hover:scale-110 {a.chip}"
				>
					<Thing nombre={a.thing} tam={52} filtro="brightness(0) invert(1) drop-shadow(0 2px 3px rgb(0 0 0 / 0.2))" />
				</div>
				<p class="text-[16px] font-bold tracking-[-0.01em] {a.tituloColor}">{a.titulo}</p>
				<p class="mt-1 text-[13px] leading-snug text-neutral-500">{a.desc}</p>
			</button>
		{/each}
	</div>
</div>
