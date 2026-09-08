/**
 * Análisis de salud por paciente a partir de sus resultados de exámenes.
 *
 * Lógica pura (sin UI/Svelte) para poder probarla con `npm test`.
 * Usa rangos de referencia habituales para adultos. NO sustituye el criterio
 * del profesional de salud: en la UI se mostrará un aviso.
 */

export type NivelHallazgo = 'ok' | 'info' | 'atencion' | 'alerta'

export interface Hallazgo {
	nivel: NivelHallazgo
	/** Mensaje corto en claro, p. ej. "Hemoglobina baja (posible anemia)". */
	mensaje: string
	/** Detalle con valor observado y referencia. */
	detalle?: string
}

export interface ResultadoParaAnalisis {
	tipo: string
	examen?: string
	fecha?: string
	tabla?: string
	/** Valor de referencia textual de procedimientos.constante (si aplica). */
	constante?: string
	/** Fila completa del detalle del examen (columnas = campos del resultado). */
	fila?: Record<string, unknown> | null
}

export interface AnalisisPorExamen {
	tipo: string
	examen: string
	fecha?: string
	hallazgos: Hallazgo[]
}

export interface AnalisisSalud {
	items: AnalisisPorExamen[]
	/** Nivel global: 'alerta' > 'atencion' > 'info' > 'ok'. */
	nivel: NivelHallazgo
	/** Frase corta de síntesis. */
	resumen: string
}

// ---------------------------------------------------------------------------
// Utilidades de normalización

/** Extrae el primer número de una cadena (acepta coma decimal y símbolos). */
export function num(v: unknown): number | null {
	if (v === null || v === undefined) return null
	const s = String(v)
		.trim()
		.replace(/[^0-9.,-]/g, '')
		.replace(',', '.')
	if (!s) return null
	const n = Number(s)
	return Number.isFinite(n) ? n : null
}

/** ¿La cadena expresa presencia/positivo? ("Positivo", "++", "Reactivo", "Presente", "SI"). */
export function esPositivo(v: unknown): boolean {
	if (v === null || v === undefined) return false
	const s = String(v).trim().toLowerCase()
	if (!s) return false
	if (/(^|[^a-z])(no|neg|ausente|no se observa|sin)\b/.test(s)) return false
	return /positiv|\+\+|reactiv|presente|anormal|^si$|^\+\b|trazas/.test(s)
}

/** ¿El texto parece un resultado negativo explícito? */
export function esNegativo(v: unknown): boolean {
	if (v === null || v === undefined) return false
	const s = String(v).trim().toLowerCase()
	return /negativ|no reactiv|ausente|no se observa|^no$|^sin\b/.test(s)
}

/** Clasifica un número dentro de [min,max]. */
export function enRango(n: number | null, min: number, max: number): 'bajo' | 'ok' | 'alto' {
	if (n === null) return 'ok'
	if (n < min) return 'bajo'
	if (n > max) return 'alto'
	return 'ok'
}

/** Detecta género femenino a partir de la columna genero ("F", "Femenino", "Mujer"). */
export function esMujer(genero?: string | null): boolean {
	const g = (genero ?? '').trim().toLowerCase()
	return g.startsWith('f') || g.includes('femenin') || g.includes('mujer')
}

/** Nivel global a partir de una lista de hallazgos. */
export function nivelDeHallazgos(hs: Hallazgo[]): NivelHallazgo {
	if (hs.some((h) => h.nivel === 'alerta')) return 'alerta'
	if (hs.some((h) => h.nivel === 'atencion')) return 'atencion'
	if (hs.some((h) => h.nivel === 'info')) return 'info'
	return 'ok'
}

export function fraseNivel(n: NivelHallazgo): string {
	switch (n) {
		case 'alerta':
			return 'Se encontraron alteraciones que requieren revisión médica.'
		case 'atencion':
			return 'Algunos valores están en límite; conviene seguimiento.'
		case 'info':
			return 'Resultados dentro de lo esperado, con observaciones informativas.'
		default:
			return 'Sin alteraciones relevantes en los exámenes analizados.'
	}
}

