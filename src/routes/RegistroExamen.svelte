<script lang="ts">
	import { onMount } from 'svelte'
	import {
		getConfiguracion,
		getDataHemat,
		getHemogramaRaytoOld,
		getInfoPaciente,
		getProcedimiento,
		guardarDetalle
	} from '../lib/api/laboratorio'
	import type { Paciente, Procedimiento } from '../lib/models/models'
	import { nombreCompletoPaciente } from '../lib/models/models'
	import { esquemas, type CampoExamen } from '../lib/schemas/resultados'
	import { htmlReporteExamen } from '../lib/reportes/print'
	import { verReporte } from '../lib/stores/reportevista.svelte'
	import { navigate } from '../lib/router.svelte'
	import { toast } from '../lib/stores/toast.svelte'
	import { calcularEdad, formatearFechaMedia } from '../lib/utils/format'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import EmptyState from '../lib/ui/EmptyState.svelte'
	import Page from '../lib/ui/Page.svelte'

	let { id, codexamen, fecha, tipo }: { id: string; codexamen: string; fecha: string; tipo: string } = $props()

	const esquema = $derived(esquemas[tipo])

	let paciente = $state<Paciente | null>(null)
	let procedimiento = $state<Procedimiento | null>(null)
	let filaAnterior = $state<Record<string, unknown> | null>(null)
	let legadoTipo5 = $state<Record<string, unknown> | null>(null)
	let cargando = $state(true)
	let guardando = $state(false)
	let estado = $state<Record<string, string>>({})

	async function cargar() {
		cargando = true
		if (!esquema) {
			cargando = false
			return
		}
		const [pac, proc, fila] = await Promise.all([
			getInfoPaciente(id),
			getProcedimiento(codexamen),
			esquema.cargar(id, fecha, codexamen)
		])
		paciente = pac
		procedimiento = proc
		filaAnterior = fila
		const nuevo: Record<string, string> = {}
		for (const c of esquema.campos) nuevo[c.clave] = ''
		if (fila) {
			for (const k of Object.keys(nuevo)) {
				const v = fila[k]
				if (v !== null && v !== undefined) nuevo[k] = String(v)
			}
		}
		estado = nuevo
		legadoTipo5 = !fila && esquema.tipo === '5' ? await getHemogramaRaytoOld(id, fecha) : null
		cargando = false
	}

	onMount(cargar)

	interface Seccion {
		titulo: string
		campos: CampoExamen[]
	}

	const secciones = $derived.by<Seccion[]>(() => {
		if (!esquema) return []
		const secs: Seccion[] = []
		let actual: Seccion | null = null
		for (const c of esquema.campos) {
			const grupo = c.grupo ?? ''
			if (!actual || actual.titulo !== grupo) {
				actual = { titulo: grupo, campos: [] }
				secs.push(actual)
			}
			actual.campos.push(c)
		}
		return secs
	})

	function construirPayload(): Record<string, string> {
		const payload: Record<string, string> = {
			identificacion: paciente?.identificacion ?? id,
			fecha,
			...Object.fromEntries(esquema?.campos.map((c) => [c.clave, (estado[c.clave] ?? '').trim()]) ?? [])
		}
		if (esquema?.conExamen) payload['examen'] = codexamen
		for (const col of esquema?.preservar ?? []) {
			if (payload[col] === undefined) {
				const v = filaAnterior?.[col]
				payload[col] = v === null || v === undefined ? '' : String(v)
			}
		}
		return payload
	}

	async function guardar() {
		if (!esquema || !paciente?.identificacion) return
		guardando = true
		const ok = await guardarDetalle(esquema.tabla, construirPayload(), codexamen, paciente.identificacion, fecha)
		guardando = false
		if (ok) {
			toast('Resultados guardados correctamente')
			navigate(`/paciente/${encodeURIComponent(paciente.identificacion)}/examenes`)
		} else {
			toast('No se pudieron guardar los resultados. Verifique la conexión.', 'error')
		}
	}

	// Importación desde el analizador Rayto (servicio local 127.0.0.1:3000)
	const MAPA_LAN: Record<string, string> = {
		wbc: 'WBC',
		lyMn: 'LYMn',
		miDn: 'MIDn',
		grAn: 'GRAn',
		lyMp: 'LYMp',
		miDp: 'MIDp',
		grAp: 'GRAp',
		rbc: 'RBC',
		hgb: 'HGB',
		mchc: 'MCHC',
		mch: 'MCH',
		mcv: 'MCV',
		rdwcv: 'RDWCV',
		rdwsd: 'RDWSD',
		hct: 'HCT',
		plt: 'PLT',
		mpv: 'MPV',
		pdw: 'PDW',
		pct: 'PCT',
		plcr: 'PLCR'
	}
	let importandoLan = $state(false)

	async function importarLan() {
		if (!paciente?.identificacion) return
		importandoLan = true
		const datos = await getDataHemat(paciente.identificacion, fecha)
		importandoLan = false
		let cargados = 0
		for (const [claveOrigen, campo] of Object.entries(MAPA_LAN)) {
			if (!(campo in estado)) continue
			const v = datos[claveOrigen]
			if (v !== undefined && v !== null && v !== '') {
				estado[campo] = String(v)
				cargados++
			}
		}
		if (cargados > 0) toast(`Se importaron ${cargados} valores del equipo (127.0.0.1:3000)`)
		else toast('No se recibieron valores del equipo. Verifique que esté conectado en 127.0.0.1:3000.', 'error')
	}

	async function imprimir() {
		if (!esquema || !paciente?.identificacion) return
		guardando = true
		const ok = await guardarDetalle(esquema.tabla, construirPayload(), codexamen, paciente.identificacion, fecha)
		guardando = false
		if (!ok) {
			toast('No se pudieron guardar los resultados antes de imprimir.', 'error')
			return
		}
		try {
			const [config, fila] = await Promise.all([
				getConfiguracion(),
				esquema.cargar(paciente.identificacion, fecha, codexamen)
			])
			const nombreExamen = procedimiento?.nombre ?? codexamen
			const proc: Procedimiento = procedimiento ?? { codigo: codexamen, nombre: nombreExamen }
			verReporte(
				htmlReporteExamen({
					config: config ?? {},
					paciente,
					examen: {
						identificacion: paciente.identificacion,
						codexamen,
						fecha,
						tipo,
						examen: nombreExamen,
						info: procedimiento?.info,
						entidad: paciente.entidad
					},
					procedimiento: proc,
					esquema,
					fila
				}),
				`Reporte — ${nombreExamen}`
			)
		} catch {
			toast('Error al preparar el reporte', 'error')
		}
	}
