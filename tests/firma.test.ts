import { test } from 'node:test'
import assert from 'node:assert/strict'
import { firmaActivaParaFecha, type FirmaActiva } from '../src/lib/reportes/firma.ts'

const diana: FirmaActiva = { nombre: 'DIANA:T.P. 00897', periodos: [{ desde: '2026-04-22', hasta: '2026-12-31' }] }
const ana: FirmaActiva = { nombre: 'ANA MARIA R', periodos: [{ desde: '2026-01-21', hasta: '2026-04-17' }] }

test('firmaActivaParaFecha: elige la firma dentro de su periodo', () => {
	assert.equal(firmaActivaParaFecha([ana, diana], '2026-06-15'), diana)
	assert.equal(firmaActivaParaFecha([ana, diana], '2026-03-01'), ana)
})

test('firmaActivaParaFecha: respeta límites inclusive (desde/hasta)', () => {
	assert.equal(firmaActivaParaFecha([diana], '2026-04-22'), diana)
	assert.equal(firmaActivaParaFecha([diana], '2026-12-31'), diana)
	assert.equal(firmaActivaParaFecha([diana], '2026-04-21'), null)
	assert.equal(firmaActivaParaFecha([diana], '2027-01-01'), null)
})

test('firmaActivaParaFecha: sin periodos siempre activa; vacío/lista nula → null', () => {
	const sinPeriodos: FirmaActiva = { nombre: 'SIEMPRE' }
	assert.equal(firmaActivaParaFecha([sinPeriodos], '2030-01-01'), sinPeriodos)
	assert.equal(firmaActivaParaFecha([], '2026-01-01'), null)
	assert.equal(firmaActivaParaFecha(null, '2026-01-01'), null)
	assert.equal(firmaActivaParaFecha(undefined, '2026-01-01'), null)
})

test('firmaActivaParaFecha: fecha inválida o ausente → null', () => {
	assert.equal(firmaActivaParaFecha([diana], undefined), null)
	assert.equal(firmaActivaParaFecha([diana], 'no-es-fecha'), null)
})

test('firmaActivaParaFecha: acepta fechas con hora (usa solo YYYY-MM-DD)', () => {
	assert.equal(firmaActivaParaFecha([diana], '2026-05-01 08:30:00'), diana)
})
