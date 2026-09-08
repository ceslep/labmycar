import { http, httpLocal, probarServidor } from '../core/http.svelte'
import { msgEsOk, payloadGuardarExamenes, type RespMsg } from './guardado'
import type {
	CodExamen,
	Configuracion,
	DataHemat,
	DetalleCoprologico,
	DetalleFrotisVaginal,
	DetalleHRayto,
	DetalleParcialOrina,
	DetallePerfilLipidico,
	DetalleTipo1,
	DetalleTipo2,
	EnvolturaConfiguracion,
	EnvolturaDetalle,
	EnvolturaUniConst,
	Examen,
	ItemOpcion,
	Paciente,
	Procedimiento,
	UniConst
} from '../models/models'

export { probarServidor }

// ---------------------------------------------------------------------------
// Configuración
// ---------------------------------------------------------------------------

export async function getConfiguracion(): Promise<Configuracion | null> {
	const r = await http<EnvolturaConfiguracion>('getConfiguracion.php')
	return r?.msg && r.data ? r.data : null
}

/** Configuración ligera (nombre/corto/logo) para arranque y portal. */
export async function getConfiguracionPublica(): Promise<Configuracion | null> {
	try {
		const r = await http<EnvolturaConfiguracion>('configPublica.php')
		return r?.msg && r.data ? r.data : null
	} catch {
		return null
	}
}

/** Autenticación: devuelve el token si la clave (T.P.) es correcta. */
export interface LoginRespuesta {
	msg?: boolean
	token?: string
	nombre?: string
}

export async function loginApi(clave: string): Promise<LoginRespuesta | null> {
	try {
		return (await http<LoginRespuesta>('login.php', { method: 'POST', body: { clave } })) ?? null
	} catch {
		return null
	}
}

export async function guardarConfiguracion(cfg: Configuracion): Promise<boolean> {
	try {
		await http('guardarConfiguracion.php', { method: 'POST', body: { ...cfg, tabla: 'configuracion' } })
		return true
	} catch {
		return false
	}
}

// ---------------------------------------------------------------------------
// Pacientes
// ---------------------------------------------------------------------------

export async function getPacientes(criterio = ''): Promise<Paciente[]> {
	try {
		if (!criterio) return (await http<Paciente[]>('getPacientes.php')) ?? []
		return (await http<Paciente[]>('getPacientes.php', { method: 'POST', body: { criterio } })) ?? []
	} catch {
		return []
	}
}

export async function getInfoPaciente(identificacion: string): Promise<Paciente | null> {
	try {
		const env = await http<EnvolturaDetalle<Paciente>>('getPaciente.php', {
			method: 'POST',
			body: { identificacion }
		})
		return env?.msg && env.data ? env.data : null
	} catch {
		return null
	}
}

/** Llama a savePaciente.php (upsert por identificación). */
export async function guardarPaciente(p: Paciente): Promise<boolean> {
	try {
		const r = await http<{ msg?: boolean }>('savePaciente.php', { method: 'POST', body: p })
		return r?.msg !== false
	} catch {
		return false
	}
}

export async function getPacientesFecha(fecha: string): Promise<Paciente[]> {
	try {
		return (await http<Paciente[]>('getPacientesFecha.php', { method: 'POST', body: { fecha } })) ?? []
	} catch {
		return []
	}
}

/** Lista de entidades conocidas (tabla `entidades`). */
export async function getEntidades(): Promise<string[]> {
	try {
		const env = await http<{ msg?: boolean; data?: Array<{ entidad?: string }> }>('getEntidades.php')
		return (env?.data ?? []).map((d) => d.entidad ?? '').filter((e) => e.trim() !== '')
	} catch {
		return []
	}
}

/** Fila de exámenes registrados en una fecha (procedimientos ⋈ examenes). */
export interface ExamenFechaRow {
	ind?: string
	codexamen?: string
	identificacion?: string
	fecha?: string
	realizado?: string
	entidad?: string
	nombre?: string
	tabla?: string
	tipo?: string
	info?: string
}

export async function getExamenesFecha(fecha: string): Promise<ExamenFechaRow[]> {
	try {
		return (await http<ExamenFechaRow[]>('getExamenesFecha.php', { method: 'POST', body: { fecha } })) ?? []
	} catch {
		return []
	}
}

/** Pacientes que tienen exámenes, filtrados por identificación o nombre. */
export interface PacienteConExamenes {
	identificacion?: string
	nombres?: string
	edad?: string
}

export async function busquedaExamenes(criterio: string): Promise<PacienteConExamenes[]> {
	try {
		const env = await http<{ msg?: boolean; data?: PacienteConExamenes[] }>('busquedaExamenes.php', {
			method: 'POST',
			body: { criterio }
		})
		return env?.data ?? []
	} catch {
		return []
	}
}

