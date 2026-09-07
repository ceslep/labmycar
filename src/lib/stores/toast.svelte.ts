export type TipoToast = 'ok' | 'error' | 'info'

export interface ToastItem {
	id: number
	msg: string
	tipo: TipoToast
}

export const toasts = $state<ToastItem[]>([])

let secuencia = 1

export function toast(msg: string, tipo: TipoToast = 'ok', duracionMs = 3200): void {
	const id = secuencia++
	toasts.push({ id, msg, tipo })
	setTimeout(() => {
		const i = toasts.findIndex((t) => t.id === id)
		if (i >= 0) toasts.splice(i, 1)
	}, duracionMs)
}
