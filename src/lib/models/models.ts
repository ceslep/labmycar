/** Modelos de datos tipados a partir de las claves JSON reales del backend
 * (verificado contra bd.sql y respuestas de libphp). Todos los campos llegan
 * como texto desde PHP; se modelan string | null. */

// ---------- Configuración del laboratorio (tabla `configuracion`, 1 fila) ----------
export interface Configuracion {
	id?: string
	nit?: string
	nombreLaboratorio?: string
	nombreCorto?: string
	direccionLaboratorio?: string
	telefonosLaboratorio?: string
	correoLaboratorio?: string
	webLaboratorio?: string
	bacteriologoLaboratorio?: string
	usuario?: string
	/** Clave de acceso (T.P.) usada por el login de la app y del panel web. */
	tarjetaPLaboratorio?: string
	/** Firma en base64 (clave real del servidor: sin la letra "l"). */
	urFirmaLaboratorio?: string
	/** Logo en base64. */
	urlLogoLaboratorio?: string
}

export interface EnvolturaConfiguracion {
	msg: boolean
	data?: Configuracion
}

// ---------- Paciente (tabla `paciente`) ----------
export interface Paciente {
	id?: string
	fecha?: string
	hora?: string
	identificacion?: string
	nombre1?: string
	nombre2?: string
	apellido1?: string
	apellido2?: string
	nombres?: string
	apellidos?: string
	genero?: string
	fecnac?: string
	edad?: string
	lugarnacimiento?: string
	direccion_residencia?: string
	ciudad_residencia?: string
	barrio?: string
	telefono?: string
	telefono_residencia2?: string
	telefono_movil?: string
	ocupacion?: string
	estado_civil?: string
	correo?: string
	email2?: string
	entidad?: string
	estado?: string
}

export function nombreCompletoPaciente(p: Paciente): string {
	const nombres = (p.nombres ?? '').trim() || [p.nombre1, p.nombre2].filter(Boolean).join(' ').trim()
	const apellidos = (p.apellidos ?? '').trim() || [p.apellido1, p.apellido2].filter(Boolean).join(' ').trim()
	return [nombres, apellidos].filter(Boolean).join(' ').trim()
}

// ---------- Examen asignado (tabla `examenes` + JOIN procedimientos) ----------
export interface Examen {
	ind?: string
	codexamen?: string
	identificacion?: string
	fecha?: string
	realizado?: string // 'S' = realizado
	entidad?: string
	examen?: string // procedimientos.nombre
	tipo?: string // procedimientos.tipo: 1,2,3,4,5,6,8...
	tabla?: string // procedimientos.tabla
	info?: string
}

export function examenRealizado(e: Examen): boolean {
	return (e.realizado ?? '') === 'S'
}

// ---------- Catálogo de procedimientos (tabla `procedimientos`) ----------
export interface Procedimiento {
	ind?: string
	codigo?: string
	uuid?: string
	nombre?: string
	tabla?: string
	info?: string
	color?: string // "r;g;b"
	constante?: string
	constante2?: string // HTML/JSON de tablas de referencia para el reporte
	unidades?: string
	tipo?: string
	tipoprocedimiento?: string
	abreviatura?: string
}

// ---------- UniConst: unidades y valor normal de un procedimiento ----------
export interface UniConst {
	unidades: string
	constante: string
}

export interface EnvolturaUniConst {
	msg?: boolean
	data?: UniConst
}

// ---------- Tablas de detalle de resultados ----------
// Los campos siguen las columnas reales de bd.sql. Se verificará cada clave
// contra su get*.php local de libphp al construir cada formulario.

export interface DetalleTipo1 {
	ind?: string
	identificacion?: string
	examen?: string
	valoracion?: string
	fecha?: string
	bacteriologo?: string
	examenind?: string
	citasind?: string
	indice?: string
	entidad?: string
	observaciones?: string
	panel2?: string
	departamento?: string
	constante?: string
	hora?: string
	pyp?: string
	nombreExamen?: string
	unidades?: string
	fechaResultados?: string
}

export interface DetalleTipo2 extends DetalleTipo1 {}

export interface DetalleParcialOrina {
	identificacion?: string
	fecha?: string
	densidad?: string
	color?: string
	aspecto?: string
	ph?: string
	olor?: string
	proteinas?: string
	glucosa?: string
	cuerpos_cetonicos?: string
	sangre_hemolizada?: string
	sangre_no_hemolizada?: string
	bilirrubina?: string
	urobilinogeno?: string
	nitritos?: string
	leucocitos?: string
	leucocitosm?: string
	moco?: string
	eritrocitos?: string
	levaduras?: string
	piocitos?: string
	celulas_epiteliales?: string
	uratos_amorfos?: string
	fosfatos_amorfos?: string
	oxalato_de_calcio?: string
	bacterias?: string
	cilindros_hialinos?: string
	cilindros_granulosos?: string
	observaciones?: string
	bacteriologo?: string
	fechahora?: string
	fechaResultados?: string
}

