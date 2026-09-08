<script lang="ts">
	import type { Snippet } from 'svelte'
	import Icon from './Icon.svelte'
	import Thing from './Thing.svelte'
	import type { NombreThing } from './Thing.svelte'

	let {
		titulo = '',
		subtitulo = '',
		iconoAtras = true,
		thing,
		thingFiltro = '',
		actions,
		children
	}: {
		titulo?: string
		subtitulo?: string
		iconoAtras?: boolean
		thing?: NombreThing
		thingFiltro?: string
		actions?: Snippet
		children?: Snippet
	} = $props()

	function atras() {
		history.back()
	}
</script>

<div class="mx-auto w-full max-w-5xl px-5 py-8 sm:px-8">
	{#if titulo}
		<div class="mb-6 flex flex-wrap items-center gap-3">
			{#if iconoAtras}
				<button
					class="rounded-full border border-black/10 bg-white/80 p-2 text-neutral-500 shadow-sm transition hover:bg-white"
					onclick={atras}
					title="Volver"
				>
					<Icon nombre="atras" tam={18} />
				</button>
			{/if}
			{#if thing}
				<Thing nombre={thing} tam={40} filtro={thingFiltro} clase="drop-shadow-sm" />
			{/if}
			<div class="min-w-0 flex-1">
				<h1 class="truncate text-2xl font-bold tracking-[-0.02em] text-neutral-900 sm:text-[28px]">{titulo}</h1>
				{#if subtitulo}
					<p class="mt-0.5 text-sm text-neutral-500">{subtitulo}</p>
				{/if}
			</div>
			{#if actions}
				{@render actions()}
			{/if}
		</div>
	{/if}
	{@render children?.()}
</div>
