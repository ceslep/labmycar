<script lang="ts">
	import { onMount } from 'svelte'
	import { route, navigate, startRouter } from './lib/router.svelte'
	import { authed, restaurarSesion } from './lib/stores/session.svelte'
	import { cargarConfigApp } from './lib/stores/configapp.svelte'
	import ConnectionBanner from './lib/ui/ConnectionBanner.svelte'
	import Toasts from './lib/ui/Toasts.svelte'
	import ReporteVista from './lib/ui/ReporteVista.svelte'
	import Login from './routes/Login.svelte'
	import PortalPacientes from './routes/PortalPacientes.svelte'
	import Shell from './routes/Shell.svelte'
	import Inicio from './routes/Inicio.svelte'
	import Pacientes from './routes/Pacientes.svelte'
	import PacienteForm from './routes/PacienteForm.svelte'
	import PacienteExamenes from './routes/PacienteExamenes.svelte'
	import RegistroExamen from './routes/RegistroExamen.svelte'
	import CrearExamen from './routes/CrearExamen.svelte'
	import Resultados from './routes/Resultados.svelte'
	import ConfiguracionPage from './routes/Configuracion.svelte'
	import ProcedimientoForm from './routes/ProcedimientoForm.svelte'
	import Panel from './routes/Panel.svelte'
	import Admin from './routes/Admin.svelte'

	onMount(() => {
		restaurarSesion()
		startRouter()
		cargarConfigApp()
	})

	const esPortal = $derived(route.name === 'portal')
	const claveRuta = $derived(route.name + ':' + JSON.stringify(route.params))

	$effect(() => {
		if (esPortal) return
		if (!authed.value && route.name !== 'login') navigate('/login')
		else if (authed.value && route.name === 'login') navigate('/inicio')
	})
</script>

<div class="min-h-dvh">
	<ConnectionBanner />
	{#if esPortal}
		<PortalPacientes />
	{:else if !authed.value}
		<Login />
	{:else}
		<Shell>
			<div class="anim-in">
				{#key claveRuta}
				{#if route.name === 'inicio'}
					<Inicio />
				{:else if route.name === 'pacientes'}
					<Pacientes />
				{:else if route.name === 'paciente-form'}
					<PacienteForm id={route.params.id} />
				{:else if route.name === 'paciente-examenes'}
					<PacienteExamenes id={route.params.id} />
				{:else if route.name === 'registro-examen'}
					<RegistroExamen
						id={route.params.id}
						codexamen={route.params.codexamen}
						fecha={route.params.fecha}
						tipo={route.params.tipo}
					/>
				{:else if route.name === 'crear-examen'}
					<CrearExamen />
				{:else if route.name === 'resultados'}
					<Resultados />
				{:else if route.name === 'configuracion'}
					<ConfiguracionPage />
				{:else if route.name === 'procedimiento-form'}
					<ProcedimientoForm codigo={route.params.codigo} />
				{:else if route.name === 'panel'}
					<Panel />
				{:else if route.name === 'admin'}
					<Admin />
				{/if}
				{/key}
			</div>
		</Shell>
	{/if}
	<Toasts />
	<ReporteVista />
</div>
