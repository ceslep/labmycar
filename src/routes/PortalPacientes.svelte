<script lang="ts">
	import { examenesPacientePublico, getConfiguracion, type ExamenPublico } from '../lib/api/laboratorio'
	import { esquemas } from '../lib/schemas/resultados'
	import { cargarImpresion, htmlReporteExamen } from '../lib/reportes/print'
	import { verReporte } from '../lib/stores/reportevista.svelte'
	import { navigate } from '../lib/router.svelte'
	import type { Paciente } from '../lib/models/models'
	import { examenRealizado, nombreCompletoPaciente } from '../lib/models/models'
	import { calcularEdad, formatearFechaLarga } from '../lib/utils/format'
	import { toast } from '../lib/stores/toast.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import EmptyState from '../lib/ui/EmptyState.svelte'
	import StatusPill from '../lib/ui/StatusPill.svelte'
	import HoverCard from '../lib/ui/HoverCard.svelte'

	let identificacion = $state('')
	let numero = $state('')
	let buscando = $state(false)
	let error = $state('')
	let filas = $state<ExamenPublico[]>([])
	let consultado = $state(false)

	async function consultar() {
		const id = identificacion.trim()
		const num = numero.trim()
		if (!id || !num) {
			error = 'Ingrese la identificación y el número verificador.'
			return
		}
		error = ''
		buscando = true
		consultado = true
		const r = await examenesPacientePublico(id, num)
		buscando = false
		if (r.length === 0) error = 'No se encontraron resultados. Verifique la identificación y el número.'
		filas = r
	}

	function pacienteDeFila(f: ExamenPublico): Paciente {
		return {
			identificacion: f.identificacion,
			nombres: f.nombres,
			fecnac: f.fecnac,
			entidad: f.entidad
		}
	}

	const grupos = $derived.by<Array<{ fecha: string; filas: ExamenPublico[] }>>(() => {
		const m = new Map<string, ExamenPublico[]>()
		for (const f of filas) {
			const fecha = f.fecha ?? ''
			if (!m.has(fecha)) m.set(fecha, [])
			m.get(fecha)!.push(f)
		}
		return Array.from(m.entries())
			.sort((a, b) => b[0].localeCompare(a[0]))
			.map(([fecha, fs]) => ({ fecha, filas: fs }))
	})

	async function imprimir(fila: ExamenPublico) {
		try {
			const examen = {
				codexamen: fila.codexamen,
				identificacion: fila.identificacion,
				fecha: fila.fecha,
				realizado: fila.realizado,
				entidad: fila.entidad,
				examen: fila.examen,
				tipo: fila.tipo,
				tabla: fila.tabla,
				info: fila.info
			}
			const [config, carga] = await Promise.all([getConfiguracion(), cargarImpresion(examen, esquemas)])
			verReporte(
				htmlReporteExamen({
					config: config ?? {},
					paciente: pacienteDeFila(fila),
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
</script>

<div class="flex min-h-dvh flex-col" style="background: linear-gradient(160deg,#161569 0%,#1a1a80 40%,#0e7490 100%)">
	<header class="flex items-center justify-between px-6 py-4">
		<div class="flex items-center gap-2.5 text-white">
			<div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/30">
				<Icon nombre="lab" tam={19} />
			</div>
			<div>
				<p class="text-[15px] font-extrabold leading-tight">Consulta de resultados</p>
				<p class="text-[11px] text-white/70">Portal del paciente</p>
			</div>
		</div>
		<button
			class="rounded-xl bg-white/15 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white ring-1 ring-white/30 transition hover:bg-white/25"
			onclick={() => navigate('/login')}
		>
			Acceso del laboratorio
		</button>
	</header>

	<main class="mx-auto w-full max-w-3xl flex-1 px-4 pb-12 pt-6 sm:px-6">
		<div class="rounded-3xl bg-white p-7 shadow-2xl">
			<h1 class="text-xl font-extrabold text-neutral-800">Consulte sus resultados</h1>
			<p class="mt-1 text-sm text-neutral-500">
				Ingrese su número de identificación y su <b>número verificador</b> (año de nacimiento o los 4 últimos
				dígitos de su teléfono registrado).
			</p>

			<div class="mt-5 grid gap-4 sm:grid-cols-2">
				<label class="block">
					<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">Identificación</span>
					<input
						bind:value={identificacion}
						type="text"
						placeholder="N.º de documento"
						class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
					/>
				</label>
				<label class="block">
					<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">Número verificador</span>
					<input
						bind:value={numero}
						type="text"
						placeholder="Ej. 1990 o 1234"
						class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
						onkeydown={(e) => {
							if (e.key === 'Enter') consultar()
						}}
					/>
				</label>
			</div>

			{#if error}
				<p class="mt-3 flex items-center gap-1.5 text-[13px] font-semibold text-red-600">
					<Icon nombre="alerta" tam={14} />
					{error}
				</p>
			{/if}

			<button
				class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl py-3 text-[15px] font-bold text-white shadow-lg transition hover:brightness-110 disabled:opacity-60"
				style="background: linear-gradient(135deg,#161569,#0e7490)"
				onclick={consultar}
				disabled={buscando}
			>
				<Icon nombre="buscar" tam={17} />
				{buscando ? 'Consultando…' : 'Consultar resultados'}
			</button>
		</div>

		{#if buscando}
			<div class="mt-6 [&>div]:py-4"><Loader texto="Consultando…" tam={20} /></div>
		{:else if filas.length > 0}
			<div class="mt-6 rounded-3xl bg-white p-6 shadow-2xl">
				{#if grupos.length > 0}
					{@const p = pacienteDeFila(filas[0])}
					<p class="text-base font-extrabold text-neutral-800">{nombreCompletoPaciente(p)}</p>
					<p class="mb-4 text-[13px] text-neutral-500">
						CC {filas[0].identificacion}
						{#if filas[0].fecnac} · {calcularEdad(filas[0].fecnac)}{/if}
						{#if filas[0].entidad} · {filas[0].entidad}{/if}
					</p>
				{/if}

				{#each grupos as g (g.fecha)}
					<div class="mb-5 last:mb-0">
						<p class="mb-2 border-b border-neutral-100 pb-1.5 text-sm font-extrabold uppercase tracking-wide text-brand">
							{formatearFechaLarga(g.fecha)}
						</p>
						<div class="space-y-2">
							{#each g.filas as f (f.codexamen)}
								<HoverCard>
									<div class="flex items-center gap-3 p-3.5">
										<div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#161569] to-[#0e7490] text-white">
											<Icon nombre="lab" tam={19} />
										</div>
										<div class="min-w-0 flex-1">
											<p class="truncate text-sm font-bold text-neutral-800">{f.examen ?? f.codexamen}</p>
											<p class="text-[11px] text-neutral-400">
												{examenRealizado(f) ? 'Resultado disponible' : 'En proceso'}
											</p>
										</div>
										<StatusPill
											texto={examenRealizado(f) ? 'Realizado' : 'Pendiente'}
											icono={examenRealizado(f) ? 'check' : 'reloj'}
											color={examenRealizado(f) ? 'emerald' : 'amber'}
										/>
										<button
											class="inline-flex items-center gap-1.5 rounded-lg bg-neutral-100 px-3 py-1.5 text-xs font-bold text-neutral-700 transition hover:bg-brand hover:text-white disabled:opacity-40"
											disabled={!examenRealizado(f)}
											onclick={() => imprimir(f)}
											title="Ver e imprimir reporte (PDF)"
										>
											<Icon nombre="imprimir" tam={14} />
											Reporte
										</button>
									</div>
								</HoverCard>
							{/each}
						</div>
					</div>
				{/each}
			</div>
		{:else if consultado}
			<div class="mt-6 rounded-3xl bg-white p-4 shadow-2xl">
				<EmptyState icono="buscar" titulo="Sin resultados para mostrar" />
			</div>
		{/if}
	</main>

	<footer class="px-6 py-4 text-center text-[12px] text-white/70">
		Resultados emitidos por el laboratorio. Para dudas, contacte al laboratorio directamente.
	</footer>
</div>
