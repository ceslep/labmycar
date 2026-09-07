<script lang="ts">
	import { onMount } from 'svelte'
	import {
		eliminarProcedimiento,
		getItemsExamen,
		getProcedimiento,
		guardarItemsExamen,
		guardarProcedimiento
	} from '../lib/api/laboratorio'
	import type { Procedimiento } from '../lib/models/models'
	import { navigate } from '../lib/router.svelte'
	import { toast } from '../lib/stores/toast.svelte'
	import Field from '../lib/ui/Field.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import Page from '../lib/ui/Page.svelte'

	let { codigo }: { codigo?: string } = $props()

	const editando = $derived(!!codigo && codigo !== 'nuevo')
	let cargando = $state(true)
	let guardando = $state(false)
	let error = $state('')
	let ind = $state('')

	let f = $state({
		codigo: '',
		nombre: '',
		tabla: '',
		tipo: '',
		unidades: '',
		constante: '',
		constante2: '',
		info: '',
		color: '',
		abreviatura: '',
		tipoprocedimiento: ''
	})

	// Editor de opciones (items editables para valoración)
	let campoOpcion = $state('Valoracion')
	let items = $state<string[]>([])
	let nuevoItem = $state('')
	let cargandoItems = $state(false)
	let guardandoItems = $state(false)

	async function cargarItems() {
		if (!f.codigo) return
		cargandoItems = true
		const lista = (await getItemsExamen(f.codigo, campoOpcion)).filter((i) => i !== '')
		items = lista
		cargandoItems = false
	}

	async function guardarOpciones() {
		if (!f.codigo) return
		guardandoItems = true
		const ok = await guardarItemsExamen(f.codigo, campoOpcion, items)
		guardandoItems = false
		if (ok) toast('Opciones guardadas correctamente')
		else toast('No se pudieron guardar las opciones', 'error')
	}

	function agregarItem() {
		const v = nuevoItem.trim()
		if (!v) return
		items = [...items, v]
		nuevoItem = ''
	}

	async function cargar() {
		if (!editando) {
			cargando = false
			f.codigo = codigo === 'nuevo' ? '' : (codigo ?? '')
			return
		}
		const p: Procedimiento | null = await getProcedimiento(codigo ?? '')
		if (p) {
			f.codigo = p.codigo ?? ''
			f.nombre = p.nombre ?? ''
			f.tabla = p.tabla ?? ''
			f.tipo = p.tipo ?? ''
			f.unidades = p.unidades ?? ''
			f.constante = p.constante ?? ''
			f.constante2 = p.constante2 ?? ''
			f.info = p.info ?? ''
			f.color = p.color ?? ''
			f.abreviatura = p.abreviatura ?? ''
			f.tipoprocedimiento = p.tipoprocedimiento ?? ''
			ind = p.ind ?? ''
			await cargarItems()
		} else {
			toast('Procedimiento no encontrado', 'error')
		}
		cargando = false
	}

	onMount(cargar)

	function validar(): string {
		if (!f.codigo.trim()) return 'El código es obligatorio.'
		if (!f.nombre.trim()) return 'El nombre es obligatorio.'
		if (!f.tabla.trim()) return 'La tabla de destino es obligatoria.'
		if (!f.tipo.trim()) return 'El tipo es obligatorio (define la vista de registro).'
		return ''
	}

	async function guardar() {
		const v = validar()
		if (v) {
			error = v
			return
		}
		error = ''
		guardando = true
		const ok = await guardarProcedimiento(f)
		guardando = false
		if (ok) {
			toast('Procedimiento guardado correctamente')
			navigate('/configuracion')
		} else {
			error = 'No se pudo guardar el procedimiento. Verifique la conexión.'
		}
	}

	async function eliminar() {
		if (!ind) return
		if (!window.confirm('¿Desea eliminar este procedimiento del catálogo?')) return
		const ok = await eliminarProcedimiento(ind)
		if (ok) {
			toast('Procedimiento eliminado')
			navigate('/configuracion')
		} else {
			toast('No se pudo eliminar el procedimiento', 'error')
		}
	}
</script>

