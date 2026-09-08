UPDATE examenes e
SET e.realizado = 'S'
WHERE EXISTS (
    SELECT 1 FROM examen_tipo_1 t
    WHERE t.identificacion = e.identificacion AND t.examen = e.codexamen AND t.fecha = e.fecha
)
OR EXISTS (
    SELECT 1 FROM examen_tipo_2 t
    WHERE t.identificacion = e.identificacion AND t.examen = e.codexamen AND t.fecha = e.fecha
)
OR EXISTS (
    SELECT 1 FROM examen_tipo_3 t
    WHERE t.identificacion = e.identificacion AND t.examen = e.codexamen AND t.fecha = e.fecha
)
OR EXISTS (
    SELECT 1 FROM examen_tipo_4 t
    WHERE t.identificacion = e.identificacion AND t.examen = e.codexamen AND t.fecha = e.fecha
)
OR EXISTS (
    SELECT 1 FROM examen_tipo_5 t
    WHERE t.identificacion = e.identificacion AND t.examen = e.codexamen AND t.fecha = e.fecha
)
OR EXISTS (
    SELECT 1 FROM examen_tipo_7 t
    WHERE t.identificacion = e.identificacion AND t.examen = e.codexamen AND t.fecha = e.fecha
)
OR EXISTS (
    SELECT 1 FROM perfilLipidico t
    WHERE t.identificacion = e.identificacion AND t.fecha = e.fecha
)
OR EXISTS (
    SELECT 1 FROM hemogramaRayto t
    WHERE t.identificacion = e.identificacion AND t.fecha = e.fecha
)
OR EXISTS (
    SELECT 1 FROM coprologico t
    WHERE t.identificacion = e.identificacion AND t.fecha = e.fecha
)
OR EXISTS (
    SELECT 1 FROM parcialOrina t
    WHERE t.identificacion = e.identificacion AND t.fecha = e.fecha
)
OR EXISTS (
    SELECT 1 FROM frotisVaginal t
    WHERE t.identificacion = e.identificacion AND t.fecha = e.fecha
);
