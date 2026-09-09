/**
 * Firma del bacteriólogo por fecha, con la misma regla que usa el servidor
 * (libphp/printphp/configuracion_bacteriologos.php con su firmas.json):
 * una firma sin periodos siempre está activa; con periodos, está activa cuando
 * desde <= fecha del reporte <= hasta. Es la fuente que usan los PDF del
 * servidor (prt.php / printphp) y con la que el reporte interno debe coincidir.
 */

export interface FirmaActiva {
	nombre: string
	cargo?: string
	/** Imagen de firma (p. ej. data:image/png;base64,…). */
	imagen_firma?: string
	/** Archivo alternativo alojado en printphp/ (si no hay imagen_firma). */
	archivo?: string
	periodos?: Array<{ desde?: string; hasta?: string }>
}

/** Parte de fecha YYYY-MM-DD de un texto de fecha; '' si no es válida. */
function diaIso(fecha?: string | null): string {
	const t = String(fecha ?? '').trim().slice(0, 10)
	return /^\d{4}-\d{2}-\d{2}$/.test(t) ? t : ''
}

/**
 * Devuelve la firma activa para la fecha del reporte, o null si no hay firma
 * que cubra la fecha (el llamador usa entonces la config de la BD).
 */
export function firmaActivaParaFecha(firmas: FirmaActiva[] | null | undefined, fecha?: string | null): FirmaActiva | null {
	if (!firmas || firmas.length === 0) return null
	const dia = diaIso(fecha)
	if (!dia) return null
	for (const f of firmas) {
		const periodos = f.periodos ?? []
		if (periodos.length === 0) return f
		const activa = periodos.some((p) => {
			const desde = p?.desde ? String(p.desde).slice(0, 10) : ''
			const hasta = p?.hasta ? String(p.hasta).slice(0, 10) : ''
			return (!desde || dia >= desde) && (!hasta || dia <= hasta)
		})
		if (activa) return f
	}
	return null
}
