<?php

function analizarRetina($rutaImagen) {
    $ch = curl_init("http://18.119.14.223:8000/api/analizar");

    $file = new CURLFile(
        $rutaImagen,
        'image/jpeg',
        'retina.jpg'
    );

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['file' => $file],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);

    curl_close($ch);

    return json_decode($response, true);
}

$resultado = analizarRetina('C:/imagenes/prueba.jpg');

echo '<pre>';
print_r($resultado);
echo '</pre>';