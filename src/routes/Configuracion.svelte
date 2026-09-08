<script lang="ts">
	import { onMount } from 'svelte'
	import {
		eliminarProcedimiento,
		getConfiguracion,
		getProcedimientos,
		guardarConfiguracion
	} from '../lib/api/laboratorio'
	import type { Configuracion, Procedimiento } from '../lib/models/models'
	import { navigate } from '../lib/router.svelte'
	import { toast } from '../lib/stores/toast.svelte'
	import { aplicarConfigApp } from '../lib/stores/configapp.svelte'
	import { colorRgb } from '../lib/utils/format'
	import Field from '../lib/ui/Field.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import EmptyState from '../lib/ui/EmptyState.svelte'
	import Thing from '../lib/ui/Thing.svelte'

	type Tab = 'lab' | 'proc'

	const CLAVE_TAB = 'labmycar:cfg_tab'
	let tab = $state<Tab>((typeof localStorage !== 'undefined' && (localStorage.getItem(CLAVE_TAB) as Tab)) || 'lab')

	function setTab(t: Tab) {
		tab = t
		try {
			localStorage.setItem(CLAVE_TAB, t)
		} catch {
			/* noop */
		}
	}

	// ---------------- Laboratorio ----------------
	let cfg = $state<Configuracion | null>(null)
	let cargandoCfg = $state(true)
	let guardandoCfg = $state(false)
	let errorCfg = $state('')

	let f = $state({
		nit: '',
		nombreLaboratorio: '',
		nombreCorto: '',
		direccionLaboratorio: '',
		telefonosLaboratorio: '',
		correoLaboratorio: '',
		webLaboratorio: '',
		bacteriologoLaboratorio: '',
		tarjetaPLaboratorio: ''
	})
	let firmaB64 = $state('')
	let logoB64 = $state('')

	function dataUrl(b64: string): string {
		return b64 ? `data:image/png;base64,${b64}` : ''
	}

	function leerArchivo(file: File): Promise<string> {
		return new Promise((resolve, reject) => {
			const r = new FileReader()
			r.onload = () => {
				const s = String(r.result ?? '')
				resolve(s.includes(',') ? s.slice(s.indexOf(',') + 1) : s)
			}
			r.onerror = () => reject(r.error)
			r.readAsDataURL(file)
		})
	}

	const MAX_IMAGEN = 2 * 1024 * 1024

	async function manejarArchivo(file: File, aplicar: (b64: string) => void) {
		if (file.size > MAX_IMAGEN) {
			toast('La imagen supera 2 MB. Use una más pequeña (p. ej. PNG optimizado).', 'error')
			return
		}
		aplicar(await leerArchivo(file))
	}

	async function cargarConfig() {
		cargandoCfg = true
		const c = await getConfiguracion()
		cfg = c
		aplicarConfigApp(c)
		if (c) {
			f.nit = c.nit ?? ''
			f.nombreLaboratorio = c.nombreLaboratorio ?? ''
			f.nombreCorto = c.nombreCorto ?? ''
			f.direccionLaboratorio = c.direccionLaboratorio ?? ''
			f.telefonosLaboratorio = c.telefonosLaboratorio ?? ''
			f.correoLaboratorio = c.correoLaboratorio ?? ''
			f.webLaboratorio = c.webLaboratorio ?? ''
			f.bacteriologoLaboratorio = c.bacteriologoLaboratorio ?? ''
			f.tarjetaPLaboratorio = c.tarjetaPLaboratorio ?? ''
			firmaB64 = c.urFirmaLaboratorio ?? ''
			logoB64 = c.urlLogoLaboratorio ?? ''
		}
		cargandoCfg = false
	}

	async function guardarLab() {
		if (!f.nombreLaboratorio.trim()) {
			errorCfg = 'El nombre del laboratorio es obligatorio.'
			return
		}
		if (!f.tarjetaPLaboratorio.trim()) {
			errorCfg = 'La T.P. (clave de acceso) no puede quedar vacía, o nadie podrá ingresar.'
			return
		}
		errorCfg = ''
		guardandoCfg = true
		const payload: Configuracion = {
			nit: f.nit.trim(),
			nombreLaboratorio: f.nombreLaboratorio.trim(),
			nombreCorto: f.nombreCorto.trim(),
			direccionLaboratorio: f.direccionLaboratorio,
			telefonosLaboratorio: f.telefonosLaboratorio.trim(),
			correoLaboratorio: f.correoLaboratorio.trim(),
			webLaboratorio: f.webLaboratorio.trim(),
			bacteriologoLaboratorio: f.bacteriologoLaboratorio.trim(),
			tarjetaPLaboratorio: f.tarjetaPLaboratorio,
			urFirmaLaboratorio: firmaB64,
			urlLogoLaboratorio: logoB64
		}
		const ok = await guardarConfiguracion(payload)
		guardandoCfg = false
		if (ok) {
			toast('Configuración guardada correctamente')
			aplicarConfigApp(payload)
			await cargarConfig()
		} else {
			errorCfg = 'No se pudo guardar la configuración. Verifique la conexión.'
		}
	}

	// ---------------- Procedimientos ----------------
	let lista = $state<Procedimiento[]>([])
	let cargandoP = $state(true)
	let busqueda = $state('')

	async function cargarProc() {
		cargandoP = true
		lista = await getProcedimientos()
		cargandoP = false
	}

	const filtroProc = $derived(
		lista.filter((p) => {
			const q = busqueda.trim().toLowerCase()
			if (!q) return true
			return `${p.nombre ?? ''} ${p.codigo ?? ''} ${p.abreviatura ?? ''}`.toLowerCase().includes(q)
		})
	)

	async function eliminarProc(p: Procedimiento) {
		if (!p.ind) return
		if (!window.confirm(`¿Eliminar "${p.nombre}" del catálogo?`)) return
		const ok = await eliminarProcedimiento(p.ind)
		if (ok) {
			toast('Procedimiento eliminado')
			await cargarProc()
		} else {
			toast('No se pudo eliminar el procedimiento', 'error')
		}
	}

	onMount(() => {
		cargarConfig()
		cargarProc()
	})
