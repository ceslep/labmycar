<script lang="ts">
	import { onMount } from 'svelte'
	import { getEntidades, getInfoPaciente, guardarPaciente } from '../lib/api/laboratorio'
	import type { Paciente } from '../lib/models/models'
	import { navigate } from '../lib/router.svelte'
	import { toast } from '../lib/stores/toast.svelte'
	import { hoyISO } from '../lib/utils/format'
	import Field from '../lib/ui/Field.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import Page from '../lib/ui/Page.svelte'

	let { id }: { id?: string } = $props()

	const editando = $derived(!!id)
	const GENEROS = ['Masculino', 'Femenino']

	let cargando = $state(true)
	let guardando = $state(false)
	let error = $state('')
	let entidades = $state<string[]>([])

	let f = $state({
		identificacion: '',
		nombres: '',
		apellidos: '',
		fecnac: '',
		genero: '',
		telefono: '',
		correo: '',
		entidad: ''
	})

	function rellenar(p: Paciente) {
		f.identificacion = p.identificacion ?? ''
		f.nombres = p.nombres ?? p.nombre1 ?? ''
		f.apellidos = p.apellidos ?? p.apellido1 ?? ''
		f.fecnac = (p.fecnac ?? '').slice(0, 10)
		f.genero = p.genero ?? ''
		f.telefono = p.telefono ?? ''
		f.correo = p.correo ?? ''
		f.entidad = p.entidad ?? ''
	}

	async function cargar() {
		if (!id) {
			cargando = false
			return
		}
		const p = await getInfoPaciente(id)
		if (p) rellenar(p)
		else toast('Paciente no encontrado', 'error')
		cargando = false
	}

	onMount(async () => {
		entidades = await getEntidades()
		await cargar()
	})

	function validar(): string {
		if (f.identificacion.trim().length < 6) return 'La identificación debe tener al menos 6 caracteres.'
		if (f.nombres.trim().length < 3) return 'Los nombres deben tener al menos 3 caracteres.'
		if (f.apellidos.trim().length < 5) return 'Los apellidos deben tener al menos 5 caracteres.'
		if (!f.fecnac) return 'Debe seleccionar la fecha de nacimiento.'
		if (f.fecnac > hoyISO()) return 'La fecha de nacimiento no puede ser futura.'
		if (!f.genero) return 'Debe seleccionar el género.'
		const tel = f.telefono.replace(/\D/g, '')
		if (f.telefono.trim() !== '' && tel.length < 10) return 'El teléfono debe tener al menos 10 dígitos.'
		if (f.correo.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.correo.trim()))
			return 'El correo electrónico no es válido.'
		return ''
	}

	async function guardar() {
		const v = validar()
		if (v) {
			error = v
			window.scrollTo({ top: 0 })
			return
		}
		error = ''
		guardando = true
		const datos: Paciente = {
			identificacion: f.identificacion.trim(),
			nombres: f.nombres.trim(),
			apellidos: f.apellidos.trim(),
			fecnac: f.fecnac,
			genero: f.genero,
			telefono: f.telefono.trim(),
			correo: f.correo.trim(),
			entidad: f.entidad.trim()
		}
		const ok = await guardarPaciente(datos)
		guardando = false
		if (ok) {
			toast(editando ? 'Paciente actualizado correctamente' : 'Paciente registrado correctamente')
			navigate('/pacientes')
		} else {
			error = 'No se pudo guardar el paciente. Verifique la conexión con el servidor.'
		}
	}
</script>

<Page
	thing="patient"
	titulo={editando ? 'Editar paciente' : 'Nuevo paciente'}
	subtitulo={editando && f.identificacion ? `Identificación: ${f.identificacion}` : 'Registre los datos del paciente'}
>
	{#snippet actions()}
		<button
			class="inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"
			onclick={guardar}
			disabled={guardando || cargando}
		>
			<Icon nombre="check" tam={16} />
			{guardando ? 'Guardando…' : 'Guardar'}
		</button>
	{/snippet}

	{#if cargando}
		<Loader texto="Cargando datos del paciente…" />
	{:else}
		{#if error}
			<div class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
				<Icon nombre="alerta" tam={16} clase="mt-0.5 shrink-0" />
				{error}
			</div>
		{/if}

		<div class="space-y-4 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
			<div class="grid gap-4 sm:grid-cols-2">
				<Field label="Identificación *" bind:value={f.identificacion} placeholder="N.º de documento" disabled={editando} />
				<Field label="Nombres *" bind:value={f.nombres} placeholder="Nombres completos" />
			</div>

			<div class="grid gap-4 sm:grid-cols-2">
				<Field label="Apellidos *" bind:value={f.apellidos} placeholder="Apellidos completos" />
				<Field label="Teléfono" bind:value={f.telefono} tipo="tel" placeholder="10 dígitos" />
			</div>

			<div class="grid gap-4 sm:grid-cols-2">
				<label class="block">
					<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">Fecha de nacimiento *</span>
					<input
						type="date"
						bind:value={f.fecnac}
						max={hoyISO()}
						class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] text-neutral-900 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
					/>
				</label>
				<label class="block">
					<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">Género *</span>
					<select
						bind:value={f.genero}
						class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] text-neutral-900 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
					>
						<option value="">Seleccione…</option>
						{#each GENEROS as g (g)}
							<option value={g}>{g}</option>
						{/each}
					</select>
				</label>
			</div>

			<div class="grid gap-4 sm:grid-cols-2">
				<Field label="Correo electrónico" bind:value={f.correo} tipo="email" placeholder="correo@ejemplo.com" />
				<label class="block">
					<span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-neutral-500">Entidad</span>
					<input
						list="lista-entidades"
						bind:value={f.entidad}
						placeholder="EPS / entidad"
						class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-[15px] text-neutral-900 outline-none transition placeholder:text-neutral-400 focus:border-brand focus:ring-2 focus:ring-brand/20"
					/>
					<datalist id="lista-entidades">
						{#each entidades as e (e)}
							<option value={e}>{e}</option>
						{/each}
					</datalist>
				</label>
			</div>
		</div>
	{/if}
</Page>
