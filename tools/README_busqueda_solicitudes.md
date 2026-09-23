# Busqueda rapida de solicitudes por escuelas

Este flujo automatiza la consulta del respaldo SQL diario contra una lista de escuelas objetivo.

## Archivos

- `tools/buscar_solicitudes_escuelas.py`: script principal.
- `tools/escuelas_busqueda.csv`: lista editable de escuelas, CCT, municipio, localidad y alias.

## Uso recomendado

Ejecutar desde la raiz del proyecto:

```bash
/Users/erikcarrillo/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/bin/python3 tools/buscar_solicitudes_escuelas.py \
  --sql "/Users/erikcarrillo/Downloads/segeydat_solicitudes (5).sql" \
  --escuelas tools/escuelas_busqueda.csv \
  --salida reporte_solicitudes_escuelas
```

El script genera:

- `reporte_solicitudes_escuelas.csv`
- `reporte_solicitudes_escuelas.md`
- `reporte_solicitudes_escuelas.docx`

## Los 6 pasos que automatiza

1. Importa el SQL descargado en una base temporal de MariaDB/MySQL.
2. Lee la lista de escuelas desde `tools/escuelas_busqueda.csv`.
3. Cruza solicitudes por CCT exacto, CCT mencionado, nombre de escuela, alias, municipio y localidad.
4. Anexa comentarios de seguimiento y tareas asociadas.
5. Genera CSV, Markdown y Word con resumen por estatus.
6. Elimina la base temporal.

## Como editar busquedas

Modificar `tools/escuelas_busqueda.csv`.

Columnas:

- `cct`: CCT objetivo. Es la coincidencia mas fuerte.
- `nombre`: nombre oficial o principal.
- `municipio`: municipio esperado.
- `localidad`: localidad esperada.
- `alias`: variantes separadas por punto y coma.

Ejemplo:

```csv
cct,nombre,municipio,localidad,alias
31EES0024S,CARLOS MARX,PROGRESO,PROGRESO,"SECUNDARIA GENERAL CARLOS MARX;SECUNDARIA 3;SECUNDARIA NO.3"
```

## Notas de precision

- Si aparece el CCT exacto, se toma como coincidencia.
- Si el CCT aparece en descripcion, comentarios o tareas, se toma como coincidencia.
- Si solo aparece el nombre, el script exige que la localidad o municipio aparezca cerca del nombre para evitar homonimos.
- Municipio/localidad por si solos no cuentan como coincidencia.

