const KEY = 'labmycar:base_url'
export const DEFAULT_BASE_URL = 'https://mycar.iedeoccidente.com/libphp/'

function leerGuardada(): string {
	if (typeof localStorage === 'undefined') return DEFAULT_BASE_URL
	return localStorage.getItem(KEY) ?? DEFAULT_BASE_URL
}

export const baseUrl = $state({ value: leerGuardada() })

export function getBaseUrl(): string {
	const v = baseUrl.value.trim()
	if (!v) return DEFAULT_BASE_URL
	return v.endsWith('/') ? v : v + '/'
}

export function setBaseUrl(url: string): void {
	const t = url.trim()
	if (!t) return
	baseUrl.value = t.endsWith('/') ? t : t + '/'
	try {
		localStorage.setItem(KEY, baseUrl.value)
	} catch {
		/* almacenamiento no disponible */
	}
}

export function resetBaseUrl(): void {
	baseUrl.value = DEFAULT_BASE_URL
	try {
		localStorage.removeItem(KEY)
	} catch {
		/* almacenamiento no disponible */
	}
}
