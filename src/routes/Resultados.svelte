<script lang="ts">
	import { onMount } from 'svelte'
	import { getExamenesFecha, getPacientesFecha, type ExamenFechaRow } from '../lib/api/laboratorio'
	import { navigate } from '../lib/router.svelte'
	import type { Paciente } from '../lib/models/models'
	import { nombreCompletoPaciente } from '../lib/models/models'
	import { examenRealizado } from '../lib/models/models'
	import { hoyISO, formatearFechaMedia, sumarDiasISO } from '../lib/utils/format'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import EmptyState from '../lib/ui/EmptyState.svelte'
	import HoverCard from '../lib/ui/HoverCard.svelte'
	import StatusPill from '../lib/ui/StatusPill.svelte'
import Thing from '../lib/ui/Thing.svelte'

	let vista = $state<'hoy' | 'dias'>('hoy')
	let pacientesHoy = $state<Paciente[]>([])
	let cargandoHoy = $state(true)

	let dias = $state<Array<{ fecha: string; total: number; realizados: number; pacientes: number }>>([])
	let cargandoDias = $state(false)
	let diasCargados = $state(false)
	let diaAbierto = $state('')
	let filasDia = $state<ExamenFechaRow[]>([])
	let cargandoDia = $state(false)

	const DIAS_ATRAS = 21

	async function cargarHoy() {
		cargandoHoy = true
		pacientesHoy = await getPacientesFecha(hoyISO())
		cargandoHoy = false
	}

	async function cargarDias() {
		if (diasCargados && !cargandoDias) return
		cargandoDias = true
		dias = []
		const fechas = Array.from({ length: DIAS_ATRAS }, (_, i) => sumarDiasISO(hoyISO(), -i))
		const resultados = await Promise.all(fechas.map((f) => getExamenesFecha(f)))
		fechas.forEach((fecha, i) => {
			const filas = resultados[i]
			if (filas.length === 0) return
			const pacientesUnicos = new Set(filas.map((f2) => f2.identificacion ?? '')).size
			dias.push({
				fecha,
				total: filas.length,
				realizados: filas.filter((f2) => examenRealizado(f2)).length,
				pacientes: pacientesUnicos
			})
		})
		cargandoDias = false
		diasCargados = true
	}

	async function abrirDia(fecha: string) {
		diaAbierto = fecha
		cargandoDia = true
		filasDia = await getExamenesFecha(fecha)
		cargandoDia = false
	}

	onMount(() => {
		cargarHoy()
	})

	interface GrupoDia {
		identificacion: string
		filas: ExamenFechaRow[]
	}
	const gruposDia = $derived.by<GrupoDia[]>(() => {
		const mapa = new Map<string, GrupoDia>()
		for (const r of filasDia) {
			const id = r.identificacion ?? ''
			if (!mapa.has(id)) mapa.set(id, { identificacion: id, filas: [] })
			mapa.get(id)!.filas.push(r)
		}
		return Array.from(mapa.values()).sort((a, b) => a.identificacion.localeCompare(b.identificacion))
	})

	function abrir(p: Paciente) {
		navigate(`/paciente/${encodeURIComponent(p.identificacion ?? '')}/examenes`)
	}
</script>