// ---------------------------------------------------------------------------
// Análisis por tipo

function v(fila: Record<string, unknown> | null | undefined, claves: string[]): unknown {
	if (!fila) return null
	for (const c of claves) {
		const val = fila[c]
		if (val !== null && val !== undefined && String(val).trim() !== '') return val
	}
	return null
}

function add(hs: Hallazgo[], nivel: NivelHallazgo, mensaje: string, detalle?: string): void {
	if (nivel === 'ok') return
	hs.push({ nivel, mensaje, detalle })
}

/** Cuadro hemático / hemograma (tipo 5, tabla hemogramaRayto). Rangos adultos. */
export function analizarTipo5(fila: Record<string, unknown> | null | undefined, genero?: string | null): Hallazgo[] {
	const hs: Hallazgo[] = []
	const mujer = esMujer(genero)
	const wbc = num(v(fila, ['WBC', 'leucocitos']))
	const hgb = num(v(fila, ['HGB', 'hemoglobina']))
	const hct = num(v(fila, ['HCT', 'hematocrito']))
	const plt = num(v(fila, ['PLT', 'plaquetas']))
	const mcv = num(v(fila, ['MCV']))
	const mch = num(v(fila, ['MCH']))
	const mchc = num(v(fila, ['MCHC']))
	const rbc = num(v(fila, ['RBC', 'eritrocitos']))

	// Serie roja (rangos según sexo)
	if (hgb !== null) {
		const lo = mujer ? 12 : 13.5
		const hi = mujer ? 15.5 : 17.5
		if (hgb < lo) add(hs, 'alerta', `Hemoglobina baja (${hgb} g/dL): posible anemia.`, `Referencia: ${lo}–${hi} g/dL`)
		else if (hgb > hi) add(hs, 'atencion', `Hemoglobina elevada (${hgb} g/dL).`, `Referencia: ${lo}–${hi} g/dL`)
	}
	if (hct !== null) {
		const lo = mujer ? 36 : 40
		const hi = mujer ? 46 : 50
		if (hct < lo) add(hs, 'alerta', `Hematocrito bajo (${hct}%): refuerza posible anemia.`, `Referencia: ${lo}–${hi}%`)
		else if (hct > hi) add(hs, 'atencion', `Hematocrito elevado (${hct}%).`, `Referencia: ${lo}–${hi}%`)
	}
	if (mcv !== null) {
		if (mcv < 80) add(hs, 'atencion', `MCV bajo (${mcv} fL): anemia microcítica.`, 'Referencia: 80–100 fL')
		else if (mcv > 100) add(hs, 'atencion', `MCV alto (${mcv} fL): posible anemia macrocítica.`, 'Referencia: 80–100 fL')
	}
	if (mch !== null) {
		if (mch < 27 || mch > 33) add(hs, 'info', `HCM ${mch} pg (referencia 27–33).`, undefined)
	}
	if (mchc !== null) {
		if (mchc < 32 || mchc > 36) add(hs, 'info', `CMHC ${mchc} g/dL (referencia 32–36).`, undefined)
	}
	if (rbc !== null) {
		const lo = mujer ? 4 : 4.5
		const hi = mujer ? 5.5 : 6
		if (rbc < lo) add(hs, 'info', `Glóbulos rojos ${rbc} (referencia ${lo}–${hi}).`, undefined)
		else if (rbc > hi) add(hs, 'info', `Glóbulos rojos elevados (${rbc}).`, `Referencia: ${lo}–${hi}`)
	}
	// Serie blanca
	if (wbc !== null) {
		if (wbc < 4) add(hs, 'atencion', `Leucocitos bajos (${wbc} ×10³/µL): posible leucopenia.`, 'Referencia: 4–11')
		else if (wbc > 11) add(hs, 'alerta', `Leucocitos altos (${wbc} ×10³/µL): posible infección o proceso inflamatorio.`, 'Referencia: 4–11')
	}
	if (plt !== null) {
		if (plt < 150) add(hs, 'alerta', `Plaquetas bajas (${plt} ×10³/µL): posible trombocitopenia.`, 'Referencia: 150–450')
		else if (plt > 450) add(hs, 'atencion', `Plaquetas elevadas (${plt} ×10³/µL).`, 'Referencia: 150–450')
	}
	return hs
}

