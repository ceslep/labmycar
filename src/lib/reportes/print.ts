import type { Configuracion, Examen, Paciente, Procedimiento } from '../models/models'
import { nombreCompletoPaciente, examenRealizado } from '../models/models'
import type { EsquemaExamen } from '../schemas/resultados'
import { getConfiguracion, getProcedimiento } from '../api/laboratorio'
import { calcularEdad, formatearFechaLarga } from '../utils/format'
import { htmlTablaReferencia } from './referencia'
import { getBaseUrl } from '../core/config.svelte'

/** Abre una ventana de impresión en blanco (debe llamarse dentro del gestor de clic). */
export function abrirVentanaImpresion(): Window | null {
	return window.open('', '_blank', 'width=900,height=700')
}

function esc(s: unknown): string {
	return String(s ?? '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
}

function css(): string {
	return `
* { box-sizing: border-box; }
body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11.5px; line-height: 1.45; color: #16181d; margin: 0; }
@page { size: Letter; margin: 13mm 14mm; }
.pagina { page-break-after: always; }
.pagina:last-child { page-break-after: auto; }
.cabecera { display: flex; gap: 14px; align-items: center; padding-bottom: 10px; border-bottom: 3px solid #0a7cff; }
.cabecera img { max-height: 78px; max-width: 150px; object-fit: contain; }
.cabecera .datos { flex: 1; text-align: center; }
.cabecera .datos h1 { margin: 0; font-size: 17px; color: #0a7cff; letter-spacing: .4px; }
.cabecera .datos p { margin: 1.5px 0; font-size: 10px; color: #3f4451; }
.cabecera .datos .nit { margin-top: 3px; font-size: 9.5px; font-weight: 700; letter-spacing: 1.2px; color: #6b7080; }
.titulo { text-align: center; margin: 14px 0 3px; font-size: 14.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #0a7cff; }
.subtitulo { text-align: center; margin: 0 0 11px; font-size: 10.5px; color: #555; }
.paciente { width: 100%; border-collapse: collapse; margin-bottom: 11px; }
.paciente td { border: 1px solid #c3c7d2; padding: 4.5px 7px; font-size: 11px; }
.paciente .et { background: #eef0fa; font-weight: 700; width: 12%; letter-spacing: .2px; }
table.detalle { width: 100%; border-collapse: collapse; margin-bottom: 11px; }
table.detalle th, table.detalle td { border: 1px solid #c3c7d2; padding: 4.5px 7px; font-size: 11px; }
table.detalle th { background: #0a7cff; color: #ffffff; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .6px; }
.grupo td { background: #eef0fa; font-weight: 800; text-transform: uppercase; font-size: 10px; letter-spacing: .6px; color: #0a7cff; }
.valor { text-align: center; font-weight: 700; }
.obs { margin: 3px 0 8px; font-size: 11px; white-space: pre-wrap; }
.pie { margin-top: 30px; display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; }
.pie .nota { font-size: 9.5px; color: #6b7080; max-width: 45%; }
.firma { text-align: center; min-width: 220px; }
.firma img { max-height: 64px; max-width: 180px; object-fit: contain; }
.firma .linea { border-top: 1.5px solid #16181d; margin-top: 3px; padding-top: 5px; font-size: 10.5px; font-weight: 700; color: #0a7cff; }
.firma .cargo { font-size: 9.5px; color: #3f4451; margin-top: 2px; }
.obs-bloque h3 { font-size: 10px; margin: 9px 0 2px; color: #0a7cff; text-transform: uppercase; letter-spacing: .5px; }
.referencia { margin: 0 0 9px; font-size: 10.5px; color: #3f4451; }
.ref-bloque { margin: 2px 0 10px; }
.ref-bloque h3 { font-size: 10px; margin: 0 0 4px; color: #0a7cff; text-transform: uppercase; letter-spacing: .5px; }
.ref-bloque table.ref-tabla { width: 100%; border-collapse: collapse; }
.ref-bloque table.ref-tabla th, .ref-bloque table.ref-tabla td { border: 1px solid #c3c7d2; padding: 4px 6px; font-size: 10px; text-align: left; vertical-align: top; }
.ref-bloque table.ref-tabla thead th { background: #eef0fa; color: #0a7cff; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
.ref-bloque table.ref-tabla tbody tr:nth-child(even) td { background: #f7f8fc; }
.ref-bloque pre { margin: 0; font-family: inherit; font-size: 10px; white-space: pre-wrap; color: #3f4451; }
`
}

interface DatosReporte {
	config: Configuracion
	paciente: Paciente
	examen: Examen
	procedimiento: Procedimiento
	esquema?: EsquemaExamen
	fila?: Record<string, unknown> | null
}

function cabeceraHtml(config: Configuracion): string {
	const logo = config.urlLogoLaboratorio
	const logoTag = logo ? `<img src="${esc(logo.startsWith('data:') ? logo : `data:image/png;base64,${logo}`)}" alt="logo" />` : ''
	return `<div class="cabecera">${logoTag}<div class="datos">
		<h1>${esc(config.nombreLaboratorio || 'Laboratorio')}</h1>
		${config.direccionLaboratorio ? `<p>${esc(config.direccionLaboratorio.replace(/\r\n/g, ' · '))}</p>` : ''}
		<p>${[config.telefonosLaboratorio, config.correoLaboratorio, config.webLaboratorio].filter(Boolean).map(esc).join(' · ')}</p>
		${config.nit ? `<p class="nit">NIT: ${esc(config.nit)}</p>` : ''}
	</div></div>`
}

function pacienteHtml(p: Paciente): string {
	const edad = p.fecnac ? calcularEdad(p.fecnac) : p.edad ?? ''
	return `<table class="paciente"><tr>
		<td class="et">Paciente</td><td><b>${esc(nombreCompletoPaciente(p))}</b></td>
		<td class="et">Identificación</td><td>${esc(p.identificacion)}</td>
	</tr><tr>
		<td class="et">Edad</td><td>${esc(edad)}</td>
		<td class="et">Entidad</td><td>${esc(p.entidad ?? '')}</td>
	</tr></table>`
}

function cuerpoExamenHtml(d: DatosReporte): string {
	const { esquema, fila } = d
	const unidad = d.procedimiento.unidades

	// Construye las filas agrupadas SOLO con parámetros que tengan valor:
	// los exámenes de varios valores (hemograma, perfil, orina...) no deben
	// mostrar filas vacías en el reporte (vista previa y PDF usan este HTML).
	const grupos: Array<{ nombre: string; filas: string[] }> = []
	if (esquema) {
		for (const c of esquema.campos) {
			const valor = fila?.[c.clave] ?? ''
			const texto = String(valor).trim()
			if (!texto) continue // parámetro sin valor: no aparece
			const grupo = c.grupo ?? ''
			let g = grupos.length ? grupos[grupos.length - 1] : null
			if (!g || g.nombre !== grupo) {
				g = { nombre: grupo, filas: [] }
				grupos.push(g)
			}
			if (c.multilinea) {
				g.filas.push(`<tr><td colspan="2">${esc(c.etiqueta)}</td><td class="valor" style="text-align:left">${esc(valor)}</td></tr>`)
			} else {
				g.filas.push(`<tr><td style="width:60%">${esc(c.etiqueta)}</td><td class="valor" style="width:25%">${esc(valor)}</td><td style="width:15%;text-align:center">${unidad ? esc(unidad) : ''}</td></tr>`)
			}
		}
	}
	const filas: string[] = []
	for (const g of grupos) {
		if (g.filas.length === 0) continue
		if (g.nombre) filas.push(`<tr class="grupo"><td colspan="3">${esc(g.nombre)}</td></tr>`)
		filas.push(...g.filas)
	}

	const observaciones = fila?.['observaciones'] ?? ''
	const html = `
		<div class="pagina">
			${cabeceraHtml(d.config)}
			<div class="titulo">${esc(d.examen.examen ?? d.procedimiento.nombre ?? 'Resultados')}</div>
			<div class="subtitulo">${esc(formatearFechaLarga(d.examen.fecha ?? d.procedimiento.info ?? ''))}</div>
			${pacienteHtml(d.paciente)}
			${filas.length ? `<table class="detalle"><tr><th>Examen / Parámetro</th><th style="width:25%;text-align:center">Resultado</th><th style="width:15%;text-align:center">Unidades</th></tr>${filas.join('')}</table>` : ''}
			${d.procedimiento.constante ? `<p class="referencia"><b>Valor de referencia:</b> ${esc(d.procedimiento.constante)}</p>` : ''}
			${htmlTablaReferencia(d.procedimiento.constante2)}
			${observaciones ? `<div class="obs-bloque"><h3>Observaciones</h3><div class="obs">${esc(observaciones)}</div></div>` : ''}
			${pieHtml(d.config)}
		</div>`
	return html
}

function pieHtml(config: Configuracion): string {
	const firma = config.urFirmaLaboratorio
	const firmaTag = firma ? `<img src="${esc(firma.startsWith('data:') ? firma : `data:image/png;base64,${firma}`)}" alt="firma" />` : ''
	const bio = config.bacteriologoLaboratorio
	const tp = config.tarjetaPLaboratorio
	return `<div class="pie"><div class="nota">Documento generado por el sistema del laboratorio.<br />Los resultados corresponden exclusivamente a la muestra del paciente.</div><div class="firma">${firmaTag}<div class="linea">${esc(bio || 'Bacteriólogo')}${tp ? esc(` — T.P. ${tp}`) : ''}</div><div class="cargo">Bacteriólogo</div></div></div>`
}

/** Reporte de un solo examen. */
export function htmlReporteExamen(d: DatosReporte): string {
	return `<html><head><meta charset="utf-8" /><title>${esc(d.examen.examen ?? 'Resultados')}</title><style>${css()}</style></head><body>${cuerpoExamenHtml(d)}</body></html>`
}

/** Reporte conjunto (todos los exámenes) de un paciente+fecha. */
export function htmlReporteTodos(
	config: Configuracion,
	paciente: Paciente,
	items: Array<{ examen: Examen; procedimiento: Procedimiento; esquema?: EsquemaExamen; fila?: Record<string, unknown> | null }>
): string {
	const paginas = items
		.map((i) => cuerpoExamenHtml({ config, paciente, examen: i.examen, procedimiento: i.procedimiento, esquema: i.esquema, fila: i.fila }))
		.join('')
	return `<html><head><meta charset="utf-8" /><title>Resultados — ${esc(nombreCompletoPaciente(paciente))}</title><style>${css()}</style></head><body>${paginas}</body></html>`
}

export interface CargaImpresion {
	examen: Examen
	procedimiento: Procedimiento
	esquema?: EsquemaExamen
	fila?: Record<string, unknown> | null
}

/** Carga todo lo necesario para imprimir un examen (catálogo + detalle). */
export async function cargarImpresion(examen: Examen, esquemas: Record<string, EsquemaExamen>): Promise<CargaImpresion> {
	const [proc, esquema] = await Promise.all([
		getProcedimiento(examen.codexamen ?? ''),
		Promise.resolve(examen.tipo ? esquemas[examen.tipo] : undefined)
	])
	const procedimiento = proc ?? { codigo: examen.codexamen, nombre: examen.examen, info: examen.info }
	let fila: Record<string, unknown> | null = null
	if (esquema && examen.identificacion && examen.fecha) {
		fila = await esquema.cargar(examen.identificacion, examen.fecha, examen.codexamen ?? '')
	}
	return { examen, procedimiento, esquema, fila }
}

/** Imprime el reporte en la ventana indicada. Devuelve false si falló. */
export function escribirReporte(win: Window | null, html: string): boolean {
	if (!win || win.closed) return false
	try {
		win.document.open()
		win.document.write(html)
		win.document.close()
		win.focus()
		setTimeout(() => {
			try {
				win.print()
			} catch {
				/* el usuario imprime manualmente */
			}
		}, 350)
		return true
	} catch {
		return false
	}
}

/** Fecha de cabecera de un reporte conjunto. */
export function primeraFechaExamen(examenes: Examen[]): string {
	return examenes.find((e) => e.fecha)?.fecha ?? ''
}

function construirQuery(params: Record<string, string | undefined>): string {
	const query = Object.entries(params)
		.filter(([, v]) => v !== undefined && v !== '')
		.map(([k, v]) => `${k}=${encodeURIComponent(v as string)}`)
		.join('&')
	return query
}

/** URL del reporte individual generado por el servidor (printphp/print_examen.php), como hacía prt.php. */
export function urlPrintphpExamen(opts: {
	identificacion: string
	fecha: string
	codexamen: string
	nombre?: string
	nombres?: string
	entidad?: string
	edad?: string
	tipo?: string
	tabla?: string
	info?: string
}): string {
	const q = construirQuery({
		idx: Math.random().toString(36).slice(2),
		identificacion: opts.identificacion,
		fecha: opts.fecha,
		nombres: opts.nombres,
		tabla: opts.tabla,
		info: opts.info ?? opts.nombre,
		codexamen: opts.codexamen,
		edad: opts.edad,
		entidad: opts.entidad,
		tipo: opts.tipo,
		embedido: '1',
		ver: '1'
	})
	return getBaseUrl() + 'printphp/print_examen.php?' + q
}

/** URL del reporte conjunto del paciente+fecha (printphp/imprimirTodo.php). */
export function urlPrintphpTodo(opts: {
	identificacion: string
	fecha: string
	nombres?: string
	entidad?: string
	edad?: string
}): string {
	const q = construirQuery({
		idx: Math.random().toString(36).slice(2),
		identificacion: opts.identificacion,
		fecha: opts.fecha,
		nombres: opts.nombres,
		edad: opts.edad,
		entidad: opts.entidad,
		info: 'Resultados',
		ver: '1'
	})
	return getBaseUrl() + 'printphp/imprimirTodo.php?' + q
}
