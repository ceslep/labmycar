<script lang="ts">
	import { imprimiendo } from '../stores/imprimiendo.svelte'
</script>

{#if imprimiendo.activo}
	<div class="fixed inset-0 z-[300] flex items-center justify-center bg-white/70 backdrop-blur-[2px]">
		<div class="flex flex-col items-center gap-3 rounded-2xl border border-neutral-200 bg-white px-8 py-6 shadow-2xl">
			<div style="width:32px;height:32px" class="animate-spin rounded-full border-[3px] border-brand/25 border-t-brand"></div>
			<p class="text-sm font-semibold text-neutral-600">{imprimiendo.texto || 'Preparando reporte…'}</p>
		</div>
	</div>
{/if}