/** Perfil lipídico (tipo 8). */
export function analizarTipo8(fila: Record<string, unknown> | null | undefined): Hallazgo[] {
	const hs: Hallazgo[] = []
	const col = num(v(fila, ['colesterol_total', 'colesterol']))
	const hdl = num(v(fila, ['colesterol_hdl', 'hdl']))
	const ldl = num(v(fila, ['colesterol_ldl', 'ldl']))
	const vldl = num(v(fila, ['colesterol_vldl', 'vldl']))
	const trig = num(v(fila, ['trigliceridos']))

	if (col !== null) {
		if (col >= 240) add(hs, 'alerta', `Colesterol total alto (${col} mg/dL).`, 'Referencia: <200 (deseable)')
		else if (col >= 200) add(hs, 'atencion', `Colesterol total en límite alto (${col} mg/dL).`, 'Referencia: <200 (deseable)')
	}
	if (ldl !== null) {
		if (ldl >= 160) add(hs, 'alerta', `LDL alto (${ldl} mg/dL).`, 'Referencia: <100 óptimo, <130 aceptable')
		else if (ldl >= 130) add(hs, 'atencion', `LDL en límite alto (${ldl} mg/dL).`, 'Referencia: <100 óptimo, <130 aceptable')
		else if (ldl >= 100) add(hs, 'info', `LDL ${ldl} mg/dL (por encima del óptimo <100).`, undefined)
	}
	if (hdl !== null && hdl < 40) {
		add(hs, 'atencion', `HDL bajo (${hdl} mg/dL): menor protección cardiovascular.`, 'Referencia: ≥40 mg/dL')
	}
	if (trig !== null) {
		if (trig >= 200) add(hs, 'alerta', `Triglicéridos altos (${trig} mg/dL).`, 'Referencia: <150')
		else if (trig >= 150) add(hs, 'atencion', `Triglicéridos en límite (${trig} mg/dL).`, 'Referencia: <150')
	}
	if (vldl !== null && vldl > 40) add(hs, 'info', `VLDL ${vldl} mg/dL (referencia 5–40).`, undefined)
	return hs
}

/** Parcial de orina (tipo 3). */
export function analizarTipo3(fila: Record<string, unknown> | null | undefined): Hallazgo[] {
	const hs: Hallazgo[] = []
	const densidad = num(v(fila, ['densidad']))
	const ph = num(v(fila, ['ph']))
	if (densidad !== null) {
		if (densidad < 1.005) add(hs, 'atencion', `Densidad urinaria baja (${densidad}).`, 'Referencia: 1.005–1.030')
		else if (densidad > 1.03) add(hs, 'atencion', `Densidad urinaria alta (${densidad}).`, 'Referencia: 1.005–1.030')
	}
	if (ph !== null && (ph < 4.5 || ph > 8)) {
		add(hs, 'atencion', `pH urinario ${ph} fuera del rango habitual.`, 'Referencia: 4.5–8')
	}
	if (esPositivo(v(fila, ['nitritos']))) add(hs, 'alerta', 'Nitritos positivos: posible infección urinaria bacteriana.')
	if (esPositivo(v(fila, ['leucocitos', 'leucocitosm']))) add(hs, 'atencion', 'Leucocitos presentes en orina: posible infección/inflamación.')
	if (esPositivo(v(fila, ['proteinas']))) add(hs, 'atencion', 'Proteínas en orina (proteinuria); requiere valoración.')
	if (esPositivo(v(fila, ['glucosa']))) add(hs, 'alerta', 'Glucosa en orina: posible elevación de glicemia; requiere control.')
	if (esPositivo(v(fila, ['cuerpos_cetonicos']))) add(hs, 'atencion', 'Cuerpos cetónicos positivos en orina.')
	if (esPositivo(v(fila, ['sangre_hemolizada', 'sangre_no_hemolizada', 'eritrocitos']))) add(hs, 'alerta', 'Sangre/eritrocitos en orina (hematuria); requiere valoración.')
	if (esPositivo(v(fila, ['bilirrubina']))) add(hs, 'atencion', 'Bilirrubina en orina (referencia: negativa).')
	if (esPositivo(v(fila, ['urobilinogeno']))) add(hs, 'info', 'Urobilinógeno aumentado en orina.')
	if (esPositivo(v(fila, ['cilindros_hialinos', 'cilindros_granulosos']))) add(hs, 'info', 'Cilindros presentes en orina.')
	return hs
}

