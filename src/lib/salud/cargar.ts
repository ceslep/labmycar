import { esquemas } from '../schemas/resultados'
import { cargarImpresion } from '../reportes/print'
import { examenRealizado, type Examen } from '../models/models'
import { analizarSalud, type AnalisisSalud, type ResultadoParaAnalisis } from './analisis'

/** Tipos con motor de análisis disponible. */
export const TIPOS_ANALIZABLES = ['1', '2', '3', '4', '5', '6', '8']

/** Forma mínima de un examen para poder analizarlo (Examen y ExamenPublico la cumplen). */
export interface ExamenAnalizable {
	identificacion?: string
	codexamen?: string
	fecha?: string
	realizado?: string
	tipo?: string
	examen?: string
	tabla?: string
	info?: string
}

function aExamen(e: ExamenAnalizable): Examen {
	return {
		identificacion: e.identificacion,
		codexamen: e.codexamen,
		fecha: e.fecha,
		realizado: e.realizado,
		tipo: e.tipo,
		examen: e.examen,
		tabla: e.tabla,
		info: e.info
	}
}

/**
 * Carga el análisis de salud del paciente: toma el resultado MÁS RECIENTE de
 * cada tipo de examen analizable (para no sobrecargar con todo el historial) y
 * los evalúa con el motor de `analisis.ts`.
 */
export async function cargarAnalisisDesdeExamenes(
	examenes: ExamenAnalizable[],
	genero?: string | null
): Promise<AnalisisSalud> {
	const candidatos = examenes.filter(
		(e) => examenRealizado(aExamen(e)) && e.tipo && TIPOS_ANALIZABLES.includes(e.tipo) && esquemas[e.tipo]
	)
	// Último resultado por tipo (fecha mayor; las fechas son ISO comparables).
	const porTipo = new Map<string, ExamenAnalizable>()
	for (const e of candidatos) {
		const actual = porTipo.get(e.tipo!)
		if (!actual || (e.fecha ?? '') > (actual.fecha ?? '')) porTipo.set(e.tipo!, e)
	}

	const items: ResultadoParaAnalisis[] = []
	for (const ex of porTipo.values()) {
		try {
			const carga = await cargarImpresion(aExamen(ex), esquemas)
			if (!carga.fila) continue
			items.push({
				tipo: ex.tipo ?? '',
				examen: carga.procedimiento?.nombre ?? ex.examen ?? `Examen tipo ${ex.tipo}`,
				fecha: ex.fecha,
				tabla: ex.tabla,
				constante: carga.procedimiento?.constante,
				fila: carga.fila
			})
		} catch {
			// Si un detalle no se puede cargar, se omite sin romper el análisis.
		}
	}
	return analizarSalud(items, genero)
}

/** ¿Hay algún examen realizado que el motor pueda analizar? */
export function tieneResultadosAnalizables(examenes: ExamenAnalizable[]): boolean {
	return examenes.some((e) => examenRealizado(aExamen(e)) && e.tipo && TIPOS_ANALIZABLES.includes(e.tipo))
}

export interface HistorialExamen {
	tipo: string
	fecha: string
	fila: Record<string, unknown> | null
}

/**
 * Carga el historial (últimas `limite` fechas realizadas por tipo) con su fila
 * de detalle, para alimentar los gráficos de evolución.
 */
export async function cargarHistorialExamenes(
	examenes: ExamenAnalizable[],
	limite = 12
): Promise<HistorialExamen[]> {
	const realizados = examenes
		.filter((e) => examenRealizado(aExamen(e)) && e.tipo && e.fecha && esquemas[e.tipo])
		.sort((a, b) => (b.fecha ?? '').localeCompare(a.fecha ?? ''))
	// Últimos `limite` por tipo (fechas descendentes ya).
	const porTipo = new Map<string, number>()
	const elegidos: ExamenAnalizable[] = []
	for (const e of realizados) {
		const n = porTipo.get(e.tipo!) ?? 0
		if (n >= limite) continue
		porTipo.set(e.tipo!, n + 1)
		elegidos.push(e)
	}
	const salida: HistorialExamen[] = []
	for (const ex of elegidos) {
		try {
			const esquema = esquemas[ex.tipo!]
			const fila = await esquema.cargar(ex.identificacion ?? '', ex.fecha ?? '', ex.codexamen ?? '')
			salida.push({ tipo: ex.tipo!, fecha: ex.fecha!, fila })
		} catch {
			// Si una fila no carga, se omite sin romper el historial.
		}
	}
	return salida
}
