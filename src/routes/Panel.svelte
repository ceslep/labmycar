<script lang="ts">
	import { onMount } from 'svelte'
	import {
		busquedaExamenes,
		getConfiguracion,
		getExamenesFecha,
		getInfoPaciente,
		type ExamenFechaRow,
		type PacienteConExamenes
	} from '../lib/api/laboratorio'
	import { esquemas } from '../lib/schemas/resultados'
	import { cargarImpresion, htmlReporteExamen, htmlReporteTodos } from '../lib/reportes/print'
	import { verReporte } from '../lib/stores/reportevista.svelte'
	import { navigate } from '../lib/router.svelte'
	import { examenRealizado } from '../lib/models/models'
	import { toast } from '../lib/stores/toast.svelte'
	import { hoyISO, formatearFechaMedia, sumarDiasISO } from '../lib/utils/format'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import EmptyState from '../lib/ui/EmptyState.svelte'
	import StatusPill from '../lib/ui/StatusPill.svelte'
	import HoverCard from '../lib/ui/HoverCard.svelte'

	let vista = $state<'dia' | 'busqueda'>('dia')
	let fechaDesde = $state(hoyISO())
	let fechaHasta = $state(hoyISO())
	let filas = $state<ExamenFechaRow[]>([])
	let cargandoDia = $state(false)
	let consultado = $state(false)

	// Filtros de la consulta por fecha (como en prt.php: entidad / solo resultados)
	let entidadSel = $state('')
	let soloRealizados = $state(false)
	let textoExamen = $state('')

	let criterio = $state('')
	let pacientes = $state<PacienteConExamenes[]>([])
	let buscando = $state(false)
	let buscado = $state(false)

	const MAX_RANGO = 31

	function diffDias(a: string, b: string): number {
		const d1 = new Date(a + 'T00:00:00').getTime()
		const d2 = new Date(b + 'T00:00:00').getTime()
		return Math.round((d2 - d1) / 86400000)
	}

	async function consultarDia() {
		if (fechaHasta < fechaDesde) {
			toast('La fecha final no puede ser anterior a la inicial', 'error')
			return
		}
		const dias = diffDias(fechaDesde, fechaHasta)
		if (dias > MAX_RANGO) {
			toast(`El rango máximo es de ${MAX_RANGO} días`, 'error')
			return
		}
		cargandoDia = true
		consultado = true
		const fechas = Array.from({ length: dias + 1 }, (_, i) => sumarDiasISO(fechaDesde, i))
		const resultados = await Promise.all(fechas.map((f) => getExamenesFecha(f)))
		filas = resultados.flat()
		entidadSel = ''
		cargandoDia = false
	}

	const etiquetaRango = $derived(
		fechaDesde === fechaHasta
			? formatearFechaMedia(fechaDesde)
			: `${formatearFechaMedia(fechaDesde)} a ${formatearFechaMedia(fechaHasta)}`
	)

	async function buscar() {
		const c = criterio.trim()
		if (c.length < 3) {
			toast('Escriba al menos 3 caracteres para buscar', 'info')
			return
		}
		buscando = true
		buscado = true
		pacientes = await busquedaExamenes(c)
		buscando = false
	}

	onMount(consultarDia)

	const entidadesDia = $derived(
		Array.from(new Set(filas.map((f) => (f.entidad ?? '').trim()).filter(Boolean))).sort((a, b) =>
			a.localeCompare(b, 'es')
		)
	)

	const filasFiltradas = $derived(
		filas.filter((f) => {
			if (entidadSel && (f.entidad ?? '').trim() !== entidadSel) return false
			if (soloRealizados && !examenRealizado(f)) return false
			const q = textoExamen.trim().toLowerCase()
			if (q && !`${f.nombre ?? ''} ${f.codexamen ?? ''}`.toLowerCase().includes(q)) return false
			return true
		})
	)

	interface GrupoDia {
		identificacion: string
		filas: ExamenFechaRow[]
	}
	const grupos = $derived.by<GrupoDia[]>(() => {
		const mapa = new Map<string, GrupoDia>()
		for (const r of filasFiltradas) {
			const id = r.identificacion ?? ''
			if (!mapa.has(id)) mapa.set(id, { identificacion: id, filas: [] })
			mapa.get(id)!.filas.push(r)
		}
		return Array.from(mapa.values()).sort((a, b) => a.identificacion.localeCompare(b.identificacion))
	})

	const totalDia = $derived(filasFiltradas.length)
	const realizadosDia = $derived(filasFiltradas.filter((f) => examenRealizado(f)).length)

	function aExamen(r: ExamenFechaRow) {
		return {
			codexamen: r.codexamen,
			identificacion: r.identificacion,
			fecha: r.fecha,
			realizado: r.realizado,
			entidad: r.entidad,
			examen: r.nombre,
			tipo: r.tipo,
			tabla: r.tabla,
			info: r.info
		}
	}

	async function imprimirExamen(r: ExamenFechaRow) {
		if (!r.identificacion) return
		try {
			const [config, carga] = await Promise.all([
				getConfiguracion(),
				cargarImpresion(aExamen(r), esquemas)
			])
			const pac = (await getInfoPaciente(r.identificacion)) ?? { identificacion: r.identificacion }
			verReporte(
				htmlReporteExamen({
					config: config ?? {},
					paciente: pac,
					examen: carga.examen,
					procedimiento: carga.procedimiento,
					esquema: carga.esquema,
					fila: carga.fila
				}),
				`Reporte — ${r.nombre ?? r.codexamen ?? 'Resultados'}`
			)
		} catch {
			toast('Error al preparar el reporte', 'error')
		}
	}

	async function imprimirGrupo(g: GrupoDia) {
		const realizados = g.filas.filter(examenRealizado)
		if (realizados.length === 0) {
			toast('No hay exámenes realizados para imprimir en este grupo', 'info')
			return
		}
		try {
			const [config, pac] = await Promise.all([
				getConfiguracion(),
				getInfoPaciente(g.identificacion)
			])
			const items = await Promise.all(realizados.map((r) => cargarImpresion(aExamen(r), esquemas)))
			verReporte(
				htmlReporteTodos(
					config ?? {},
					pac ?? { identificacion: g.identificacion },
					items
				),
				`Resultados — CC ${g.identificacion}`
			)
		} catch {
			toast('Error al preparar el reporte', 'error')
		}
	}