/** Coprológico (tipo 4): presencia de parásitos o sangre oculta. */
export function analizarTipo4(fila: Record<string, unknown> | null | undefined): Hallazgo[] {
	const hs: Hallazgo[] = []
	if (!fila) return hs
	const parasitos: Array<[string, string]> = [
		['endamoeba_histolitica_quistes', 'Endamoeba histolítica'],
		['endamoeba_histolitica_trofozoitos', 'Endamoeba histolítica (trofozoítos)'],
		['giarda_lamblia_quistes', 'Giardia lamblia'],
		['ascaris', 'Ascaris lumbricoides'],
		['tricocefalos', 'Trichocéfalos'],
		['uncinaria', 'Uncinarias'],
		['tenia_saginata', 'Tenia saginata'],
		['tenia_solium', 'Tenia solium'],
		['strongiloides_larva', 'Strongyloides (larvas)'],
		['oxiuros_huevos', 'Oxiuros'],
		['Blastocystis_hominis_quistes', 'Blastocystis hominis'],
		['Blastocystis_hominis_trofozoitos', 'Blastocystis hominis (trofozoítos)']
	]
	for (const [clave, nombre] of parasitos) {
		if (esPositivo(fila[clave])) add(hs, 'alerta', `${nombre}: hallazgo parasitario en heces.`, `Observado: ${String(fila[clave])}`)
	}
	if (esPositivo(fila['sangre_oculta'])) add(hs, 'alerta', 'Sangre oculta positiva en heces; requiere valoración.')
	if (esPositivo(v(fila, ['lecucocitos', 'leucocitos']))) add(hs, 'atencion', 'Leucocitos en heces: posible proceso inflamatorio/infeccioso.')
	return hs
}

/** Frotis vaginal (tipo 6). */
export function analizarTipo6(fila: Record<string, unknown> | null | undefined): Hallazgo[] {
	const hs: Hallazgo[] = []
	if (!fila) return hs
	if (esPositivo(v(fila, ['trichonomas_vaginales', 'trichomonas']))) add(hs, 'alerta', 'Trichomonas vaginalis: infección por tricomoniasis.')
	if (esPositivo(v(fila, ['blastoconidias', 'seudomicelios', 'candida']))) add(hs, 'alerta', 'Levaduras/blastoconidias o seudomicelios: sugiere candidiasis vaginal.')
	if (esPositivo(v(fila, ['celulas_guia1', 'celulas_guia2']))) add(hs, 'alerta', 'Células guía: sugiere vaginosis bacteriana.')
	if (esPositivo(v(fila, ['gardnerella_sp']))) add(hs, 'atencion', 'Gardnerella presente: posible vaginosis bacteriana.')
	if (esPositivo(v(fila, ['prueba_de_aminas']))) add(hs, 'atencion', 'Prueba de aminas positiva: sugiere vaginosis bacteriana.')
	if (esPositivo(v(fila, ['pmn', 'pmnx']))) add(hs, 'info', 'Polimorfonucleares (leucocitos) aumentados en frotis.')
	return hs
}

