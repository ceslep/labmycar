<script lang="ts">
	import type { Snippet } from 'svelte'
	import Icon from './Icon.svelte'
	import type { NombreIcono } from './iconos'

	let {
		icono = 'alerta',
		titulo,
		subtitulo = '',
		children
	}: { icono?: NombreIcono; titulo: string; subtitulo?: string; children?: Snippet } = $props()
</script>

<div class="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
	<div class="mb-1 rounded-2xl bg-neutral-100 p-4 text-neutral-400">
		<Icon nombre={icono} tam={30} />
	</div>
	<p class="text-base font-bold text-neutral-700">{titulo}</p>
	{#if subtitulo}
		<p class="max-w-sm text-sm text-neutral-500">{subtitulo}</p>
	{/if}
	{@render children?.()}
</div>
