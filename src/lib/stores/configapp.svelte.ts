import { getConfiguracionPublica } from '../api/laboratorio'
import type { Configuracion } from '../models/models'

export interface EstadoConfigApp {
	loaded: boolean
	logo: string
	logoData: string
	nombreLab: string
	nombreCorto: string
	clave: string
}

function aDataUrl(b64: string): string {
	return b64 ? (b64.startsWith('data:') ? b64 : `data:image/png;base64,${b64}`) : ''
}

function aplicar(c: Configuracion | null): void {
	estadoConfig.logo = c?.urlLogoLaboratorio ?? ''
	estadoConfig.logoData = aDataUrl(c?.urlLogoLaboratorio ?? '')
	estadoConfig.nombreLab = c?.nombreLaboratorio?.trim() || 'Laboratorio'
	estadoConfig.nombreCorto = c?.nombreCorto?.trim() || ''
	estadoConfig.clave = c?.tarjetaPLaboratorio ?? ''
	estadoConfig.loaded = true
}

export const estadoConfig = $state<EstadoConfigApp>({
	loaded: false,
	logo: '',
	logoData: '',
	nombreLab: 'Laboratorio',
	nombreCorto: '',
	clave: ''
})

export async function cargarConfigApp(): Promise<void> {
	if (estadoConfig.loaded) return
	aplicar(await getConfiguracionPublica())
}

export function aplicarConfigApp(c: Configuracion | null): void {
	aplicar(c)
}