/**
 * Valoraciones (tipo 1/2): resultado cualitativo/cuantitativo con constante de
 * referencia textual (p. ej. "NO REACTIVO", "0 - 1", "menor de 1.0").
 */
export function analizarTipo12(fila: Record<string, unknown> | null | undefined, constante?: string): Hallazgo[] {
	const hs: Hallazgo[] = []
	const valor = String(v(fila, ['valoracion']) ?? '').trim()
	if (!valor) return hs
	const constRef = String(constante ?? '').trim()
	// Cualitativos comunes
	if (/reactiv|positiv|no reactiv|negativ/i.test(constRef)) {
		const reactivo = esPositivo(valor)
		const negativo = esNegativo(valor)
		if (constRef.toLowerCase().includes('no reactiv')) {
			if (reactivo) add(hs, 'alerta', `Resultado REACTIVO (referencia: ${constRef}).`)
		} else if (/positiv|reactiv/i.test(constRef)) {
			if (negativo) add(hs, 'alerta', `Resultado NEGATIVO, pero la referencia esperada es ${constRef}.`)
		}
		return hs
	}
	// Rangos numéricos textuales: "3 - 35", "hasta 25", "menor de 1.0", "> 5"
	const nVal = num(valor)
	if (nVal === null) return hs
	const m = constRef.match(/([\d]+[.,]?[\d]*)\s*[-–a]\s*([\d]+[.,]?[\d]*)/i)
	if (m) {
		const lo = Number(m[1].replace(',', '.'))
		const hi = Number(m[2].replace(',', '.'))
		if (nVal < lo) add(hs, 'alerta', `Valor bajo (${nVal}); referencia ${constRef}.`)
		else if (nVal > hi) add(hs, 'alerta', `Valor alto (${nVal}); referencia ${constRef}.`)
		return hs
	}
	const mHasta = constRef.match(/(?:hasta|menor(?: de)?|inferior a)\s*([\d]+[.,]?[\d]*)/i)
	if (mHasta) {
		const tope = Number(mHasta[1].replace(',', '.'))
		if (nVal > tope) add(hs, 'alerta', `Valor alto (${nVal}); referencia ${constRef}.`)
		return hs
	}
	const mMas = constRef.match(/(?:mayor(?: de)?|superior a|>)\s*([\d]+[.,]?[\d]*)/i)
	if (mMas) {
		const piso = Number(mMas[1].replace(',', '.'))
		if (nVal < piso) add(hs, 'alerta', `Valor bajo (${nVal}); referencia ${constRef}.`)
	}
	return hs
}

/** Analiza un examen según su tipo y devuelve sus hallazgos. */
export function analizarResultado(r: ResultadoParaAnalisis, genero?: string | null): Hallazgo[] {
	const fila = r.fila ?? {}
	switch (r.tipo) {
		case '5':
			return analizarTipo5(fila, genero)
		case '8':
			return analizarTipo8(fila)
		case '3':
			return analizarTipo3(fila)
		case '4':
			return analizarTipo4(fila)
		case '6':
			return analizarTipo6(fila)
		case '1':
		case '2':
			return analizarTipo12(fila, r.constante)
		default:
			return []
	}
}

/** Analiza varios resultados (puede incluir varios del mismo tipo) y agrega. */
export function analizarSalud(items: ResultadoParaAnalisis[], genero?: string | null): AnalisisSalud {
	const porExamen: AnalisisPorExamen[] = items
		.filter((i) => i && i.tipo && i.fila)
		.map((i) => ({
			tipo: i.tipo,
			examen: i.examen ?? `Examen tipo ${i.tipo}`,
			fecha: i.fecha,
			hallazgos: analizarResultado(i, genero)
		}))
		.filter((a) => a.hallazgos.length > 0)

	const todos = porExamen.flatMap((a) => a.hallazgos)
	const nivel = nivelDeHallazgos(todos)
	return {
		items: porExamen,
		nivel,
		resumen: fraseNivel(nivel)
	}
}
