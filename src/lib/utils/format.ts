/** Utilidades de formato en español (replican intl + helpers de la app Dart). */

export const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
export const MESES = [
	'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
	'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
]

/** Fecha actual local en formato yyyy-MM-dd. */
export function hoyISO(): string {
	const d = new Date()
	const mm = String(d.getMonth() + 1).padStart(2, '0')
	const dd = String(d.getDate()).padStart(2, '0')
	return `${d.getFullYear()}-${mm}-${dd}`
}

/** Suma (o resta si negativo) días a una fecha ISO yyyy-MM-dd. */
export function sumarDiasISO(iso: string, dias: number): string {
	const d = new Date(iso + 'T00:00:00')
	d.setDate(d.getDate() + dias)
	const mm = String(d.getMonth() + 1).padStart(2, '0')
	const dd = String(d.getDate()).padStart(2, '0')
	return `${d.getFullYear()}-${mm}-${dd}`
}

/** "Lunes 3 de enero de 2024" desde una fecha ISO yyyy-MM-dd. */
export function formatearFechaLarga(iso?: string): string {
	if (!iso) return ''
	const d = new Date(iso + (iso.length === 10 ? 'T00:00:00' : ''))
	if (isNaN(d.getTime())) return iso
	return `${DIAS[d.getDay()]} ${d.getDate()} de ${MESES[d.getMonth()]} de ${d.getFullYear()}`
}

/** dd/mm/aaaa */
export function formatearFechaCorta(iso?: string): string {
	if (!iso) return ''
	const d = new Date(iso.length === 10 ? iso + 'T00:00:00' : iso)
	if (isNaN(d.getTime())) return iso
	return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
}

/** "3 de enero de 2024" sin día de semana. */
export function formatearFechaMedia(iso?: string): string {
	if (!iso) return ''
	const d = new Date(iso.length === 10 ? iso + 'T00:00:00' : iso)
	if (isNaN(d.getTime())) return iso
	return `${d.getDate()} de ${MESES[d.getMonth()]} de ${d.getFullYear()}`
}

/** Años, meses y días transcurridos desde fecnac (ISO yyyy-MM-dd). */
export function calcularEdad(fecnac?: string): string {
	if (!fecnac) return ''
	const nac = new Date(fecnac.length === 10 ? fecnac + 'T00:00:00' : fecnac)
	if (isNaN(nac.getTime())) return ''
	const ahora = new Date()
	let anios = ahora.getFullYear() - nac.getFullYear()
	let meses = ahora.getMonth() - nac.getMonth()
	let dias = ahora.getDate() - nac.getDate()
	if (dias < 0) {
		meses--
		dias += new Date(ahora.getFullYear(), ahora.getMonth(), 0).getDate()
	}
	if (meses < 0) {
		anios--
		meses += 12
	}
	if (anios >= 2) return `${anios} años`
	if (anios === 1) return '1 año'
	if (meses > 0) return `${meses} mes${meses > 1 ? 'es' : ''}`
	return `${dias} día${dias !== 1 ? 's' : ''}`
}

export function primeraMayuscula(s?: string): string {
	if (!s) return ''
	return s.charAt(0).toUpperCase() + s.slice(1)
}

/** Pinta un color "r;g;b" del catálogo como css. */
export function colorRgb(css?: string): string | undefined {
	if (!css) return undefined
	const m = /^\s*(\d{1,3})\s*;\s*(\d{1,3})\s*;\s*(\d{1,3})\s*$/.exec(css)
	if (!m) return undefined
	return `rgb(${m[1]}, ${m[2]}, ${m[3]})`
}