</script>

<Page
	titulo={procedimiento?.nombre ?? 'Registro de resultados'}
	subtitulo={
		paciente
			? `${nombreCompletoPaciente(paciente)} · CC ${paciente.identificacion}${paciente.fecnac ? ` · ${calcularEdad(paciente.fecnac)}` : ''} · ${formatearFechaMedia(fecha)}`
			: 'Cargando…'
	}
>
	{#snippet actions()}
		{#if !cargando && esquema}
			<div class="flex flex-wrap gap-2">
				{#if esquema.tipo === '5'}
					<button
						class="inline-flex items-center gap-2 rounded-xl border border-accent/40 bg-sky-50 px-4 py-2.5 text-sm font-bold text-accent transition hover:bg-sky-100 disabled:opacity-60"
						onclick={importarLan}
						disabled={guardando || importandoLan}
						title="Importar del analizador (127.0.0.1:3000)"
					>
						<Icon nombre="refrescar" tam={16} />
						{importandoLan ? 'Importando…' : 'Importar (LAN)'}
					</button>
				{/if}
				<button
					class="inline-flex items-center gap-2 rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-sm font-bold text-neutral-700 transition hover:bg-neutral-50 disabled:opacity-60"
					onclick={imprimir}
					disabled={guardando}
				>
					<Icon nombre="imprimir" tam={16} />
					Imprimir
				</button>
				<button
					class="inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"
					onclick={guardar}
					disabled={guardando}
				>
					<Icon nombre="check" tam={16} />
					{guardando ? 'Guardando…' : 'Guardar resultados'}
				</button>
			</div>
		{/if}
	{/snippet}

	{#if cargando}
		<Loader texto="Cargando datos del examen…" />
	{:else if !esquema}
		<EmptyState
			icono="alerta"
			titulo="Tipo de examen sin vista"
			subtitulo={`El tipo ${tipo} no tiene un formulario definido en la SPA todavía (catálogo tipo 7 u otros).`}
		/>
	{:else}
		{#if procedimiento && (procedimiento.constante || procedimiento.unidades)}
			<div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-[13px] text-sky-800">
				<b>Referencia:</b>
				{#if procedimiento.constante} {procedimiento.constante}{/if}
				{#if procedimiento.unidades} · Unidades: {procedimiento.unidades}{/if}
			</div>
		{/if}

		{#if legadoTipo5 && Object.keys(legadoTipo5).length > 1}
			<div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-[13px] text-amber-800">
				<p class="flex items-center gap-2 font-bold">
					<Icon nombre="alerta" tam={15} />
					Este paciente tiene un hemograma en el esquema antiguo (examen_tipo_5)
				</p>
				<p class="mt-1">
					Los valores no se copian automáticamente para evitar errores clínicos. Si debe conservarlos,
					regístrelos en el formulario actual (referencia rápida abajo):
				</p>
				<div class="mt-2 grid gap-x-6 gap-y-0.5 sm:grid-cols-2">
					{#each ['hemoglobina', 'hematocrito', 'leucocitos', 'sedimentacion', 'wbc', 'rbc', 'plt'] as k (k)}
						{#if (legadoTipo5?.[k] ?? '') !== '' && (legadoTipo5?.[k] ?? '') !== null}
							<span><b class="uppercase">{k}:</b> {String(legadoTipo5[k])}</span>
						{/if}
					{/each}
				</div>
			</div>
		{/if}

		<div class="space-y-5">
			{#each secciones as s (s.titulo)}
				<section class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
					{#if s.titulo}
						<h2 class="mb-4 border-b border-neutral-100 pb-2 text-sm font-extrabold uppercase tracking-wide text-brand">
							{s.titulo}
						</h2>
					{/if}
					<div class="grid gap-x-4 gap-y-3 md:grid-cols-3">
						{#each s.campos as c (c.clave)}
							{#if c.multilinea}
								<label class="block md:col-span-3">
									<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">{c.etiqueta}</span>
									<textarea
										bind:value={estado[c.clave]}
										rows={3}
										class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] text-neutral-900 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
									></textarea>
								</label>
							{:else}
								<label class="block">
									<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">{c.etiqueta}</span>
									<input
										bind:value={estado[c.clave]}
										type="text"
										class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] text-neutral-900 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
									/>
								</label>
							{/if}
						{/each}
					</div>
				</section>
			{/each}
		</div>
	{/if}
</Page>
