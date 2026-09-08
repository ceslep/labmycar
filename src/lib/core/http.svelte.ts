import { getBaseUrl } from './config.svelte'
import { token } from '../stores/token.svelte'

/** Endpoints que exigen token (escrituras y datos sensibles del Panel/Admin). */
const TOKEN_PATHS = [
	'savePaciente.php',
	'guardarExamen.php',
	'guardarExamenes.php',
	'updateExamen.php',
	'eliminarExamen.php',
	'eliminarProcedimiento.php',
	'guardarProcedimiento.php',
	'guardarConfiguracion.php',
	'guardarItemsExamenes.php',
	'setEntidad.php',
	'panelPacientes.php',
	'panelPacientesPorIds.php',
	'adminDatos.php',
	'migracion_indices.php'
]

/** Estado del servidor: value = caído/OK; url/detalle = último fallo para diagnóstico. */
export const serverDown = $state({ value: false, url: '', detalle: '' })

export class ApiError extends Error {
	constructor(
		public status: number,
		message: string
	) {
		super(message)
		this.name = 'ApiError'
	}
}

export interface HttpOptions {
	method?: 'GET' | 'POST' | 'PUT' | 'DELETE'
	body?: unknown
}

function marcarCaido(url: string, detalle: string): void {
	serverDown.value = true
	serverDown.url = url
	serverDown.detalle = detalle
}

function marcarOk(): void {
	serverDown.value = false
	serverDown.url = ''
	serverDown.detalle = ''
}

/**
 * Petición HTTP al backend PHP.
 *
 * Nota CORS: el backend responde `Access-Control-Allow-Origin: *` pero no
 * contesta preflight (OPTIONS) con allow-methods/headers. Para evitarlo se
 * envía el cuerpo JSON sin cabecera `Content-Type` explícita: el navegador la
 * fija como `text/plain;charset=UTF-8`, tipo "safelisted" que no dispara
 * preflight (igual que hacía el cliente Dart original).
 */
export async function http<T>(path: string, opts: HttpOptions = {}): Promise<T> {
	const url = getBaseUrl() + path.replace(/^\//, '')
	// Sin cabeceras personalizadas → el navegador no dispara preflight CORS.
	// El token viaja en el body (text/plain) para los endpoints protegidos.
	let cuerpo = opts.body === undefined ? undefined : opts.body
	if (token.value && TOKEN_PATHS.some((p) => path.includes(p))) {
		const base = (cuerpo && typeof cuerpo === 'object' ? { ...(cuerpo as Record<string, unknown>) } : {}) as Record<string, unknown>
		base['token'] = token.value
		cuerpo = base
	}
	let res: Response
	const ctrl = new AbortController()
	const tiempo = setTimeout(() => ctrl.abort(), 20000)
	try {
		res = await fetch(url, {
			method: opts.method ?? 'GET',
			body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo),
			signal: ctrl.signal
		})
	} catch (e) {
		const detalle = e instanceof Error && e.name === 'AbortError' ? 'Tiempo de espera agotado (20 s)' : e instanceof Error ? e.message : String(e)
		marcarCaido(url, detalle)
		console.error('[API] Error de red al conectar con', url, '->', detalle)
		throw new ApiError(0, 'No hay conexión con el servidor. Verifique su conexión a internet.')
	} finally {
		clearTimeout(tiempo)
	}
	if (res.status >= 500) marcarCaido(url, `HTTP ${res.status}`)
	else marcarOk()
	if (!res.ok) {
		console.error('[API] Respuesta inesperada de', url, '-> status', res.status)
	}
	const texto = await res.text()
	if (!res.ok) throw new ApiError(res.status, `Error HTTP ${res.status}`)
	if (!texto) return undefined as T
	try {
		return JSON.parse(texto) as T
	} catch (e) {
		console.error('[API] Respuesta no JSON de', url, '->', e instanceof Error ? e.message : e)
		throw new ApiError(0, 'Respuesta inválida del servidor (no es JSON).')
	}
}

/** Petición a un servicio local arbitrario (p. ej. el analizador Rayto en 127.0.0.1:3000). */
export async function httpLocal<T>(url: string, body: unknown): Promise<T> {
	let res: Response
	try {
		res = await fetch(url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(body)
		})
	} catch (e) {
		const detalle = e instanceof Error ? e.message : String(e)
		console.error('[API] No se pudo conectar con el servicio local', url, '->', detalle)
		throw new ApiError(0, `No se pudo conectar con el servicio local ${url}`)
	}
	if (!res.ok) throw new ApiError(res.status, `Error HTTP ${res.status}`)
	const texto = await res.text()
	if (!texto) return undefined as T
	return JSON.parse(texto) as T
}

/** Comprueba si el servidor responde (GET getProcedimientos.php con timeout). */
export async function probarServidor(): Promise<boolean> {
	const url = getBaseUrl() + 'getProcedimientos.php'
	try {
		const ctrl = new AbortController()
		const t = setTimeout(() => ctrl.abort(), 8000)
		const res = await fetch(url, { signal: ctrl.signal })
		clearTimeout(t)
		if (res.status >= 500) marcarCaido(url, `HTTP ${res.status}`)
		else marcarOk()
		return res.status === 200
	} catch (e) {
		const detalle = e instanceof Error ? e.message : String(e)
		marcarCaido(url, detalle)
		console.error('[API] Fallo de verificación del servidor ->', detalle)
		return false
	}
}
