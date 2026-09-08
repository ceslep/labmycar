<script lang="ts">
	import { estadoValor, type SerieMarcador } from '../salud/marcadores'
	import Icon from './Icon.svelte'
	import Thing from './Thing.svelte'
	import { formatearFechaMedia } from '../utils/format'

	let { series }: { series: SerieMarcador[] } = $props()

	// Cada serie con 1 punto → vista "último vs rango"; con ≥2 → se ofrece evolución.
	let vista = $state<'evolucion' | 'rango'>('evolucion')

	function colorEstado(s: 'ok' | 'atencion' | 'alto' | 'bajo'): string {
		switch (s) {
			case 'atencion':
				return '#d97706' // ámbar-600
			case 'alto':
			case 'bajo':
				return '#dc2626' // red-600
			default:
				return '#10b981' // emerald-500
		}
	}

	function textoEstado(s: 'ok' | 'atencion' | 'alto' | 'bajo'): string {
		switch (s) {
			case 'atencion':
				return 'En atención'
			case 'alto':
				return 'Alto'
			case 'bajo':
				return 'Bajo'
			default:
				return 'Normal'
		}
	}

	// ---------- Cálculo de escala compartida ----------
	function dominio(s: SerieMarcador): [number, number] {
		const vals = s.puntos.map((p) => p.valor)
		const limites = [s.m.okMin, s.m.okMax, s.m.atenMin, s.m.atenMax].filter(
			(v): v is number => typeof v === 'number'
		)
		const todos = [...vals, ...limites]
		let lo = Math.min(...todos)
		let hi = Math.max(...todos)
		const rango = hi - lo
		const pad = rango === 0 ? Math.abs(hi) * 0.1 || 1 : rango * 0.12
		lo -= pad
		hi += pad
		return [lo, hi]
	}

	function pct(v: number, [lo, hi]: [number, number]): number {
		if (hi === lo) return 50
		return ((v - lo) / (hi - lo)) * 100
	}

	function serieConEvolucion(): SerieMarcador[] {
		return series.filter((s) => s.puntos.length >= 2)
	}
</script>

