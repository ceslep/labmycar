<script lang="ts">
	import { serverDown, probarServidor } from '../core/http.svelte'
	import Icon from './Icon.svelte'
	import { toast } from '../stores/toast.svelte'

	let detalleVisible = $state(false)

	async function reintentar() {
		const ok = await probarServidor()
		if (ok) toast('Conexión restablecida', 'ok')
		else toast('El servidor sigue sin responder', 'error')
	}
</script>

{#if serverDown.value}
	<div
		class="fixed inset-x-0 top-0 z-[90] flex items-center gap-3 bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-md"
	>
		<button class="flex items-center gap-2" onclick={() => (detalleVisible = !detalleVisible)} title="Ver detalle técnico">
			<Icon nombre="alerta" tam={16} />
			<span>Sin conexión con el servidor. Verifique su conexión a internet.</span>
		</button>
		<div class="flex-1"></div>
		<button
			class="rounded-lg bg-white/20 px-3 py-1 text-xs font-bold uppercase tracking-wide transition hover:bg-white/30"
			onclick={reintentar}
		>
			Reintentar
		</button>
	</div>
	{#if detalleVisible && serverDown.url}
		<div class="fixed inset-x-0 top-11 z-[90] bg-red-900/95 px-4 py-2 font-mono text-[11px] text-red-100 shadow-md">
			URL: {serverDown.url} — {serverDown.detalle}
		</div>
	{/if}
{/if}
