<script lang="ts">
	import { getPacientesFecha } from '../lib/api/laboratorio'
	import { navigate } from '../lib/router.svelte'
	import { hoyISO, formatearFechaLarga } from '../lib/utils/format'
	import { toast } from '../lib/stores/toast.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import type { NombreIcono } from '../lib/ui/iconos'
	import Loader from '../lib/ui/Loader.svelte'

	let cargandoHoy = $state(false)
	let cantidadHoy = $state<number | null>(null)

	async function verExamenesHoy() {
		cargandoHoy = true
		try {
			const lista = await getPacientesFecha(hoyISO())
			cantidadHoy = lista.length
			toast(`Pacientes con exámenes hoy: ${lista.length}`, 'info')
		} catch {
			toast('No se pudieron cargar los exámenes del día', 'error')
		} finally {
			cargandoHoy = false
		}
	}

	const accesos: Array<{ titulo: string; desc: string; icono: NombreIcono; color: string; accion: () => void }> = [
		{
			titulo: 'Nuevo examen',
			desc: 'Asignar exámenes a un paciente',
			icono: 'mas',
			color: 'from-[#161569] to-[#0e7490]',
			accion: () => navigate('/crear-examen')
		},
		{
			titulo: 'Nuevo paciente',
			desc: 'Registrar un paciente',
			icono: 'usuarioMas',
			color: 'from-[#0e7490] to-[#0aa6b0]',
			accion: () => navigate('/pacientes/nuevo')
		},
		{
			titulo: 'Ver pacientes',
			desc: 'Buscar y consultar pacientes',
			icono: 'pacientes',
			color: 'from-[#161569] to-[#3b3aa5]',
			accion: () => navigate('/pacientes')
		}
	]
</script>

<div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6">
	<!-- Saludo -->
	<div class="rounded-3xl p-8 text-white shadow-xl" style="background: linear-gradient(135deg,#161569,#0e7490)">
		<p class="text-sm font-semibold uppercase tracking-widest text-white/70">Hoy es</p>
		<h1 class="mt-1 text-2xl font-extrabold sm:text-3xl">{formatearFechaLarga(hoyISO())}</h1>
		<div class="mt-5 flex flex-wrap gap-3">
			<button
				class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-brand shadow transition hover:bg-white/90"
				onclick={() => navigate('/crear-examen')}
			>
				<Icon nombre="mas" tam={16} />
				Examen nuevo
			</button>
			<button
				class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-5 py-2.5 text-sm font-bold text-white ring-1 ring-white/40 transition hover:bg-white/25"
				onclick={verExamenesHoy}
				disabled={cargandoHoy}
			>
				<Icon nombre="calendario" tam={16} />
				{cargandoHoy ? 'Consultando…' : 'Exámenes del día'}
			</button>
		</div>
		{#if cantidadHoy !== null}
			<p class="mt-3 text-sm text-white/85">
				{cantidadHoy > 0
					? `${cantidadHoy} paciente${cantidadHoy > 1 ? 's' : ''} con exámenes hoy. Abre Resultados para verlos.`
					: 'No hay exámenes registrados hoy.'}
			</p>
		{/if}
		{#if cargandoHoy}
			<div class="mt-2 [&>div]:py-1 [&>div]:text-white"><Loader texto="" tam={18} /></div>
		{/if}
	</div>

	<!-- Accesos rápidos -->
	<div class="mt-6 grid gap-4 sm:grid-cols-3">
		{#each accesos as a (a.titulo)}
			<button
				class="group rounded-2xl border border-neutral-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
				onclick={a.accion}
			>
				<div
					class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow {a.color}"
				>
					<Icon nombre={a.icono} tam={22} />
				</div>
				<p class="font-bold text-neutral-800 group-hover:text-brand">{a.titulo}</p>
				<p class="mt-0.5 text-[13px] text-neutral-500">{a.desc}</p>
			</button>
		{/each}
	</div>
</div>
