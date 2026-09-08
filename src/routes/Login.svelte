<script lang="ts">
	import { onMount } from 'svelte'
	import { cargarConfigApp, estadoConfig } from '../lib/stores/configapp.svelte'
	import { navigate } from '../lib/router.svelte'
	import { iniciarSesion } from '../lib/stores/session.svelte'
	import { guardarToken } from '../lib/stores/token.svelte'
	import { loginApi } from '../lib/api/laboratorio'
	import { probarServidor } from '../lib/core/http.svelte'
	import Icon from '../lib/ui/Icon.svelte'
	import Loader from '../lib/ui/Loader.svelte'
	import { toast } from '../lib/stores/toast.svelte'

	let clave = $state('')
	let error = $state('')
	let entrando = $state(false)

	onMount(cargarConfigApp)

	async function probar() {
		const ok = await probarServidor()
		toast(ok ? 'El servidor responde correctamente' : 'El servidor sigue sin responder', ok ? 'ok' : 'error')
		if (ok) await cargarConfigApp()
	}

	async function verificar() {
		const ingreso = clave.trim()
		if (!ingreso) {
			error = 'Ingrese la clave de acceso'
			return
		}
		entrando = true
		error = ''
		const res = await loginApi(ingreso)
		entrando = false
		if (res?.msg && res.token) {
			guardarToken(res.token)
			iniciarSesion()
			toast('Bienvenido(a)', 'ok')
			navigate('/inicio')
		} else {
			error = 'Clave incorrecta o servidor no disponible. Verifique e intente de nuevo.'
			clave = ''
		}
	}
</script>

<div
	class="relative flex min-h-dvh flex-col items-center justify-center overflow-hidden bg-neutral-100 px-6 py-12"
	style="background-image: radial-gradient(circle at 15% 20%, rgba(10,124,255,0.10), transparent 45%), radial-gradient(circle at 85% 85%, rgba(14,116,144,0.10), transparent 45%);"
>
	<img
		src="/thiings/microscope.png"
		alt=""
		aria-hidden="true"
		class="pointer-events-none absolute -left-6 top-14 hidden w-44 opacity-25 drop-shadow-lg lg:block anim-float"
	/>
	<img
		src="/thiings/stethoscope.png"
		alt=""
		aria-hidden="true"
		class="pointer-events-none absolute -right-4 bottom-16 hidden w-44 opacity-25 drop-shadow-lg lg:block"
		style="animation:animFloat 9s ease-in-out infinite reverse"
	/>
	<div class="w-full max-w-sm">
		<div class="rounded-[28px] border border-black/5 bg-white p-8 shadow-[0_20px_60px_-20px_rgba(0,0,0,0.25)] sm:p-10">
			<div class="mb-6 text-center">
				{#if estadoConfig.logoData}
					<img src={estadoConfig.logoData} alt="Logo del laboratorio" class="mx-auto mb-4 h-20 object-contain" />
				{:else}
					<div
						class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl text-white shadow-sm"
						style="background: linear-gradient(135deg,#0a7cff,#0e7490)"
					>
						<Icon nombre="lab" tam={28} />
					</div>
				{/if}
				<h1 class="text-[26px] font-bold tracking-[-0.02em] text-neutral-900">{estadoConfig.nombreLab}</h1>
				<p class="mt-1 text-sm text-neutral-500">Ingrese su clave de acceso</p>
			</div>

			{#if !estadoConfig.loaded}
				<div class="py-6"><Loader texto="Cargando configuración…" /></div>
			{:else}
				<div>
					<input
						bind:value={clave}
						type="password"
						placeholder="Clave"
						class="w-full rounded-2xl border border-black/10 bg-neutral-50 px-4 py-3 text-[15px] outline-none transition placeholder:text-neutral-400 focus:border-brand focus:bg-white focus:ring-4 focus:ring-brand/15"
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
						class="mt-4 w-full rounded-2xl bg-brand py-3 text-[15px] font-semibold text-white shadow-[0_8px_20px_-6px_rgba(10,124,255,0.6)] transition hover:bg-brand-600 active:scale-[0.99] disabled:opacity-60"
						onclick={verificar}
						disabled={entrando}
					>
						{entrando ? 'Ingresando…' : 'Ingresar'}
					</button>
					<button
						class="mt-2 w-full rounded-2xl border border-black/10 py-2.5 text-[13px] font-semibold text-neutral-600 transition hover:bg-neutral-50"
						onclick={probar}
					>
						Probar servidor
					</button>
				</div>
			{/if}
		</div>
		<p class="mt-5 text-center text-[13px]">
			<button
				class="font-semibold text-brand underline decoration-brand/30 underline-offset-4 transition hover:decoration-brand"
				onclick={() => navigate('/portal')}
			>
				¿Es paciente? Consulte sus resultados aquí
			</button>
		</p>
	</div>
</div>