/** Examen de un paciente en la consulta pública (identificación + número verificador). */
export interface ExamenPublico {
	identificacion?: string
	codexamen?: string
	fecha?: string
	realizado?: string
	entidad?: string
	examen?: string
	tipo?: string
	tabla?: string
	info?: string
	nombres?: string
	genero?: string
	fecnac?: string
	edad?: string
}

export async function examenesPacientePublico(identificacion: string, numero: string): Promise<ExamenPublico[]> {
	try {
		return (await http<ExamenPublico[]>('getExamenesPaciente2.php', {
			method: 'POST',
			body: { identificacion, numero }
		})) ?? []
	} catch {
		return []
	}
}

/** Cambia la entidad de un examen (endpoint libphp/setEntidad.php). */
export async function setEntidadExamen(
	identificacion: string,
	fecha: string,
	codexamen: string,
	entidad: string
): Promise<boolean> {
	try {
		const r = await http<{ msg?: boolean }>('setEntidad.php', {
			method: 'POST',
			body: { identificacion, fecha, codexamen, entidad }
		})
		return r?.msg === true
	} catch {
		return false
	}
}

/** Resultado de la búsqueda avanzada de pacientes (libphp/panelPacientes.php). */
export interface PacienteBusqueda {
	identificacion?: string
	nombre_completo?: string
	fecnac?: string
	genero?: string
	telefono?: string
	telefono_movil?: string
	correo?: string
	ciudad_residencia?: string
	entidad?: string
	total_visitas?: string
	ultima_visita?: string
	total_examenes?: string
	con_resultados?: string
}

export interface CriteriosPaciente {
	identificacion?: string
	nombres?: string
	telefono?: string
	ciudad?: string
	entidad?: string
	soloConResultados?: boolean
}

export async function panelPacientes(criterios: CriteriosPaciente): Promise<PacienteBusqueda[]> {
	try {
		const cuerpo = {
			identificacion: criterios.identificacion ?? '',
			nombres: criterios.nombres ?? '',
			telefono: criterios.telefono ?? '',
			ciudad: criterios.ciudad ?? '',
			entidad: criterios.entidad ?? '',
			solo_con_resultados: criterios.soloConResultados ? '1' : '0',
			limit: 50
		}
		return (await http<PacienteBusqueda[]>('panelPacientes.php', { method: 'POST', body: cuerpo })) ?? []
	} catch {
		return []
	}
}

/** Datos del módulo Admin (equivalente SPA de admin.php). */export interface AdminFila {
	identificacion?: string
	fecha?: string
	entidad?: string
	codexamen?: string
	realizado?: string
	paciente?: string
	genero?: string
	examen?: string
	examen_tabla?: string
	examen_tipo?: string
	resumen?: string
}

export interface AdminDatos {
	success?: boolean
	stats?: {
		total?: number
		realizados?: number
		pendientes?: number
		hoy?: number
		ayer?: number
		ultimos14?: number
		pacientes?: number
		visitas?: number
		tasa_realizacion?: number
		promedio_diario?: number
		hoy_vs_ayer?: number
	}
	charts?: {
		tendencia_labels?: string[]
		tendencia_data?: number[]
		entidad_labels?: string[]
		entidad_data?: number[]
		genero_labels?: string[]
		genero_data?: number[]
		tipo_labels?: string[]
		tipo_data?: number[]
		estadoEntidad_labels?: string[]
		estadoEntidad_realizados?: number[]
		estadoEntidad_pendientes?: number[]
	}
	health?: {
		condiciones?: Array<{ label?: string; total?: number }>
		genero?: Record<string, number>
		edad?: Record<string, number>
		tabla?: Record<string, number>
		total?: number
	}
	tablas?: Array<{ tabla?: string; nombre?: string }>
	entidades?: string[]
	total?: number
	page?: number
	pages?: number
	filas?: AdminFila[]
}

export interface CriteriosAdmin {
	fechaInicio?: string
	fechaFin?: string
	entidad?: string
	buscar?: string
	estado?: string
	tabla?: string
	page?: number
	perPage?: number
	analisis?: boolean
	analisisDesde?: string
	analisisHasta?: string
}

/** Datos de pacientes por lista de identificaciones (tarjetas del Panel). */
export interface NombrePaciente {
	identificacion?: string
	nombre_completo?: string
	fecnac?: string
	genero?: string
	entidad?: string
}

export async function nombresPacientes(ids: string[]): Promise<NombrePaciente[]> {
	const limpios = Array.from(new Set(ids.filter(Boolean))).slice(0, 600)
	if (limpios.length === 0) return []
	try {
		return (await http<NombrePaciente[]>('panelPacientesPorIds.php', {
			method: 'POST',
			body: { ids: limpios }
		})) ?? []
	} catch {
		return []
	}
}

