import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
	esMujerMarcador,
	estadoValor,
	extraerNumero,
	construirSeries,
	marcadoresTipo5,
	marcadoresTipo8,
	marcadoresTipo3
} from '../src/lib/salud/marcadores.ts'

test('esMujerMarcador', () => {
	assert.equal(esMujerMarcador('F'), true)
	assert.equal(esMujerMarcador('Femenino'), true)
	assert.equal(esMujerMarcador('M'), false)
	assert.equal(esMujerMarcador('Masculino'), false)
	assert.equal(esMujerMarcador(null), false)
})

test('extraerNumero', () => {
	assert.equal(extraerNumero('12.5'), 12.5)
	assert.equal(extraerNumero('5,5'), 5.5)
	assert.equal(extraerNumero('>200 mg/dL'), 200)
	assert.equal(extraerNumero('abc'), null)
	assert.equal(extraerNumero(null), null)
})

test('marcadoresTipo5: rangos según sexo', () => {
	const mujer = marcadoresTipo5('F').find((m) => m.clave === 'HGB')!
	const hombre = marcadoresTipo5('M').find((m) => m.clave === 'HGB')!
	assert.equal(mujer.okMin, 12)
	assert.equal(hombre.okMin, 13.5)
})

test('estadoValor: HGB por debajo del rango', () => {
	const hgb = marcadoresTipo5('M').find((m) => m.clave === 'HGB')!
	assert.equal(estadoValor(hgb, 10), 'bajo')
	assert.equal(estadoValor(hgb, 15), 'ok')
	assert.equal(estadoValor(hgb, 19), 'alto')
})

test('estadoValor: colesterol con zona de atención', () => {
	const col = marcadoresTipo8().find((m) => m.clave === 'colesterol_total')!
	assert.equal(estadoValor(col, 180), 'ok')
	assert.equal(estadoValor(col, 220), 'atencion')
	assert.equal(estadoValor(col, 260), 'alto')
})

test('estadoValor: HDL solo con mínimo', () => {
	const hdl = marcadoresTipo8().find((m) => m.clave === 'colesterol_hdl')!
	assert.equal(estadoValor(hdl, 55), 'ok')
	assert.equal(estadoValor(hdl, 30), 'bajo')
})

test('construirSeries: agrupa por marcador y descarta no numéricos', () => {
	const hist = [
		{ tipo: '5', fecha: '2026-01-01', fila: { HGB: '13.0', WBC: 'Positivo', MCV: 'abc' } },
		{ tipo: '5', fecha: '2026-02-01', fila: { HGB: '14.2', WBC: '6.5' } },
		{ tipo: '8', fecha: '2026-02-01', fila: { colesterol_total: '180', trigliceridos: '120' } }
	]
	const series = construirSeries(hist, 'M')
	const hgb = series.find((s) => s.m.clave === 'HGB')!
	assert.equal(hgb.puntos.length, 2)
	assert.equal(hgb.puntos[0].valor, 13)
	// WBC tiene solo 1 punto numérico, pero se incluye (1 punto)
	const wbc = series.find((s) => s.m.clave === 'WBC')!
	assert.equal(wbc.puntos.length, 1)
	const col = series.find((s) => s.m.clave === 'colesterol_total')!
	assert.equal(col.puntos.length, 1)
})

test('construirSeries: ordena por fecha ascendente', () => {
	const hist = [
		{ tipo: '5', fecha: '2026-05-01', fila: { HGB: '15' } },
		{ tipo: '5', fecha: '2026-01-01', fila: { HGB: '13' } }
	]
	const hgb = construirSeries(hist, 'F').find((s) => s.m.clave === 'HGB')!
	assert.deepEqual(hgb.puntos.map((p) => p.valor), [13, 15])
})

test('marcadoresTipo3: densidad con escala fina', () => {
	const dens = marcadoresTipo3().find((m) => m.clave === 'densidad')!
	assert.equal(dens.okMin, 1.005)
	assert.equal(dens.okMax, 1.03)
})