<div class="mx-auto w-full max-w-5xl px-5 py-8 sm:px-8">
	<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
		<Thing nombre="medical-report" tam={40} clase="drop-shadow-sm" />
		<div class="min-w-0 flex-1">
			<h1 class="text-2xl font-bold tracking-[-0.02em] text-neutral-900">Resultados</h1>
			<p class="mt-0.5 text-sm text-neutral-500">
				{#if vista === 'hoy'}
					Pacientes con exámenes el {formatearFechaMedia(hoyISO())}
				{:else}
					Días con resultados registrados (últimos {DIAS_ATRAS} días)
				{/if}
			</p>
		</div>
		<div class="flex rounded-xl bg-neutral-200/70 p-1">
			<button
				class="rounded-lg px-4 py-1.5 text-sm font-bold transition {vista === 'hoy' ? 'bg-white text-brand shadow' : 'text-neutral-500'}"
				onclick={() => {
					vista = 'hoy'
					cargarHoy()
				}}
			>
				Hoy
			</button>
			<button
				class="rounded-lg px-4 py-1.5 text-sm font-bold transition {vista === 'dias' ? 'bg-white text-brand shadow' : 'text-neutral-500'}"
				onclick={() => {
					vista = 'dias'
					cargarDias()
				}}
			>
				Días con resultados
			</button>
		</div>
	</div>

	{#if vista === 'hoy'}
		{#if cargandoHoy}
			<Loader texto="Consultando exámenes del día…" />
		{:else if pacientesHoy.length === 0}
			<EmptyState
				thing="stethoscope"
				icono="calendario"
				titulo="Sin exámenes hoy"
				subtitulo="No hay pacientes con exámenes registrados para la fecha de hoy."
			/>
		{:else}
			<div class="space-y-2.5">
				{#each pacientesHoy as p (p.identificacion)}
					<HoverCard onclick={() => abrir(p)}>
						<div class="flex items-center gap-3 p-4">
							<div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-[#0a7cff] to-[#0e7490] font-extrabold text-white">
								{nombreCompletoPaciente(p)
									.split(/\s+/)
									.slice(0, 2)
									.map((s) => s.charAt(0))
									.join('')
									.toUpperCase()}
							</div>
							<div class="min-w-0 flex-1">
								<p class="truncate text-sm font-bold text-neutral-800">{nombreCompletoPaciente(p)}</p>
								<p class="text-[12px] text-neutral-500">CC {p.identificacion}{#if p.entidad} · {p.entidad}{/if}</p>
							</div>
							<Icon nombre="siguiente" tam={16} clase="text-neutral-300" />
						</div>
					</HoverCard>
				{/each}
			</div>
		{/if}
	{:else}
		{#if cargandoDias}
			<Loader texto="Buscando días con resultados…" />
		{:else if dias.length === 0}
			<EmptyState
				icono="resultados"
				titulo="Sin resultados en los últimos días"
				subtitulo={`No se encontraron exámenes en los últimos ${DIAS_ATRAS} días.`}
			/>
		{:else}
			<div class="space-y-2.5">
				{#each dias as d (d.fecha)}
					<div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
						<button class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-neutral-50" onclick={() => abrirDia(d.fecha)}>
							<div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
								<Icon nombre="calendario" tam={17} />
							</div>
							<span class="min-w-0 flex-1 font-bold text-neutral-800">{formatearFechaMedia(d.fecha)}</span>
							<span class="hidden text-[12px] text-neutral-500 sm:inline">{d.pacientes} pacientes</span>
							<span class="text-[12px] text-neutral-500">
								{d.total} examen{d.total > 1 ? 'es' : ''} · {d.realizados} realizados
							</span>
							<Icon nombre="siguiente" tam={15} clase="text-neutral-300" />
						</button>
						{#if diaAbierto === d.fecha}
							<div class="border-t border-neutral-100 px-4 pb-3 pt-2">
								{#if cargandoDia}
									<Loader texto="" tam={18} />
								{:else if gruposDia.length === 0}
									<p class="py-2 text-[13px] text-neutral-400">Sin exámenes ese día.</p>
								{:else}
									<div class="space-y-2">
										{#each gruposDia as g (g.identificacion)}
											<div class="flex items-center gap-3 rounded-xl bg-neutral-50 px-3 py-2">
												<span class="w-28 shrink-0 truncate text-[12px] font-bold text-neutral-600">CC {g.identificacion}</span>
												<div class="min-w-0 flex-1 space-y-0.5">
													{#each g.filas as r (r.codexamen)}
														<div class="flex items-center gap-2">
															<span class="min-w-0 flex-1 truncate text-[13px] text-neutral-700">{r.nombre ?? r.codexamen}</span>
															<StatusPill
																texto={examenRealizado(r) ? 'Realizado' : 'Pendiente'}
																icono={examenRealizado(r) ? 'check' : 'reloj'}
																color={examenRealizado(r) ? 'emerald' : 'amber'}
															/>
														</div>
													{/each}
												</div>
												<button
													class="shrink-0 rounded-lg p-1.5 text-neutral-400 transition hover:bg-white hover:text-brand"
													title="Ver exámenes del paciente"
													onclick={() => navigate(`/paciente/${encodeURIComponent(g.identificacion)}/examenes`)}
												>
													<Icon nombre="ojo" tam={16} />
												</button>
											</div>
										{/each}
									</div>
								{/if}
							</div>
						{/if}
					</div>
				{/each}
			</div>
		{/if}
	{/if}
</div>