{#if series.length === 0}
	<div class="flex items-center gap-2 rounded-2xl border border-dashed border-neutral-200 bg-neutral-50/60 p-4 text-[13px] text-neutral-500">
		<Icon nombre="resultados" tam={16} />
		Sin marcadores numéricos con historial para graficar todavía.
	</div>
{:else}
	<div class="mb-3 flex flex-wrap items-center gap-2">
		{#if serieConEvolucion().length > 0}
			<button
				class="rounded-full px-3 py-1.5 text-[12px] font-bold transition {vista === 'evolucion'
					? 'bg-brand text-white'
					: 'bg-white text-neutral-600 ring-1 ring-neutral-200 hover:bg-neutral-50'}"
				onclick={() => (vista = 'evolucion')}
			>
				Evolución
			</button>
		{/if}
		<button
			class="rounded-full px-3 py-1.5 text-[12px] font-bold transition {vista === 'rango'
				? 'bg-brand text-white'
				: 'bg-white text-neutral-600 ring-1 ring-neutral-200 hover:bg-neutral-50'}"
			onclick={() => (vista = 'rango')}
		>
			Último valor vs rango
		</button>
		<span class="ml-auto flex items-center gap-3 text-[11px] text-neutral-400">
			<span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full" style="background:#10b981"></span>Normal</span>
			<span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full" style="background:#d97706"></span>Atención</span>
			<span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full" style="background:#dc2626"></span>Alto/Bajo</span>
		</span>
	</div>

	{#if vista === 'rango' || serieConEvolucion().length === 0}
		<!-- Vista: último valor contra su rango de referencia -->
		<div class="space-y-3">
			{#each series as s (s.tipo + s.m.clave)}
				{@const ultimo = s.puntos[s.puntos.length - 1]}
				{@const est = estadoValor(s.m, ultimo.valor)}
				{@const dom = dominio(s)}
				{@const loPct = s.m.okMin !== undefined ? pct(s.m.okMin, dom) : 0}
				{@const hiPct = s.m.okMax !== undefined ? pct(s.m.okMax, dom) : 100}
				{@const valPct = pct(ultimo.valor, dom)}
				{@const okInicio = Math.min(loPct, hiPct)}
				{@const okAncho = Math.abs(hiPct - loPct)}
				<div class="rounded-2xl border border-neutral-100 bg-neutral-50/60 p-3.5">
					<div class="flex flex-wrap items-baseline justify-between gap-x-2">
						<p class="text-[13px] font-extrabold text-neutral-800">{s.m.nombre}</p>
						<p class="text-[12px] font-bold {est === 'ok' ? 'text-emerald-700' : est === 'atencion' ? 'text-amber-600' : 'text-red-600'}">
							{ultimo.valor}{s.m.unidad ? ` ${s.m.unidad}` : ''}
							<span class="ml-1 font-semibold text-neutral-400">({textoEstado(est)})</span>
						</p>
					</div>
					{#if s.puntos.length > 1}
						<p class="text-[11px] text-neutral-400">
							Última medición: {formatearFechaMedia(ultimo.fecha)}
							{#if s.puntos.length > 1} · {s.puntos.length} muestras{/if}
						</p>
					{/if}
					<div class="relative mt-2 h-2.5 w-full rounded-full bg-neutral-200/80">
						{#if okInicio < 100 && okAncho > 0}
							<div
								class="absolute top-0 h-full bg-emerald-300/70"
								style="left:{Math.max(0, okInicio)}%;width:{Math.min(100 - Math.max(0, okInicio), okAncho)}%"
							></div>
						{/if}
						<div
							class="absolute top-1/2 h-4 w-1 -translate-y-1/2 rounded-full ring-2 ring-white"
							style="left:calc({valPct}% - 2px);background:{colorEstado(est)}"
							title="{s.m.nombre}: {ultimo.valor} {s.m.unidad}"
						></div>
					</div>
					<div class="mt-1 flex justify-between text-[10px] font-semibold text-neutral-400">
						<span>{s.m.okMin !== undefined ? `mín ${s.m.okMin}` : ''}</span>
						<span>{s.m.okMax !== undefined ? `máx ${s.m.okMax}` : ''}</span>
					</div>
				</div>
			{/each}
		</div>
	{:else}
		<!-- Vista: evolución temporal por marcador -->
		<div class="grid gap-4 lg:grid-cols-2">
			{#each serieConEvolucion() as s (s.tipo + s.m.clave)}
				{@const dom = dominio(s)}
				{@const n = s.puntos.length}
				{@const yPos = (v: number) => (100 - pct(v, dom)) }
				<div class="rounded-2xl border border-neutral-100 bg-neutral-50/60 p-3.5">
					<p class="text-[13px] font-extrabold text-neutral-800">{s.m.nombre}</p>
					<p class="text-[11px] text-neutral-400">
						{s.m.unidad ? `Unidad: ${s.m.unidad} · ` : ''}
						Rango normal: {s.m.okMin ?? '—'}–{s.m.okMax ?? '—'}
					</p>
					<svg viewBox="0 0 320 150" class="mt-2 w-full">
						<!-- Banda del rango normal -->
						{#if s.m.okMin !== undefined && s.m.okMax !== undefined}
							<rect
								x="0"
								y={yPos(s.m.okMax)}
								width="320"
								height={Math.max(0, yPos(s.m.okMin) - yPos(s.m.okMax))}
								fill="rgba(16,185,129,0.12)"
							/>
						{/if}
						<!-- Línea de evolución -->
						<polyline
							fill="none"
							stroke="#0a7cff"
							stroke-width="2.5"
							stroke-linecap="round"
							stroke-linejoin="round"
							points={s.puntos
								.map((p, i) => {
									const x = (i / Math.max(1, n - 1)) * 320
									const y = (100 - pct(p.valor, dom)) * 1.5
									return `${x},${y}`
								})
								.join(' ')}
						/>
						<!-- Puntos -->
						{#each s.puntos as p, i (s.m.clave + i)}
							{@const est = estadoValor(s.m, p.valor)}
							<circle
								cx={(i / Math.max(1, n - 1)) * 320}
								cy={(100 - pct(p.valor, dom)) * 1.5}
								r="5"
								fill={colorEstado(est)}
								stroke="#fff"
								stroke-width="1.5"
							>
								<title>{formatearFechaMedia(p.fecha)}: {p.valor}{s.m.unidad ? ` ${s.m.unidad}` : ''}</title>
							</circle>
						{/each}
					</svg>
					<div class="mt-1 flex justify-between text-[10px] font-semibold text-neutral-400">
						<span>{formatearFechaMedia(s.puntos[0].fecha)}</span>
						<span>{formatearFechaMedia(s.puntos[n - 1].fecha)}</span>
					</div>
				</div>
			{/each}
		</div>
	{/if}

	<div class="mt-3 flex items-start gap-2 rounded-xl bg-sky-50 px-3 py-2 text-[11px] leading-snug text-sky-800">
		<Thing nombre="shield" tam={16} clase="mt-0.5 shrink-0" />
		<div>
			Rangos de referencia generales para adultos. Si solo hay una muestra de un marcador no se dibuja su
			evolución (se muestra el valor contra el rango). Los valores se toman de los exámenes realizados.
		</div>
	</div>
{/if}
