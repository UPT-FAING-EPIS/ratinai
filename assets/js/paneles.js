const PanelesRetinAI = (() => {
    function descargarCsv(nombreArchivo, columnas, filas) {
        const escapar = valor => `"${String(valor ?? '').replaceAll('"', '""')}"`;
        const contenido = [columnas.map(escapar).join(','), ...filas.map(fila => fila.map(escapar).join(','))].join('\r\n');
        const enlace = document.createElement('a');
        enlace.href = URL.createObjectURL(new Blob(['\ufeff' + contenido], {type: 'text/csv;charset=utf-8'}));
        enlace.download = nombreArchivo;
        enlace.hidden = true;
        document.body.appendChild(enlace);
        enlace.click();
        const rutaTemporal = enlace.href;
        enlace.remove();
        window.setTimeout(() => URL.revokeObjectURL(rutaTemporal), 1000);
    }

    function filtrarTabla(tabla, filtros) {
        document.querySelectorAll(`${tabla} tbody tr[data-fila]`).forEach(fila => {
            const visible = filtros.every(filtro => {
                const valor = (document.querySelector(filtro.selector)?.value || '').trim().toLocaleLowerCase('es');
                return valor === '' || (fila.dataset[filtro.campo] || '').toLocaleLowerCase('es').includes(valor);
            });
            fila.hidden = !visible;
        });
    }

    function prepararAyuda() {
        document.querySelectorAll('[data-ayuda]').forEach(boton => boton.addEventListener('click', () => {
            const ayuda = document.getElementById(boton.dataset.ayuda);
            ayuda?.classList.toggle('visible');
        }));
        document.querySelectorAll('.ayuda-flotante').forEach(ayuda => ayuda.addEventListener('click', () => ayuda.classList.remove('visible')));
    }

    document.addEventListener('DOMContentLoaded', prepararAyuda);
    return { descargarCsv, filtrarTabla };
})();
