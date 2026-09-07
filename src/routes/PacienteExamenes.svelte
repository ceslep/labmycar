<script lang="ts">
	import { onMount } from 'svelte'
	import {
		examenesPaciente,
		examenesPacienteFecha,
		getConfiguracion,
		getInfoPaciente
	} from '../lib/api/laboratorio'
	import { esquemas } from '../lib/schemas/resultados'
	import {
		cargarImpresion,
		htmlReporteExamen,
		htmlReporteTodos,
		primeraFechaExamen
	} from '../lib/reportes/print'
	import { verReporte } from '../lib/stores/reportevista.svelte'
	import type { Examen, Paciente } from '../lib/models/models'
	import { nombreCompletoPaciente, examenRealizado } from '../lib/models/models'
	import { navigate } from '../lib/router.svelte'
	import { calcularEdad, formatearFechaMedia } from '../lib/utils/format'
	import { toast } from '../lib/stores/toast.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import EmptyState from '../lib/ui/EmptyState.svelte'
	import Page from '../lib/ui/Page.svelte'
	import StatusPill from '../lib/ui/StatusPill.svelte'
	import HoverCard from '../lib/ui/HoverCard.svelte'

	let { id }: { id: string } = $props()

	let paciente = $state<Paciente | null>(null)
	let examenes = $state<Examen[]>([])
	let cargando = $state(true)
	let fechaSeleccionada = $state('Todos')

	const fechas = $derived(['Todos'].concat(Array.from(new Set(examenes.map((e) => e.fecha ?? '').filter(Boolean))).sort().reverse()))
	const filtrados = $derived(
		fechaSeleccionada === 'Todos' ? examenes : examenes.filter((e) => e.fecha === fechaSeleccionada)
	)

	async function cargar() {
		cargando = true
		const [pac, exs] = await Promise.all([getInfoPaciente(id), examenesPaciente(id)])
		paciente = pac
		examenes = exs ?? []
		cargando = false
	}

	onMount(cargar)

	const TIPOS_CON_VISTA = ['1', '2', '3', '4', '5', '6', '8']

	function abrirRegistro(e: Examen) {
		if (e.tipo && TIPOS_CON_VISTA.includes(e.tipo)) {
			navigate(
				`/registro/${encodeURIComponent(id)}/${encodeURIComponent(e.codexamen ?? '')}/${encodeURIComponent(e.fecha ?? '')}/${e.tipo}`
			)
		} else {
			toast(`El tipo ${e.tipo ?? '?'} no tiene formulario definido todavía`, 'info')
		}
	}

	function pacienteBase(): Paciente {
		return paciente ?? { identificacion: id }
	}

	async function imprimirUno(e: Examen) {
		try {
			const [config, carga] = await Promise.all([getConfiguracion(), cargarImpresion(e, esquemas)])
			verReporte(
				htmlReporteExamen({
					config: config ?? {},
					paciente: pacienteBase(),
					examen: carga.examen,
					procedimiento: carga.procedimiento,
					esquema: carga.esquema,
					fila: carga.fila
				}),
				`Reporte — ${carga.examen.examen ?? 'Resultados'}`
			)
		} catch {
			toast('Error al preparar el reporte', 'error')
		}
	}

	async function imprimirTodos() {
		if (filtrados.length === 0) return
		try {
			const fecha = fechaSeleccionada !== 'Todos' ? fechaSeleccionada : primeraFechaExamen(filtrados)
			const [config, lista] = await Promise.all([getConfiguracion(), examenesPacienteFecha(id, fecha)])
			const realizados = lista.filter(examenRealizado)
			if (realizados.length === 0) {
				toast('No hay exámenes realizados en esa fecha para imprimir', 'info')
				return
			}
			const items = await Promise.all(realizados.map((ex) => cargarImpresion(ex, esquemas)))
			verReporte(
				htmlReporteTodos(config ?? {}, pacienteBase(), items),
				`Resultados — ${nombreCompletoPaciente(pacienteBase())}`
			)
		} catch {
			toast('Error al preparar el reporte', 'error')
		}
	}
</script>

<Page
	titulo={paciente ? nombreCompletoPaciente(paciente) : 'Consultando paciente…'}
	subtitulo={paciente ? `${paciente.identificacion ?? ''}${paciente.fecnac ? ` · ${calcularEdad(paciente.fecnac)}` : ''}` : ''}
>
	{#snippet actions()}
		{#if examenes.length > 0}
			<button
				class="inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-700"
				onclick={imprimirTodos}
				title="Imprimir todos los exámenes de la fecha"
			>
				<Icon nombre="imprimir" tam={15} />
				Imprimir todos
			</button>
		{/if}
		<button
			class="inline-flex items-center gap-2 rounded-xl border border-neutral-300 bg-white px-4 py-2 text-sm font-bold text-neutral-700 transition hover:bg-neutral-50"
			onclick={cargar}
			title="Actualizar"
		>
			<Icon nombre="refrescar" tam={15} />
			Actualizar
		</button>
	{/snippet}

	{#if cargando}
		<Loader texto="Cargando exámenes…" />
	{:else if examenes.length === 0}
		<EmptyState
			icono="resultados"
			titulo="Sin exámenes registrados"
			subtitulo="Este paciente no tiene exámenes asignados aún."
		/>
	{:else}
		{#if fechas.length > 1}
			<div class="mb-4 flex flex-wrap gap-2">
				{#each fechas as f (f)}
					<button
						class="rounded-full px-3.5 py-1.5 text-[12px] font-bold transition {fechaSeleccionada === f
							? 'bg-brand text-white'
							: 'bg-white text-neutral-600 ring-1 ring-neutral-200 hover:bg-neutral-50'}"
						onclick={() => (fechaSeleccionada = f)}
					>
						{f === 'Todos' ? 'Todos' : formatearFechaMedia(f)}
					</button>
				{/each}
			</div>
		{/if}
		<div class="space-y-2.5">
			{#each filtrados as e (e.ind ?? `${e.codexamen}-${e.fecha}`)}
				<HoverCard onclick={() => abrirRegistro(e)}>
					<div class="flex items-center gap-4 p-4">
						<div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#161569] to-[#0e7490] text-white">
							<Icon nombre="lab" tam={20} />
						</div>
						<div class="min-w-0 flex-1">
							<p class="truncate text-sm font-bold text-neutral-800">{e.examen ?? 'Examen'}</p>
							<p class="text-[12px] text-neutral-500">
								{formatearFechaMedia(e.fecha)}
								{#if e.entidad} · {e.entidad}{/if}
							</p>
						</div>
						<StatusPill
							texto={examenRealizado(e) ? 'Realizado' : 'Pendiente'}
							icono={examenRealizado(e) ? 'check' : 'reloj'}
							color={examenRealizado(e) ? 'emerald' : 'amber'}
						/>
						<button
							class="rounded-lg p-2 text-neutral-400 transition hover:bg-neutral-100 hover:text-brand"
							title="Imprimir reporte"
							onclick={(ev) => {
								ev.stopPropagation()
								imprimirUno(e)
							}}
						>
							<Icon nombre="imprimir" tam={16} />
						</button>
						<Icon nombre="siguiente" tam={16} clase="text-neutral-300" />
					</div>
				</HoverCard>
			{/each}
		</div>
	{/if}
</Page>
