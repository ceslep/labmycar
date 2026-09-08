<script lang="ts">
	// Gráficos decorativos "thiings" (copiados de libphp/icons/thiings a /public/thiings)
	export type NombreThing =
		| 'blood'
		| 'building'
		| 'dna'
		| 'laboratory'
		| 'medical-report'
		| 'microscope'
		| 'patient'
		| 'shield'
		| 'stethoscope'

	let {
		nombre,
		tam = 48,
		clase = '',
		filtro = ''
	}: { nombre: NombreThing; tam?: number; clase?: string; filtro?: string } = $props()
</script>

<img
	src={'/thiings/' + nombre + '.png'}
	alt=""
	aria-hidden="true"
	draggable="false"
	loading="lazy"
	style="width:{tam}px;height:{tam}px;object-fit:contain;{filtro ? `filter:${filtro};` : ''}"
	class={clase}
/>
