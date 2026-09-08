/**
 * Marcadores numéricos con rangos de referencia para los gráficos de evolución
 * de salud. Lógica pura (sin UI) para poder probarla con `npm test`.
 * Los rangos son los habituales para adultos; la UI muestra el aviso médico.
 */

export type EstadoValor = 'ok' | 'atencion' | 'alto' | 'bajo'

export interface Marcador {
	clave: string
	nombre: string
	unidad: string
	/** Límites de normalidad (según el caso). */
	okMin?: number
	okMax?: number
	/** Límites de "atención" (entre ok y atención el punto se pinta ámbar). */
	atenMax?: number
	atenMin?: number
}

export interface PuntoSerie {
	fecha: string
	valor: number
}

export interface SerieMarcador {
	tipo: string
	m: Marcador
	puntos: PuntoSerie[]
}

export interface HistorialFila {
	tipo: string
	fecha: string
	fila: Record<string, unknown> | null | undefined
}

export function esMujerMarcador(genero?: string | null): boolean {
	const g = (genero ?? '').trim().toLowerCase()
	return g.startsWith('f') || g.includes('femenin') || g.includes('mujer')
}

/** Marcadores del cuadro hemático (tipo 5). */
export function marcadoresTipo5(genero?: string | null): Marcador[] {
	const mujer = esMujerMarcador(genero)
	return [
		{
			clave: 'HGB',
			nombre: 'Hemoglobina',
			unidad: 'g/dL',
			okMin: mujer ? 12 : 13.5,
			okMax: mujer ? 15.5 : 17.5
		},
		{ clave: 'HCT', nombre: 'Hematocrito', unidad: '%', okMin: mujer ? 36 : 40, okMax: mujer ? 46 : 50 },
		{ clave: 'WBC', nombre: 'Leucocitos', unidad: '×10³/µL', okMin: 4, okMax: 11 },
		{ clave: 'PLT', nombre: 'Plaquetas', unidad: '×10³/µL', okMin: 150, okMax: 450 },
		{ clave: 'MCV', nombre: 'MCV', unidad: 'fL', okMin: 80, okMax: 100 },
		{ clave: 'MCH', nombre: 'HCM', unidad: 'pg', okMin: 27, okMax: 33 },
		{ clave: 'MCHC', nombre: 'CMHC', unidad: 'g/dL', okMin: 32, okMax: 36 }
	]
}

/** Marcadores del perfil lipídico (tipo 8). */
export function marcadoresTipo8(): Marcador[] {
	return [
		{ clave: 'colesterol_total', nombre: 'Colesterol total', unidad: 'mg/dL', okMax: 200, atenMax: 239 },
		{ clave: 'colesterol_ldl', nombre: 'Colesterol LDL', unidad: 'mg/dL', okMax: 100, atenMax: 159 },
		{ clave: 'colesterol_hdl', nombre: 'Colesterol HDL', unidad: 'mg/dL', okMin: 40 },
		{ clave: 'trigliceridos', nombre: 'Triglicéridos', unidad: 'mg/dL', okMax: 150, atenMax: 199 }
	]
}

/** Marcadores del parcial de orina (tipo 3) cuando son numéricos. */
export function marcadoresTipo3(): Marcador[] {
	return [
		{ clave: 'densidad', nombre: 'Densidad', unidad: '', okMin: 1.005, okMax: 1.03 },
		{ clave: 'ph', nombre: 'pH', unidad: '', okMin: 4.5, okMax: 8 }
	]
}

export function marcadoresDeTipo(tipo: string, genero?: string | null): Marcador[] {
	switch (tipo) {
		case '5':
			return marcadoresTipo5(genero)
		case '8':
			return marcadoresTipo8()
		case '3':
			return marcadoresTipo3()
		default:
			return []
	}
}

export function extraerNumero(v: unknown): number | null {
	if (v === null || v === undefined) return null
	const s = String(v).trim().replace(',', '.').replace(/[^0-9.\-]/g, '')
	if (!s) return null
	const n = Number(s)
	return Number.isFinite(n) ? n : null
}

/** Estado del valor según los límites del marcador. */
export function estadoValor(m: Marcador, valor: number): EstadoValor {
	if (m.okMin !== undefined && m.okMax !== undefined) {
		if (valor < m.okMin) return 'bajo'
		if (valor > m.okMax) return m.atenMax !== undefined && valor <= m.atenMax ? 'atencion' : 'alto'
		return 'ok'
	}
	if (m.okMax !== undefined) {
		if (valor <= m.okMax) return 'ok'
		return m.atenMax !== undefined && valor <= m.atenMax ? 'atencion' : 'alto'
	}
	if (m.okMin !== undefined) {
		if (valor >= m.okMin) return 'ok'
		return m.atenMin !== undefined && valor >= m.atenMin ? 'atencion' : 'bajo'
	}
	return 'ok'
}

/**
 * Construye una serie por marcador a partir del historial de filas ya cargadas.
 * Las filas deben venir ordenadas por fecha (ascendente) por el llamador o se
 * ordenan aquí por fecha.
 */
export function construirSeries(
	historial: HistorialFila[],
	genero?: string | null,
	soloTipos: string[] = ['3', '5', '8']
): SerieMarcador[] {
	const series: SerieMarcador[] = []
	for (const tipo of soloTipos) {
		const marcadores = marcadoresDeTipo(tipo, genero)
		if (marcadores.length === 0) continue
		const filas = historial
			.filter((h) => h.tipo === tipo)
			.slice()
			.sort((a, b) => a.fecha.localeCompare(b.fecha))
		for (const m of marcadores) {
			const puntos: PuntoSerie[] = []
			for (const h of filas) {
				const val = extraerNumero(h.fila?.[m.clave])
				if (val !== null) puntos.push({ fecha: h.fecha, valor: val })
			}
			if (puntos.length > 0) series.push({ tipo, m, puntos })
		}
	}
	return series
}
