export type RouteName =
	| 'login'
	| 'portal'
	| 'inicio'
	| 'pacientes'
	| 'paciente-examenes'
	| 'paciente-form'
	| 'registro-examen'
	| 'crear-examen'
	| 'resultados'
	| 'configuracion'
	| 'procedimiento-form'
	| 'panel'

export interface RouteState {
	name: RouteName
	params: Record<string, string>
}

export const route = $state<RouteState>({ name: 'login', params: {} })

interface Compilada {
	name: RouteName
	re: RegExp
	keys: string[]
}

const definiciones: Array<{ pattern: string; name: RouteName }> = [
	{ pattern: '/login', name: 'login' },
	{ pattern: '/portal', name: 'portal' },
	{ pattern: '/inicio', name: 'inicio' },
	{ pattern: '/pacientes', name: 'pacientes' },
	{ pattern: '/pacientes/nuevo', name: 'paciente-form' },
	{ pattern: '/paciente/:id/editar', name: 'paciente-form' },
	{ pattern: '/paciente/:id/examenes', name: 'paciente-examenes' },
	{ pattern: '/registro/:id/:codexamen/:fecha/:tipo', name: 'registro-examen' },
	{ pattern: '/crear-examen', name: 'crear-examen' },
	{ pattern: '/resultados', name: 'resultados' },
	{ pattern: '/configuracion', name: 'configuracion' },
	{ pattern: '/procedimiento/nuevo', name: 'procedimiento-form' },
	{ pattern: '/procedimiento/:codigo', name: 'procedimiento-form' },
	{ pattern: '/panel', name: 'panel' }
]

const compiladas: Compilada[] = definiciones.map((d) => {
	const keys: string[] = []
	const re = new RegExp(
		'^' +
			d.pattern.replace(/:[^/]+/g, (m) => {
				keys.push(m.slice(1))
				return '([^/]+)'
			}) +
			'$'
	)
	return { name: d.name, re, keys }
})

function sincronizar(): void {
	const raw = (typeof location !== 'undefined' ? location.hash : '#/login').replace(/^#/, '') || '/login'
	const segmento = raw.split('?')[0]
	for (const c of compiladas) {
		const m = c.re.exec(segmento)
		if (m) {
			const params: Record<string, string> = {}
			c.keys.forEach((k, i) => {
				try {
					params[k] = decodeURIComponent(m[i + 1])
				} catch {
					params[k] = m[i + 1]
				}
			})
			route.name = c.name
			route.params = params
			return
		}
	}
	route.name = 'inicio'
	route.params = {}
}

export function navigate(path: string): void {
	const h = path.startsWith('/') ? path : '/' + path
	if (typeof location !== 'undefined') location.hash = '#' + h
}

export function startRouter(): void {
	sincronizar()
	if (typeof window !== 'undefined') window.addEventListener('hashchange', sincronizar)
}
