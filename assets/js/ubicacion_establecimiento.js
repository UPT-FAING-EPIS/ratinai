(() => {
    const boton = document.getElementById('obtener-ubicacion');
    const latitud = document.getElementById('latitud');
    const longitud = document.getElementById('longitud');
    const estado = document.getElementById('estado-ubicacion');
    const botonMapa = document.getElementById('elegir-en-mapa');
    const botonBuscar = document.getElementById('buscar-en-mapa');
    const direccion = document.getElementById('direccion');
    const contenedorMapa = document.getElementById('mapa-establecimiento');
    if (!boton || !latitud || !longitud || !estado) return;

    const DECIMALES_COORDENADA = 7;
    const TIEMPO_MAXIMO_UBICACION_MS = 10000;
    const EDAD_MAXIMA_UBICACION_MS = 60000;
    const INTERVALO_MINIMO_BUSQUEDA_MS = 1500;
    const mostrarEstado = (mensaje) => { estado.textContent = mensaje; };
    let mapa = null;
    let marcador = null;
    let ultimaBusqueda = 0;

    function asignarCoordenadas(nuevaLatitud, nuevaLongitud) {
        latitud.value = nuevaLatitud.toFixed(DECIMALES_COORDENADA);
        longitud.value = nuevaLongitud.toFixed(DECIMALES_COORDENADA);
        mostrarEstado(`Ubicación confirmada: ${latitud.value}, ${longitud.value}`);
        if (mapa) {
            const posicion = [nuevaLatitud, nuevaLongitud];
            if (marcador) marcador.setLatLng(posicion);
            else marcador = L.marker(posicion).addTo(mapa);
            mapa.setView(posicion, 16);
        }
    }

    botonMapa?.addEventListener('click', () => {
        if (!window.L || !contenedorMapa) {
            mostrarEstado('No se pudo cargar el mapa. Puede usar el GPS o la dirección escrita.');
            return;
        }
        contenedorMapa.style.display = 'block';
        if (!mapa) {
            mapa = L.map(contenedorMapa).setView([0, 0], 2);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19,
            }).addTo(mapa);
            mapa.on('click', evento => asignarCoordenadas(evento.latlng.lat, evento.latlng.lng));
            if (latitud.value && longitud.value) asignarCoordenadas(Number(latitud.value), Number(longitud.value));
        }
        mapa.invalidateSize();
        mostrarEstado('Haz clic en el punto exacto del establecimiento.');
    });

    botonBuscar?.addEventListener('click', async () => {
        const consulta = direccion?.value.trim() || '';
        if (consulta.length < 5) {
            mostrarEstado('Escribe primero una dirección suficientemente detallada.');
            return;
        }
        if (Date.now() - ultimaBusqueda < INTERVALO_MINIMO_BUSQUEDA_MS) return;
        ultimaBusqueda = Date.now();
        botonBuscar.disabled = true;
        try {
            const respuesta = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(consulta), {
                headers: { Accept: 'application/json' },
            });
            if (!respuesta.ok) throw new Error('La búsqueda de direcciones no está disponible.');
            const lugares = await respuesta.json();
            if (!Array.isArray(lugares) || lugares.length === 0) throw new Error('No se encontró la dirección. Confirma el punto en el mapa o usa el GPS.');
            botonMapa.click();
            mapa.setView([Number(lugares[0].lat), Number(lugares[0].lon)], 17);
            mostrarEstado('Dirección localizada. Haz clic en el punto exacto para confirmar las coordenadas.');
        } catch (error) {
            mostrarEstado(error.message);
        } finally {
            botonBuscar.disabled = false;
        }
    });

    boton.addEventListener('click', () => {
        if (!navigator.geolocation) {
            mostrarEstado('Este navegador no permite obtener la ubicación. Puede continuar con la dirección escrita.');
            return;
        }
        boton.disabled = true;
        mostrarEstado('Solicitando ubicación…');
        navigator.geolocation.getCurrentPosition(
            (posicion) => {
                asignarCoordenadas(posicion.coords.latitude, posicion.coords.longitude);
                boton.disabled = false;
            },
            () => {
                mostrarEstado('No se obtuvo la ubicación. Puede continuar con la dirección escrita.');
                boton.disabled = false;
            },
            { enableHighAccuracy: true, timeout: TIEMPO_MAXIMO_UBICACION_MS, maximumAge: EDAD_MAXIMA_UBICACION_MS }
        );
    });
})();
