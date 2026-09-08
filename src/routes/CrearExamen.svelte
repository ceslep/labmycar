<script lang="ts">
	import { onMount } from 'svelte'
	import {
		getInfoPaciente,
		getProcedimientos,
		getSeleccionados,
		guardarExamenes
	} from '../lib/api/laboratorio'
	import type { Paciente, Procedimiento } from '../lib/models/models'
	import { nombreCompletoPaciente } from '../lib/models/models'
	import { navigate } from '../lib/router.svelte'
	import { toast } from '../lib/stores/toast.svelte'
	import { calcularEdad, hoyISO, colorRgb } from '../lib/utils/format'
	import Field from '../lib/ui/Field.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import EmptyState from '../lib/ui/EmptyState.svelte'
	import Page from '../lib/ui/Page.svelte'

	let paso = $state(1)
	let fecha = $state(hoyISO())
	let identificacion = $state('')
	let paciente = $state<Paciente | null>(null)
	let buscando = $state(false)
	let errorBusqueda = $state('')

	let catalogo = $state<Procedimiento[]>([])
	let elegidos = $state<Procedimiento[]>([])
	let busquedaCat = $state('')
	let cargandoCatalogo = $state(false)

	let guardando = $state(false)

	async function buscarPaciente() {
		const id = identificacion.trim()
		if (!id) {
			errorBusqueda = 'Ingrese la identificación del paciente.'
			return
		}
		buscando = true
		errorBusqueda = ''
		paciente = null
		const p = await getInfoPaciente(id)
		buscando = false
		if (p && (p.identificacion ?? '').trim() !== '') {
			paciente = p
		} else {
			errorBusqueda = 'No se encontró un paciente con esa identificación.'
		}
	}

	async function irSeleccion() {
		if (!paciente?.identificacion) return
		paso = 2
		cargandoCatalogo = true
		errorBusqueda = ''
		const [cats, yaAsignados] = await Promise.all([
			getProcedimientos(),
			getSeleccionados(paciente.identificacion, fecha)
		])
		catalogo = cats
		// Preseleccionar los exámenes ya asignados en esa fecha
		elegidos = yaAsignados.filter((p) => p.codigo)
		cargandoCatalogo = false
	}

	const catalogFiltro = $derived(
		catalogo.filter((p) => {
			const q = busquedaCat.trim().toLowerCase()
			if (!q) return true
			return `${p.nombre ?? ''} ${p.codigo ?? ''} ${p.abreviatura ?? ''}`.toLowerCase().includes(q)
		})
	)

	function estaElegido(codigo?: string): boolean {
		return elegidos.some((e) => e.codigo === codigo)
	}

	function alternar(p: Procedimiento) {
		if (estaElegido(p.codigo)) elegidos = elegidos.filter((e) => e.codigo !== p.codigo)
		else elegidos = [...elegidos, p]
	}

	function quitar(codigo?: string) {
		elegidos = elegidos.filter((e) => e.codigo !== codigo)
	}

	function volverPaso2() {
		paso = 2
	}

	async function guardar() {
		if (!paciente?.identificacion || elegidos.length === 0) return
		guardando = true
		const ok = await guardarExamenes(elegidos, paciente.identificacion, fecha)
		guardando = false
		if (ok) {
			toast(`Se asignaron ${elegidos.length} exámenes correctamente`)
			navigate(`/paciente/${encodeURIComponent(paciente.identificacion)}/examenes`)
		} else {
			toast('No se pudieron asignar los exámenes. Verifique la conexión.', 'error')
		}
	}

	onMount(() => {
		fecha = hoyISO()
	})

	const ETAPAS = ['Paciente y fecha', 'Seleccionar exámenes', 'Confirmar y guardar']
</script>

<Page
	thing="laboratory"
	titulo="Nuevo examen"
	subtitulo={ETAPAS[paso - 1]}
	iconoAtras={paso === 1}
