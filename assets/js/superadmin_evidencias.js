document.addEventListener('DOMContentLoaded', () => {
    const abrir = document.getElementById('evidencia-abrir');
    const imagen = document.getElementById('evidencia-imagen');
    const pdf = document.getElementById('evidencia-pdf');
    const botones = document.querySelectorAll('[data-evidencia]');

    if (!abrir || !imagen || !pdf || botones.length === 0) return;

    let urlActual = null;

    const reemplazarConArchivoTemporal = () => {
        const elementoActivo = pdf.style.display === 'block' ? pdf : imagen;
        const fuente = elementoActivo.getAttribute('src');
        if (!fuente || fuente.startsWith('blob:')) return;

        fetch(fuente)
            .then(respuesta => respuesta.blob())
            .then(archivo => {
                if (urlActual) URL.revokeObjectURL(urlActual);
                urlActual = URL.createObjectURL(archivo);
                elementoActivo.src = urlActual;
                abrir.href = urlActual;
            })
            .catch(() => {
                // El visor conserva el origen inicial si el navegador no permite convertirlo.
            });
    };

    botones.forEach(boton => boton.addEventListener('click', () => {
        window.setTimeout(reemplazarConArchivoTemporal, 0);
    }));

    window.setTimeout(reemplazarConArchivoTemporal, 0);
    window.addEventListener('beforeunload', () => {
        if (urlActual) URL.revokeObjectURL(urlActual);
    });
});
