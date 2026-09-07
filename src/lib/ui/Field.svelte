<script lang="ts">
	let {
		label,
		value = $bindable(''),
		tipo = 'text',
		placeholder = '',
		disabled = false,
		onEnter
	}: {
		label?: string
		value?: string
		tipo?: 'text' | 'password' | 'number' | 'date' | 'tel' | 'email'
		placeholder?: string
		disabled?: boolean
		onEnter?: () => void
	} = $props()
</script>

<label class="block">
	{#if label}
		<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">{label}</span>
	{/if}
	<input
		bind:value
		type={tipo}
		{placeholder}
		{disabled}
		class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] text-neutral-900 outline-none transition placeholder:text-neutral-400 focus:border-brand focus:ring-2 focus:ring-brand/20 disabled:bg-neutral-100"
		onkeydown={(e) => {
			if (e.key === 'Enter') onEnter?.()
		}}
	/>
</label>
