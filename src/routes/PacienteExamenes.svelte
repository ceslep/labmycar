<script lang="ts">
	import { onMount } from 'svelte'
	import {
		examenesPaciente,
		examenesPacienteFecha,
		getConfiguracion,
		getInfoPaciente
	} from '../lib/api/laboratorio'
	import { esquemas } from '../lib/schemas/resultados'
	import { thingExamen, FILTRO_AMARILLO } from '../lib/schemas/resultados'
	import Thing from '../lib/ui/Thing.svelte'
	import {
		cargarImpresion,
		htmlReporteExamen,
		htmlReporteTodos,
		primeraFechaExamen,
		urlPrintphpExamen
	} from '../lib/reportes/print'
	import { verReporte, verReporteUrl } from '../lib/stores/reportevista.svelte'
	import type { Examen, Paciente } from '../lib/models/models'
	import { nombreCompletoPaciente, examenRealizado } from '../lib/models/models'
	import { navigate } from '../lib/router.svelte'
	import { calcularEdad, formatearFechaMedia } from '../lib/utils/format'
	import { conImprimiendo } from '../lib/stores/imprimiendo.svelte'
import { toast } from '../lib/stores/toast.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import EmptyState from '../lib/ui/EmptyState.svelte'
	import Page from '../lib/ui/Page.svelte'
	import StatusPill from '../lib/ui/StatusPill.svelte'
	import HoverCard from '../lib/ui/HoverCard.svelte'
	import AnalisisSaludCard from '../lib/ui/AnalisisSaludCard.svelte'
	import GraficosSaludCard from '../lib/ui/GraficosSaludCard.svelte'

	let { id }: { id: string } = $props()

	let paciente = $state<Paciente | null>(null)
	let examenes = $state<Examen[]>([])
	let cargando = $state(true)
	let fechaSeleccionada = $state('Todos')

	const fechas = $derived(['Todos'].concat(Array.from(new Set(examenes.map((e) => e.fecha ?? '').filter(Boolean))).sort().reverse()))
	const filtrados = $derived(
		fechaSeleccionada === 'Todos' ? examenes : examenes.filter((e) => e.fecha === fechaSeleccionada)
	)
	const resumenHistorial = $derived({
		total: examenes.length,
		realizados: examenes.filter((e) => examenRealizado(e)).length,
		visitas: new Set(examenes.map((e) => e.fecha ?? '')).size
	})

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

	function abrirPdfServidor(e: Examen) {
		const url = urlPrintphpExamen({
			identificacion: e.identificacion ?? id,
			fecha: e.fecha ?? '',
			codexamen: e.codexamen ?? '',
			nombre: e.examen,
			info: e.info,
			nombres: paciente ? nombreCompletoPaciente(paciente) : '',
			entidad: e.entidad,
			tipo: e.tipo,
			tabla: e.tabla
		})
		if (url) verReporteUrl(url, `Reporte — ${e.examen ?? 'Resultados'}`)
	}

	function pacienteBase(): Paciente {
		return paciente ?? { identificacion: id }
	}

	async function imprimirUno(e: Examen) {
	await conImprimiendo('Preparando reporte…', async () => {
			try {
				const [config, carga] = await Promise.all([getConfiguracion(), cargarImpresion(e, esquemas)])
				verReporte(
					await htmlReporteExamen({
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
	})
}

	async function imprimirTodos() {
	await conImprimiendo('Preparando reporte…', async () => {
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
					await htmlReporteTodos(config ?? {}, pacienteBase(), items),
					`Resultados — ${nombreCompletoPaciente(pacienteBase())}`
				)
			} catch {
				toast('Error al preparar el reporte', 'error')
			}
	})
}
</script>

<Page
	thing="medical-report"
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
			thing="medical-report"
			icono="resultados"
			titulo="Sin exámenes registrados"
			subtitulo="Este paciente no tiene exámenes asignados aún."
		/>
	{:else}
		<div class="mb-4 grid grid-cols-3 gap-2">
			<div class="rounded-2xl border border-neutral-200 bg-white px-3 py-2.5 text-center shadow-sm">
				<p class="text-lg font-extrabold text-neutral-800">{resumenHistorial.visitas}</p>
				<p class="text-[10px] font-bold uppercase tracking-wide text-neutral-400">Visitas</p>
			</div>
			<div class="rounded-2xl border border-neutral-200 bg-white px-3 py-2.5 text-center shadow-sm">
				<p class="text-lg font-extrabold text-neutral-800">{resumenHistorial.total}</p>
				<p class="text-[10px] font-bold uppercase tracking-wide text-neutral-400">Exámenes</p>
			</div>
			<div class="rounded-2xl border border-emerald-100 bg-emerald-50 px-3 py-2.5 text-center shadow-sm">
				<p class="text-lg font-extrabold text-emerald-700">{resumenHistorial.realizados}</p>
				<p class="text-[10px] font-bold uppercase tracking-wide text-emerald-600/80">Emitidos</p>
			</div>
		</div>
		{#if examenes.some((e) => examenRealizado(e))}
			<div class="mb-4">
				<AnalisisSaludCard examenes={examenes} genero={paciente?.genero} titulo="Análisis de salud del paciente" />
			</div>
			{#if examenes.some((e) => examenRealizado(e) && e.tipo && ['3', '5', '8'].includes(e.tipo))}
				<div class="mb-4">
					<GraficosSaludCard examenes={examenes} genero={paciente?.genero} />
				</div>
			{/if}
		{/if}
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
					<div class="flex items-center gap-2.5 px-3 py-3 sm:gap-4 sm:p-4">
						<Thing nombre={thingExamen(e.tipo)} tam={38} filtro={e.tipo === '3' ? FILTRO_AMARILLO : ''} clase="shrink-0 drop-shadow-sm" />
						<div class="min-w-0 flex-1">
							<p class="truncate text-sm font-bold text-neutral-800">{e.examen ?? 'Examen'}</p>
							<p class="text-[12px] text-neutral-500">
								{formatearFechaMedia(e.fecha)}
								{#if e.entidad} · {e.entidad}{/if}
							</p>
						</div>
						{#if examenRealizado(e)}
							<span title="Resultado emitido (no modificable)">
								<Icon nombre="candado" tam={15} clase="shrink-0 text-neutral-400" />
							</span>
						{/if}
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
						{#if examenRealizado(e)}
							<button
								class="rounded-lg p-2 text-neutral-400 transition hover:bg-neutral-100 hover:text-accent"
								title="Ver PDF del servidor (vista original)"
								onclick={(ev) => {
									ev.stopPropagation()
									abrirPdfServidor(e)
								}}
							>
								<Icon nombre="externo" tam={16} />
							</button>
						{/if}
						<Icon nombre="siguiente" tam={16} clase="hidden text-neutral-300 sm:block" />
					</div>
				</HoverCard>
			{/each}
		</div>
	{/if}
</Page>
