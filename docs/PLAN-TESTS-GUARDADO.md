# Tests de guardado de exámenes

> Contrato de comportamiento del guardado de exámenes + correcciones aplicadas
> tras la auditoría (ver `auditoria-guardado-examenes.md`).

## Ejecutar

```bash
npm test
```

Usa el runner nativo de Node (`node --test`) sobre `tests/*.test.ts`
(Node ≥ 23 con type-stripping; el repo ya usa Node 26). No requiere BD ni
servidor: son pruebas de lógica pura y de contrato del algoritmo.

## Archivos de prueba

- `tests/guardado.test.ts` — lógica pura del cliente (`src/lib/api/guardado.ts`):
  - el payload de `guardarExamenes()` incluye **todos** los exámenes con su
    `codigo` (ninguno se pierde);
  - normalización de `msg` (true/"si"/ausente = OK; false/"no" = error);
  - descarta entradas sin código sin perder las demás;
  - `codigosUnicos()` mantiene el orden y elimina duplicados.
- `tests/guardarExamenes.sim.test.ts` — simulación del algoritmo corregido de
  `libphp/guardarExamenes.php`:
  - guarda todos los seleccionados (3 de 3);
  - re-guardar la misma selección **no duplica**;
  - un examen ya `realizado='S'` se conserva y no se duplica al reasignar;
  - deseleccionar un pendiente lo elimina; un realizado permanece aunque no se
    marque.

## Cobertura real del backend

El contrato del simulador se cumple en el endpoint corregido
`libphp/guardarExamenes.php` (transacción, INSERT solo si no existe fila,
conserva `'S'`, limpia pendientes deseleccionados). Para validarlo contra el
servidor real con datos de prueba es necesario sincronizar `libphp/` por
WinSCP (ver `parches-libphp.md`). La verificación manual guiada se describe en
la auditoría.
