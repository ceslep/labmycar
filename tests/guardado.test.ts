import { test } from 'node:test'
import assert from 'node:assert/strict'
import {
	msgEsOk,
	payloadGuardarExamenes,
	codigosUnicos
} from '../src/lib/api/guardado.ts'

test('msgEsOk: acepta true, "si" y ausencia de msg', () => {
	assert.equal(msgEsOk({ msg: true }), true)
	assert.equal(msgEsOk({ msg: 'si' }), true)
	assert.equal(msgEsOk({}), true)
	assert.equal(msgEsOk(null), false)
	assert.equal(msgEsOk(undefined), false)
})

test('msgEsOk: rechaza false y "no"', () => {
	assert.equal(msgEsOk({ msg: false }), false)
	assert.equal(msgEsOk({ msg: 'no' }), false)
})

test('payloadGuardarExamenes: incluye TODOS los exámenes con su codigo', () => {
	const procs = [{ codigo: '001' }, { codigo: '0042A' }, { codigo: '906130' }, { codigo: '3001' }]
	const payload = JSON.parse(payloadGuardarExamenes(procs)) as Array<{ codigo: string }>
	assert.deepEqual(
		payload.map((p) => p.codigo),
		['001', '0042A', '906130', '3001']
	)
	assert.equal(payload.length, 4, 'ningún examen debe perderse')
})

test('payloadGuardarExamenes: descarta entradas sin codigo pero conserva las demás', () => {
	const procs = [{ codigo: '001' }, {}, { codigo: '' }, { codigo: '3001' }]
	const payload = JSON.parse(payloadGuardarExamenes(procs)) as Array<{ codigo: string }>
	assert.deepEqual(
		payload.map((p) => p.codigo),
		['001', '3001']
	)
})

test('codigosUnicos: conserva el orden y elimina duplicados', () => {
	const procs = [{ codigo: '001' }, { codigo: '002' }, { codigo: '001' }, { codigo: '002' }]
	assert.deepEqual(codigosUnicos(procs), ['001', '002'])
})
