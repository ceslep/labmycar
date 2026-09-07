const KEY = 'labmycar:sesion'

function leerSesion(): boolean {
	if (typeof sessionStorage === 'undefined') return false
	return sessionStorage.getItem(KEY) === '1'
}

export const authed = $state({ value: leerSesion() })

export function iniciarSesion(): void {
	authed.value = true
	try {
		sessionStorage.setItem(KEY, '1')
	} catch {
		/* almacenamiento no disponible */
	}
}

export function cerrarSesion(): void {
	authed.value = false
	try {
		sessionStorage.removeItem(KEY)
	} catch {
		/* almacenamiento no disponible */
	}
}

export function restaurarSesion(): void {
	authed.value = leerSesion()
}
