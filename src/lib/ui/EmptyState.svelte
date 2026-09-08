<script lang="ts">
	import type { Snippet } from 'svelte'
	import Icon from './Icon.svelte'
	import type { NombreIcono } from './iconos'
	import Thing from './Thing.svelte'
	import type { NombreThing } from './Thing.svelte'

	let {
		icono = 'alerta',
		thing,
		titulo,
		subtitulo = '',
		children
	}: {
		icono?: NombreIcono
		thing?: NombreThing
		titulo: string
		subtitulo?: string
		children?: Snippet
	} = $props()
</script>

<div class="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
	{#if thing}
		<div class="mb-1 opacity-90">
			<Thing nombre={thing} tam={72} clase="drop-shadow-sm" />
		</div>
	{:else}
		<div class="mb-1 rounded-2xl bg-neutral-100 p-4 text-neutral-400">
			<Icon nombre={icono} tam={30} />
		</div>
	{/if}
	<p class="text-base font-semibold text-neutral-800">{titulo}</p>
	{#if subtitulo}
		<p class="max-w-sm text-sm text-neutral-500">{subtitulo}</p>
	{/if}
	{@render children?.()}
</div>