</script>

<div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6">
	<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
		<div>
			<h1 class="text-xl font-extrabold tracking-tight text-neutral-800">Panel de gestión</h1>
			<p class="text-sm text-neutral-500">Registro diario con filtros, impresión de resultados y búsqueda de pacientes</p>
		</div>
		<div class="flex rounded-xl bg-neutral-200/70 p-1">
			<button
				class="rounded-lg px-4 py-1.5 text-sm font-bold transition {vista === 'dia' ? 'bg-white text-brand shadow' : 'text-neutral-500'}"
				onclick={() => (vista = 'dia')}
			>
				Por fecha
			</button>
			<button
				class="rounded-lg px-4 py-1.5 text-sm font-bold transition {vista === 'busqueda' ? 'bg-white text-brand shadow' : 'text-neutral-500'}"
				onclick={() => (vista = 'busqueda')}
			>
				Buscar paciente
			</button>
		</div>
	</div>

	{#if vista === 'dia'}
		<div class="mb-4 flex flex-wrap items-end gap-2">
			<label class="block">
				<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">Desde</span>
				<input
					type="date"
					bind:value={fechaDesde}
					class="rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
				/>
			</label>
			<label class="block">
				<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">Hasta</span>
				<input
					type="date"
					bind:value={fechaHasta}
					class="rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
				/>
			</label>
			<button
				class="inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"
				onclick={consultarDia}
				disabled={cargandoDia}
			>
				<Icon nombre="buscar" tam={15} />
				Consultar
			</button>
			{#if consultado && !cargandoDia}
				<span class="self-center text-sm text-neutral-500">
					{totalDia} examen{totalDia !== 1 ? 'es' : ''} · {realizadosDia} realizados · {grupos.length} pacientes · {etiquetaRango}
				</span>
			{/if}
		</div>

		{#if consultado && !cargandoDia}
			<div class="mb-4 flex flex-wrap items-center gap-2 rounded-2xl border border-neutral-200 bg-white px-3 py-2">
				<Icon nombre="configuracion" tam={16} clase="text-neutral-400" />
				{#if entidadesDia.length > 0}
					<select
						bind:value={entidadSel}
						class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-[13px] outline-none focus:border-brand"
					>
						<option value="">Todas las entidades</option>
						{#each entidadesDia as e (e)}
							<option value={e}>{e}</option>
						{/each}
					</select>
				{/if}
				<input
					bind:value={textoExamen}
					type="text"
					placeholder="Filtrar examen…"
					class="w-40 rounded-lg border border-neutral-300 px-3 py-1.5 text-[13px] outline-none focus:border-brand"
				/>
				<label class="flex cursor-pointer select-none items-center gap-2 text-[13px] font-semibold text-neutral-600">
					<input type="checkbox" bind:checked={soloRealizados} class="h-4 w-4 accent-[#161569]" />
					Solo realizados
				</label>
			</div>
		{/if}

		{#if cargandoDia}
			<Loader texto="Consultando exámenes…" />
		{:else if consultado && filas.length === 0}
			<EmptyState icono="calendario" titulo="Sin exámenes esa fecha" subtitulo="No hay exámenes registrados para el día consultado." />
		{:else if grupos.length === 0}
			<EmptyState icono="buscar" titulo="Sin coincidencias" subtitulo="Ningún examen coincide con los filtros aplicados." />
		{:else}
			<div class="space-y-3">
				{#each grupos as g (g.identificacion)}
					<div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
						<div class="flex items-center justify-between border-b border-neutral-100 px-4 py-2.5">
							<p class="text-sm font-extrabold text-neutral-800">CC {g.identificacion}</p>
							<div class="flex items-center gap-2">
								<span class="text-[12px] text-neutral-400">{g.filas.length} examen{g.filas.length > 1 ? 'es' : ''}</span>
								<button
									class="rounded-lg p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-brand disabled:opacity-40"
									title="Imprimir resultados realizados de este paciente"
									disabled={!g.filas.some(examenRealizado)}
									onclick={() => imprimirGrupo(g)}
								>
									<Icon nombre="imprimir" tam={16} />
								</button>
								<button
									class="rounded-lg p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-brand"
									title="Ver exámenes del paciente"
									onclick={() => navigate(`/paciente/${encodeURIComponent(g.identificacion)}/examenes`)}
								>
									<Icon nombre="ojo" tam={16} />
								</button>
							</div>
						</div>
						<div class="divide-y divide-neutral-50">
							{#each g.filas as r (r.codexamen)}
								<div class="flex items-center gap-3 px-4 py-2">
									<span class="min-w-0 flex-1 truncate text-[13px] font-semibold text-neutral-700">{r.nombre ?? r.codexamen}</span>
									{#if r.entidad}
										<span class="hidden max-w-[140px] truncate text-[11px] text-neutral-400 md:inline">{r.entidad}</span>
									{/if}
									<StatusPill
										texto={examenRealizado(r) ? 'Realizado' : 'Pendiente'}
										icono={examenRealizado(r) ? 'check' : 'reloj'}
										color={examenRealizado(r) ? 'emerald' : 'amber'}
									/>
									<button
										class="rounded-lg p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-brand disabled:opacity-40"
										title={examenRealizado(r) ? 'Imprimir reporte' : 'Sin resultados aún'}
										disabled={!examenRealizado(r)}
										onclick={() => imprimirExamen(r)}
									>
										<Icon nombre="imprimir" tam={15} />
									</button>
								</div>
							{/each}
						</div>
					</div>
				{/each}
			</div>
		{/if}
	{:else}
		<div class="mb-4 flex flex-wrap items-end gap-2">
			<div class="relative w-full max-w-md">
				<span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-400">
					<Icon nombre="buscar" tam={16} />
				</span>
				<input
					bind:value={criterio}
					type="text"
					placeholder="Identificación o nombre del paciente…"
					class="w-full rounded-xl border border-neutral-300 bg-white py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
					onkeydown={(e) => {
						if (e.key === 'Enter') buscar()
					}}
				/>
			</div>
			<button
				class="inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"
				onclick={buscar}
				disabled={buscando}
			>
				{buscando ? 'Buscando…' : 'Buscar'}
			</button>
		</div>

		{#if buscando}
			<Loader texto="Buscando pacientes…" />
		{:else if buscado && pacientes.length === 0}
			<EmptyState icono="buscar" titulo="Sin coincidencias" subtitulo="Ningún paciente con exámenes coincide con el criterio." />
		{:else if pacientes.length > 0}
			<div class="grid gap-2.5 md:grid-cols-2 xl:grid-cols-3">
				{#each pacientes as p (p.identificacion)}
					<HoverCard onclick={() => navigate(`/paciente/${encodeURIComponent(p.identificacion ?? '')}/examenes`)}>
						<div class="flex items-center gap-3 p-3.5">
							<div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#161569] to-[#0e7490] text-sm font-extrabold text-white">
								{(p.nombres ?? '?')
									.split(/\s+/)
									.slice(0, 2)
									.map((s) => s.charAt(0))
									.join('')
									.toUpperCase()}
							</div>
							<div class="min-w-0 flex-1">
								<p class="truncate text-sm font-bold text-neutral-800">{p.nombres ?? 'Paciente'}</p>
								<p class="text-[12px] text-neutral-500">CC {p.identificacion}{#if p.edad} · {p.edad}{/if}</p>
							</div>
							<Icon nombre="siguiente" tam={16} clase="text-neutral-300" />
						</div>
					</HoverCard>
				{/each}
			</div>
		{/if}
	{/if}
</div>
