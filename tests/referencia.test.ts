import { test } from 'node:test'
import assert from 'node:assert/strict'
import { esc, sanitizarHtmlBasico, htmlTablaReferencia, referenciasParametros, normalizarClave } from '../src/lib/reportes/referencia.ts'

test('htmlTablaReferencia: vacío/undefined devuelve cadena vacía', () => {
	assert.equal(htmlTablaReferencia(undefined), '')
	assert.equal(htmlTablaReferencia(null), '')
	assert.equal(htmlTablaReferencia('   '), '')
})

test('htmlTablaReferencia: JSON array de parámetros → tabla con cabeceras', () => {
	const json = JSON.stringify([
		{ parametro: 'WBC', nombre: 'Leucocitos', valorReferencia: '4.0 - 11.0 x10³/µL' },
		{ parametro: 'HGB', nombre: 'Hemoglobina', valorReferencia: '13.5 - 17.5 g/dL' }
	])
	const html = htmlTablaReferencia(json)
	assert.match(html, /Tabla de referencia/)
	assert.match(html, /<table class="ref-tabla">/)
	assert.match(html, /<thead>/)
	assert.match(html, /parametro/)
	assert.match(html, /valorReferencia/)
	assert.ok(html.includes('4.0 - 11.0'))
	assert.ok(!html.includes('<WBC'), 'el contenido debe estar escapado')
})

test('htmlTablaReferencia: HTML crudo se conserva y sanitiza scripts', () => {
	const crudo = `<table border="1"><tr><th>A</th></tr><tr><td>B</td></tr></table><script>alert(1)</script>`
	const html = htmlTablaReferencia(crudo)
	assert.match(html, /<table /)
	assert.ok(!html.includes('<script'), 'debe eliminar scripts')
})

test('htmlTablaReferencia: texto plano se escapa en <pre>', () => {
	const html = htmlTablaReferencia('Valores <normales> & límites')
	assert.match(html, /<pre>/)
	assert.ok(!html.includes('<normales>'))
	assert.ok(html.includes('&lt;normales&gt;'))
})

test('htmlTablaReferencia: JSON inválido no lanza', () => {
	const html = htmlTablaReferencia('{ no soy json')
	assert.match(html, /<pre>/)
})

test('sanitizarHtmlBasico quita manejadores on*', () => {
	const limpio = sanitizarHtmlBasico('<td onclick="x()">ok</td>')
	assert.ok(!limpio.includes('onclick'))
	assert.ok(limpio.includes('ok'))
})

test('esc protege caracteres especiales', () => {
	assert.equal(esc('<b>&"'), '&lt;b&gt;&amp;&quot;')
})


test('referenciasParametros: JSON de parámetros → mapa por clave normalizada', () => {
	const json = JSON.stringify([
		{ parametro: 'WBC', nombre: 'Leucocitos', valorReferencia: '4.0 - 11.0 x10³/µL' },
		{ parametro: 'HGB', nombre: 'Hemoglobina', valorReferencia: 'H: 13.5-17.5 / M: 12.0-15.5 g/dL' }
	])
	const mapa = referenciasParametros(json)
	assert.ok(mapa)
	assert.equal(mapa!.get('WBC'), '4.0 - 11.0 x10³/µL')
	assert.equal(mapa!.get('HGB'), 'H: 13.5-17.5 / M: 12.0-15.5 g/dL')
})

test('referenciasParametros: devuelve null con vacío, HTML, JSON no-array o inválido', () => {
	assert.equal(referenciasParametros(undefined), null)
	assert.equal(referenciasParametros('   '), null)
	assert.equal(referenciasParametros('<table>...</table>'), null)
	assert.equal(referenciasParametros('{"parametro":"WBC"}'), null)
	assert.equal(referenciasParametros('{ no soy json'), null)
})

test('referenciasParametros: filas sin valorReferencia se descartan', () => {
	const json = JSON.stringify([{ parametro: 'RBC' }, { parametro: 'PLT', valorReferencia: '150 - 450 x10³/µL' }])
	const mapa = referenciasParametros(json)
	assert.ok(mapa)
	assert.equal(mapa!.has('RBC'), false)
	assert.equal(mapa!.get('PLT'), '150 - 450 x10³/µL')
})


test('referenciasParametros: JSON estilo perfil lipídico (dato + valor_recomendado)', () => {
	const json = JSON.stringify([
		{ dato: 'Colesterol total', valor_recomendado: '< 200 mg/dL' },
		{ dato: 'Trigliceridos', valor_recomendado: '< 150 mg/dL' }
	])
	const mapa = referenciasParametros(json)
	assert.ok(mapa)
	// Empareja ignorando acentos/espacios: 'Triglicéridos' → 'TRIGLICERIDOS'
	assert.equal(mapa!.get(normalizarClave('Triglicéridos')), '< 150 mg/dL')
	assert.equal(mapa!.get(normalizarClave('Colesterol total')), '< 200 mg/dL')
})

test('normalizarClave ignora mayúsculas, acentos y símbolos', () => {
	assert.equal(normalizarClave('RDW-CV'), 'RDWCV')
	assert.equal(normalizarClave('P-LCR'), 'PLCR')
	assert.equal(normalizarClave('Índice arterial'), 'INDICEARTERIAL')
	assert.equal(normalizarClave(' Triglicéridos '), 'TRIGLICERIDOS')
})

test('referenciasParametros: JSON con parametro punteado/acentuado', () => {
	const json = JSON.stringify([{ parametro: 'Ácido Úrico', valorReferencia: '1.5 - 7.0 mg/dL' }])
	const mapa = referenciasParametros(json)
	assert.ok(mapa)
	assert.equal(mapa!.get(normalizarClave('ACIDO URICO')), '1.5 - 7.0 mg/dL')
})