</script>

<div class="mx-auto w-full max-w-6xl px-5 py-8 sm:px-8">
	<div class="mb-6 flex items-center gap-3">
		<Thing nombre="building" tam={42} clase="drop-shadow-sm" />
		<div>
			<h1 class="text-2xl font-bold tracking-[-0.02em] text-neutral-900">Configuración</h1>
			<p class="mt-0.5 text-sm text-neutral-500">Datos del laboratorio y catálogo de procedimientos</p>
		</div>
	</div>

	<div class="mb-5 flex rounded-xl bg-neutral-200/70 p-1">
		<button
			class="flex-1 rounded-lg px-4 py-2 text-sm font-bold transition {tab === 'lab' ? 'bg-white text-brand shadow' : 'text-neutral-500'}"
			onclick={() => setTab('lab')}
		>
			Laboratorio
		</button>
		<button
			class="flex-1 rounded-lg px-4 py-2 text-sm font-bold transition {tab === 'proc' ? 'bg-white text-brand shadow' : 'text-neutral-500'}"
			onclick={() => setTab('proc')}
		>
			Procedimientos ({lista.length})
		</button>
	</div>

	{#if tab === 'lab'}
		{#if cargandoCfg}
			<Loader texto="Cargando configuración…" />
		{:else}
			{#if errorCfg}
				<div class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
					<Icon nombre="alerta" tam={16} clase="mt-0.5 shrink-0" />
					{errorCfg}
				</div>
			{/if}

			<div class="grid gap-4 lg:grid-cols-[1fr_300px]">
				<div class="space-y-4 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
					<div class="grid gap-4 sm:grid-cols-2">
						<Field label="NIT" bind:value={f.nit} />
						<Field label="Nombre del laboratorio *" bind:value={f.nombreLaboratorio} />
					</div>
					<div class="grid gap-4 sm:grid-cols-2">
						<Field label="Nombre corto" bind:value={f.nombreCorto} />
						<Field label="Teléfonos" bind:value={f.telefonosLaboratorio} />
					</div>
					<Field label="Dirección" bind:value={f.direccionLaboratorio} />
					<div class="grid gap-4 sm:grid-cols-2">
						<Field label="Correo" bind:value={f.correoLaboratorio} tipo="email" />
						<Field label="Sitio web" bind:value={f.webLaboratorio} />
					</div>
					<div class="grid gap-4 sm:grid-cols-2">
						<Field label="Bacteriólogo" bind:value={f.bacteriologoLaboratorio} />
						<Field label="T.P. (clave de acceso) *" bind:value={f.tarjetaPLaboratorio} />
					</div>
					<div class="flex justify-end pt-1">
						<button
							class="inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"
							onclick={guardarLab}
							disabled={guardandoCfg}
						>
							<Icon nombre="check" tam={16} />
							{guardandoCfg ? 'Guardando…' : 'Guardar configuración'}
						</button>
					</div>
				</div>

				<aside class="space-y-4">
					<div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
						<p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-neutral-500">Logo (aparece en reportes)</p>
						{#if logoB64}
							<img src={dataUrl(logoB64)} alt="Logo" class="mb-2 max-h-24 rounded-lg border border-neutral-200 object-contain" />
						{/if}
						<label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-neutral-300 px-3 py-2 text-sm font-bold text-neutral-600 transition hover:bg-neutral-50">
							<Icon nombre="mas" tam={15} />
							{logoB64 ? 'Cambiar logo' : 'Subir logo'}
							<input
								type="file"
								accept="image/png,image/jpeg"
								class="hidden"
								onchange={(e) => {
									const file = e.currentTarget.files?.[0]
									if (file) manejarArchivo(file, (b) => (logoB64 = b))
								}}
							/>
						</label>
					</div>
					<div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
						<p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-neutral-500">Firma (bacteriólogo)</p>
						{#if firmaB64}
							<img src={dataUrl(firmaB64)} alt="Firma" class="mb-2 max-h-24 rounded-lg border border-neutral-200 object-contain" />
						{/if}
						<label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-neutral-300 px-3 py-2 text-sm font-bold text-neutral-600 transition hover:bg-neutral-50">
							<Icon nombre="mas" tam={15} />
							{firmaB64 ? 'Cambiar firma' : 'Subir firma'}
							<input
								type="file"
								accept="image/png,image/jpeg"
								class="hidden"
								onchange={(e) => {
									const file = e.currentTarget.files?.[0]
									if (file) manejarArchivo(file, (b) => (firmaB64 = b))
								}}
							/>
						</label>
					</div>
				</aside>
			</div>
		{/if}
	{:else}
		<div class="mb-4 flex flex-wrap items-center gap-2">
			<div class="relative w-full max-w-sm">
				<span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-400">
					<Icon nombre="buscar" tam={16} />
				</span>
				<input
					bind:value={busqueda}
					type="text"
					placeholder="Buscar por nombre o código…"
					class="w-full rounded-xl border border-neutral-300 bg-white py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
				/>
			</div>
			<div class="flex-1"></div>
			<button
				class="inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700"
				onclick={() => {
					try {
						localStorage.setItem(CLAVE_TAB, 'proc')
					} catch {
						/* noop */
					}
					navigate('/procedimiento/nuevo')
				}}
			>
				<Icon nombre="mas" tam={16} />
				Nuevo procedimiento
			</button>
		</div>

		{#if cargandoP}
			<Loader texto="Cargando catálogo…" />
		{:else if filtroProc.length === 0}
			<EmptyState icono="buscar" titulo="Sin resultados" subtitulo="Ningún procedimiento coincide con la búsqueda." />
		{:else}
			<div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
				<div class="max-h-[62vh] divide-y divide-neutral-100 overflow-y-auto">
					{#each filtroProc as p (p.ind ?? p.codigo)}
						<div class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-neutral-50">
							<span
								class="h-8 w-1.5 shrink-0 rounded-full"
								style={colorRgb(p.color) ? `background-color:${colorRgb(p.color)}` : 'background-color:#d4d4d4'}
							></span>
							<div class="min-w-0 flex-1">
								<p class="truncate text-sm font-bold text-neutral-800">{p.nombre ?? '—'}</p>
								<p class="text-[11px] text-neutral-400">
									{p.codigo} · tipo {p.tipo ?? '—'} · {p.tabla ?? ''} {p.unidades ? `· ${p.unidades}` : ''}
								</p>
							</div>
							{#if p.abreviatura}
								<span class="hidden rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-bold uppercase text-neutral-500 sm:inline">
									{p.abreviatura}
								</span>
							{/if}
							<div class="flex shrink-0 items-center gap-1">
								<button
									class="rounded-lg p-2 text-neutral-400 transition hover:bg-neutral-100 hover:text-brand"
									title="Editar"
									onclick={() => {
										try {
											localStorage.setItem(CLAVE_TAB, 'proc')
										} catch {
											/* noop */
										}
										navigate(`/procedimiento/${encodeURIComponent(p.codigo ?? '')}`)
									}}
								>
									<Icon nombre="lapiz" tam={15} />
								</button>
								<button
									class="rounded-lg p-2 text-neutral-400 transition hover:bg-red-50 hover:text-red-600"
									title="Eliminar"
									onclick={() => eliminarProc(p)}
								>
									<Icon nombre="eliminar" tam={15} />
								</button>
							</div>
						</div>
					{/each}
				</div>
			</div>
		{/if}
	{/if}
</div>
