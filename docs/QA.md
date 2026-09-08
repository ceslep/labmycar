# QA — Guía de aceptación (SPA LabMyCar)

Pruebas manuales contra el backend real (`https://mycar.iedeoccidente.com/libphp`).
Cada caso debe cumplirse para considerar migrado el flujo equivalente de la app Flutter
y del manual `manual_usuario.html`.

## 1. Acceso y sesión
- [ ] `/login` muestra "Laboratorio MyCar IPS" y pide la clave T.P.
- [ ] Clave correcta → entra a Inicio; clave incorrecta → mensaje; vacía → "Ingrese la clave".
- [ ] Con la red del servidor caída (o URL errónea) aparece el banner "Sin conexión" y en el
      login se ve el motivo técnico (URL + detalle) con "Probar servidor".
- [ ] Si aparece el banner sin motivo claro, reportar el detalle del banner (clic en el texto)
      y las líneas `[API] …` de la consola.

## 2. Pacientes
- [ ] Listado con 200+ pacientes, búsqueda por identificación/nombre/teléfono.
- [ ] "Nuevo paciente": validaciones (id ≥6, nombres ≥3, apellidos ≥5, tel ≥10 si se indica,
      correo con formato, fecha no futura).
- [ ] Guardar crea el paciente (reaparece en el listado al volver).
- [ ] Editar (lápiz) → cambia datos → guardar → listado actualizado.

## 3. Asignación de exámenes
- [ ] `/crear-examen`: buscar paciente inexistente avisa y permite registrarlo.
- [ ] Catálogo cargado (~250+), filtro por texto, preselección de exámenes ya asignados.
- [ ] Guardar reemplaza las asignaciones del paciente+fecha (DELETE+INSERT) y el paciente
      queda con sus exámenes en "Pendiente".

## 4. Registro de resultados (por tipo)
Para al menos un paciente de prueba, registrar y guardar cada tipo y verificar que pasa a "Realizado":
- [ ] Tipo 1 y Tipo 2 (valoración + observaciones, dropdown si el examen tiene opciones).
- [ ] Tipo 3 Parcial de orina (27 parámetros).
- [ ] Tipo 4 Coprológico (macroscópico, parásitos quistes/trofozoítos, helmintos, sangre oculta).
- [ ] Tipo 5 Hemograma (20 parámetros + observaciones). Validar guardado; probar botón LAN con el equipo Rayto.
- [ ] Tipo 6 Frotis vaginal · Tipo 8 Perfil lipídico.
- [ ] Reguardar el mismo examen (REPLACE) no duplica resultados visibles.

## 5. Reportes / PDF
- [ ] Imprimir un examen y "Imprimir todos" abren la **vista previa en un modal** (escritorio: visor centrado
      tipo PDF con botón Imprimir/PDF y Cerrar; móvil: pantalla completa con el resultado y botón Imprimir).
- [ ] "Imprimir / PDF" abre el diálogo del navegador (Guardar como PDF correcto); Esc o clic fuera cierran el modal.
- [ ] Comparar formato con los PDF actuales de `printphp` (A/B): valores de referencia de
      `constante2` aún pendientes de replicar si el laboratorio los usa.

## 6. Configuración y catálogo
- [ ] Guardar datos del laboratorio sin vaciar la T.P. (evita bloqueo de acceso).
- [ ] Subir/ver logo y firma; verificar que `printphp/firma.png` y `logo.png` se actualizan.
- [ ] Editar un procedimiento y guardar; eliminar uno de prueba con confirmación.
- [ ] Opciones de valoración: agregar/quitar/guardar items para un examen tipo 1/2 y
      verlas en el registro.

## 7. Panel y portal
- [ ] Panel "Por fecha": resumen del día correcto; enlace a paciente. **Consultar un rango que incluya
      días sin exámenes no debe dar error de consola (listas vacías normalizadas).**
- [ ] Panel "Buscar paciente": resultados por id/nombre (búsqueda avanzada y estadísticas).
- [ ] Resumen "Por entidad" clicable, Hoy/Ayer, "Mostrar más", exportar CSV.
- [ ] Cambiar entidad de un examen desde el desplegable (requiere `setEntidad.php` sincronizado).
- [ ] Portal `/portal` (sin login): consultar con identificación + año de nacimiento o 4
      últimos del teléfono; solo se imprime reportes "Realizado".

## 8. Bloqueo normativo de resultados emitidos
- [ ] Guardar un resultado pendiente → el examen pasa a "Realizado" y al abrirlo queda **solo lectura**
      (campos deshabilitados, candado, sin Guardar).
- [ ] Intentar re-guardar por la SPA no debe funcionar; el servidor responde "Resultado ya realizado".
- [ ] "Imprimir" de un resultado emitido funciona sin intentar guardar.

## 9. Build / despliegue
- [ ] `npm run check` → 0 errores/0 warnings.
- [ ] `npm run build` → OK.
- [ ] `npm run build:app` y subir `dist/` a `/libphp/app/`; abrir la URL pública y repetir
      las pruebas 1–8 (sin CORS, mismo origen).
- [ ] Responsive: ≥900 px barra lateral; <900 px barra inferior móvil.