>
	{#snippet actions()}
		<div class="flex items-center gap-1.5 text-xs font-bold text-neutral-400">
			{#each ETAPAS as etapa, i (etapa)}
				<span
					class="rounded-full px-2.5 py-1 {i + 1 === paso
						? 'bg-brand text-white'
						: i + 1 < paso
							? 'bg-emerald-100 text-emerald-700'
							: 'bg-neutral-100 text-neutral-400'}"
				>
					{i + 1}. {etapa}
				</span>
			{/each}
		</div>
	{/snippet}

	<!-- PASO 1: paciente + fecha -->
	{#if paso === 1}
		<div class="space-y-4">
			<div class="grid gap-4 sm:grid-cols-2">
				<label class="block">
					<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">Fecha del examen *</span>
					<input
						type="date"
						bind:value={fecha}
						class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
					/>
				</label>
				<div class="flex items-end gap-2">
					<div class="flex-1">
						<Field
							label="Identificación del paciente *"
							bind:value={identificacion}
							placeholder="N.º de documento"
							onEnter={buscarPaciente}
							disabled={buscando}
						/>
					</div>
					<button
						class="mb-0.5 inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"
						onclick={buscarPaciente}
						disabled={buscando}
					>
						<Icon nombre="buscar" tam={15} />
						Buscar
					</button>
				</div>
			</div>

			{#if buscando}
				<Loader texto="Consultando paciente…" />
			{/if}

			{#if errorBusqueda}
				<div class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700">
					<Icon nombre="alerta" tam={16} clase="mt-0.5 shrink-0" />
					<div>
						{errorBusqueda}
						<button
							class="ml-2 underline decoration-amber-400 underline-offset-2 hover:text-amber-800"
							onclick={() => navigate('/pacientes/nuevo')}
						>
							Registrar paciente
						</button>
					</div>
				</div>
			{/if}

			{#if paciente}
				<div class="flex items-center gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
					<div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 text-white">
						<Icon nombre="check" tam={22} />
					</div>
					<div class="min-w-0 flex-1">
						<p class="truncate text-base font-extrabold text-neutral-800">{nombreCompletoPaciente(paciente)}</p>
						<p class="text-[13px] text-neutral-600">
							CC {paciente.identificacion}
							{#if paciente.fecnac} · {calcularEdad(paciente.fecnac)}{/if}
							{#if paciente.genero} · {paciente.genero}{/if}
							{#if paciente.entidad} · {paciente.entidad}{/if}
						</p>
					</div>
					<button
						class="inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white shadow transition hover:bg-brand-700"
						onclick={irSeleccion}
					>
						Asignar exámenes
						<Icon nombre="siguiente" tam={15} />
					</button>
				</div>
			{/if}
		</div>
	{/if}

	<!-- PASO 2: selección -->
	{#if paso === 2}
		<div class="mb-4 flex flex-wrap items-center gap-2">
			<input
				bind:value={busquedaCat}
				type="text"
				placeholder="Filtrar exámenes por nombre o código…"
				class="w-full max-w-sm rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
			/>
			<span class="text-sm font-bold text-neutral-500">
				{elegidos.length} seleccionado{elegidos.length !== 1 ? 's' : ''}
			</span>
		</div>

		{#if cargandoCatalogo}
			<Loader texto="Cargando catálogo de exámenes…" />
		{:else if catalogo.length === 0}
			<EmptyState icono="resultados" titulo="Sin exámenes en el catálogo" subtitulo="No hay procedimientos registrados." />
		{:else}
			<div class="grid gap-4 lg:grid-cols-[1fr_300px]">
				<div class="max-h-[58vh] space-y-1.5 overflow-y-auto rounded-2xl border border-neutral-200 bg-white p-2">
					{#each catalogFiltro as p (p.codigo)}
						<button
							class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition {estaElegido(p.codigo)
								? 'bg-brand/10 ring-1 ring-brand/40'
								: 'hover:bg-neutral-50'}"
							onclick={() => alternar(p)}
						>
							<span
								class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border {estaElegido(p.codigo)
									? 'border-brand bg-brand text-white'
									: 'border-neutral-300 bg-white'}"
							>
								{#if estaElegido(p.codigo)}
									<Icon nombre="check" tam={13} />
								{/if}
							</span>
							<span
								class="h-7 w-1.5 shrink-0 rounded-full"
								style={colorRgb(p.color) ? `background-color:${colorRgb(p.color)}` : 'background-color:#d4d4d4'}
							></span>
							<span class="min-w-0 flex-1">
								<span class="block truncate text-sm font-semibold text-neutral-800">{p.nombre ?? '—'}</span>
								<span class="block text-[11px] text-neutral-400">
									{p.codigo}{p.unidades ? ` · ${p.unidades}` : ''}
								</span>
							</span>
						</button>
					{/each}
				</div>

				<aside class="rounded-2xl border border-neutral-200 bg-white p-4">
					<p class="text-sm font-extrabold text-neutral-700">Seleccionados ({elegidos.length})</p>
					<div class="mt-3 max-h-[44vh] space-y-1.5 overflow-y-auto">
						{#if elegidos.length === 0}
							<p class="text-[13px] text-neutral-400">Ninguno aún. Marque exámenes en la lista.</p>
						{/if}
						{#each elegidos as e (e.codigo)}
							<div class="flex items-center gap-2 rounded-lg bg-neutral-50 px-2.5 py-1.5">
								<span class="min-w-0 flex-1 truncate text-[13px] font-semibold text-neutral-700">{e.nombre ?? e.codigo}</span>
								<button class="text-neutral-400 hover:text-red-600" onclick={() => quitar(e.codigo)} title="Quitar">
									<Icon nombre="cerrar" tam={14} />
								</button>
							</div>
						{/each}
					</div>
					<button
						class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-50"
						disabled={elegidos.length === 0}
						onclick={() => (paso = 3)}
					>
						Continuar ({elegidos.length})
						<Icon nombre="siguiente" tam={15} />
					</button>
				</aside>
			</div>
		{/if}
	{/if}

	<!-- PASO 3: confirmar -->
	{#if paso === 3}
		<div class="grid gap-4 lg:grid-cols-[1fr_280px]">
			<div class="rounded-2xl border border-neutral-200 bg-white p-4">
				<p class="mb-3 text-sm font-extrabold text-neutral-700">Resumen para {nombreCompletoPaciente(paciente ?? {})}</p>
				<div class="space-y-1.5">
					{#each elegidos as e (e.codigo)}
						<div class="flex items-center gap-3 rounded-xl bg-neutral-50 px-3 py-2">
							<span class="h-6 w-1.5 rounded-full" style={colorRgb(e.color) ? `background-color:${colorRgb(e.color)}` : 'background-color:#d4d4d4'}></span>
							<span class="min-w-0 flex-1">
								<span class="block truncate text-sm font-semibold text-neutral-800">{e.nombre ?? '—'}</span>
								<span class="text-[11px] text-neutral-400">{e.codigo}</span>
							</span>
							<button class="text-neutral-400 hover:text-red-600" onclick={() => quitar(e.codigo)} title="Quitar">
								<Icon nombre="eliminar" tam={15} />
							</button>
						</div>
					{/each}
				</div>
			</div>
			<aside class="rounded-2xl border border-neutral-200 bg-white p-4">
				<p class="text-sm font-extrabold text-neutral-700">Guardar asignación</p>
				<p class="mt-1 text-[13px] text-neutral-500">
					Se reemplazarán los exámenes de {nombreCompletoPaciente(paciente ?? {})} en la fecha seleccionada
					({fecha}) por los {elegidos.length} marcados.
				</p>
				<div class="mt-3 flex flex-col gap-2">
					<button
						class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"
						disabled={guardando || elegidos.length === 0}
						onclick={guardar}
					>
						<Icon nombre="check" tam={15} />
						{guardando ? 'Guardando…' : 'Guardar exámenes'}
					</button>
					<button
						class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-neutral-300 px-4 py-2.5 text-sm font-bold text-neutral-600 transition hover:bg-neutral-50"
						onclick={volverPaso2}
						disabled={guardando}
					>
						<Icon nombre="atras" tam={15} />
						Volver
					</button>
				</div>
			</aside>
		</div>
	{/if}
</Page>
