<script lang="ts">
	import { onMount } from 'svelte'
	import {
		getConfiguracion,
		getExamenesFecha,
		getInfoPaciente,
		nombresPacientes,
		panelPacientes,
		setEntidadExamen,
		type ExamenFechaRow,
		type PacienteBusqueda
	} from '../lib/api/laboratorio'
	import { esquemas } from '../lib/schemas/resultados'
	import { cargarImpresion, htmlReporteExamen, htmlReporteTodos, urlPrintphpExamen } from '../lib/reportes/print'
	import { verReporte, verReporteUrl } from '../lib/stores/reportevista.svelte'
	import { getBaseUrl } from '../lib/core/config.svelte'
	import { navigate } from '../lib/router.svelte'
	import { examenRealizado } from '../lib/models/models'
	import type { Paciente } from '../lib/models/models'
	import { conImprimiendo } from '../lib/stores/imprimiendo.svelte'
import { toast } from '../lib/stores/toast.svelte'
	import { hoyISO, formatearFechaMedia, sumarDiasISO, calcularEdad } from '../lib/utils/format'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import EmptyState from '../lib/ui/EmptyState.svelte'
	import StatusPill from '../lib/ui/StatusPill.svelte'
	import Thing from '../lib/ui/Thing.svelte'

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

	let formBusqueda = $state({ identificacion: '', nombres: '', telefono: '', entidad: '', solo: false })
	let pacientes = $state<PacienteBusqueda[]>([])
	let buscando = $state(false)
	let buscado = $state(false)
	let errorBusqueda = $state('')

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
		filas = resultados.flat().filter((r): r is ExamenFechaRow => Boolean(r))
		entidadSel = ''
		gruposVisibles = GRUPOS_VISIBLES
		cargandoDia = false
		cargarNombres()
	}

	const etiquetaRango = $derived(
		fechaDesde === fechaHasta
			? formatearFechaMedia(fechaDesde)
			: `${formatearFechaMedia(fechaDesde)} a ${formatearFechaMedia(fechaHasta)}`
	)

	async function buscar() {
		const f = formBusqueda
		if (!f.identificacion.trim() && !f.nombres.trim() && !f.telefono.trim() && !f.entidad.trim()) {
			toast('Indique al menos un criterio (identificación, nombre, teléfono o entidad)', 'info')
			return
		}
		errorBusqueda = ''
		buscando = true
		buscado = true
		pacientes = await panelPacientes({
			identificacion: f.identificacion,
			nombres: f.nombres,
			telefono: f.telefono,
			entidad: f.entidad,
			soloConResultados: f.solo
		})
		buscando = false
	}

	async function guardarEntidad(r: ExamenFechaRow) {
		if (!r.identificacion || !r.fecha || !r.codexamen) return
		const ok = await setEntidadExamen(r.identificacion, r.fecha, r.codexamen, r.entidad ?? '')
		toast(ok ? 'Entidad actualizada correctamente' : 'No se pudo actualizar la entidad', ok ? 'ok' : 'error')
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

	const resumenEntidades = $derived(
		Array.from(
			new Map<string, { nombre: string; total: number }>(
				filasFiltradas.map((f) => {
					const nombre = (f.entidad ?? '').trim() || '(sin entidad)'
					return [nombre, { nombre, total: 0 }]
				})
			)
		)
			.map(([, v]) => v)
			.map((v) => ({
				...v,
				total: filasFiltradas.filter((f) => ((f.entidad ?? '').trim() || '(sin entidad)') === v.nombre).length
			}))
			.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
	)

	interface GrupoDia {
		identificacion: string
		fecha: string
		filas: ExamenFechaRow[]
	}
	const grupos = $derived.by<GrupoDia[]>(() => {
		const mapa = new Map<string, GrupoDia>()
		for (const r of filasFiltradas) {
			const id = r.identificacion ?? ''
			const fecha = r.fecha ?? ''
			const clave = `${id}__${fecha}`
			if (!mapa.has(clave)) mapa.set(clave, { identificacion: id, fecha, filas: [] })
			mapa.get(clave)!.filas.push(r)
		}
		return Array.from(mapa.values()).sort((a, b) =>
			(b.fecha.localeCompare(a.fecha)) || a.identificacion.localeCompare(b.identificacion)
		)
	})

	const GRUPOS_VISIBLES = 25
	let gruposVisibles = $state(GRUPOS_VISIBLES)
	function mostrarMas() {
		gruposVisibles += GRUPOS_VISIBLES
	}

	function verSoloDia(fechaDia: string) {
		fechaDesde = fechaDia
		fechaHasta = fechaDia
		consultarDia()
	}

	function irHoy() {
		const h = hoyISO()
		fechaDesde = h
		fechaHasta = h
		consultarDia()
	}

	function irAyer() {
		const a = sumarDiasISO(hoyISO(), -1)
		fechaDesde = a
		fechaHasta = a
		consultarDia()
	}

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
	await conImprimiendo('Preparando reporte…', async () => {
			if (!r.identificacion) return
			try {
				const [config, carga] = await Promise.all([
					getConfiguracion(),
					cargarImpresion(aExamen(r), esquemas)
				])
				const pac = (await getInfoPaciente(r.identificacion)) ?? { identificacion: r.identificacion }
				verReporte(
					await htmlReporteExamen({
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
	})
}

	async function imprimirGrupo(g: GrupoDia) {
	await conImprimiendo('Preparando reporte…', async () => {
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
					await htmlReporteTodos(
						config ?? {},
						pac ?? { identificacion: g.identificacion },
						items
					),
					`Resultados — CC ${g.identificacion}`
				)
			} catch {
				toast('Error al preparar el reporte', 'error')
			}
	})
}

	// ---------------------------------------------------------------------------
	// Tarjeta expandible estilo prt.php: datos del paciente + resumen de resultados
	// ---------------------------------------------------------------------------
	interface InfoGrupo {
		paciente: Paciente
		resumenes: Record<string, string>
	}
	const expandidos = $state<Record<string, boolean>>({})
	const infos = $state<Record<string, InfoGrupo>>({})
	const cargandoGrupo = $state<Record<string, boolean>>({})
	const nombresMap = $state<Record<string, string>>({})

	async function cargarNombres() {
		const ids = Array.from(new Set(filas.map((f) => f.identificacion ?? '').filter(Boolean)))
		const rows = await nombresPacientes(ids)
		for (const r of rows) {
			if (r.identificacion && r.nombre_completo) nombresMap[r.identificacion] = r.nombre_completo
		}
	}

	function primerValorResumen(esquema: (typeof esquemas)[string], fila: Record<string, unknown> | null): string {
		if (!fila) return ''
		for (const c of esquema.campos) {
			if (c.multilinea) continue
			const v = String(fila[c.clave] ?? '').trim()
			if (v && v !== 'N/A') return v
		}
		return ''
	}

	function claveGrupo(g: GrupoDia): string {
		return `${g.identificacion}__${g.fecha}`
	}

	async function expandirGrupo(g: GrupoDia) {
		const clave = claveGrupo(g)
		const cc = g.identificacion
		if (expandidos[clave]) {
			expandidos[clave] = false
			return
		}
		expandidos[clave] = true
		if (infos[clave]) return
		cargandoGrupo[clave] = true
		try {
			const paciente = (await getInfoPaciente(cc)) ?? { identificacion: cc }
			const resumenes: Record<string, string> = {}
			const realizados = g.filas.filter(examenRealizado)
			await Promise.all(
				realizados.map(async (r) => {
					if (!r.codexamen) return
					const esquema = r.tipo ? esquemas[r.tipo] : undefined
					if (!esquema) return
					const fila = await esquema.cargar(cc, g.fecha, r.codexamen)
					const v = primerValorResumen(esquema, fila)
					if (v) resumenes[r.codexamen] = v
				})
			)
			infos[clave] = { paciente, resumenes }
		} catch {
			/* sin datos complementarios */
		} finally {
			cargandoGrupo[clave] = false
		}
	}

	function telefonoGrupo(g: GrupoDia): string {
		const t = infos[claveGrupo(g)]?.paciente.telefono ?? ''
		return t.replace(/\D/g, '')
	}

	function edadAnios(fecnac?: string): number {
		if (!fecnac) return 0
		const n = new Date(fecnac.length === 10 ? fecnac + 'T00:00:00' : fecnac)
		if (isNaN(n.getTime())) return 0
		const ahora = new Date()
		let anios = ahora.getFullYear() - n.getFullYear()
		const m = ahora.getMonth() - n.getMonth()
		if (m < 0 || (m === 0 && ahora.getDate() < n.getDate())) anios--
		return Math.max(0, anios)
	}

	function compartirWhatsapp(g: GrupoDia) {
		const tel = telefonoGrupo(g)
		if (!tel || tel === '0') {
			toast('El paciente no tiene teléfono registrado', 'error')
			return
		}
		const pacientes = infos[claveGrupo(g)]?.paciente
		const nombre = pacientes ? `${pacientes.nombres ?? ''} ${pacientes.apellidos ?? ''}`.trim() : g.identificacion
		// Enlace del reporte conjunto (mismo patrón que prt.php usa con printphp/imprimirTodo.php)
		const base = getBaseUrl()
		const params: Record<string, string> = {
			idx: Math.random().toString(36).slice(2),
			identificacion: g.identificacion,
			fecha: g.fecha || g.filas[0]?.fecha || fechaDesde,
			nombres: nombre,
			edad: String(edadAnios(pacientes?.fecnac)),
			entidad: pacientes?.entidad ?? '',
			info: 'Resultados',
			ver: '1'
		}
		const query = Object.entries(params)
			.filter(([, v]) => v)
			.map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
			.join('&')
		const urlReporte = `${base}printphp/imprimirTodo.php?${query}`
		const texto = `Laboratorio: los resultados de ${nombre || 'usted'} (CC ${g.identificacion}) están disponibles.\nAbrir reporte: ${urlReporte}`
		window.open(`https://wa.me/${tel}?text=${encodeURIComponent(texto)}`, '_blank')
	}

	function exportarPacientesCSV() {
		if (pacientes.length === 0) return
		const encabezados = ['Identificación', 'Nombre', 'Teléfono', 'Entidad', 'Visitas', 'Exámenes', 'Con resultados', 'Última visita']
		const filas = pacientes.map((p) => [
			p.identificacion ?? '',
			p.nombre_completo ?? '',
			p.telefono ?? '',
			p.entidad ?? '',
			p.total_visitas ?? '',
			p.total_examenes ?? '',
			p.con_resultados ?? '',
			p.ultima_visita ?? ''
		])
		const contenido = [encabezados, ...filas].map((f) => f.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(';')).join('\r\n')
		const blob = new Blob(['\uFEFF' + contenido], { type: 'text/csv;charset=utf-8' })
		const url = URL.createObjectURL(blob)
		const a = document.createElement('a')
		a.href = url
		a.download = 'pacientes_resultados.csv'
		a.click()
		URL.revokeObjectURL(url)
	}

	function abrirPdfServidor(g: GrupoDia, r: ExamenFechaRow) {
		const p = infos[claveGrupo(g)]?.paciente
		const nombres = p ? `${p.nombres ?? ''} ${p.apellidos ?? ''}`.trim() : ''
		const url = urlPrintphpExamen({
			identificacion: r.identificacion ?? '',
			fecha: g.fecha || r.fecha || '',
			codexamen: r.codexamen ?? '',
			nombre: r.nombre,
			info: r.info,
			nombres,
			entidad: r.entidad,
			tipo: r.tipo,
			tabla: r.tabla,
			edad: p?.fecnac ? String(edadAnios(p.fecnac)) : ''
		})
		if (url) verReporteUrl(url, `Reporte — ${r.nombre ?? 'Resultados'}`)
	}

	function exportarCSV() {
		if (filasFiltradas.length === 0) return
		const encabezados = ['Fecha', 'Identificación', 'Examen', 'Código', 'Entidad', 'Estado']
		const filas = filasFiltradas.map((f) => [
			f.fecha ?? '',
			f.identificacion ?? '',
			f.nombre ?? '',
			f.codexamen ?? '',
			f.entidad ?? '',
			examenRealizado(f) ? 'Realizado' : 'Pendiente'
		])
		const contenido = [encabezados, ...filas].map((f) => f.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(';')).join('\r\n')
		const blob = new Blob(['\uFEFF' + contenido], { type: 'text/csv;charset=utf-8' })
		const url = URL.createObjectURL(blob)
		const a = document.createElement('a')
		a.href = url
		a.download = `resultados_${fechaDesde}_a_${fechaHasta}.csv`
		a.click()
		URL.revokeObjectURL(url)
	}
</script>

<div class="mx-auto w-full max-w-6xl px-5 py-8 sm:px-8">
	<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
		<div class="flex items-center gap-3">
			<Thing nombre="microscope" tam={42} clase="drop-shadow-sm" />
			<div>
				<h1 class="text-2xl font-bold tracking-[-0.02em] text-neutral-900">Panel de gestión</h1>
				<p class="mt-0.5 text-sm text-neutral-500">Registro diario con filtros, impresión de resultados y búsqueda de pacientes</p>
			</div>
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
		<a
			href={getBaseUrl() + 'admin.php'}
			target="_blank"
			rel="noopener"
			class="inline-flex items-center gap-1.5 rounded-xl border border-neutral-300 bg-white px-3 py-2 text-xs font-bold text-neutral-600 transition hover:bg-neutral-50"
			title="Administración web (sistema PHP existente)"
		>
			<Icon nombre="configuracion" tam={14} />
			Admin
		</a>
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
			<button
				class="inline-flex items-center gap-1.5 rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm font-bold text-neutral-600 transition hover:bg-neutral-50 disabled:opacity-60"
				onclick={irHoy}
				disabled={cargandoDia}
			>
				Hoy
			</button>
			<button
				class="inline-flex items-center gap-1.5 rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm font-bold text-neutral-600 transition hover:bg-neutral-50 disabled:opacity-60"
				onclick={irAyer}
				disabled={cargandoDia}
			>
				Ayer
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
					<input type="checkbox" bind:checked={soloRealizados} class="h-4 w-4 accent-[#0a7cff]" />
					Solo realizados
				</label>
				<button
					class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[12px] font-bold text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-50"
					onclick={exportarCSV}
					disabled={filasFiltradas.length === 0}
					title="Exportar resultados del rango a CSV"
				>
					<Icon nombre="resultados" tam={14} />
					Excel/CSV
				</button>
			</div>
			{#if resumenEntidades.length > 0}
				<div class="mb-3 flex flex-wrap items-center gap-1.5">
					<span class="text-[11px] font-bold uppercase tracking-wide text-neutral-400">Por entidad:</span>
					{#each resumenEntidades as re (re.nombre)}
						<button
							class="rounded-full px-2.5 py-1 text-[11px] font-bold transition {entidadSel === re.nombre
								? 'bg-brand text-white'
								: 'bg-white text-neutral-600 ring-1 ring-neutral-200 hover:bg-neutral-50'}"
							onclick={() => (entidadSel = entidadSel === re.nombre ? '' : re.nombre)}
						>
							{re.nombre} · {re.total}
						</button>
					{/each}
				</div>
			{/if}
		{/if}

		{#if cargandoDia}
			<Loader texto="Consultando exámenes…" />
		{:else if consultado && filas.length === 0}
			<EmptyState thing="laboratory" icono="calendario" titulo="Sin exámenes esa fecha" subtitulo="No hay exámenes registrados para el día consultado." />
		{:else if grupos.length === 0}
			<EmptyState thing="medical-report" icono="buscar" titulo="Sin coincidencias" subtitulo="Ningún examen coincide con los filtros aplicados." />
		{:else}
			<div class="space-y-3">
				{#each grupos.slice(0, gruposVisibles) as g (g.identificacion + '__' + g.fecha)}
					<div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
						<div class="flex items-center justify-between gap-2 border-b border-neutral-100 px-4 py-2.5">
						<button class="flex min-w-0 flex-1 items-center gap-2 text-left" onclick={() => expandirGrupo(g)}>
							<div class="min-w-0 flex-1">
								<p class="truncate text-sm font-bold tracking-[-0.01em] text-neutral-900">
									{nombresMap[g.identificacion] ??
										(infos[claveGrupo(g)]
											? `${infos[claveGrupo(g)].paciente.nombres ?? ''} ${infos[claveGrupo(g)].paciente.apellidos ?? ''}`.trim()
											: 'Paciente')}
								</p>
								<p class="truncate text-[11px] text-neutral-500">
									CC <b>{g.identificacion}</b>
									{#if g.filas[0]?.entidad} · {g.filas[0].entidad}{/if}
									{#if fechaDesde !== fechaHasta} · {g.fecha.slice(8, 10)}/{g.fecha.slice(5, 7)}{/if}
								</p>
							</div>
							<Icon
								nombre="siguiente"
								tam={15}
								clase={'text-neutral-300 transition-transform ' + (expandidos[claveGrupo(g)] ? 'rotate-90' : '')}
							/>
						</button>
						<div class="flex flex-wrap items-center justify-end gap-1">
							<span class="hidden text-[12px] text-neutral-400 sm:inline">{g.filas.length} examen{g.filas.length > 1 ? 'es' : ''}</span>
							{#if fechaDesde !== fechaHasta}
								<button
									class="rounded-lg bg-sky-50 px-2 py-1 text-[11px] font-bold text-sky-700 transition hover:bg-sky-100"
									title="Ver solo este día"
									onclick={() => verSoloDia(g.fecha)}
								>
									{g.fecha.slice(8, 10)}/{g.fecha.slice(5, 7)}
								</button>
							{/if}
							{#if infos[claveGrupo(g)] && telefonoGrupo(g)}
								<button
									class="rounded-lg p-1.5 text-neutral-400 transition hover:bg-green-50 hover:text-green-600"
									title="Enviar por WhatsApp"
									onclick={() => compartirWhatsapp(g)}
								>
									<Icon nombre="mensaje" tam={16} clase="text-green-600" />
								</button>
							{/if}
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
					{#if expandidos[claveGrupo(g)]}
						<div class="border-b border-neutral-100 bg-neutral-50/70 px-4 py-2">
							{#if cargandoGrupo[claveGrupo(g)]}
								<p class="text-[12px] text-neutral-400">Cargando detalle del paciente…</p>
							{:else if infos[claveGrupo(g)]}
								{@const inf = infos[claveGrupo(g)].paciente}
								<div class="grid gap-x-6 gap-y-0.5 text-[12px] text-neutral-600 sm:grid-cols-2">
									{#if inf.fecnac}<span><b>Edad:</b> {calcularEdad(inf.fecnac)}</span>{/if}
									{#if inf.telefono}<span><b>Teléfono:</b> {inf.telefono}</span>{/if}
									{#if inf.entidad}<span><b>Entidad:</b> {inf.entidad}</span>{/if}
									{#if inf.correo}<span class="truncate"><b>Correo:</b> {inf.correo}</span>{/if}
								</div>
							{/if}
						</div>
					{/if}
					<div class="divide-y divide-neutral-50">
						{#each g.filas as r (r.codexamen)}
							<div class="flex items-center gap-3 px-4 py-2">
								<div class="min-w-0 flex-1">
									<p class="truncate text-[13px] font-semibold text-neutral-700">{r.nombre ?? r.codexamen}</p>
									{#if expandidos[claveGrupo(g)] && infos[claveGrupo(g)]?.resumenes[r.codexamen ?? '']}
										<p class="truncate font-mono text-[11px] text-emerald-700">
											Resultado: {infos[claveGrupo(g)].resumenes[r.codexamen ?? '']}
										</p>
									{/if}
								</div>
								{#if entidadesDia.length > 0}
									<select
										value={r.entidad ?? ''}
										class="hidden max-w-[160px] truncate rounded-lg border border-neutral-200 bg-white px-1.5 py-1 text-[11px] outline-none transition focus:border-brand md:block"
										title="Cambiar entidad del examen"
										onchange={(e) => {
											r.entidad = (e.currentTarget as HTMLSelectElement).value
											guardarEntidad(r)
										}}
									>
										<option value="">Sin entidad</option>
										{#each entidadesDia as ent (ent)}
											<option value={ent}>{ent}</option>
										{/each}
									</select>
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
								{#if examenRealizado(r)}
									<button
										class="rounded-lg p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-accent"
										title="Ver PDF generado por el servidor (vista original)"
										onclick={() => abrirPdfServidor(g, r)}
									>
										<Icon nombre="externo" tam={15} />
									</button>
								{/if}
							</div>
							{/each}
						</div>
					</div>
				{/each}
			</div>
			{#if grupos.length > gruposVisibles}
				<div class="flex justify-center pt-2">
					<button
						class="inline-flex items-center gap-2 rounded-xl border border-neutral-300 bg-white px-5 py-2 text-sm font-bold text-brand transition hover:bg-neutral-50"
						onclick={mostrarMas}
					>
						<Icon nombre="siguiente" tam={15} clase="rotate-90" />
						Mostrar más ({grupos.length - gruposVisibles} restantes)
					</button>
				</div>
			{/if}
		{/if}
	{:else}
		<div class="mb-4 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
			<p class="mb-3 text-sm font-extrabold text-neutral-700">Búsqueda avanzada de pacientes</p>
			<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
				<label class="block">
					<span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Identificación</span>
					<input
						bind:value={formBusqueda.identificacion}
						type="text"
						placeholder="Documento"
						class="w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-brand"
					/>
				</label>
				<label class="block">
					<span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Nombres</span>
					<input
						bind:value={formBusqueda.nombres}
						type="text"
						placeholder="Nombre o apellidos"
						class="w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-brand"
					/>
				</label>
				<label class="block">
					<span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Teléfono</span>
					<input
						bind:value={formBusqueda.telefono}
						type="text"
						placeholder="Número"
						class="w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-brand"
					/>
				</label>
				<label class="block">
					<span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Entidad</span>
					<input
						bind:value={formBusqueda.entidad}
						type="text"
						placeholder="EPS / entidad"
						class="w-full rounded-xl border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-brand"
					/>
				</label>
				<div class="flex items-end gap-2">
					<label class="mb-2 flex cursor-pointer items-center gap-1.5 text-[12px] font-semibold text-neutral-600">
						<input type="checkbox" bind:checked={formBusqueda.solo} class="h-4 w-4 accent-[#0e7490]" />
						Solo con resultados
					</label>
					<button
						class="mb-0.5 inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"
						onclick={buscar}
						disabled={buscando}
					>
						<Icon nombre="buscar" tam={15} />
						{buscando ? '…' : 'Buscar'}
					</button>
				</div>
			</div>
			{#if errorBusqueda}
				<p class="mt-2 text-[13px] font-semibold text-red-600">{errorBusqueda}</p>
			{/if}
		</div>

		{#if buscando}
			<Loader texto="Buscando pacientes…" />
		{:else if buscado && pacientes.length === 0}
			<EmptyState thing="patient" icono="buscar" titulo="Sin coincidencias" subtitulo="Ningún paciente con exámenes coincide con los criterios." />
		{:else if pacientes.length > 0}
			<div class="mb-2 flex flex-wrap items-center justify-between gap-2">
				<p class="text-sm text-neutral-500">{pacientes.length} paciente{pacientes.length > 1 ? 's' : ''} encontrado{pacientes.length > 1 ? 's' : ''}</p>
				<button
					class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[12px] font-bold text-emerald-700 transition hover:bg-emerald-100"
					onclick={exportarPacientesCSV}
				>
					<Icon nombre="resultados" tam={14} />
					Excel/CSV
				</button>
			</div>
			<div class="grid gap-3 md:grid-cols-2">
				{#each pacientes as p (p.identificacion)}
					<div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm transition hover:shadow-md">
						<div class="flex items-start gap-3">
							<div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#0a7cff] to-[#0e7490] text-sm font-extrabold text-white">
								{(p.nombre_completo ?? '?')
									.split(/\s+/)
									.slice(0, 2)
									.map((s) => s.charAt(0))
									.join('')
									.toUpperCase()}
							</div>
							<div class="min-w-0 flex-1">
								<p class="truncate text-sm font-extrabold text-neutral-800">{p.nombre_completo ?? 'Paciente'}</p>
								<p class="mt-0.5 flex flex-wrap gap-1.5 text-[11px] text-neutral-500">
									<span class="rounded-md bg-neutral-100 px-1.5 py-0.5 font-mono font-bold">{p.identificacion}</span>
									{#if p.telefono}<span>📞 {p.telefono}</span>{/if}
									{#if p.ciudad_residencia}<span>{p.ciudad_residencia}</span>{/if}
									{#if p.entidad}<span>· {p.entidad}</span>{/if}
								</p>
							</div>
							<button
								class="shrink-0 rounded-lg bg-brand/10 p-2 text-brand transition hover:bg-brand hover:text-white"
								title="Ver exámenes del paciente"
								onclick={() => navigate(`/paciente/${encodeURIComponent(p.identificacion ?? '')}/examenes`)}
							>
								<Icon nombre="ojo" tam={17} />
							</button>
						</div>
						<div class="mt-3 grid grid-cols-4 gap-2 text-center">
							<div class="rounded-xl bg-neutral-50 py-1.5">
								<p class="text-sm font-extrabold text-neutral-800">{p.total_visitas ?? 0}</p>
								<p class="text-[10px] text-neutral-400">Visitas</p>
							</div>
							<div class="rounded-xl bg-neutral-50 py-1.5">
								<p class="text-sm font-extrabold text-neutral-800">{p.total_examenes ?? 0}</p>
								<p class="text-[10px] text-neutral-400">Exámenes</p>
							</div>
							<div class="rounded-xl bg-emerald-50 py-1.5">
								<p class="text-sm font-extrabold text-emerald-700">{p.con_resultados ?? 0}</p>
								<p class="text-[10px] text-emerald-600/70">Con resultados</p>
							</div>
							<div class="rounded-xl bg-neutral-50 py-1.5">
								<p class="truncate px-1 text-sm font-extrabold text-neutral-800" title={p.ultima_visita}>{p.ultima_visita?.slice(0, 10) ?? '—'}</p>
								<p class="text-[10px] text-neutral-400">Última visita</p>
							</div>
						</div>
					</div>
				{/each}
			</div>
		{/if}
	{/if}
</div>
