(() => {
    const campoLatitud = document.getElementById('latitud');
    const campoLongitud = document.getElementById('longitud');
    const campoDireccion = document.getElementById('direccion');
    const mensajeUbicacion = document.getElementById('estado-ubicacion');
    const contenedorMapa = document.getElementById('mapa-establecimiento');
    const botonMapa = document.getElementById('elegir-en-mapa');
    const botonBuscar = document.getElementById('buscar-en-mapa');
    const botonUbicacion = document.getElementById('obtener-ubicacion');

    if (!campoLatitud || !campoLongitud || !campoDireccion || !mensajeUbicacion || !contenedorMapa) return;

    const DECIMALES_COORDENADA = 7;
    const ZOOM_INICIAL = 5;
    const ZOOM_UBICACION = 17;
    const TIEMPO_MAXIMO_UBICACION_MS = 10000;
    const EDAD_MAXIMA_UBICACION_MS = 60000;
    const INTERVALO_MINIMO_BUSQUEDA_MS = 1500;
    const CENTRO_PERU = [-9.2, -75];
    let mapa = null;
    let marcador = null;
    let ultimaBusqueda = 0;

    function mostrarEstado(mensaje) {
        mensajeUbicacion.textContent = mensaje;
    }

    /** Guarda un punto elegido de manera explícita y lo muestra en el mapa. */
    function asignarCoordenadas(nuevaLatitud, nuevaLongitud, mensaje = 'Ubicación marcada. Puedes mover el pin para ajustarla.') {
        if (!Number.isFinite(nuevaLatitud) || !Number.isFinite(nuevaLongitud)) return;

        campoLatitud.value = nuevaLatitud.toFixed(DECIMALES_COORDENADA);
        campoLongitud.value = nuevaLongitud.toFixed(DECIMALES_COORDENADA);
        mostrarEstado(mensaje);

        if (!mapa) return;
        const posicion = [nuevaLatitud, nuevaLongitud];
        if (marcador) {
            marcador.setLatLng(posicion);
        } else {
            marcador = L.marker(posicion, { draggable: true }).addTo(mapa);
            marcador.on('dragend', () => {
                const punto = marcador.getLatLng();
                asignarCoordenadas(punto.lat, punto.lng);
            });
        }
        mapa.setView(posicion, ZOOM_UBICACION);
    }

    /** Mantiene el mapa visible al abrir el formulario público; en administración se abre con el botón existente. */
    function inicializarMapa() {
        if (mapa) {
            mapa.invalidateSize();
            return true;
        }
        if (!window.L) {
            mostrarEstado('No se pudo cargar el mapa. La dirección escrita permite continuar.');
            contenedorMapa.textContent = 'Mapa no disponible en este momento.';
            return false;
        }

        mapa = L.map(contenedorMapa).setView(CENTRO_PERU, ZOOM_INICIAL);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(mapa);
        mapa.on('click', evento => asignarCoordenadas(evento.latlng.lat, evento.latlng.lng));

        if (campoLatitud.value && campoLongitud.value) {
            asignarCoordenadas(Number(campoLatitud.value), Number(campoLongitud.value));
        }
        setTimeout(() => mapa.invalidateSize(), 0);
        return true;
    }

    if (botonMapa) {
        botonMapa.addEventListener('click', () => {
            contenedorMapa.style.display = 'block';
            inicializarMapa();
        });
    } else {
        inicializarMapa();
    }

    botonBuscar?.addEventListener('click', async () => {
        const consulta = campoDireccion.value.trim();
        if (consulta.length < 5) {
            mostrarEstado('Escribe primero una dirección detallada en los datos del centro.');
            campoDireccion.focus();
            return;
        }
        if (Date.now() - ultimaBusqueda < INTERVALO_MINIMO_BUSQUEDA_MS) return;
        ultimaBusqueda = Date.now();
        botonBuscar.disabled = true;
        mostrarEstado('Buscando la dirección…');

        try {
            const respuesta = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(consulta), {
                headers: { Accept: 'application/json' },
            });
            if (!respuesta.ok) throw new Error('La búsqueda de direcciones no está disponible.');

            const lugares = await respuesta.json();
            if (!Array.isArray(lugares) || lugares.length === 0) {
                throw new Error('No se encontró esa dirección. Puedes marcar el punto directamente en el mapa.');
            }

            if (botonMapa) contenedorMapa.style.display = 'block';
            if (!inicializarMapa()) return;
            asignarCoordenadas(Number(lugares[0].lat), Number(lugares[0].lon), 'Dirección localizada. Ajusta el pin sobre la entrada del establecimiento si hace falta.');
        } catch (error) {
            mostrarEstado(error.message);
        } finally {
            botonBuscar.disabled = false;
        }
    });

    campoDireccion.addEventListener('keydown', evento => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            botonBuscar?.click();
        }
    });

    campoDireccion.addEventListener('input', () => {
        if (!campoLatitud.value && !campoLongitud.value) return;
        campoLatitud.value = '';
        campoLongitud.value = '';
        if (marcador) {
            marcador.remove();
            marcador = null;
        }
        mostrarEstado('La dirección cambió. Busca de nuevo o marca el punto en el mapa.');
    });

    botonUbicacion?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            mostrarEstado('El navegador no permite acceder a tu ubicación. Puedes marcarla en el mapa.');
            return;
        }
        botonUbicacion.disabled = true;
        mostrarEstado('Solicitando permiso de ubicación…');
        navigator.geolocation.getCurrentPosition(
            posicion => {
                if (botonMapa) contenedorMapa.style.display = 'block';
                inicializarMapa();
                asignarCoordenadas(posicion.coords.latitude, posicion.coords.longitude, 'Ubicación del dispositivo marcada. Verifica que coincida con el centro.');
                botonUbicacion.disabled = false;
            },
            () => {
                mostrarEstado('No se obtuvo tu ubicación. Puedes buscar la dirección o marcar el punto en el mapa.');
                botonUbicacion.disabled = false;
            },
            { enableHighAccuracy: true, timeout: TIEMPO_MAXIMO_UBICACION_MS, maximumAge: EDAD_MAXIMA_UBICACION_MS }
        );
    });
})();
