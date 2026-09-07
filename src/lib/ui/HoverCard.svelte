<script lang="ts">
	import type { Snippet } from 'svelte'

	let {
		activo = false,
		onclick,
		children
	}: {
		activo?: boolean
		onclick?: () => void
		children?: Snippet
	} = $props()
</script>

<div
	role="button"
	tabindex="0"
	class="cursor-pointer rounded-xl border bg-white transition hover:shadow-md {activo
		? 'border-brand/30 shadow-sm'
		: 'border-neutral-200 hover:border-neutral-300'}"
	onclick={onclick}
	onkeydown={(e) => {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault()
			onclick?.()
		}
	}}
>
	{@render children?.()}
</div>
