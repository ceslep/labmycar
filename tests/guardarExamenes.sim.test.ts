import { test } from 'node:test'
import assert from 'node:assert/strict'

/**
 * Simulación fiel de la lógica corregida de libphp/guardarExamenes.php
 * (sin BD): asegura que el algoritmo produce exactamente una fila por examen
 * seleccionado, nunca borra ni duplica los ya realizados ('S') y elimina los
 * pendientes deseleccionados. El comportamiento real se verifica en el
 * endpoint PHP de producción; aquí se fija el contrato del algoritmo.
 */

type Fila = { codexamen: string; identificacion: string; fecha: string; realizado: string | null }

const ID = 'TEST-001'
const FECHA = '2026-01-15'

function aplicarGuardar(filas: Fila[], cods: string[]): Fila[] {
	const seleccion = new Set(cods)
	let salida: Fila[] = filas.filter((f) => !(f.identificacion === ID && f.fecha === FECHA && f.realizado !== 'S'))
	// Para cada seleccionado sin fila 'S' existente se inserta un pendiente.
	const hayS = new Set(filas.filter((f) => f.identificacion === ID && f.fecha === FECHA && f.realizado === 'S').map((f) => f.codexamen))
	for (const code of cods) {
		if (!hayS.has(code)) {
			salida.push({ codexamen: code, identificacion: ID, fecha: FECHA, realizado: null })
		}
	}
	return salida
}

const estado = (filas: Fila[]) =>
	filas.filter((f) => f.identificacion === ID && f.fecha === FECHA)

test('guarda todos los exámenes seleccionados (3 de 3)', () => {
	const filas = aplicarGuardar([], ['A1', 'B2', 'C3'])
	const e = estado(filas)
	assert.equal(e.length, 3, 'deben guardarse los 3 exámenes')
	assert.deepEqual(
		e.map((f) => f.codexamen).sort(),
		['A1', 'B2', 'C3']
	)
})

test('re-guardar la misma selección NO duplica', () => {
	let filas = aplicarGuardar([], ['A1', 'B2', 'C3'])
	filas = aplicarGuardar(filas, ['A1', 'B2', 'C3'])
	assert.equal(estado(filas).length, 3)
})

test('un examen ya realizado (S) se conserva y no se duplica al reasignar', () => {
	let filas = aplicarGuardar([], ['A1', 'B2'])
	// Marcar A1 como realizado
	filas = filas.map((f) => (f.codexamen === 'A1' ? { ...f, realizado: 'S' } : f))
	// Reasignar A1+B2
	filas = aplicarGuardar(filas, ['A1', 'B2'])
	const e = estado(filas)
	assert.equal(e.filter((f) => f.codexamen === 'A1').length, 1, 'A1 no se duplica')
	assert.equal(e.find((f) => f.codexamen === 'A1')!.realizado, 'S', 'A1 sigue realizado')
	assert.equal(e.filter((f) => f.codexamen === 'B2').length, 1)
})

test('al deseleccionar un examen pendiente se elimina; los realizados permanecen', () => {
	let filas = aplicarGuardar([], ['A1', 'B2', 'C3'])
	// Marcar B2 como realizado y luego guardar solo A1
	filas = filas.map((f) => (f.codexamen === 'B2' ? { ...f, realizado: 'S' } : f))
	filas = aplicarGuardar(filas, ['A1'])
	const e = estado(filas)
	assert.deepEqual(
		e.map((f) => f.codexamen).sort(),
		['A1', 'B2'],
		'C3 (pendiente) se elimina; B2 (realizado) se conserva aunque no esté seleccionado'
	)
	assert.equal(e.find((f) => f.codexamen === 'B2')!.realizado, 'S')
})
