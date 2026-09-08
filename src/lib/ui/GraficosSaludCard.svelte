<script lang="ts">
	import { cargarHistorialExamenes, type ExamenAnalizable } from '../salud/cargar'
	import { construirSeries, type SerieMarcador } from '../salud/marcadores'
	import GraficosSalud from './GraficosSalud.svelte'
	import Icon from './Icon.svelte'
	import Thing from './Thing.svelte'

	let {
		examenes,
		genero = null,
		titulo = 'Gráficas de resultados'
	}: { examenes: ExamenAnalizable[]; genero?: string | null; titulo?: string } = $props()

	let series = $state<SerieMarcador[]>([])
	let cargando = $state(true)
	let error = $state('')
	let intento = $state(0)

	$effect(() => {
		void examenes
		void genero
		void intento
		const miIntento = intento
		const hay = examenes.some((e) => e.realizado === 'S' && e.tipo && ['3', '5', '8'].includes(e.tipo))
		if (!hay) {
			series = []
			cargando = false
			return
		}
		cargando = true
		error = ''
		cargarHistorialExamenes(examenes, 12)
			.then((hist) => {
				if (miIntento !== intento) return
				series = construirSeries(hist, genero)
				cargando = false
			})
			.catch((e) => {
				if (miIntento !== intento) return
				error = e instanceof Error ? e.message : 'No se pudieron cargar los gráficos'
				series = []
				cargando = false
			})
	})

	function recalcular() {
		intento++
	}
</script>

<div class="rounded-3xl border border-black/5 bg-white/90 p-5 shadow-[0_10px_30px_-20px_rgba(0,0,0,0.35)] backdrop-blur sm:p-6">
	<div class="flex flex-wrap items-center justify-between gap-2">
		<div class="flex items-center gap-3">
			<div class="grid h-10 w-10 place-items-center rounded-2xl bg-gradient-to-br from-violet-400 to-purple-600 shadow-sm">
				<Thing nombre="dna" tam={22} filtro="brightness(0) invert(1)" />
			</div>
			<div>
				<p class="text-[15px] font-extrabold text-neutral-900">{titulo}</p>
				<p class="text-[11px] text-neutral-400">Evolución de marcadores y valores frente a su rango</p>
			</div>
		</div>
		<button
			class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-2.5 py-1.5 text-[12px] font-bold text-neutral-600 transition hover:bg-neutral-50"
			onclick={recalcular}
			disabled={cargando}
			title="Recargar datos de los gráficos"
		>
			<Icon nombre="refrescar" tam={13} />
			Actualizar
		</button>
	</div>

	<div class="mt-4">
		{#if cargando}
			<div class="flex items-center gap-2 text-sm text-neutral-500">
				<Icon nombre="refrescar" tam={15} clase="animate-spin" />
				Cargando historial de resultados…
			</div>
		{:else if error}
			<p class="flex items-center gap-1.5 text-[13px] font-semibold text-red-600">
				<Icon nombre="alerta" tam={14} />
				{error}
			</p>
		{:else}
			<GraficosSalud {series} />
		{/if}
	</div>
</div>
