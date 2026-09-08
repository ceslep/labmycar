const KEY = 'labmycar:sesion'
import { token, limpiarToken } from './token.svelte'

function leerSesion(): boolean {
	if (typeof sessionStorage === 'undefined') return false
	return sessionStorage.getItem(KEY) === '1'
}

export const authed = $state({ value: leerSesion() && token.value !== '' })

export function iniciarSesion(): void {
	if (!token.value) return
	authed.value = true
	try {
		sessionStorage.setItem(KEY, '1')
	} catch {
		/* almacenamiento no disponible */
	}
}

export function cerrarSesion(): void {
	authed.value = false
	limpiarToken()
	try {
		sessionStorage.removeItem(KEY)
	} catch {
		/* almacenamiento no disponible */
	}
}

export function restaurarSesion(): void {
	authed.value = leerSesion() && token.value !== ''
}
