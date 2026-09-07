<script lang="ts">
	import { onMount } from 'svelte'
	import { getPacientes } from '../lib/api/laboratorio'
	import { navigate } from '../lib/router.svelte'
	import type { Paciente } from '../lib/models/models'
	import { nombreCompletoPaciente } from '../lib/models/models'
	import { calcularEdad } from '../lib/utils/format'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import EmptyState from '../lib/ui/EmptyState.svelte'
	import HoverCard from '../lib/ui/HoverCard.svelte'

	let lista = $state<Paciente[]>([])
	let base = $state<Paciente[]>([])
	let busqueda = $state('')
	let cargando = $state(true)
	let error = $state(false)
	let temporizador: ReturnType<typeof setTimeout> | undefined

	async function cargar() {
		cargando = true
		error = false
		const r = await getPacientes('')
		if (r.length === 0) error = true
		base = r
		lista = r
		cargando = false
	}

	onMount(cargar)

	// Búsqueda: ≥3 caracteres consulta al servidor (toda la base); si no, filtra en cliente.
	async function buscarServidor() {
		const q = busqueda.trim()
		if (q.length < 3) {
			lista = base
			return
		}
		lista = await getPacientes(q)
	}

	function alEscribirBusqueda() {
		clearTimeout(temporizador)
		temporizador = setTimeout(buscarServidor, 350)
	}

	const filtrados = $derived(
		lista.filter((p) => {
			const q = busqueda.trim().toLowerCase()
			if (!q || q.length >= 3) return true
			const texto = `${p.identificacion ?? ''} ${nombreCompletoPaciente(p)} ${p.telefono ?? ''} ${p.entidad ?? ''}`.toLowerCase()
			return texto.includes(q)
		})
	)

	function iniciales(p: Paciente): string {
		return nombreCompletoPaciente(p)
			.split(/\s+/)
			.slice(0, 2)
			.map((s) => s.charAt(0))
			.join('')
			.toUpperCase()
	}

	function abrir(p: Paciente) {
		navigate(`/paciente/${encodeURIComponent(p.identificacion ?? '')}/examenes`)
	}
</script>

<div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6">
	<div class="mb-5 flex flex-wrap items-center gap-3">
		<div class="flex-1">
			<h1 class="text-xl font-extrabold tracking-tight text-neutral-800">Pacientes</h1>
			<p class="text-sm text-neutral-500">
				{lista.length > 0 ? `${lista.length} paciente${lista.length > 1 ? 's' : ''} registrados` : ''}
			</p>
		</div>
		<div class="flex flex-wrap items-center gap-2">
			<div class="relative w-full max-w-xs">
				<span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-400">
					<Icon nombre="buscar" tam={16} />
				</span>
				<input
					bind:value={busqueda}
					type="text"
					placeholder="Buscar por identificación, nombre… (≥3: toda la base)"
					oninput={alEscribirBusqueda}
					class="w-full rounded-xl border border-neutral-300 bg-white py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
				/>
			</div>
			<button
				class="inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white shadow transition hover:bg-brand-700"
				onclick={() => navigate('/pacientes/nuevo')}
			>
				<Icon nombre="usuarioMas" tam={16} />
				Nuevo paciente
			</button>
		</div>
	</div>

	{#if cargando}
		<Loader texto="Cargando pacientes…" />
	{:else if error && lista.length === 0}
		<EmptyState
			icono="alerta"
			titulo="Error de conexión"
			subtitulo="No se pudieron cargar los pacientes. Verifique su conexión."
		>
			<button
				class="mt-3 inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-700"
				onclick={cargar}
			>
				<Icon nombre="refrescar" tam={15} />
				Reintentar
			</button>
		</EmptyState>
	{:else if filtrados.length === 0}
		<EmptyState icono="buscar" titulo="Sin resultados" subtitulo="Ningún paciente coincide con la búsqueda." />
	{:else}
		<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
			{#each filtrados as p (p.identificacion)}
				<HoverCard activo onclick={() => abrir(p)}>
					<div class="flex items-center gap-3 p-4">
						<div
							class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-extrabold text-white {((p.genero ?? '')
								.toLowerCase()
								.startsWith('f'))
								? 'bg-pink-500'
								: 'bg-sky-600'}"
						>
							{iniciales(p)}
						</div>
						<div class="min-w-0 flex-1">
							<p class="truncate text-sm font-bold text-neutral-800">{nombreCompletoPaciente(p)}</p>
							<p class="truncate text-[12px] text-neutral-500">
								CC {p.identificacion}
								{#if p.fecnac} · {calcularEdad(p.fecnac)}{/if}
							</p>
							{#if p.entidad}
								<span class="mt-1 inline-block rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-neutral-500">
									{p.entidad}
								</span>
							{/if}
						</div>
						<div class="flex shrink-0 items-center gap-1">
							<button
								class="rounded-lg p-2 text-neutral-400 transition hover:bg-neutral-100 hover:text-brand"
								title="Editar paciente"
								onclick={(e) => {
									e.stopPropagation()
									navigate(`/paciente/${encodeURIComponent(p.identificacion ?? '')}/editar`)
								}}
							>
								<Icon nombre="lapiz" tam={15} />
							</button>
							<Icon nombre="siguiente" tam={16} clase="text-neutral-300" />
						</div>
					</div>
				</HoverCard>
			{/each}
		</div>
	{/if}
</div>