export interface ResultadoMigracion {
	msg?: boolean
	resumen?: Array<{ tabla?: string; duplicados_eliminados?: number; indice_unico?: string }>
	errores?: string[]
}

/** Ejecuta la migración de índices (dedupe + claves únicas). Idempotente. */
export async function migracionIndices(): Promise<ResultadoMigracion | null> {
	try {
		const r = await http<ResultadoMigracion>('migracion_indices.php', { method: 'POST', body: {} })
		return r ?? null
	} catch {
		return null
	}
}

export async function adminDatos(criterios: CriteriosAdmin): Promise<AdminDatos | null> {	try {
		const cuerpo = {
			fecha_inicio: criterios.fechaInicio ?? '',
			fecha_fin: criterios.fechaFin ?? '',
			entidad: criterios.entidad ?? '',
			buscar: criterios.buscar ?? '',
			estado: criterios.estado ?? '',
			tabla: criterios.tabla ?? '',
			page: criterios.page ?? 1,
			per_page: criterios.perPage ?? 25,
			analisis: criterios.analisis ? '1' : '0',
			analisis_desde: criterios.analisisDesde ?? '',
			analisis_hasta: criterios.analisisHasta ?? ''
		}
		const r = await http<AdminDatos>('adminDatos.php', { method: 'POST', body: cuerpo })
		return r ?? null
	} catch {
		return null
	}
}

// ---------------------------------------------------------------------------
// Exámenes (asignaciones)
// ---------------------------------------------------------------------------

/** Exámenes de un paciente (criterio = identificación) o globales si vacío. */
export async function examenesPaciente(criterio: string): Promise<Examen[]> {
	try {
		if (!criterio) return (await http<Examen[]>('getExamenesPaciente.php')) ?? []
		return (await http<Examen[]>('getExamenesPaciente.php', { method: 'POST', body: { criterio } })) ?? []
	} catch {
		return []
	}
}

export async function examenesPacienteFecha(identificacion: string, fecha: string): Promise<Examen[]> {
	try {
		return (await http<Examen[]>('getExamenesPacienteFecha.php', {
			method: 'POST',
			body: { identificacion, fecha }
		})) ?? []
	} catch {
		return []
	}
}

/** Guarda un detalle de examen y lo marca como realizado (solo si el detalle se guardó). */
export async function guardarDetalle(
	tabla: string,
	datos: Record<string, string | number | null | undefined>,
	codexamen: string,
	identificacion: string,
	fecha: string
): Promise<boolean> {
	try {
		const r = await http<RespMsg>('guardarExamen.php', {
			method: 'POST',
			body: { ...datos, tabla, codexamen }
		})
		// Si el detalle no se guardó (msg:false / "no"), NO se marca como realizado.
		if (!msgEsOk(r)) return false
		return await updateExamen(codexamen, identificacion, fecha)
	} catch {
		return false
	}
}

export async function updateExamen(codexamen: string, identificacion: string, fecha: string): Promise<boolean> {
	try {
		const r = await http<RespMsg>('updateExamen.php', {
			method: 'POST',
			body: { codexamen, identificacion, fecha }
		})
		return msgEsOk(r)
	} catch {
		return false
	}
}

export async function eliminarExamen(identificacion: string, fecha: string): Promise<boolean> {
	try {
		await http('eliminarExamen.php', { method: 'POST', body: { identificacion, fecha } })
		return true
	} catch {
		return false
	}
}

// ---------------------------------------------------------------------------
// Catálogo de procedimientos
// ---------------------------------------------------------------------------

export async function getProcedimientos(): Promise<Procedimiento[]> {
	try {
		return (await http<Procedimiento[]>('getProcedimientos.php')) ?? []
	} catch {
		return []
	}
}

export async function getProcedimiento(codigo: string): Promise<Procedimiento | null> {
	try {
		return await http<Procedimiento>('getProcedimiento.php', { method: 'POST', body: { codigo } })
	} catch {
		return null
	}
}

export async function guardarProcedimiento(p: Procedimiento): Promise<boolean> {
	try {
		const cuerpo = {
			codigo: p.codigo ?? '',
			nombre: p.nombre ?? '',
			tabla: p.tabla ?? '',
			info: p.info ?? '',
			color: p.color ?? '',
			constante: p.constante ?? '',
			constante2: p.constante2 ?? '',
			unidades: p.unidades ?? '',
			tipo: p.tipo ?? '',
			tipoprocedimiento: p.tipoprocedimiento ?? '',
			abreviatura: p.abreviatura ?? ''
		}
		await http('guardarProcedimiento.php', { method: 'POST', body: cuerpo })
		return true
	} catch {
		return false
	}
}

