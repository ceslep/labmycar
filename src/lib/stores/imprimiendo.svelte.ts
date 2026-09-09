export const imprimiendo = $state({ activo: false, texto: '' })

/** Muestra el indicador mientras se ejecuta la tarea (p. ej. preparar un reporte). */
export async function conImprimiendo<T>(texto: string, tarea: () => Promise<T>): Promise<T> {
	imprimiendo.activo = true
	imprimiendo.texto = texto
	try {
		return await tarea()
	} finally {
		imprimiendo.activo = false
		imprimiendo.texto = ''
	}
}
