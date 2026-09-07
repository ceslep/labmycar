<script lang="ts">
	import type { Snippet } from 'svelte'
	import Icon from './Icon.svelte'

	let {
		titulo = '',
		subtitulo = '',
		iconoAtras = true,
		actions,
		children
	}: {
		titulo?: string
		subtitulo?: string
		iconoAtras?: boolean
		actions?: Snippet
		children?: Snippet
	} = $props()

	function atras() {
		history.back()
	}
</script>

<div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6">
	{#if titulo}
		<div class="mb-5 flex flex-wrap items-center gap-3">
			{#if iconoAtras}
				<button
					class="rounded-xl border border-neutral-200 bg-white p-2 text-neutral-600 transition hover:bg-neutral-50"
					onclick={atras}
					title="Volver"
				>
					<Icon nombre="atras" tam={18} />
				</button>
			{/if}
			<div class="min-w-0 flex-1">
				<h1 class="truncate text-xl font-extrabold tracking-tight text-neutral-800">{titulo}</h1>
				{#if subtitulo}
					<p class="text-sm text-neutral-500">{subtitulo}</p>
				{/if}
			</div>
			{#if actions}
				{@render actions()}
			{/if}
		</div>
	{/if}
	{@render children?.()}
</div>