export async function eliminarProcedimiento(ind: string | number): Promise<boolean> {
	try {
		const r = await http<{ msg?: boolean }>('eliminarProcedimiento.php', {
			method: 'POST',
			body: { ind: Number(ind) }
		})
		return r?.msg === true
	} catch {
		return false
	}
}

export async function getSeleccionados(identificacion: string, fecha: string): Promise<Procedimiento[]> {
	try {
		return (await http<Procedimiento[]>('getSeleccionados.php', {
			method: 'POST',
			body: { identificacion, fecha }
		})) ?? []
	} catch {
		return []
	}
}

export async function guardarExamenes(
	procedimientos: Procedimiento[],
	identificacion: string,
	fecha: string
): Promise<boolean> {
	try {
		const r = await http<RespMsg>('guardarExamenes.php', {
			method: 'POST',
			body: {
				examenes: payloadGuardarExamenes(procedimientos),
				identificacion,
				fecha
			}
		})
		return msgEsOk(r)
	} catch {
		return false
	}
}

export async function getUniConst(codexamen: string): Promise<UniConst> {
	try {
		const env = await http<EnvolturaUniConst>('getUniConst.php', { method: 'POST', body: { codexamen } })
		return env?.data ?? { unidades: '', constante: '' }
	} catch {
		return { unidades: '', constante: '' }
	}
}

// ---------------------------------------------------------------------------
// Opciones editables por examen (opcionesExamenes)
// ---------------------------------------------------------------------------

export async function getExamenesWithItems(): Promise<CodExamen[]> {
	try {
		return (await http<CodExamen[]>('getExamenesWithItems.php')) ?? []
	} catch {
		return []
	}
}

export async function getItemsExamen(codexamen: string, campo: string): Promise<string[]> {
	try {
		const datos = await http<ItemOpcion[]>('getItemsExamenes.php', {
			method: 'POST',
			body: { codexamen, campo }
		})
		return [''].concat((datos ?? []).map((d) => d.item ?? ''))
	} catch {
		return []
	}
}

export async function guardarItemsExamen(codexamen: string, campo: string, items: string[]): Promise<boolean> {
	try {
		await http('guardarItemsExamenes.php', {
			method: 'POST',
			body: { codexamen, campo, items: JSON.stringify(items.map((i) => ({ item: i }))) }
		})
		return true
	} catch {
		return false
	}
}

// ---------------------------------------------------------------------------
// Detalles por tipo de examen
// ---------------------------------------------------------------------------

async function getDetalle<T>(
	endpoint: string,
	identificacion: string,
	fecha: string,
	codexamen?: string
): Promise<T | null> {
	try {
		const body: Record<string, string> = { identificacion, fecha }
		if (codexamen !== undefined) body['codexamen'] = codexamen
		const env = await http<EnvolturaDetalle<T>>(endpoint, {
			method: 'POST',
			body
		})
		return env?.msg && env.data ? env.data : null
	} catch {
		return null
	}
}

export const getTipo1 = (identificacion: string, fecha: string, codexamen?: string) =>
	getDetalle<DetalleTipo1>('getTipo1.php', identificacion, fecha, codexamen)
export const getTipo2 = (identificacion: string, fecha: string, codexamen?: string) =>
	getDetalle<DetalleTipo2>('getTipo2.php', identificacion, fecha, codexamen)
export const getParcialOrina = (identificacion: string, fecha: string) => getDetalle<DetalleParcialOrina>('getParcialOrina.php', identificacion, fecha)
export const getCoprologico = (identificacion: string, fecha: string) => getDetalle<DetalleCoprologico>('getCoprologico.php', identificacion, fecha)
export const getFrotisVaginal = (identificacion: string, fecha: string) => getDetalle<DetalleFrotisVaginal>('getFrotisVaginal.php', identificacion, fecha)
export const getPerfilLipidico = (identificacion: string, fecha: string) => getDetalle<DetallePerfilLipidico>('getPerfilLipidico.php', identificacion, fecha)
export const getHemogramaRaytoNew = (identificacion: string, fecha: string) => getDetalle<DetalleHRayto>('getHemogramaRaytoNew.php', identificacion, fecha)

/** Hemograma en esquema antiguo (tabla examen_tipo_5). */
export const getHemogramaRaytoOld = (identificacion: string, fecha: string) =>
	getDetalle<Record<string, unknown>>('getHemogramaRayto.php', identificacion, fecha)

/** Datos crudos del analizador Rayto (servicio local). */
export async function getDataHemat(identificacion: string, fecha: string): Promise<DataHemat> {
	try {
		return await httpLocal<DataHemat>('http://127.0.0.1:3000/dataHemat', { identificacion, fecha })
	} catch {
		return {}
	}
}
