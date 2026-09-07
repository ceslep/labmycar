<script lang="ts">
	let { texto = 'Cargando…', tam = 22 }: { texto?: string; tam?: number } = $props()
</script>

<div class="flex flex-col items-center justify-center gap-3 py-10 text-neutral-500">
	<div
		style="width:{tam}px;height:{tam}px"
		class="animate-spin rounded-full border-[3px] border-brand/25 border-t-brand"
	></div>
	{#if texto}
		<p class="text-sm">{texto}</p>
	{/if}
</div>
