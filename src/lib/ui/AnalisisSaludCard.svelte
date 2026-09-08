<script lang="ts">
	import { cargarAnalisisDesdeExamenes, tieneResultadosAnalizables, type ExamenAnalizable } from '../salud/cargar'
	import type { AnalisisSalud, NivelHallazgo } from '../salud/analisis'
	import Icon from './Icon.svelte'
	import type { NombreIcono } from './iconos'
	import Thing from './Thing.svelte'
	import { formatearFechaMedia } from '../utils/format'

	let {
		examenes,
		genero = null,
		titulo = 'Análisis de salud'
	}: { examenes: ExamenAnalizable[]; genero?: string | null; titulo?: string } = $props()

	let analisis = $state<AnalisisSalud | null>(null)
	let cargando = $state(false)
	let error = $state('')
	/** Contador para invalidar respuestas asíncronas obsoletas. */
	let intento = $state(0)

	$effect(() => {
		void examenes
		void genero
		void intento
		const miIntento = intento
		const hay = tieneResultadosAnalizables(examenes)
		if (!hay) {
			analisis = null
			cargando = false
			error = ''
			return
		}
		cargando = true
		error = ''
		cargarAnalisisDesdeExamenes(examenes, genero)
			.then((r) => {
				if (miIntento !== intento) return
				analisis = r
				cargando = false
			})
			.catch((e) => {
				if (miIntento !== intento) return
				error = e instanceof Error ? e.message : 'No se pudo calcular el análisis'
				analisis = null
				cargando = false
			})
	})

	function recalcular() {
		intento++
	}

	const estiloNivel: Record<NivelHallazgo, { pill: string; icono: NombreIcono; texto: string }> = {
		ok: { pill: 'bg-emerald-100 text-emerald-700', icono: 'check', texto: 'Sin alteraciones' },
		info: { pill: 'bg-sky-100 text-sky-700', icono: 'mensaje', texto: 'Con observaciones' },
		atencion: { pill: 'bg-amber-100 text-amber-700', icono: 'reloj', texto: 'Requiere seguimiento' },
		alerta: { pill: 'bg-red-100 text-red-700', icono: 'alerta', texto: 'Requiere revisión' }
	}

	function colorHallazgo(n: NivelHallazgo): string {
		switch (n) {
			case 'alerta':
				return 'text-red-600'
			case 'atencion':
				return 'text-amber-600'
			case 'info':
				return 'text-sky-600'
			default:
				return 'text-emerald-600'
		}
	}

	function dotHallazgo(n: NivelHallazgo): string {
		switch (n) {
			case 'alerta':
				return 'bg-red-500'
			case 'atencion':
				return 'bg-amber-500'
			case 'info':
				return 'bg-sky-500'
			default:
				return 'bg-emerald-500'
		}
	}
</script>

<div class="rounded-3xl border border-black/5 bg-white/90 p-5 shadow-[0_10px_30px_-20px_rgba(0,0,0,0.35)] backdrop-blur sm:p-6">
	<div class="flex flex-wrap items-center justify-between gap-2">
		<div class="flex items-center gap-3">
			<div class="grid h-10 w-10 place-items-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 shadow-sm">
				<Thing nombre="shield" tam={22} filtro="brightness(0) invert(1)" />
			</div>
			<div>
				<p class="text-[15px] font-extrabold text-neutral-900">{titulo}</p>
				<p class="text-[11px] text-neutral-400">Calculado desde los últimos resultados de cada examen</p>
			</div>
		</div>
		<button
			class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-2.5 py-1.5 text-[12px] font-bold text-neutral-600 transition hover:bg-neutral-50"
			onclick={recalcular}
			disabled={cargando}
			title="Recalcular el análisis"
		>
			<Icon nombre="refrescar" tam={13} />
			Recalcular
		</button>
	</div>

	{#if cargando}
		<div class="mt-4 flex items-center gap-2 text-sm text-neutral-500">
			<Icon nombre="refrescar" tam={15} clase="animate-spin" />
			Calculando análisis de salud…
		</div>
	{:else if error}
		<p class="mt-4 flex items-center gap-1.5 text-[13px] font-semibold text-red-600">
			<Icon nombre="alerta" tam={14} />
			{error}
		</p>
	{:else if !analisis}
		<p class="mt-4 text-[13px] text-neutral-400">
			Este paciente aún no tiene resultados emitidos de exámenes analizables (cuadro hemático, perfil lipídico,
			parcial de orina, coprológico, frotis o valoraciones). El análisis aparecerá cuando existan.
		</p>
	{:else if analisis.items.length === 0}
		<p class="mt-4 flex items-center gap-1.5 text-[13px] font-semibold text-emerald-700">
			<Icon nombre="check" tam={15} />
			Sin alteraciones relevantes en los resultados analizados.
		</p>
	{:else}
		{@const e = estiloNivel[analisis.nivel]}
		<div class="mt-4 flex flex-wrap items-center gap-2">
			<span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[12px] font-bold {e.pill}">
				<Icon nombre={e.icono} tam={13} />
				{e.texto}
			</span>
			<span class="text-[12px] text-neutral-500">{analisis.resumen}</span>
		</div>

		<div class="mt-4 space-y-3">
			{#each analisis.items as it (it.tipo + ':' + (it.fecha ?? ''))}
				<div class="rounded-2xl border border-neutral-100 bg-neutral-50/60 p-3.5">
					<p class="flex flex-wrap items-center gap-x-2 text-[13px] font-extrabold text-neutral-800">
						{it.examen}
						{#if it.fecha}
							<span class="text-[11px] font-semibold text-neutral-400">{formatearFechaMedia(it.fecha)}</span>
						{/if}
					</p>
					<ul class="mt-1.5 space-y-1.5">
						{#each it.hallazgos as h (h.mensaje)}
							<li class="flex items-start gap-2 text-[13px] leading-snug">
								<span class={"mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full " + dotHallazgo(h.nivel)}></span>
								<span class="min-w-0">
									<span class={colorHallazgo(h.nivel)}>{h.mensaje}</span>
									{#if h.detalle}
										<span class="block text-[11px] text-neutral-400">{h.detalle}</span>
									{/if}
								</span>
							</li>
						{/each}
					</ul>
				</div>
			{/each}
		</div>

		<p class="mt-3 text-[11px] italic leading-snug text-neutral-400">
			Análisis automático con rangos de referencia generales para adultos. No sustituye la valoración del
			profesional de la salud.
		</p>
	{/if}
</div>