<Page titulo={editando ? 'Editar procedimiento' : 'Nuevo procedimiento'} subtitulo={editando ? `Código: ${f.codigo}` : 'Registre un examen en el catálogo'}>
	{#snippet actions()}
		<div class="flex gap-2">
			{#if editando && ind}
				<button
					class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-bold text-red-700 transition hover:bg-red-100"
					onclick={eliminar}
				>
					<Icon nombre="eliminar" tam={15} />
					Eliminar
				</button>
			{/if}
			<button
				class="inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"
				onclick={guardar}
				disabled={guardando || cargando}
			>
				<Icon nombre="check" tam={16} />
				{guardando ? 'Guardando…' : 'Guardar'}
			</button>
		</div>
	{/snippet}

	{#if cargando}
		<Loader texto="Cargando procedimiento…" />
	{:else}
		{#if error}
			<div class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
				<Icon nombre="alerta" tam={16} clase="mt-0.5 shrink-0" />
				{error}
			</div>
		{/if}

		<div class="space-y-4 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
			<div class="grid gap-4 sm:grid-cols-2">
				<Field label="Código *" bind:value={f.codigo} placeholder="Ej. 3000" disabled={editando} />
				<Field label="Nombre *" bind:value={f.nombre} placeholder="Nombre del examen" />
			</div>
			<div class="grid gap-4 sm:grid-cols-3">
				<label class="block">
					<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">Tabla de destino *</span>
					<input
						list="tablas-conocidas"
						bind:value={f.tabla}
						placeholder="Ej. hemogramaRayto"
						class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
					/>
					<datalist id="tablas-conocidas">
						{#each ['examen_tipo_1', 'examen_tipo_2', 'parcialOrina', 'coprologico', 'hemogramaRayto', 'frotisVaginal', 'perfilLipidico'] as t (t)}
							<option value={t}>{t}</option>
						{/each}
					</datalist>
				</label>
				<Field label="Tipo *" bind:value={f.tipo} placeholder="1..8 (define la vista)" />
				<Field label="Color (r;g;b)" bind:value={f.color} placeholder="Ej. 0;128;0" />
			</div>
			<div class="grid gap-4 sm:grid-cols-3">
				<Field label="Unidades" bind:value={f.unidades} placeholder="Ej. mg/dl" />
				<Field label="Valor de referencia (constante)" bind:value={f.constante} placeholder="Texto normal del reporte" />
				<Field label="Abreviatura" bind:value={f.abreviatura} placeholder="Abreviatura" />
			</div>
			<Field label="Título del reporte (info)" bind:value={f.info} placeholder="Título que aparece en el reporte" />
			<label class="block">
				<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">Tabla de referencia (constante2, HTML/JSON)</span>
				<textarea
					bind:value={f.constante2}
					rows={4}
					placeholder="HTML o JSON de valores de referencia para el reporte"
					class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 font-mono text-[13px] outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
				></textarea>
			</label>
			<Field label="Código SOAT/CUPS (tipoprocedimiento)" bind:value={f.tipoprocedimiento} />
		</div>

		{#if editando}
			<div class="mt-5 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
				<h2 class="text-sm font-extrabold uppercase tracking-wide text-brand">Opciones de valoración (dropdown)</h2>
				<p class="mb-3 text-[13px] text-neutral-500">
					Opciones predefinidas que se ofrecen al registrar resultados (sistema de "items" por examen).
				</p>
				<div class="grid gap-4 sm:grid-cols-[200px_1fr_auto]">
					<Field label="Campo" bind:value={campoOpcion} />
					<div class="flex items-end gap-2">
						<input
							bind:value={nuevoItem}
							placeholder="Nueva opción…"
							class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
							onkeydown={(e) => {
								if (e.key === 'Enter') agregarItem()
							}}
						/>
						<button
							class="rounded-xl bg-neutral-100 p-2.5 text-neutral-600 transition hover:bg-neutral-200"
							onclick={agregarItem}
							title="Agregar opción"
						>
							<Icon nombre="mas" tam={18} />
						</button>
					</div>
					<button
						class="inline-flex h-[46px] items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"
						onclick={guardarOpciones}
						disabled={guardandoItems || cargandoItems}
					>
						<Icon nombre="check" tam={15} />
						Guardar
					</button>
				</div>
				{#if cargandoItems}
					<Loader texto="" tam={18} />
				{:else}
					<div class="mt-3 flex flex-wrap gap-2">
						{#if items.length === 0}
							<span class="text-[13px] text-neutral-400">Sin opciones definidas.</span>
						{/if}
						{#each items as it, i (it + i)}
							<span class="inline-flex items-center gap-1.5 rounded-full bg-neutral-100 px-3 py-1 text-[13px] font-semibold text-neutral-700">
								{it}
								<button class="text-neutral-400 hover:text-red-600" onclick={() => (items = items.filter((_, x) => x !== i))}>
									<Icon nombre="cerrar" tam={13} />
								</button>
							</span>
						{/each}
					</div>
				{/if}
			</div>
		{/if}
	{/if}
</Page>
