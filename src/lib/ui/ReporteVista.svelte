<script lang="ts">
	import { reporteVista, cerrarReporte } from '../stores/reportevista.svelte'
	import Icon from './Icon.svelte'
	import { toast } from '../stores/toast.svelte'
	import { abrirVentanaImpresion, escribirReporte } from '../reportes/print'

	let iframeEl: HTMLIFrameElement | undefined = $state()

	// Bloquea el scroll del fondo mientras el modal está abierto.
	$effect(() => {
		if (!reporteVista.abierta) return
		const anterior = document.body.style.overflow
		document.body.style.overflow = 'hidden'
		return () => {
			document.body.style.overflow = anterior
		}
	})

	function imprimir() {
		// Intento 1: imprimir el contenido del iframe directamente.
		try {
			const cw = iframeEl?.contentWindow
			if (cw && typeof cw.print === 'function') {
				cw.focus()
				cw.print()
				return
			}
		} catch {
			/* continúa con el respaldo */
		}
		// Intento 2 (navegadores que no imprimen desde iframe): ventana temporal con el mismo HTML.
		try {
			const win = abrirVentanaImpresion()
			if (win && escribirReporte(win, reporteVista.html)) return
		} catch {
			/* continúa */
		}
		toast('No se pudo imprimir. Use el diálogo del navegador o pruebe de nuevo.', 'error')
	}

	function onKey(e: KeyboardEvent) {
		if (e.key === 'Escape' && reporteVista.abierta) cerrarReporte()
	}
</script>

<svelte:window onkeydown={onKey} />

{#if reporteVista.abierta}
	<!-- Fondo (cierre al hacer clic fuera; el teclado cierra con Esc vía <svelte:window>) -->
	<!-- svelte-ignore a11y_no_static_element_interactions, a11y_click_events_have_key_events -->
	<div
		class="fixed inset-0 z-[110] flex bg-black/60 backdrop-blur-[2px] sm:items-center sm:justify-center sm:p-6"
		onclick={(e) => {
			if (e.target === e.currentTarget) cerrarReporte()
		}}
	>
		<!-- Panel: pantalla completa en móvil; centrado tipo visor en escritorio -->
		<div class="flex h-full w-full flex-col overflow-hidden bg-neutral-100 shadow-2xl sm:h-[90vh] sm:max-w-5xl sm:rounded-2xl">
			<!-- Barra superior -->
			<div class="flex items-center gap-2 border-b border-neutral-200 bg-white px-3 py-2.5 sm:px-4">
				<div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-[#161569] to-[#0e7490] text-white">
					<Icon nombre="lab" tam={17} />
				</div>
				<div class="min-w-0 flex-1">
					<p class="truncate text-sm font-extrabold text-neutral-800">{reporteVista.titulo || 'Vista previa del reporte'}</p>
					<p class="hidden text-[11px] text-neutral-400 sm:block">Vista previa del reporte · use Imprimir o Guardar como PDF</p>
				</div>
				<button
					class="inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white shadow transition hover:bg-brand-700"
					onclick={imprimir}
				>
					<Icon nombre="imprimir" tam={16} />
					<span class="hidden sm:inline">Imprimir / PDF</span>
					<span class="sm:hidden">Imprimir</span>
				</button>
				<button
					class="inline-flex items-center gap-1.5 rounded-xl border border-neutral-200 px-3 py-2 text-sm font-bold text-neutral-500 transition hover:bg-neutral-100"
					onclick={cerrarReporte}
					title="Cerrar (Esc)"
				>
					<Icon nombre="cerrar" tam={16} />
				</button>
			</div>

			<!-- Vista previa del documento -->
			<div class="min-h-0 flex-1 overflow-auto bg-neutral-200/70 p-2 sm:p-6">
				<div class="mx-auto w-full max-w-[800px] shadow-lg">
					<!-- svelte-ignore a11y_no_static_element_interactions -->
					<iframe
						bind:this={iframeEl}
						title={reporteVista.titulo || 'Reporte'}
						srcdoc={reporteVista.html}
						class="h-[72vh] w-full rounded-sm border-0 bg-white sm:h-[74vh]"
					></iframe>
				</div>
			</div>
		</div>
	</div>
{/if}
