const KEY = 'labmycar:token'

function leer(): string {
	if (typeof localStorage === 'undefined') return ''
	return localStorage.getItem(KEY) ?? ''
}

export const token = $state({ value: leer() })

export function guardarToken(t: string): void {
	token.value = t
	try {
		localStorage.setItem(KEY, t)
	} catch {
		/* almacenamiento no disponible */
	}
}

export function limpiarToken(): void {
	token.value = ''
	try {
		localStorage.removeItem(KEY)
	} catch {
		/* almacenamiento no disponible */
	}
}
