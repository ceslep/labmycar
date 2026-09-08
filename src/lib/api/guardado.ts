/**
 * Lógica pura del guardado de exámenes (sin dependencias de Svelte/UI),
 * reutilizada por la capa API y cubierta por tests en tests/.
 */

export interface RespMsg {
	msg?: boolean | string
}

/** Los endpoints heredados responden msg como booleano o como "si"/"no". */
export function msgEsOk(r: RespMsg | null | undefined): boolean {
	return r != null && r.msg !== false && r.msg !== 'no'
}

/** Payload de asignación: un objeto {codigo} por cada procedimiento, sin pérdidas. */
export function payloadGuardarExamenes(procedimientos: Array<{ codigo?: string }>): string {
	return JSON.stringify(
		procedimientos
			.filter((p) => p && p.codigo)
			.map((p) => ({ codigo: p.codigo }))
	)
}

/** Códigos únicos (conserva el orden de llegada). */
export function codigosUnicos(procedimientos: Array<{ codigo?: string }>): string[] {
	const vistos = new Set<string>()
	const salida: string[] = []
	for (const p of procedimientos) {
		const c = (p?.codigo ?? '').trim()
		if (c && !vistos.has(c)) {
			vistos.add(c)
			salida.push(c)
		}
	}
	return salida
}
