export interface VistaReporte {
	abierta: boolean
	titulo: string
	/** Reporte HTML generado en el cliente (se muestra con srcdoc). */
	html: string
	/** Reporte del servidor (printphp, PDF/HTML) mostrado con <iframe src>. */
	url: string
}

export const reporteVista = $state<VistaReporte>({ abierta: false, titulo: '', html: '', url: '' })

export function verReporte(html: string, titulo: string): void {
	reporteVista.html = html
	reporteVista.url = ''
	reporteVista.titulo = titulo
	reporteVista.abierta = true
}

/** Abre en el modal interno un reporte servido por URL (p. ej. printphp/*.php). */
export function verReporteUrl(url: string, titulo: string): void {
	reporteVista.url = url
	reporteVista.html = ''
	reporteVista.titulo = titulo
	reporteVista.abierta = true
}

export function cerrarReporte(): void {
	reporteVista.abierta = false
	reporteVista.html = ''
	reporteVista.url = ''
	reporteVista.titulo = ''
}
