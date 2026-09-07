<script lang="ts">
	import Icon from './Icon.svelte'
	import type { NombreIcono } from './iconos'

	let {
		texto,
		icono = 'check',
		color = 'emerald'
	}: { texto: string; icono?: NombreIcono; color?: 'emerald' | 'amber' | 'red' | 'sky' | 'neutral' } = $props()

	const paleta: Record<string, string> = {
		emerald: 'bg-emerald-100 text-emerald-700',
		amber: 'bg-amber-100 text-amber-700',
		red: 'bg-red-100 text-red-700',
		sky: 'bg-sky-100 text-sky-700',
		neutral: 'bg-neutral-200 text-neutral-700'
	}
</script>

<span
	class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {paleta[color]}"
>
	<Icon nombre={icono} tam={13} />
	{texto}
</span>
