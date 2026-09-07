export interface VistaReporte {
	abierta: boolean
	titulo: string
	html: string
}

export const reporteVista = $state<VistaReporte>({ abierta: false, titulo: '', html: '' })

export function verReporte(html: string, titulo: string): void {
	reporteVista.html = html
	reporteVista.titulo = titulo
	reporteVista.abierta = true
}

export function cerrarReporte(): void {
	reporteVista.abierta = false
	reporteVista.html = ''
	reporteVista.titulo = ''
}
