<script lang="ts">
	import { onMount } from 'svelte'
	import { adminDatos, migracionIndices, type AdminDatos, type AdminFila } from '../lib/api/laboratorio'
	import { navigate } from '../lib/router.svelte'
	import { examenRealizado } from '../lib/models/models'
	import { hoyISO, formatearFechaMedia, sumarDiasISO } from '../lib/utils/format'
	import { toast } from '../lib/stores/toast.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import EmptyState from '../lib/ui/EmptyState.svelte'
	import StatusPill from '../lib/ui/StatusPill.svelte'
import Thing from '../lib/ui/Thing.svelte'

	let fechaInicio = $state(hoyISO())
	let fechaFin = $state(hoyISO())
	let entidad = $state('')
	let tabla = $state('')
	let estado = $state('')
	let buscar = $state('')
	let page = $state(1)

	let datos = $state<AdminDatos | null>(null)
	let health = $state<NonNullable<AdminDatos['health']> | null>(null)
	let cargando = $state(true)
	let cargandoSalud = $state(false)
	let error = $state('')

	const TIPOS_VISTA = ['1', '2', '3', '4', '5', '6', '8']

	const stats = $derived(datos?.stats ?? {})

	const donaEstado = $derived.by(() => {
		const r = Number(stats.realizados ?? 0)
		const p = Number(stats.pendientes ?? 0)
		const t = r + p || 1
		const deg = Math.round((r / t) * 360)
		return {
			grad: `conic-gradient(#34d399 0deg ${deg}deg, #fbbf24 ${deg}deg 360deg)`,
			pct: Math.round((r / t) * 100)
		}
	})

	const COND_COLORES = ['#f43f5e', '#fb923c', '#eab308', '#a855f7', '#ec4899', '#06b6d4', '#14b8a6', '#6366f1', '#3b82f6', '#10b981', '#f97316', '#0ea5e9', '#64748b']

	async function cargar(salud = false, analisisDesde = '', analisisHasta = '') {
		cargando = true
		error = ''
		const r = await adminDatos({
			fechaInicio,
			fechaFin,
			entidad,
			tabla,
			estado,
			buscar,
			page,
			perPage: 25,
			analisis: salud,
			analisisDesde,
			analisisHasta
		})
		if (!r) {
			error = 'No se pudieron cargar los datos de administración. Verifique la conexión y que adminDatos.php esté sincronizado.'
		} else {
			datos = r
			if (salud && r.health) health = r.health
		}
		cargando = false
	}

	function consultar() {
		page = 1
		cargar(false)
	}

	async function verSalud() {
		cargandoSalud = true
		// El análisis de salud usa el último año para acotar la carga.
		await cargar(true, sumarDiasISO(hoyISO(), -365), hoyISO())
		cargandoSalud = false
	}

	let migrando = $state(false)

	async function correrMigracion() {
		if (!window.confirm('Deduplicará filas repetidas y creará índices únicos en las tablas del laboratorio. ¿Ejecutar?')) return
		migrando = true
		const r = await migracionIndices()
		migrando = false
		if (!r || !r.msg) {
			toast('No se pudo ejecutar la migración. ¿Sincronizaste migracion_indices.php y tienes token?', 'error')
			return
		}
		const dups = (r.resumen ?? []).reduce((a, x) => a + (x.duplicados_eliminados ?? 0), 0)
		toast(
			`Migración: ${dups} duplicado${dups !== 1 ? 's' : ''} eliminado${dups !== 1 ? 's' : ''}` +
				(r.errores?.length ? ` · ${r.errores.length} error(es) — revisa la consola` : ' · índices listos'),
			r.errores?.length ? 'error' : 'ok'
		)
		if (r.errores?.length) console.error('[migracion]', r.errores)
	}

	function irPagina(p: number) {
		if (p < 1 || p > (datos?.pages ?? 1)) return
		page = p
		cargar(false)
	}

	function abrirFila(f: AdminFila) {
		const tipoFila = f.examen_tipo
		if (tipoFila && TIPOS_VISTA.includes(tipoFila) && f.codexamen && f.fecha) {
			navigate(`/registro/${encodeURIComponent(f.identificacion ?? '')}/${encodeURIComponent(f.codexamen)}/${encodeURIComponent(f.fecha)}/${tipoFila}`)
		} else {
			navigate(`/paciente/${encodeURIComponent(f.identificacion ?? '')}/examenes`)
		}
	}

	function exportarCSV() {
		const filas = datos?.filas ?? []
		if (filas.length === 0) return
		const cab = ['Fecha', 'Identificación', 'Paciente', 'Examen', 'Entidad', 'Estado', 'Resultado']
		const fil = filas.map((f) => [
			f.fecha ?? '',
			f.identificacion ?? '',
			f.paciente ?? '',
			f.examen ?? f.examen_tabla ?? '',
			f.entidad ?? '',
			examenRealizado(f) ? 'Realizado' : 'Pendiente',
			f.resumen ?? ''
		])
		const contenido = [cab, ...fil].map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(';')).join('\r\n')
		const blob = new Blob(['\uFEFF' + contenido], { type: 'text/csv;charset=utf-8' })
		const url = URL.createObjectURL(blob)
		const a = document.createElement('a')
		a.href = url
		a.download = `admin_resultados_${hoyISO()}.csv`
		a.click()
		URL.revokeObjectURL(url)
	}

	onMount(() => cargar(false))

	function barraLista(labels: string[] = [], data: number[] = [], color = 'bg-brand') {
		const max = Math.max(1, ...data)
		return labels.map((l, i) => ({
			label: l,
			valor: data[i] ?? 0,
			ancho: Math.max(2, Math.round(((data[i] ?? 0) / max) * 100))
		})).sort((a, b) => b.valor - a.valor)
	}

	const maxLista = (labels: string[], data: number[]) => Math.max(1, ...data)
