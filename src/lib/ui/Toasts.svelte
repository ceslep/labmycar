<script lang="ts">
	import { toasts } from '../stores/toast.svelte'
	import Icon from './Icon.svelte'
	import type { NombreIcono } from './iconos'

	const estilos: Record<string, { borde: string; icono: NombreIcono }> = {
		ok: { borde: 'border-emerald-200 bg-emerald-50 text-emerald-800', icono: 'check' },
		error: { borde: 'border-red-200 bg-red-50 text-red-800', icono: 'alerta' },
		info: { borde: 'border-sky-200 bg-sky-50 text-sky-800', icono: 'reloj' }
	}
</script>

<div class="pointer-events-none fixed right-4 top-4 z-[100] flex w-80 flex-col gap-2">
	{#each toasts as t (t.id)}
		<div
			class="pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg {estilos[
				t.tipo
			].borde}"
		>
			<Icon nombre={estilos[t.tipo].icono} tam={18} clase="mt-0.5 shrink-0" />
			<span class="font-medium">{t.msg}</span>
		</div>
	{/each}
</div>
