/**
 * Tabla de referencia de un examen (campo `procedimientos.constante2`).
 *
 * El campo puede venir como:
 *  - HTML crudo (p. ej. una <table> de clasificación);
 *  - JSON (p. ej. lista de parámetros con su valor de referencia);
 *  - texto plano.
 * Este módulo lo convierte en HTML formateado y seguro para el reporte
 * (la vista previa en pantalla y el PDF comparten el mismo HTML).
 */

/** Escapa texto para insertarlo como contenido HTML. */
export function esc(s: unknown): string {
	return String(s ?? '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
}

/** Sanitización básica de HTML de configuración (quita scripts y manejadores). */
export function sanitizarHtmlBasico(html: string): string {
	return html
		.replace(/<script[\s\S]*?<\/script>/gi, '')
		.replace(/\son\w+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '')
		.replace(/javascript\s*:/gi, '')
}

function esObjetoPlano(v: unknown): v is Record<string, unknown> {
	return typeof v === 'object' && v !== null && !Array.isArray(v)
}

const CLAVES_PREFERIDAS = ['parametro', 'nombre', 'descripcion', 'valorReferencia', 'referencia', 'valor', 'rango', 'unidad']

/** Ordena las claves de cada fila con las preferidas primero y el resto después. */
function ordenarClaves(filas: Array<Record<string, unknown>>): string[] {
	const presentes = new Set<string>()
	for (const f of filas) for (const k of Object.keys(f)) presentes.add(k)
	const preferidas = CLAVES_PREFERIDAS.filter((c) => presentes.has(c))
	const resto = Array.from(presentes).filter((c) => !CLAVES_PREFERIDAS.includes(c))
	return [...preferidas, ...resto]
}

function filasHtml(keys: string[], filas: Array<Record<string, unknown>>): string {
	const head = keys.map((k) => `<th>${esc(k)}</th>`).join('')
	const cuerpo = filas
		.map((f) => `<tr>${keys.map((k) => `<td>${esc(f[k])}</td>`).join('')}</tr>`)
		.join('')
	return `<table class="ref-tabla"><thead><tr>${head}</tr></thead><tbody>${cuerpo}</tbody></table>`
}

function jsonATabla(json: unknown): string | null {
	// Array de filas (objetos) → tabla con columnas derivadas.
	if (Array.isArray(json)) {
		const filas = json.filter(esObjetoPlano)
		if (filas.length === 0) return null
		const keys = ordenarClaves(filas as Array<Record<string, unknown>>)
		return filasHtml(keys, filas as Array<Record<string, unknown>>)
	}
	// Objeto plano {clave: valor} → tabla de 2 columnas.
	if (esObjetoPlano(json)) {
		const entradas = Object.entries(json as Record<string, unknown>).filter(([, v]) => v !== null && v !== undefined && v !== '')
		if (entradas.length === 0) return null
		const cuerpo = entradas.map(([k, v]) => `<tr><th>${esc(k)}</th><td>${esc(v)}</td></tr>`).join('')
		return `<table class="ref-tabla">${cuerpo}</table>`
	}
	return null
}

/**
 * Convierte `constante2` en el bloque HTML de la tabla de referencia.
 * Devuelve '' si el campo está vacío. No lanza: ante un JSON inválido se cae
 * a tratarlo como HTML/texto.
 */
export function htmlTablaReferencia(constante2?: string | null): string {
	const crudo = String(constante2 ?? '').trim()
	if (!crudo) return ''

	let contenido: string
	const primer = crudo.trimStart()
	if (primer.startsWith('{') || primer.startsWith('[')) {
		try {
			const json = JSON.parse(crudo)
			contenido = jsonATabla(json) ?? `<pre>${esc(crudo)}</pre>`
		} catch {
			contenido = `<pre>${esc(crudo)}</pre>`
		}
	} else if (primer.startsWith('<')) {
		contenido = sanitizarHtmlBasico(crudo)
	} else {
		contenido = `<pre>${esc(crudo)}</pre>`
	}
	return `<div class="ref-bloque"><h3>Tabla de referencia</h3>${contenido}</div>`
}
