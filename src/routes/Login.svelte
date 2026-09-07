<script lang="ts">
	import { onMount } from 'svelte'
	import { getConfiguracion } from '../lib/api/laboratorio'
	import { navigate } from '../lib/router.svelte'
	import { iniciarSesion } from '../lib/stores/session.svelte'
	import { serverDown, probarServidor } from '../lib/core/http.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import { toast } from '../lib/stores/toast.svelte'

	let cargando = $state(true)
	let errorCarga = $state('')
	let clave = $state('')
	let error = $state('')
	let claveCorrecta = $state('')
	let nombreLab = $state('Laboratorio')

	async function cargarConfig() {
		cargando = true
		errorCarga = ''
		try {
			const cfg = await getConfiguracion()
			claveCorrecta = cfg?.tarjetaPLaboratorio ?? ''
			nombreLab = cfg?.nombreLaboratorio?.trim() || 'Laboratorio'
		} catch (e) {
			errorCarga = e instanceof Error ? e.message : String(e)
		} finally {
			cargando = false
		}
	}

	onMount(cargarConfig)

	async function probar() {
		const ok = await probarServidor()
		toast(ok ? 'El servidor responde correctamente' : 'El servidor sigue sin responder', ok ? 'ok' : 'error')
		if (ok) await cargarConfig()
	}

	function verificar() {
		const ingreso = clave.trim()
		if (!ingreso) {
			error = 'Ingrese la clave de acceso'
			return
		}
		if (ingreso === claveCorrecta) {
			iniciarSesion()
			toast('Bienvenido(a)', 'ok')
			navigate('/inicio')
		} else {
			error = 'Clave incorrecta'
			clave = ''
		}
	}
</script>

<div
	class="flex min-h-dvh items-center justify-center px-6 py-10"
	style="background: linear-gradient(155deg, #161569 0%, #1a1a80 45%, #0e7490 100%)"
>
	<div class="w-full max-w-[400px]">
		<div class="rounded-3xl bg-white p-8 shadow-2xl">
			<div
				class="mx-auto flex h-[60px] w-[60px] items-center justify-center rounded-2xl text-white shadow-lg"
				style="background: linear-gradient(135deg, #161569, #0e7490)"
			>
				<Icon nombre="lab" tam={30} />
			</div>
			<h1 class="mt-5 text-center text-xl font-extrabold tracking-tight text-neutral-900">{nombreLab}</h1>
			<p class="mt-1 text-center text-[13px] text-neutral-500">Ingrese su clave de acceso</p>

			{#if cargando}
				<div class="py-8"><Loader texto="Cargando configuración…" /></div>
			{:else if errorCarga}
				<div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4">
					<p class="flex items-center gap-2 text-sm font-bold text-red-700">
						<Icon nombre="alerta" tam={16} />
						No se pudo cargar la configuración del laboratorio
					</p>
					<p class="mt-2 text-[13px] text-red-600">{errorCarga}</p>
					{#if serverDown.url}
						<p class="mt-1 break-all font-mono text-[11px] text-red-500">
							{serverDown.url}
							{#if serverDown.detalle} — {serverDown.detalle}{/if}
						</p>
					{/if}
					<div class="mt-3 flex gap-2">
						<button
							class="flex-1 rounded-lg bg-red-600 px-3 py-2 text-xs font-bold uppercase tracking-wide text-white transition hover:bg-red-700"
							onclick={() => cargarConfig()}
						>
							Reintentar
						</button>
						<button
							class="flex-1 rounded-lg border border-red-300 px-3 py-2 text-xs font-bold uppercase tracking-wide text-red-700 transition hover:bg-red-100"
							onclick={probar}
						>
							Probar servidor
						</button>
					</div>
				</div>
			{:else}
				<div class="mt-6">
					<input
						bind:value={clave}
						type="password"
						placeholder="Clave"
						class="w-full rounded-xl border border-neutral-300 bg-neutral-50 px-4 py-3 text-[15px] outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20"
						onkeydown={(e) => {
							if (e.key === 'Enter') verificar()
						}}
					/>
					{#if error}
						<p class="mt-2 flex items-center gap-1.5 text-[13px] font-semibold text-red-600">
							<Icon nombre="alerta" tam={14} />
							{error}
						</p>
					{/if}
					<button
						class="mt-4 w-full rounded-xl py-3 text-[15px] font-bold text-white shadow-lg transition hover:brightness-110 active:scale-[0.99]"
						style="background: linear-gradient(135deg, #161569, #0e7490)"
						onclick={verificar}
					>
						Ingresar
					</button>
				</div>
			{/if}
		</div>
		<p class="mt-4 text-center text-[13px]">
			<button
				class="font-semibold text-white/85 underline decoration-white/50 underline-offset-2 transition hover:text-white"
				onclick={() => navigate('/portal')}
			>
				¿Es paciente? Consulte sus resultados aquí
			</button>
		</p>
	</div>
</div>