export interface DetalleCoprologico {
	identificacion?: string
	fecha?: string
	consistencia?: string
	color?: string
	sangre?: string
	moco?: string
	otros_macroscopicos?: string
	ph?: string
	// protozoos en quistes y trofozoítos (claves = columnas reales de la tabla `coprologico`)
	endamoeba_histolitica_quistes?: string
	endamoeba_coli_quistes?: string
	endolimax_quistes?: string
	iodamoeba_quistes?: string
	giarda_lamblia_quistes?: string
	chilomastix_mesnili_quistes?: string
	trichomona_hominis_quistes?: string
	balantidium_coli_quistes?: string
	endamoeba_histolitica_trofozoitos?: string
	endamoeba_coli_trofozoitos?: string
	endolimax_trofozoitos?: string
	iodamoeba_trofozoitos?: string
	giarda_lamblia_trofozoitos?: string
	chilomastix_mesnili_trofozoitos?: string
	trichomona_hominis_trofozoitos?: string
	balantidium_coli_trofozoitos?: string
	Blastocystis_hominis_quistes?: string
	Blastocystis_hominis_trofozoitos?: string
	// helmintos
	ascaris?: string
	tricocefalos?: string
	uncinaria?: string
	tenia_saginata?: string
	tenia_solium?: string
	himenolepsis?: string
	strongiloides_larva?: string
	oxiuros_huevos?: string
	sangre_oculta?: string
	lecucocitos?: string
	observaciones?: string
	bacteriologo?: string
	fechahora?: string
	fechaResultados?: string
}

export interface DetalleFrotisVaginal {
	identificacion?: string
	fecha?: string
	examen?: string
	otros_fresco?: string
	prueba_de_aminas?: string
	celulas_guia1?: string
	ph?: string
	trichonomas_vaginales?: string
	pmn?: string
	celulas_guia2?: string
	blastoconidias?: string
	seudomicelios?: string
	lactobacillus?: string
	gardnerella_sp?: string
	bacteroides_sp?: string
	mobilluncus_sp?: string
	pmnx?: string
	intracelulares?: string
	extracelulares?: string
	observaciones?: string
	bacteriologo?: string
	fechaResultados?: string
}

export interface DetallePerfilLipidico {
	id?: string
	identificacion?: string
	fecha?: string
	colesterol_total?: string
	colesterol_hdl?: string
	colesterol_vldl?: string
	colesterol_ldl?: string
	trigliceridos?: string
	indice_arterial?: string
	bacteriologo?: string
	observaciones?: string
	fechaResultados?: string
}

/** Hemograma nuevo (tabla `hemogramaRayto`, equipo Rayto, claves MAYÚSCULAS en JSON). */
export interface DetalleHRayto {
	id?: string
	identificacion?: string
	fecha?: string
	WBC?: string
	LYMn?: string
	MIDn?: string
	GRAn?: string
	LYMp?: string
	MIDp?: string
	GRAp?: string
	RBC?: string
	HGB?: string
	MCHC?: string
	MCH?: string
	MCV?: string
	RDWCV?: string
	RDWSD?: string
	HCT?: string
	PLT?: string
	MPV?: string
	PDW?: string
	PCT?: string
	PLCR?: string
	observaciones?: string
	bacteriologo?: string
	fechahora?: string
	fechaResultados?: string
}

/** Datos crudos del analizador Rayto (servicio local 127.0.0.1:3000/dataHemat). */
export interface DataHemat {
	wbc?: string
	lyMn?: string
	miDn?: string
	grAn?: string
	lyMp?: string
	miDp?: string
	grAp?: string
	rbc?: string
	hgb?: string
	mchc?: string
	mch?: string
	mcv?: string
	rdwcv?: string
	rdwsd?: string
	hct?: string
	plt?: string
	mpv?: string
	pdw?: string
	pct?: string
	plcr?: string
	[clave: string]: string | undefined
}

/** Envolturas típicas de respuestas de detalle. */
export interface EnvolturaDetalle<T> {
	msg?: boolean
	data?: T
}

export interface CodExamen {
	codexamen?: string
}

/** Forma de opciones editables por examen (opcionesExamenes). */
export interface ItemOpcion {
	item?: string
}
