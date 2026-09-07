<script lang="ts">
	import { ICONOS, type NombreIcono } from './iconos'

	let {
		nombre,
		tam = 18,
		clase = ''
	}: { nombre: NombreIcono; tam?: number; clase?: string } = $props()
</script>

<svg
	viewBox="0 0 24 24"
	width={tam}
	height={tam}
	fill="none"
	stroke="currentColor"
	stroke-width="2"
	stroke-linecap="round"
	stroke-linejoin="round"
	class={clase}
	aria-hidden="true"
>
	{#each ICONOS[nombre] as d}
		<path {d} />
	{/each}
</svg>