</script>

<div class="mx-auto w-full max-w-6xl px-5 py-8 sm:px-8">
	<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
		<div class="flex items-center gap-3">
			<Thing nombre="shield" tam={40} clase="drop-shadow-sm" />
			<div>
				<h1 class="text-2xl font-bold tracking-[-0.02em] text-neutral-900">Administración</h1>
				<p class="mt-0.5 text-sm text-neutral-500">Estadísticas, análisis y consulta administrativa de exámenes</p>
			</div>
		</div>
		<div class="flex gap-2">
			{#if (datos?.filas?.length ?? 0) > 0}
				<button
					class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100"
					onclick={exportarCSV}
				>
					<Icon nombre="resultados" tam={14} />
					Excel/CSV
				</button>
			{/if}
			<button
				class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700 transition hover:bg-amber-100 disabled:opacity-60"
				onclick={correrMigracion}
				disabled={migrando}
				title="Deduplicar filas y crear índices únicos (una sola vez)"
			>
				<Icon nombre="refrescar" tam={14} />
				{migrando ? 'Migrando…' : 'Migrar índices'}
			</button>
			<button
				class="inline-flex items-center gap-1.5 rounded-xl bg-brand px-3 py-2 text-xs font-bold text-white transition hover:bg-brand-600 disabled:opacity-60"
				onclick={verSalud}
				disabled={cargandoSalud}
			>
				<Icon nombre="reloj" tam={14} />
				{cargandoSalud ? 'Analizando…' : health ? 'Refrescar salud' : 'Análisis de salud'}
			</button>
		</div>
	</div>

	{#if cargando}
		<Loader texto="Cargando datos de administración…" />
	{:else if error}
		<EmptyState icono="alerta" titulo="No se pudieron cargar los datos" subtitulo={error} />
	{:else}
		<!-- Tarjetas de estadísticas -->
		<div class="mb-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
			<div class="rounded-3xl border border-black/5 bg-white/80 p-5 backdrop-blur">
				<div class="mb-2 flex h-9 w-9 items-center justify-center rounded-2xl bg-gradient-to-br from-[#6366f1] to-[#8b5cf6] text-white shadow-sm">
					<Icon nombre="resultados" tam={18} />
				</div>
				<p class="text-[26px] font-bold tracking-[-0.02em] text-neutral-900">{stats.total ?? 0}</p>
				<p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Total exámenes</p>
			</div>
			<div class="rounded-3xl border border-black/5 bg-white/80 p-5 backdrop-blur">
				<div class="mb-2 flex h-9 w-9 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-sm">
					<Icon nombre="check" tam={18} />
				</div>
				<p class="text-[26px] font-bold tracking-[-0.02em] text-neutral-900">{stats.realizados ?? 0}</p>
				<p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600">Realizados</p>
			</div>
			<div class="rounded-3xl border border-black/5 bg-white/80 p-5 backdrop-blur">
				<div class="mb-2 flex h-9 w-9 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow-sm">
					<Icon nombre="reloj" tam={18} />
				</div>
				<p class="text-[26px] font-bold tracking-[-0.02em] text-neutral-900">{stats.pendientes ?? 0}</p>
				<p class="text-[11px] font-semibold uppercase tracking-wide text-amber-600">Pendientes</p>
			</div>
			<div class="rounded-3xl border border-black/5 bg-white/80 p-5 backdrop-blur">
				<div class="mb-2 flex h-9 w-9 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-sm">
					<Icon nombre="calendario" tam={18} />
				</div>
				<p class="text-[26px] font-bold tracking-[-0.02em] text-neutral-900">{stats.hoy ?? 0}</p>
				<p class="text-[11px] font-semibold uppercase tracking-wide text-sky-600">Hoy</p>
				<p class="mt-0.5 text-[10px] text-neutral-400">
					vs ayer: {stats.hoy_vs_ayer ?? 0 >= 0 ? '+' : ''}{stats.hoy_vs_ayer ?? 0}%
				</p>
			</div>
		</div>

		<!-- Indicadores -->
		<div class="mb-4 grid gap-3 rounded-3xl border border-black/5 bg-white/70 p-4 backdrop-blur sm:grid-cols-2 lg:grid-cols-4">
			<div>
				<p class="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Tasa de realización</p>
				<div class="mt-1.5 flex items-center gap-2">
					<div class="h-2 flex-1 overflow-hidden rounded-full bg-neutral-200">
						<div class="h-2 rounded-full bg-gradient-to-r from-emerald-500 to-teal-500" style="width:{stats.tasa_realizacion ?? 0}%"></div>
					</div>
					<span class="text-sm font-bold text-emerald-600">{(stats.tasa_realizacion ?? 0).toFixed(1)}%</span>
				</div>
			</div>
			<div class="grid grid-cols-3 gap-2">
				<div class="text-center">
					<p class="text-lg font-bold text-neutral-900">{stats.pacientes ?? 0}</p>
					<p class="text-[10px] text-neutral-400">Pacientes</p>
				</div>
				<div class="text-center">
					<p class="text-lg font-bold text-neutral-900">{stats.visitas ?? 0}</p>
					<p class="text-[10px] text-neutral-400">Visitas</p>
				</div>
				<div class="text-center">
					<p class="text-lg font-bold text-neutral-900">{stats.promedio_diario ?? 0}</p>
					<p class="text-[10px] text-neutral-400">Prom/día 14d</p>
				</div>
			</div>
			<div class="lg:col-span-2 flex items-center justify-center gap-5 rounded-2xl bg-neutral-50/80 p-3">
				<div class="relative h-24 w-24 shrink-0" style={`background:${donaEstado.grad}`}>
					<div class="absolute inset-2 flex flex-col items-center justify-center rounded-full bg-white">
						<span class="text-lg font-extrabold text-neutral-900">{donaEstado.pct}%</span>
						<span class="text-[9px] uppercase tracking-wide text-neutral-400">emitido</span>
					</div>
				</div>
				<div class="space-y-1 text-[12px]">
					<p class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>Realizados: <b>{stats.realizados ?? 0}</b></p>
					<p class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>Pendientes: <b>{stats.pendientes ?? 0}</b></p>
				</div>
			</div>
		</div>

		<!-- Filtros del listado -->
		<div class="mb-4 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
			<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
				<label class="block">
					<span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Desde</span>
					<input type="date" bind:value={fechaInicio} class="w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-sm" />
				</label>
				<label class="block">
					<span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Hasta</span>
					<input type="date" bind:value={fechaFin} class="w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-sm" />
				</label>
				<label class="block">
					<span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Entidad</span>
					<select bind:value={entidad} class="w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-sm">
						<option value="">Todas</option>
						{#each datos?.entidades ?? [] as e (e)}
							<option value={e}>{e}</option>
						{/each}
					</select>
				</label>
				<label class="block">
					<span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Tipo de examen</span>
					<select bind:value={tabla} class="w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-sm">
						<option value="">Todos</option>
						{#each datos?.tablas ?? [] as t, i (t.tabla + '_' + i)}
							<option value={t.tabla}>{t.nombre}</option>
						{/each}
					</select>
				</label>
				<label class="block">
					<span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-neutral-500">Estado</span>
					<select bind:value={estado} class="w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-sm">
						<option value="">Todos</option>
						<option value="realizado">Realizado</option>
						<option value="pendiente">Pendiente</option>
					</select>
				</label>
				<div class="flex items-end gap-1.5">
					<input
						bind:value={buscar}
						placeholder="Buscar (id/nombre/correo/tel)…"
						class="w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-sm"
						onkeydown={(e) => {
							if (e.key === 'Enter') consultar()
						}}
					/>
					<button class="rounded-lg bg-brand px-3 py-1.5 text-sm font-bold text-white" onclick={consultar}>
						<Icon nombre="buscar" tam={15} />
					</button>
				</div>
			</div>
		</div>

		<!-- Listado -->
		{#if (datos?.filas?.length ?? 0) === 0}
			<EmptyState thing="medical-report" icono="resultados" titulo="Sin exámenes" subtitulo="No hay exámenes con los filtros seleccionados." />
		{:else}
			<div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
				<div class="max-h-[55vh] divide-y divide-neutral-100 overflow-y-auto">
					{#each datos?.filas ?? [] as f (f.identificacion + '_' + f.codexamen + '_' + f.fecha)}
						<div class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-neutral-50">
							<div class="min-w-0 flex-1">
								<p class="truncate text-[13px] font-bold text-neutral-800">{f.examen ?? f.examen_tabla ?? 'Examen'}</p>
								<p class="truncate text-[11px] text-neutral-500">
									{f.paciente} · CC {f.identificacion} · {formatearFechaMedia(f.fecha)} {f.entidad ? `· ${f.entidad}` : ''}
								</p>
								{#if f.resumen && f.resumen !== 'Pendiente' && f.resumen !== 'Sin valor'}
									<p class="truncate font-mono text-[11px] text-emerald-700">{f.resumen}</p>
								{/if}
							</div>
							<StatusPill
								texto={examenRealizado(f) ? 'Realizado' : 'Pendiente'}
								icono={examenRealizado(f) ? 'check' : 'reloj'}
								color={examenRealizado(f) ? 'emerald' : 'amber'}
							/>
							<button
								class="shrink-0 rounded-lg bg-brand/10 p-2 text-brand transition hover:bg-brand hover:text-white"
								title="Abrir examen"
								onclick={() => abrirFila(f)}
							>
								<Icon nombre="ojo" tam={16} />
							</button>
						</div>
					{/each}
				</div>
				{#if (datos?.pages ?? 1) > 1}
					<div class="flex items-center justify-between border-t border-neutral-100 px-4 py-2">
						<button
							class="rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-bold text-neutral-600 disabled:opacity-40"
							disabled={page <= 1}
							onclick={() => irPagina(page - 1)}
						>
							← Anterior
						</button>
						<span class="text-xs text-neutral-500">
							Página {datos?.page ?? 1} de {datos?.pages ?? 1} · {datos?.total ?? 0} registros
						</span>
						<button
							class="rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-bold text-neutral-600 disabled:opacity-40"
							disabled={page >= (datos?.pages ?? 1)}
							onclick={() => irPagina(page + 1)}
						>
							Siguiente →
						</button>
					</div>
				{/if}
			</div>
		{/if}

		<!-- Análisis: gráficos simples -->
		{#if datos?.charts}
			{@const c = datos.charts}
			<div class="mt-6 grid gap-4 lg:grid-cols-2">
				<div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
					<p class="mb-3 text-sm font-extrabold text-neutral-700">Exámenes por entidad (top)</p>
					<div class="space-y-1.5">
						{#each barraLista(c.entidad_labels, c.entidad_data, 'bg-brand') as b (b.label)}
							<div class="flex items-center gap-2 text-[12px]">
								<span class="w-32 truncate text-neutral-600">{b.label}</span>
								<div class="h-3 flex-1 rounded bg-neutral-100"><div class="h-3 rounded bg-brand" style="width:{b.ancho}%"></div></div>
								<span class="w-8 text-right font-bold">{b.valor}</span>
							</div>
						{/each}
					</div>
				</div>
				<div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
					<p class="mb-3 text-sm font-extrabold text-neutral-700">Exámenes por tipo</p>
					<div class="space-y-1.5">
						{#each barraLista(c.tipo_labels, c.tipo_data) as b (b.label)}
							<div class="flex items-center gap-2 text-[12px]">
								<span class="w-40 truncate text-neutral-600">{b.label}</span>
								<div class="h-3 flex-1 rounded bg-neutral-100"><div class="h-3 rounded bg-accent" style="width:{b.ancho}%"></div></div>
								<span class="w-8 text-right font-bold">{b.valor}</span>
							</div>
						{/each}
					</div>
				</div>
				<div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
					<p class="mb-3 text-sm font-extrabold text-neutral-700">Realizados vs Pendientes por entidad</p>
					{#if (c.estadoEntidad_labels?.length ?? 0) === 0}
						<p class="text-[13px] text-neutral-400">Sin datos de entidades.</p>
					{/if}
					{#each c.estadoEntidad_labels ?? [] as l, i (l)}
						{@const max = Math.max(1, (c.estadoEntidad_realizados?.[i] ?? 0) + (c.estadoEntidad_pendientes?.[i] ?? 0))}
						<div class="mb-2">
							<p class="text-[12px] font-semibold text-neutral-600">{l}</p>
							<div class="flex h-3 gap-0.5 overflow-hidden rounded bg-neutral-100">
								<div class="bg-emerald-500" style="width:{((c.estadoEntidad_realizados?.[i] ?? 0) / max) * 100}%"></div>
								<div class="bg-amber-400" style="width:{((c.estadoEntidad_pendientes?.[i] ?? 0) / max) * 100}%"></div>
							</div>
							<p class="text-[10px] text-neutral-400">
								{c.estadoEntidad_realizados?.[i] ?? 0} realizados · {c.estadoEntidad_pendientes?.[i] ?? 0} pendientes
							</p>
						</div>
					{/each}
				</div>
				<div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
					<p class="mb-3 text-sm font-extrabold text-neutral-700">Tendencia 14 días</p>
					<div class="flex h-28 items-end gap-1">
						{#each (c.tendencia_labels ?? []) as l, i (l)}
							{@const max = maxLista(c.tendencia_labels ?? [], c.tendencia_data ?? [])}
							<div class="flex flex-1 flex-col items-center gap-1">
								<div class="w-full rounded-t bg-gradient-to-t from-brand to-accent" style="height:{((c.tendencia_data?.[i] ?? 0) / max) * 100}px"></div>
								<span class="text-[8px] text-neutral-400">{l}</span>
							</div>
						{/each}
					</div>
				</div>
			</div>
		{/if}

		<!-- Salud -->
		{#if health}
			<div class="mt-6 overflow-hidden rounded-3xl border border-black/5 bg-white/85 backdrop-blur">
				<div class="flex flex-wrap items-center justify-between gap-2 border-b border-black/5 bg-gradient-to-r from-rose-50/80 via-amber-50/60 to-emerald-50/60 px-5 py-4">
					<div>
						<p class="text-base font-bold tracking-[-0.01em] text-neutral-900">Análisis de salud</p>
						<p class="text-[12px] text-neutral-500">Alteraciones detectadas a partir de los resultados del laboratorio</p>
					</div>
					<span class="rounded-full bg-white/80 px-4 py-1.5 text-sm font-extrabold text-rose-600 shadow-sm">
						{health.total ?? 0} hallazgo{(health.total ?? 0) !== 1 ? 's' : ''}
					</span>
				</div>
				<div class="p-5">
					<div class="flex flex-wrap gap-2">
						{#each health.condiciones ?? [] as cd, i (cd.label + '_' + i)}
							<span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-[12px] font-bold shadow-sm" style={`background:${COND_COLORES[i % COND_COLORES.length]}1a;color:${COND_COLORES[i % COND_COLORES.length]}`}>
								<span class="h-2 w-2 rounded-full" style={`background:${COND_COLORES[i % COND_COLORES.length]}`}></span>
								{cd.label}
								<span class="rounded-full bg-white/70 px-1.5">{cd.total}</span>
							</span>
						{/each}
						{#if (health.condiciones?.length ?? 0) === 0}
							<p class="rounded-full bg-emerald-50 px-4 py-1.5 text-[13px] font-semibold text-emerald-700">Sin condiciones destacadas 🎉</p>
						{/if}
					</div>

					<div class="mt-5 grid gap-4 md:grid-cols-3">
						<div class="rounded-2xl bg-neutral-50/80 p-4">
							<p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-neutral-500">Por género</p>
							<div class="space-y-1.5">
								{#each Object.entries(health.genero ?? {}) as [g, n] (g)}
									{@const maxG = Math.max(1, ...Object.values(health.genero ?? {}))}
									<div class="flex items-center gap-2 text-[12px]">
										<span class="w-20 text-neutral-600">{g}</span>
										<div class="h-2 flex-1 rounded-full bg-neutral-200"><div class="h-2 rounded-full bg-gradient-to-r from-fuchsia-500 to-pink-500" style={`width:${Math.round((Number(n) / maxG) * 100)}%`}></div></div>
										<b class="w-8 text-right">{n}</b>
									</div>
								{/each}
							</div>
						</div>
						<div class="rounded-2xl bg-neutral-50/80 p-4">
							<p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-neutral-500">Por rango de edad</p>
							<div class="space-y-1.5">
								{#each Object.entries(health.edad ?? {}) as [g, n] (g)}
									{@const maxE = Math.max(1, ...Object.values(health.edad ?? {}))}
									<div class="flex items-center gap-2 text-[12px]">
										<span class="w-10 text-neutral-600">{g}</span>
										<div class="h-2 flex-1 rounded-full bg-neutral-200"><div class="h-2 rounded-full bg-gradient-to-r from-sky-500 to-cyan-500" style={`width:${Math.round((Number(n) / maxE) * 100)}%`}></div></div>
										<b class="w-8 text-right">{n}</b>
									</div>
								{/each}
							</div>
						</div>
						<div class="rounded-2xl bg-neutral-50/80 p-4">
							<p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-neutral-500">Por tipo de examen</p>
							<div class="space-y-1.5">
								{#each Object.entries(health.tabla ?? {}) as [g, n] (g)}
									{@const maxT = Math.max(1, ...Object.values(health.tabla ?? {}))}
									<div class="flex items-center gap-2 text-[12px]">
										<span class="w-32 truncate text-neutral-600">{g}</span>
										<div class="h-2 flex-1 rounded-full bg-neutral-200"><div class="h-2 rounded-full bg-gradient-to-r from-violet-500 to-indigo-500" style={`width:${Math.round((Number(n) / maxT) * 100)}%`}></div></div>
										<b class="w-8 text-right">{n}</b>
									</div>
								{/each}
							</div>
						</div>
					</div>

					<p class="mt-4 rounded-xl bg-neutral-50 px-3 py-2 text-[11px] leading-relaxed text-neutral-500">
						<b>Umbrales de referencia:</b> anemia (Hb &lt;12/13 g/dL F/M), infecciones (leucocitos &gt;11 000 o &lt;4 000),
						plaquetas (&lt;150 000 / &gt;450 000), glucosa/nitritos/proteínas/bilirrubina positivos en orina, colesterol &gt;200,
						LDL &gt;130, HDL bajo (&lt;50 F / &lt;40 M), triglicéridos &gt;150, PT fuera de 11–13,5 s.
						Los datos solo ayudan a detectar posibles señales; no sustituyen la interpretación clínica.
					</p>
				</div>
			</div>
		{/if}

		{#if !cargando && !health}
			<div class="mt-4 text-center">
				<button class="rounded-xl border border-neutral-300 px-5 py-2 text-sm font-bold text-neutral-600" onclick={verSalud}>
					Mostrar análisis de salud
				</button>
			</div>
		{/if}
	{/if}
</div>
