import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
	num,
	esPositivo,
	esNegativo,
	analizarTipo5,
	analizarTipo8,
	analizarTipo3,
	analizarTipo4,
	analizarTipo6,
	analizarTipo12,
	analizarSalud,
	fraseNivel
} from '../src/lib/salud/analisis.ts'

test('num: normaliza coma decimal, símbolos y espacios', () => {
	assert.equal(num('5,5'), 5.5)
	assert.equal(num(' 12.5 '), 12.5)
	assert.equal(num('>200 mg/dL'), 200)
	assert.equal(num('1.020'), 1.02)
	assert.equal(num('abc'), null)
	assert.equal(num(null), null)
	assert.equal(num(''), null)
})

test('esPositivo / esNegativo', () => {
	assert.equal(esPositivo('Positivo'), true)
	assert.equal(esPositivo('++'), true)
	assert.equal(esPositivo('Reactivo'), true)
	assert.equal(esPositivo('NO REACTIVO'), false)
	assert.equal(esPositivo('Negativo'), false)
	assert.equal(esPositivo(''), false)
	assert.equal(esNegativo('NO REACTIVO'), true)
	assert.equal(esNegativo('Negativo'), true)
})

test('tipo5: hemoglobina baja en mujer → anemia (alerta)', () => {
	const hs = analizarTipo5({ HGB: '10.2', HCT: '31', WBC: '6.0', PLT: '220' }, 'F')
	assert.ok(hs.some((h) => h.nivel === 'alerta' && /hemoglobina baja/i.test(h.mensaje)))
	assert.ok(hs.some((h) => h.nivel === 'alerta' && /hematocrito bajo/i.test(h.mensaje)))
})

test('tipo5: hemoglobina normal en hombre no genera alerta de anemia', () => {
	const hs = analizarTipo5({ HGB: '15.0', HCT: '44', WBC: '7', PLT: '250', MCV: '90' }, 'M')
	assert.equal(hs.some((h) => /hemoglobina baja/i.test(h.mensaje)), false)
	assert.equal(hs.some((h) => /hematocrito bajo/i.test(h.mensaje)), false)
})

test('tipo5: leucocitos altos → infección (alerta)', () => {
	const hs = analizarTipo5({ WBC: '16.4', HGB: '14', PLT: '200' })
	assert.ok(hs.some((h) => h.nivel === 'alerta' && /leucocitos altos/i.test(h.mensaje)))
})

test('tipo8: perfil lipídico alterado', () => {
	const hs = analizarTipo8({ colesterol_total: '245', colesterol_ldl: '165', colesterol_hdl: '35', trigliceridos: '210' })
	assert.ok(hs.some((h) => h.nivel === 'alerta' && /colesterol total alto/i.test(h.mensaje)))
	assert.ok(hs.some((h) => h.nivel === 'alerta' && /LDL alto/i.test(h.mensaje)))
	assert.ok(hs.some((h) => h.nivel === 'atencion' && /HDL bajo/i.test(h.mensaje)))
	assert.ok(hs.some((h) => h.nivel === 'alerta' && /triglicéridos altos/i.test(h.mensaje)))
})

test('tipo3: nitritos positivos → alerta de infección urinaria', () => {
	const hs = analizarTipo3({ nitritos: 'Positivo', densidad: '1.010', ph: '6' })
	assert.ok(hs.some((h) => h.nivel === 'alerta' && /nitritos positivos/i.test(h.mensaje)))
})

test('tipo3: orina normal sin hallazgos', () => {
	const hs = analizarTipo3({ nitritos: 'Negativo', densidad: '1.015', ph: '6', glucosa: 'Negativo', proteinas: 'Negativo' })
	assert.equal(hs.length, 0)
})

test('tipo4: parásito detectado', () => {
	const hs = analizarTipo4({ ascariasis: '', ascaris: 'Positivo' })
	assert.ok(hs.some((h) => h.nivel === 'alerta' && /Ascaris/i.test(h.mensaje)))
})

test('tipo6: tricomonas → alerta', () => {
	const hs = analizarTipo6({ trichonomas_vaginales: 'Positivo' })
	assert.ok(hs.some((h) => h.nivel === 'alerta' && /Trichomonas/i.test(h.mensaje)))
})

test('tipo1/2: valoración reactiva frente a referencia "no reactivo"', () => {
	const hs = analizarTipo12({ valoracion: 'Reactivo' }, 'NO REACTIVO')
	assert.ok(hs.some((h) => h.nivel === 'alerta' && /REACTIVO/i.test(h.mensaje)))
})

test('tipo1/2: rango numérico alto', () => {
	const hs = analizarTipo12({ valoracion: '80' }, '10 - 40')
	assert.ok(hs.some((h) => h.nivel === 'alerta' && /valor alto/i.test(h.mensaje)))
})

test('analizarSalud: agrega y clasifica el nivel global', () => {
	const a = analizarSalud(
		[
			{ tipo: '5', examen: 'Hemograma', fila: { HGB: '9.8', WBC: '7' } },
			{ tipo: '8', examen: 'Perfil lipídico', fila: { colesterol_total: '190', trigliceridos: '120' } }
		],
		'F'
	)
	assert.equal(a.nivel, 'alerta')
	assert.ok(a.items.length >= 1)
	assert.ok(fraseNivel(a.nivel).length > 0)
})

test('analizarSalud: todo normal → nivel ok', () => {
	const a = analizarSalud([
		{ tipo: '5', examen: 'Hemograma', fila: { HGB: '14', HCT: '42', WBC: '6.5', PLT: '250', MCV: '90' } }
	])
	assert.equal(a.nivel, 'ok')
	assert.equal(a.items.length, 0)
})
