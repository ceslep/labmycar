import { test } from 'node:test'
import assert from 'node:assert/strict'
import { esc, sanitizarHtmlBasico, htmlTablaReferencia } from '../src/lib/reportes/referencia.ts'

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
