<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f7f9fc">
    <meta name="description" content="RetinAI acompaña la evaluación de retinografías con inteligencia artificial, historial de pacientes e informes. Diseñado para profesionales de oftalmología.">
    <title>RetinAI — Una nueva mirada a la salud visual</title>
    <link rel="icon" type="image/png" href="assets/images/logo_retinai_fondo_color.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
    <script src="assets/js/index.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <svg class="svg-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <symbol id="icon-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
        <symbol id="icon-arrow-up" viewBox="0 0 24 24"><path d="M6 18 18 6M6 6h12v12"/></symbol>
        <symbol id="icon-eye" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="icon-document" viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></symbol>
        <symbol id="icon-history" viewBox="0 0 24 24"><path d="M3 11a9 9 0 1 1 2.7 7.4M3 4v7h7M12 7v5l3 2"/></symbol>
        <symbol id="icon-shield" viewBox="0 0 24 24"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></symbol>
        <symbol id="brand-eye" viewBox="0 0 48 32"><path d="M2 16S10 3 24 3s22 13 22 13-8 13-22 13S2 16 2 16Z" fill="none" stroke="currentColor" stroke-width="2.7"/><circle cx="24" cy="16" r="10" fill="none" stroke="currentColor" stroke-width="2.7"/><circle cx="24" cy="16" r="4" fill="currentColor"/></symbol>
    </svg>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="./" aria-label="RetinAI — Inicio"><svg class="brand-symbol" aria-hidden="true"><use href="#brand-eye"/></svg><span>Retin<span class="brand-ai">AI</span></span></a>
            <button class="menu-toggle" type="button" aria-controls="site-navigation" aria-expanded="false" aria-label="Abrir menú"><span></span><span></span></button>
            <nav class="site-navigation" id="site-navigation" aria-label="Navegación principal">
                <a href="#plataforma">La plataforma</a><a href="#captura">La retinografía</a><a href="#preguntas">Preguntas frecuentes</a>
                <a class="nav-access" href="views/auth/login.php" data-enter>Ingresar <svg class="icon" aria-hidden="true"><use href="#icon-arrow-up"/></svg></a>
            </nav>
        </div>
    </header>
    <main id="contenido">
        <section class="hero" aria-labelledby="hero-title">
            <div class="container hero-grid">
                <div class="hero-copy">
                    <p class="eyebrow"><span class="status-dot"></span> INTELIGENCIA ARTIFICIAL · OFTALMOLOGÍA</p>
                    <h1 id="hero-title">Una nueva<br>mirada a la<br><span>salud visual.</span></h1>
                    <p class="hero-description">Tu experiencia clínica, acompañada de inteligencia artificial. Analiza retinografías y reúne cada resultado en un mismo lugar.</p>
                    <div class="hero-actions">
                        <a class="button button-primary" href="views/auth/login.php" data-enter>Ingresar a la plataforma <svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></a>
                        <a class="button button-secondary" href="views/auth/solicitud_registro.php" data-enter>Registrar centro oftalmológico <svg class="icon" aria-hidden="true"><use href="#icon-arrow-up"/></svg></a>
                    </div>
                    <a class="text-link hero-discover" href="#plataforma">Descubrir RetinAI <span aria-hidden="true">↘</span></a>
                    <div class="hero-note"><svg class="icon" aria-hidden="true"><use href="#icon-shield"/></svg><span>La decisión final siempre es del médico.</span></div>
                </div>
                <div class="eye-scene" aria-label="Ilustración interactiva de un ojo robótico azul">
                    <div class="scene-grid" aria-hidden="true"></div>
                    <span class="scene-coordinate coordinate-top" aria-hidden="true">VISIÓN + INTELIGENCIA</span>
                    <span class="scene-cross cross-one" aria-hidden="true">+</span><span class="scene-cross cross-two" aria-hidden="true">+</span>
                    <svg class="robot-eye" viewBox="0 0 600 560" fill="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="shell" x1="300" y1="160" x2="300" y2="397" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset=".38" stop-color="#f7fbff"/><stop offset=".7" stop-color="#dfe8f4"/><stop offset="1" stop-color="#a7b9d4"/></linearGradient>
                            <linearGradient id="rim" x1="130" y1="160" x2="410" y2="382" gradientUnits="userSpaceOnUse"><stop stop-color="#d9e8f8"/><stop offset=".45" stop-color="#fff"/><stop offset="1" stop-color="#829bbb"/></linearGradient>
                            <radialGradient id="iris"><stop offset=".36" stop-color="#10223f"/><stop offset=".47" stop-color="#3aa9ff"/><stop offset=".57" stop-color="#103977"/><stop offset=".76" stop-color="#287eed"/><stop offset=".93" stop-color="#142d56"/><stop offset="1" stop-color="#060e1e"/></radialGradient>
                            <radialGradient id="pupil"><stop stop-color="#07111d"/><stop offset=".8" stop-color="#010712"/><stop offset="1" stop-color="#0d2848"/></radialGradient>
                            <radialGradient id="eye-halo"><stop stop-color="#5d9bfa" stop-opacity=".18"/><stop offset="1" stop-color="#5d9bfa" stop-opacity="0"/></radialGradient>
                            <linearGradient id="glint" x1="240" y1="192" x2="325" y2="260" gradientUnits="userSpaceOnUse"><stop stop-color="white" stop-opacity=".85"/><stop offset="1" stop-color="white" stop-opacity="0"/></linearGradient>
                            <filter id="shell-shadow" x="-30%" y="-70%" width="160%" height="250%"><feDropShadow dx="0" dy="22" stdDeviation="19" flood-color="#163c77" flood-opacity=".16"/></filter>
                            <clipPath id="eye-window"><path d="M88 280C149 197 214 176 300 176s151 21 212 104c-61 83-126 104-212 104S149 363 88 280Z"/></clipPath>
                        </defs>
                        <circle cx="300" cy="280" r="262" fill="url(#eye-halo)"/>
                        <g class="orbital-lines" stroke="#cad9ec"><circle cx="300" cy="280" r="223" stroke-dasharray="2 10"/><circle cx="300" cy="280" r="199" stroke-dasharray="105 240 20 110"/><path d="M300 44v23M300 493v23M64 280h22M514 280h22"/></g>
                        <g class="orbit-accent" stroke="#2563eb" stroke-width="2"><path d="M300 57a223 223 0 0 1 174 83"/><circle cx="474" cy="140" r="4" fill="#2563eb" stroke="none"/></g>
                        <g filter="url(#shell-shadow)">
                            <path d="M57 280c66-97 142-136 243-136s177 39 243 136c-66 97-142 136-243 136S123 377 57 280Z" fill="url(#shell)" stroke="url(#rim)" stroke-width="2"/>
                            <path d="M73 280c62-90 134-120 227-120s165 30 227 120c-62 90-134 120-227 120S135 370 73 280Z" stroke="#fff" stroke-opacity=".85" stroke-width="2"/>
                            <path d="M88 280C149 197 214 176 300 176s151 21 212 104c-61 83-126 104-212 104S149 363 88 280Z" fill="#071321" stroke="#b7c8df" stroke-width="3"/>
                            <g clip-path="url(#eye-window)">
                                <path d="M80 280h440M300 170v220" stroke="#234266" stroke-opacity=".7"/>
                                <g id="eye-gaze">
                                    <circle cx="300" cy="280" r="126" fill="#101d30" stroke="#7690b1" stroke-width="1.5"/>
                                    <circle cx="300" cy="280" r="116" stroke="#263d59" stroke-width="9"/>
                                    <circle cx="300" cy="280" r="113" stroke="#6383aa" stroke-width="2" stroke-dasharray="1 8"/>
                                    <circle cx="300" cy="280" r="104" fill="url(#iris)" stroke="#3982d8" stroke-width="2"/>
                                    <g stroke="#7fc9ff" stroke-width="1.4"><path d="M300 215v-25" transform="rotate(0 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(5 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(10 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(15 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(20 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(25 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(30 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(35 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(40 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(45 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(50 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(55 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(60 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(65 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(70 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(75 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(80 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(85 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(90 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(95 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(100 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(105 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(110 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(115 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(120 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(125 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(130 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(135 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(140 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(145 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(150 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(155 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(160 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(165 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(170 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(175 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(180 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(185 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(190 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(195 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(200 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(205 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(210 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(215 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(220 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(225 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(230 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(235 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(240 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(245 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(250 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(255 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(260 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(265 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(270 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(275 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(280 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(285 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(290 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(295 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(300 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(305 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(310 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(315 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(320 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(325 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(330 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(335 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(340 300 280)" opacity="0.35"/><path d="M300 215v-25" transform="rotate(345 300 280)" opacity="0.7"/><path d="M300 215v-18" transform="rotate(350 300 280)" opacity="0.35"/><path d="M300 215v-18" transform="rotate(355 300 280)" opacity="0.35"/></g>
                                    <circle cx="300" cy="280" r="65" stroke="#a0e1ff" stroke-opacity=".8"/><circle cx="300" cy="280" r="60" stroke="#1e70c9" stroke-width="4"/>
                                    <circle class="eye-pupil" cx="300" cy="280" r="53" fill="url(#pupil)" stroke="#8fddff" stroke-opacity=".5"/>
                                    <path d="M242 225c14-15 39-20 58-15" stroke="url(#glint)" stroke-width="15" stroke-linecap="round"/>
                                    <ellipse cx="273" cy="253" rx="15" ry="9" transform="rotate(-30 273 253)" fill="white" opacity=".82"/><circle cx="330" cy="306" r="5" fill="#a4d9ff" opacity=".65"/>
                                </g>
                            </g>
                            <path d="m92 268 13 12-13 12M508 268l-13 12 13 12" stroke="#6985aa" stroke-width="2"/>
                        </g>
                        <g stroke="#9db6d7"><path d="M411 107h69l22-22M151 441h-49l-20 20"/></g><circle cx="411" cy="107" r="3" fill="#2563eb"/><circle cx="151" cy="441" r="3" fill="#2563eb"/>
                    </svg>
                    <div class="eye-caption"><span class="status-dot"></span><span>Una visión complementaria.<br><strong>El mismo compromiso con tus pacientes.</strong></span></div>
                    <span class="scene-coordinate coordinate-bottom" aria-hidden="true">RETINAI / VISIÓN ASISTIDA</span>
                </div>
            </div>
            <div class="container hero-bottom"><span>Diseñado para profesionales de oftalmología</span><div class="hero-capabilities"><span>Análisis referencial</span><span>Historial organizado</span><span>Informes PDF</span></div><a href="#plataforma" class="scroll-cue" aria-label="Explorar la plataforma">↓</a></div>
        </section>

        <section class="platform-section section" id="plataforma" aria-labelledby="platform-title">
            <div class="container">
                <div class="section-heading reveal"><div><p class="eyebrow">01 / LA PLATAFORMA</p><h2 id="platform-title">De una imagen<br>a una visión más completa.</h2></div><p>Un espacio para analizar, consultar y documentar. Con la información a mano y tu criterio en el centro.</p></div>
                <div class="platform-showcase reveal">
                    <div class="showcase-sidebar">
                        <span class="showcase-brand">Retin<span>AI</span><span class="demo-tag">FLUJO DEL SISTEMA</span></span>
                        <div class="demo-tabs" role="tablist" aria-label="Explorar funciones de RetinAI" aria-orientation="vertical">
                            <button id="tab-analysis" class="demo-tab is-active" type="button" role="tab" aria-selected="true" aria-controls="demo-analysis" data-demo="analysis"><span class="tab-number">01</span><span><strong>Analiza</strong><small>Una imagen. Información útil.</small></span><svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></button>
                            <button id="tab-history" class="demo-tab" type="button" role="tab" aria-selected="false" aria-controls="demo-history" tabindex="-1" data-demo="history"><span class="tab-number">02</span><span><strong>Consulta</strong><small>Cada control, en su lugar.</small></span><svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></button>
                            <button id="tab-report" class="demo-tab" type="button" role="tab" aria-selected="false" aria-controls="demo-report" tabindex="-1" data-demo="report"><span class="tab-number">03</span><span><strong>Documenta</strong><small>Tu valoración, por escrito.</small></span><svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></button>
                        </div>
                        <p class="showcase-note"><svg class="icon" aria-hidden="true"><use href="#icon-shield"/></svg>El resultado de IA acompaña la evaluación del especialista.</p>
                    </div>
                    <div class="demo-workspace">
                        <div class="workspace-top"><span><span class="workspace-dot"></span> Espacio del médico</span><span class="workspace-avatar" aria-hidden="true">DR</span></div>
                        <div id="demo-analysis" class="demo-panel" role="tabpanel" aria-labelledby="tab-analysis" tabindex="0">
                            <div class="demo-heading"><div><span class="micro-label">ANÁLISIS RETINAL</span><h3>Una imagen, otra perspectiva.</h3></div><span class="example-badge">Proceso</span></div>
                            <div class="analysis-preview">
                                <figure class="retina-preview"><img src="assets/images/retinopatia_normal.jpg" alt="Ejemplo de fotografía de fondo de ojo" width="512" height="512" loading="lazy"><figcaption>Retinografía de ejemplo<span>JPG</span></figcaption></figure>
                                <div class="probability-preview"><p class="micro-label">SALIDA REFERENCIAL DEL MODELO</p><div class="demo-result">Probabilidades por categoría</div>
                                    <div class="probability-row"><span>Normal</span><strong>—</strong><div class="probability-track"><i style="--value:0%"></i></div></div>
                                    <div class="probability-row"><span>Retinopatía diabética</span><strong>—</strong><div class="probability-track"><i style="--value:0%"></i></div></div>
                                    <div class="probability-row"><span>Glaucoma</span><strong>—</strong><div class="probability-track"><i style="--value:0%"></i></div></div>
                                    <div class="probability-row"><span>Catarata</span><strong>—</strong><div class="probability-track"><i style="--value:0%"></i></div></div>
                                    <p class="demo-disclaimer">Los valores aparecen después de procesar una retinografía con el servicio CNN.</p>
                                </div>
                            </div>
                        </div>
                        <div id="demo-history" class="demo-panel" role="tabpanel" aria-labelledby="tab-history" tabindex="0" hidden>
                            <div class="demo-heading"><div><span class="micro-label">HISTORIAL DEL PACIENTE</span><h3>Cada consulta tiene contexto.</h3></div><span class="example-badge">Proceso</span></div>
                            <div class="history-patient"><span class="patient-monogram" aria-hidden="true">P</span><div><strong>Paciente identificado</strong><span>Historial vinculado al código real del paciente</span></div><span class="history-total">Controles</span></div>
                            <div class="timeline">
                                <div class="timeline-entry"><span class="timeline-point"></span><div><h4>Control más reciente</h4><p>Imagen, resultados y observaciones médicas reales.</p></div><span class="timeline-document"><svg class="icon" aria-hidden="true"><use href="#icon-document"/></svg>PDF</span></div>
                                <div class="timeline-entry"><span class="timeline-point"></span><div><h4>Seguimiento</h4><p>Los controles se ordenan por su fecha real.</p></div><span class="timeline-document"><svg class="icon" aria-hidden="true"><use href="#icon-history"/></svg></span></div>
                            </div>
                        </div>
                        <div id="demo-report" class="demo-panel" role="tabpanel" aria-labelledby="tab-report" tabindex="0" hidden>
                            <div class="demo-heading"><div><span class="micro-label">REPORTE DEL ANÁLISIS</span><h3>La información que importa.</h3></div><span class="example-badge">Estructura</span></div>
                            <div class="report-preview"><div class="paper-header"><strong>Retin<span>AI</span></strong><span>INFORME REFERENCIAL</span></div><h4>Análisis de retinografía</h4><div class="paper-meta"><span>Datos del paciente identificado</span><span>Fecha del análisis</span></div><div class="paper-results"><div><span class="micro-label">RESULTADO DE IA</span><p>Salida real de la CNN y probabilidades por categoría.</p><span class="micro-label">VALORACIÓN MÉDICA</span><p>Diagnóstico y observaciones aprobados por el especialista.</p></div></div><p class="paper-footer">Apoyo referencial. La decisión clínica corresponde al médico.</p></div>
                        </div>
                    </div>
                </div>
                <div class="capability-row reveal">
                    <article><svg class="icon" aria-hidden="true"><use href="#icon-eye"/></svg><h3>Una lectura complementaria</h3><p>Resultados por categoría para acompañar la revisión de la retinografía.</p></article>
                    <article><svg class="icon" aria-hidden="true"><use href="#icon-history"/></svg><h3>El contexto, siempre cerca</h3><p>Encuentra estudios anteriores mediante el DNI o el código del paciente.</p></article>
                    <article><svg class="icon" aria-hidden="true"><use href="#icon-document"/></svg><h3>Documentación organizada</h3><p>Reúne la imagen, el resultado y tu valoración en un reporte descargable.</p></article>
                </div>
            </div>
        </section>

        <section class="capture-section section" id="captura" aria-labelledby="capture-title">
            <div class="container capture-grid">
                <div class="capture-visual reveal">
                    <div class="capture-visual-label"><span class="micro-label">EL ORIGEN DE LA IMAGEN</span><svg class="icon" aria-hidden="true"><use href="#icon-eye"/></svg></div>
                    <svg class="fundus-camera" viewBox="0 0 520 420" role="img" aria-labelledby="camera-title camera-description">
                        <title id="camera-title">Ilustración de una cámara de fondo de ojo</title><desc id="camera-description">Equipo genérico con objetivo óptico, pantalla y apoyo para el paciente. No representa una marca o modelo compatible específico.</desc>
                        <defs><linearGradient id="camera-white" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#fff"/><stop offset="1" stop-color="#cbd6e5"/></linearGradient><linearGradient id="camera-side"><stop stop-color="#8496b0"/><stop offset="1" stop-color="#dce5f0"/></linearGradient><linearGradient id="camera-dark" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#34465f"/><stop offset="1" stop-color="#0b1423"/></linearGradient><radialGradient id="camera-lens"><stop stop-color="#071529"/><stop offset=".35" stop-color="#124f99"/><stop offset=".7" stop-color="#51a2f6"/><stop offset="1" stop-color="#0b223f"/></radialGradient><filter id="camera-shadow"><feGaussianBlur stdDeviation="8"/></filter></defs>
                        <ellipse cx="257" cy="365" rx="165" ry="18" fill="#123969" opacity=".12" filter="url(#camera-shadow)"/>
                        <path d="M99 332 331 300l97 37-234 39Z" fill="#c1ccdc"/><path d="m99 332 95 32 234-27v15l-234 27-95-31Z" fill="url(#camera-white)"/><path d="m99 332 95 32 234-27" fill="none" stroke="white" stroke-width="3"/>
                        <path d="m222 214 46-8 1 120-47 7Z" fill="url(#camera-side)"/><path d="m268 206 26 13v104l-25 3Z" fill="#97a9bf"/>
                        <path d="m186 116 107-20 72 42v89l-114 24-65-42Z" fill="url(#camera-white)" stroke="#b2c1d5"/><path d="m293 96 72 42v89l-72-43Z" fill="#d4deeb"/><path d="m186 116 65 38 114-16" fill="none" stroke="white" stroke-width="2"/>
                        <path d="m191 118 50 30v96l-50-33Z" fill="url(#camera-dark)"/><path d="m199 133 33 20v48l-33-19Z" fill="#296ad2" stroke="#6ca9ed"/><path d="m204 173 10-14 7 6 6-4" fill="none" stroke="#b8e8ff" stroke-width="2"/>
                        <path d="m305 148 46 20v46l-46-24Z" fill="#9eafc5"/><path d="m332 172 48-15 9 35-42 19Z" fill="url(#camera-dark)"/>
                        <ellipse cx="381" cy="175" rx="20" ry="29" transform="rotate(-15 381 175)" fill="#172b46" stroke="#697e9b" stroke-width="5"/><ellipse cx="383" cy="175" rx="12" ry="20" transform="rotate(-15 383 175)" fill="url(#camera-lens)" stroke="#67adf2"/><ellipse cx="383" cy="172" rx="4" ry="9" fill="#051127"/><ellipse cx="379" cy="166" rx="3" ry="5" fill="white" opacity=".7"/>
                        <path d="M406 314V118c0-8-6-13-14-14l-36-7" stroke="#8da0ba" stroke-width="8" fill="none" stroke-linecap="round"/><path d="m352 96 25 8" stroke="#263c57" stroke-width="13" stroke-linecap="round"/><path d="m387 245 35 10" stroke="#8da0ba" stroke-width="7"/><path d="m374 239 22-4 33 11-18 7Z" fill="#263c57"/>
                        <path d="m153 317 3-30" stroke="#263b58" stroke-width="8" stroke-linecap="round"/><ellipse cx="157" cy="284" rx="10" ry="7" fill="#36506e"/><path d="m237 124 22 13 28-5" stroke="#2563eb" stroke-width="3" fill="none" stroke-linecap="round"/>
                        <g stroke="#8ca6c7" stroke-width="1" fill="none"><path d="M374 179h85v-35"/><path d="M205 159H75v-34"/></g><g fill="#547194" font-family="DM Mono, monospace" font-size="10"><text x="402" y="131">OBJETIVO</text><text x="37" y="111">CAPTURA</text></g>
                    </svg>
                    <div class="capture-visual-bottom"><span>Cámara de fondo de ojo</span><span>Ilustración de referencia</span></div>
                </div>
                <div class="capture-copy reveal"><p class="eyebrow">02 / LA RETINOGRAFÍA</p><h2 id="capture-title">Todo comienza<br>con una buena imagen.</h2><p>La retinografía es una fotografía del fondo del ojo, obtenida con una cámara de fondo de ojo o retinógrafo. Es el punto de partida del análisis en RetinAI.</p><p>El equipo captura la imagen. El profesional la exporta y la carga en la plataforma para su análisis referencial.</p>
                    <dl class="capture-specs"><div><dt>Formato</dt><dd>JPG o PNG</dd></div><div><dt>Tamaño máximo</dt><dd>10 MB</dd></div><div><dt>Tipo de imagen</dt><dd>Fondo de ojo</dd></div><div><dt>Incorporación</dt><dd>Carga manual</dd></div></dl><p class="capture-note">La compatibilidad debe evaluarse con imágenes del equipo utilizado. La ilustración no representa un dispositivo certificado para RetinAI.</p>
                </div>
            </div>
        </section>
        <section class="principle-section" aria-labelledby="principle-title"><div class="container principle-inner reveal"><span class="principle-icon"><svg class="icon" aria-hidden="true"><use href="#icon-shield"/></svg></span><div><p class="eyebrow">TECNOLOGÍA CON UN PROPÓSITO CLARO</p><h2 id="principle-title">La IA aporta información.<br>Tu criterio le da sentido.</h2></div><p>RetinAI ofrece apoyo referencial. Cada resultado requiere la evaluación del profesional y se interpreta en el contexto clínico del paciente.</p></div></section>
        <section class="faq-section section" id="preguntas" aria-labelledby="faq-title">
            <div class="container faq-grid">
                <div class="reveal"><p class="eyebrow">03 / ANTES DE COMENZAR</p><h2 id="faq-title">Una mirada<br>a tus preguntas.</h2><p class="faq-intro">Lo esencial para conocer cómo encaja RetinAI en tu trabajo.</p></div>
                <div class="faq-list reveal">
                    <details open><summary>¿RetinAI reemplaza el diagnóstico del médico?<span class="faq-plus" aria-hidden="true"></span></summary><p>No. Presenta resultados referenciales que el médico debe revisar junto con el contexto clínico del paciente. La valoración y la decisión final corresponden al profesional.</p></details>
                    <details><summary>¿Qué imágenes puedo cargar?<span class="faq-plus" aria-hidden="true"></span></summary><p>Fotografías de fondo de ojo en JPG o PNG, de hasta 10 MB. Una fotografía externa del ojo o una imagen OCT no equivale a la retinografía utilizada por este sistema.</p></details>
                    <details><summary>¿Cómo accede mi establecimiento?<span class="faq-plus" aria-hidden="true"></span></summary><p>El responsable del centro puede <a href="views/auth/solicitud_registro.php" data-enter>enviar una solicitud de registro</a>. Una vez aprobado el acceso, el administrador del establecimiento gestiona las cuentas de sus médicos.</p></details>
                    <details><summary>¿Necesito conectar el equipo a RetinAI?<span class="faq-plus" aria-hidden="true"></span></summary><p>No hay conexión directa con el equipo de captura. El médico carga la imagen exportada desde su dispositivo. La compatibilidad de esas imágenes debe evaluarse antes de su uso.</p></details>
                </div>
            </div>
        </section>
        <section class="join-section" aria-labelledby="join-title"><div class="container"><div class="join-panel reveal"><div class="join-rings" aria-hidden="true"><i></i><i></i><i></i></div><div class="join-copy"><p class="eyebrow">RETINAI PARA TU ESTABLECIMIENTO</p><h2 id="join-title">Tu experiencia.<br>Una nueva perspectiva.</h2><p>Conoce una forma de reunir el análisis retinal,<br class="desktop-break"> el historial y la documentación de tus pacientes.</p></div><div class="join-actions"><a class="button button-primary" href="views/auth/solicitud_registro.php" data-enter>Solicitar registro del centro <svg class="icon" aria-hidden="true"><use href="#icon-arrow"/></svg></a><a class="text-link" href="views/auth/login.php" data-enter>Ya tengo una cuenta <span aria-hidden="true">↗</span></a></div></div></div></section>
    </main>
    <footer class="site-footer"><div class="container"><div class="footer-top"><a class="brand" href="./" aria-label="RetinAI — Inicio"><svg class="brand-symbol" aria-hidden="true"><use href="#brand-eye"/></svg><span>Retin<span class="brand-ai">AI</span></span></a><p>Una visión complementaria para la oftalmología.</p><a href="#contenido" class="back-top">Volver arriba <span aria-hidden="true">↑</span></a></div><div class="footer-bottom"><span>© <span id="copyright-year">2026</span> RetinAI</span><span>Diseñado para profesionales · Tacna, Perú</span><span>Apoyo referencial. Decisión médica.</span></div></div></footer>
    <div class="entry-transition" aria-hidden="true"><div class="transition-iris"></div><span>Retin<span>AI</span></span></div>
</body>
</html>
