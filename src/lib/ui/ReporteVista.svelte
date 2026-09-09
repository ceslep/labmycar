<script lang="ts">
	import { reporteVista, cerrarReporte } from '../stores/reportevista.svelte'
	import Icon from './Icon.svelte'
	import { toast } from '../stores/toast.svelte'
	import { abrirVentanaImpresion, escribirReporte } from '../reportes/print'
	import { estadoConfig } from '../stores/configapp.svelte'
	import Thing from './Thing.svelte'

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
		// Reporte del servidor (PDF): se intenta imprimir el iframe; si el visor no lo
		// permite, se abre el PDF en pestaña para imprimirlo desde el visor del navegador.
		if (reporteVista.url) {
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
			window.open(reporteVista.url, '_blank')
			return
		}
		// Reporte HTML del cliente.
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
				<div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white">
					{#if estadoConfig.logoData}
						<img src={estadoConfig.logoData} alt="Logo" class="h-7 w-7 object-contain" />
					{:else}
						<Thing nombre="laboratory" tam={26} />
					{/if}
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
					{#if reporteVista.url}
						<iframe
							bind:this={iframeEl}
							title={reporteVista.titulo || 'Reporte'}
							src={reporteVista.url}
							class="h-[72vh] w-full rounded-sm border-0 bg-white sm:h-[74vh]"
						></iframe>
					{:else}
						<iframe
							bind:this={iframeEl}
							title={reporteVista.titulo || 'Reporte'}
							srcdoc={reporteVista.html}
							class="h-[72vh] w-full rounded-sm border-0 bg-white sm:h-[74vh]"
						></iframe>
					{/if}
				</div>
			</div>
		</div>
	</div>
{/if}
