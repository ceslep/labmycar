import {
	getCoprologico,
	getFrotisVaginal,
	getHemogramaRaytoNew,
	getParcialOrina,
	getPerfilLipidico,
	getTipo1,
	getTipo2
} from '../api/laboratorio'

export interface CampoExamen {
	clave: string
	etiqueta: string
	grupo?: string
	multilinea?: boolean
}

export interface EsquemaExamen {
	tipo: string
	tabla: string
	/** true si la tabla incluye la columna `examen` = codexamen (tipo 1/2). */
	conExamen?: boolean
	/** Cargar el detalle existente. Devuelve null si no hay registro. */
	cargar: (identificacion: string, fecha: string, codexamen: string) => Promise<Record<string, unknown> | null>
	/** Campos editables del formulario (en orden de presentación). */
	campos: CampoExamen[]
	/** Columnas reales adicionales que deben conservarse al guardar (REPLACE reemplaza la fila completa). */
	preservar?: string[]
}

export const esquemas: Record<string, EsquemaExamen> = {
	'1': {
		tipo: '1',
		tabla: 'examen_tipo_1',
		conExamen: true,
		cargar: async (id, fecha, code) => aReg(getTipo1(id, fecha, code)),
		campos: [
			{ clave: 'valoracion', etiqueta: 'Valoración' },
			{ clave: 'observaciones', etiqueta: 'Observaciones', multilinea: true }
		],
		preservar: ['examenind', 'citasind', 'indice', 'entidad', 'panel2', 'departamento', 'pyp', 'fechaResultados']
	},
	'2': {
		tipo: '2',
		tabla: 'examen_tipo_2',
		conExamen: true,
		cargar: async (id, fecha, code) => aReg(getTipo2(id, fecha, code)),
		campos: [
			{ clave: 'valoracion', etiqueta: 'Valoración' },
			{ clave: 'observaciones', etiqueta: 'Observaciones', multilinea: true }
		],
		preservar: ['examenind', 'citasind', 'indice', 'entidad', 'departamento', 'pyp', 'fechaResultados']
	},
	'3': {
		tipo: '3',
		tabla: 'parcialOrina',
		cargar: async (id, fecha) => aReg(getParcialOrina(id, fecha)),
		campos: [
			{ clave: 'densidad', etiqueta: 'Densidad' },
			{ clave: 'color', etiqueta: 'Color' },
			{ clave: 'aspecto', etiqueta: 'Aspecto' },
			{ clave: 'ph', etiqueta: 'pH' },
			{ clave: 'olor', etiqueta: 'Olor' },
			{ clave: 'proteinas', etiqueta: 'Proteínas' },
			{ clave: 'glucosa', etiqueta: 'Glucosa' },
			{ clave: 'cuerpos_cetonicos', etiqueta: 'Cuerpos cetónicos' },
			{ clave: 'sangre_hemolizada', etiqueta: 'Sangre hemolizada' },
			{ clave: 'sangre_no_hemolizada', etiqueta: 'Sangre no hemolizada' },
			{ clave: 'bilirrubina', etiqueta: 'Bilirrubina' },
			{ clave: 'urobilinogeno', etiqueta: 'Urobilinógeno' },
			{ clave: 'nitritos', etiqueta: 'Nitritos' },
			{ clave: 'leucocitos', etiqueta: 'Leucocitos' },
			{ clave: 'leucocitosm', etiqueta: 'Leucocitos /ml' },
			{ clave: 'moco', etiqueta: 'Moco' },
			{ clave: 'eritrocitos', etiqueta: 'Eritrocitos' },
			{ clave: 'levaduras', etiqueta: 'Levaduras' },
			{ clave: 'piocitos', etiqueta: 'Piocitos' },
			{ clave: 'celulas_epiteliales', etiqueta: 'Células epiteliales' },
			{ clave: 'uratos_amorfos', etiqueta: 'Uratos amorfos' },
			{ clave: 'fosfatos_amorfos', etiqueta: 'Fosfatos amorfos' },
			{ clave: 'oxalato_de_calcio', etiqueta: 'Oxalato de calcio' },
			{ clave: 'bacterias', etiqueta: 'Bacterias' },
			{ clave: 'cilindros_hialinos', etiqueta: 'Cilindros hialinos' },
			{ clave: 'cilindros_granulosos', etiqueta: 'Cilindros granulosos' },
			{ clave: 'observaciones', etiqueta: 'Observaciones', multilinea: true }
		],
		preservar: ['fechaResultados']
	},
	'4': {
		tipo: '4',
		tabla: 'coprologico',
		cargar: async (id, fecha) => aReg(getCoprologico(id, fecha)),
		campos: [
			{ clave: 'consistencia', etiqueta: 'Consistencia', grupo: 'Examen macroscópico' },
			{ clave: 'color', etiqueta: 'Color', grupo: 'Examen macroscópico' },
			{ clave: 'sangre', etiqueta: 'Sangre', grupo: 'Examen macroscópico' },
			{ clave: 'moco', etiqueta: 'Moco', grupo: 'Examen macroscópico' },
			{ clave: 'otros_macroscopicos', etiqueta: 'Otros hallazgos macroscópicos', grupo: 'Examen macroscópico', multilinea: true },
			{ clave: 'ph', etiqueta: 'pH', grupo: 'Examen macroscópico' },
			{ clave: 'endamoeba_histolitica_quistes', etiqueta: 'Endamoeba histolítica', grupo: 'Protozoarios (quistes)' },
			{ clave: 'endamoeba_coli_quistes', etiqueta: 'Endamoeba coli', grupo: 'Protozoarios (quistes)' },
			{ clave: 'endolimax_quistes', etiqueta: 'Endolimax nana', grupo: 'Protozoarios (quistes)' },
			{ clave: 'iodamoeba_quistes', etiqueta: 'Iodamoeba butschlii', grupo: 'Protozoarios (quistes)' },
			{ clave: 'giarda_lamblia_quistes', etiqueta: 'Giardia lamblia', grupo: 'Protozoarios (quistes)' },
			{ clave: 'chilomastix_mesnili_quistes', etiqueta: 'Chilomastix mesnili', grupo: 'Protozoarios (quistes)' },
			{ clave: 'trichomona_hominis_quistes', etiqueta: 'Trichomona hominis', grupo: 'Protozoarios (quistes)' },
			{ clave: 'balantidium_coli_quistes', etiqueta: 'Balantidium coli', grupo: 'Protozoarios (quistes)' },
			{ clave: 'endamoeba_histolitica_trofozoitos', etiqueta: 'Endamoeba histolítica', grupo: 'Protozoarios (trofozoítos)' },
			{ clave: 'endamoeba_coli_trofozoitos', etiqueta: 'Endamoeba coli', grupo: 'Protozoarios (trofozoítos)' },
			{ clave: 'endolimax_trofozoitos', etiqueta: 'Endolimax nana', grupo: 'Protozoarios (trofozoítos)' },
			{ clave: 'iodamoeba_trofozoitos', etiqueta: 'Iodamoeba butschlii', grupo: 'Protozoarios (trofozoítos)' },
			{ clave: 'giarda_lamblia_trofozoitos', etiqueta: 'Giardia lamblia', grupo: 'Protozoarios (trofozoítos)' },
			{ clave: 'chilomastix_mesnili_trofozoitos', etiqueta: 'Chilomastix mesnili', grupo: 'Protozoarios (trofozoítos)' },
			{ clave: 'trichomona_hominis_trofozoitos', etiqueta: 'Trichomona hominis', grupo: 'Protozoarios (trofozoítos)' },
			{ clave: 'balantidium_coli_trofozoitos', etiqueta: 'Balantidium coli', grupo: 'Protozoarios (trofozoítos)' },
			{ clave: 'Blastocystis_hominis_quistes', etiqueta: 'Blastocystis hominis (quistes)', grupo: 'Blastocystis hominis' },
			{ clave: 'Blastocystis_hominis_trofozoitos', etiqueta: 'Blastocystis hominis (trofozoítos)', grupo: 'Blastocystis hominis' },
			{ clave: 'ascaris', etiqueta: 'Ascaris lumbricoides', grupo: 'Helmintos' },
			{ clave: 'tricocefalos', etiqueta: 'Trichocéfalos', grupo: 'Helmintos' },
			{ clave: 'uncinaria', etiqueta: 'Uncinaria', grupo: 'Helmintos' },
			{ clave: 'tenia_saginata', etiqueta: 'Tenia saginata', grupo: 'Helmintos' },
			{ clave: 'tenia_solium', etiqueta: 'Tenia solium', grupo: 'Helmintos' },
			{ clave: 'himenolepsis', etiqueta: 'Himenolepsis nana', grupo: 'Helmintos' },
			{ clave: 'strongiloides_larva', etiqueta: 'Strongyloides (larvas)', grupo: 'Helmintos' },
			{ clave: 'oxiuros_huevos', etiqueta: 'Oxiuros (huevos)', grupo: 'Helmintos' },
			{ clave: 'sangre_oculta', etiqueta: 'Sangre oculta', grupo: 'Otros' },
			{ clave: 'lecucocitos', etiqueta: 'Leucocitos', grupo: 'Otros' },
			{ clave: 'observaciones', etiqueta: 'Observaciones', grupo: 'Otros', multilinea: true }
		],
		preservar: ['fechaResultados']
	},
	'5': {
		tipo: '5',
		tabla: 'hemogramaRayto',
		cargar: async (id, fecha) => aReg(getHemogramaRaytoNew(id, fecha)),
		campos: [
			{ clave: 'WBC', etiqueta: 'WBC', grupo: 'Serie blanca' },
			{ clave: 'LYMn', etiqueta: 'LYM#', grupo: 'Serie blanca' },
			{ clave: 'MIDn', etiqueta: 'MID#', grupo: 'Serie blanca' },
			{ clave: 'GRAn', etiqueta: 'GRA#', grupo: 'Serie blanca' },
			{ clave: 'LYMp', etiqueta: 'LYM%', grupo: 'Serie blanca' },
			{ clave: 'MIDp', etiqueta: 'MID%', grupo: 'Serie blanca' },
			{ clave: 'GRAp', etiqueta: 'GRA%', grupo: 'Serie blanca' },
			{ clave: 'RBC', etiqueta: 'RBC', grupo: 'Serie roja' },
			{ clave: 'HGB', etiqueta: 'HGB', grupo: 'Serie roja' },
			{ clave: 'HCT', etiqueta: 'HCT', grupo: 'Serie roja' },
			{ clave: 'MCV', etiqueta: 'MCV', grupo: 'Serie roja' },
			{ clave: 'MCH', etiqueta: 'MCH', grupo: 'Serie roja' },
			{ clave: 'MCHC', etiqueta: 'MCHC', grupo: 'Serie roja' },
			{ clave: 'RDWCV', etiqueta: 'RDW-CV', grupo: 'Serie roja' },
			{ clave: 'RDWSD', etiqueta: 'RDW-SD', grupo: 'Serie roja' },
			{ clave: 'PLT', etiqueta: 'PLT', grupo: 'Plaquetas' },
			{ clave: 'MPV', etiqueta: 'MPV', grupo: 'Plaquetas' },
			{ clave: 'PDW', etiqueta: 'PDW', grupo: 'Plaquetas' },
			{ clave: 'PCT', etiqueta: 'PCT', grupo: 'Plaquetas' },
			{ clave: 'PLCR', etiqueta: 'P-LCR', grupo: 'Plaquetas' },
			{ clave: 'observaciones', etiqueta: 'Observaciones', multilinea: true }
		],
		preservar: ['fechaResultados']
	},
	'6': {
		tipo: '6',
		tabla: 'frotisVaginal',
		cargar: async (id, fecha) => aReg(getFrotisVaginal(id, fecha)),
		campos: [
			{ clave: 'otros_fresco', etiqueta: 'Otros hallazgos (fresco)', grupo: 'Examen en fresco' },
			{ clave: 'prueba_de_aminas', etiqueta: 'Prueba de aminas', grupo: 'Examen en fresco' },
			{ clave: 'celulas_guia1', etiqueta: 'Células guía', grupo: 'Examen en fresco' },
			{ clave: 'ph', etiqueta: 'pH', grupo: 'Examen en fresco' },
			{ clave: 'trichonomas_vaginales', etiqueta: 'Trichomonas vaginalis', grupo: 'Examen en fresco' },
			{ clave: 'pmn', etiqueta: 'PMN', grupo: 'Examen en fresco' },
			{ clave: 'celulas_guia2', etiqueta: 'Células guía (2)', grupo: 'Gram' },
			{ clave: 'blastoconidias', etiqueta: 'Blastoconidias', grupo: 'Gram' },
			{ clave: 'seudomicelios', etiqueta: 'Seudomicelios', grupo: 'Gram' },
			{ clave: 'lactobacillus', etiqueta: 'Lactobacillus sp', grupo: 'Gram' },
			{ clave: 'gardnerella_sp', etiqueta: 'Gardnerella sp', grupo: 'Gram' },
			{ clave: 'bacteroides_sp', etiqueta: 'Bacteroides sp', grupo: 'Gram' },
			{ clave: 'mobilluncus_sp', etiqueta: 'Mobiluncus sp', grupo: 'Gram' },
			{ clave: 'pmnx', etiqueta: 'PMN (x campo)', grupo: 'Gram' },
			{ clave: 'intracelulares', etiqueta: 'Diplococos intracelulares', grupo: 'Gram' },
			{ clave: 'extracelulares', etiqueta: 'Diplococos extracelulares', grupo: 'Gram' },
			{ clave: 'observaciones', etiqueta: 'Observaciones', multilinea: true }
		],
		preservar: ['examen', 'fechaResultados']
	},
	'8': {
		tipo: '8',
		tabla: 'perfilLipidico',
		cargar: async (id, fecha) => aReg(getPerfilLipidico(id, fecha)),
		campos: [
			{ clave: 'colesterol_total', etiqueta: 'Colesterol total', grupo: 'Perfil lipídico' },
			{ clave: 'colesterol_hdl', etiqueta: 'Colesterol HDL', grupo: 'Perfil lipídico' },
			{ clave: 'colesterol_vldl', etiqueta: 'Colesterol VLDL', grupo: 'Perfil lipídico' },
			{ clave: 'colesterol_ldl', etiqueta: 'Colesterol LDL', grupo: 'Perfil lipídico' },
			{ clave: 'trigliceridos', etiqueta: 'Triglicéridos', grupo: 'Perfil lipídico' },
			{ clave: 'indice_arterial', etiqueta: 'Índice arterial', grupo: 'Perfil lipídico' },
			{ clave: 'observaciones', etiqueta: 'Observaciones', multilinea: true }
		],
		preservar: ['fechaResultados']
	}
}

export const NOMBRES_TIPO: Record<string, string> = {
	1: 'Examen (valoración)',
	2: 'Examen (valoración)',
	3: 'Parcial de orina',
	4: 'Coprológico',
	5: 'Cuadro hemático (hemograma)',
	6: 'Frotis vaginal',
	8: 'Perfil lipídico'
}

/** Convierte una respuesta tipada de detalle a un mapa plano de columnas. */
async function aReg<T extends object>(p: Promise<T | null>): Promise<Record<string, unknown> | null> {
	const r = await p
	return r ? Object.assign({}, r) : null
}
