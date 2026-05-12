<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RetinAI - Análisis Automatizado de Imágenes Retinianas</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>

    <!-- HERO SECTION -->
    <section class="hero section bg-gradient">
        <div class="container">
            <div class="hero-brand">
                <div class="logo-mark">
                    <svg viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="white" stroke-width="1.5"/>
                        <circle cx="12" cy="12" r="3.5" fill="white" opacity=".6"/>
                        <circle cx="12" cy="12" r="1.2" fill="white"/>
                    </svg>
                </div>
                <div class="logo-text">Retin<span>AI</span></div>
            </div>
            
            <h1 class="hero-title">Inteligencia Artificial para el <span>Diagnóstico Visual</span></h1>
            <p class="hero-subtitle">Agilizamos el análisis automatizado de imágenes retinianas para recintos de oftalmología en Tacna. Precisión clínica, reducción de tiempos y prevención de la discapacidad visual en un solo lugar.</p>
            
            <div class="hero-actions">
                <a href="views/auth/login.php" class="btn btn-primary btn-lg">Ingresar a la Plataforma</a>
                <a href="#solucion" class="btn btn-secondary btn-lg">Conocer más</a>
            </div>
        </div>
    </section>

    <!-- EL PROBLEMA SECTION -->
    <section class="section bg-white">
        <div class="container split-layout">
            <div class="split-text">
                <div class="badge">El Reto Actual</div>
                <h2>¿Por qué nace RetinAI?</h2>
                <p>Actualmente, el personal del servicio de oftalmología enfrenta una <strong>alta carga de trabajo</strong> y una falta de herramientas tecnológicas accesibles que agilicen el tamizaje.</p>
                <p>Esto resulta en diagnósticos lentos, demoras en la detección de enfermedades retinianas tratables y procedimientos comerciales con costos superiores a los <strong>$15,000 USD</strong>. Afectando tanto a los especialistas como a los pacientes que requieren atención oportuna.</p>
            </div>
            <div class="split-visual">
                <div class="visual-card">
                    <div class="stat">
                        <span class="stat-number">+80%</span>
                        <span class="stat-desc">Más económico que las soluciones importadas actuales.</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- OBJETIVOS / CARACTERÍSTICAS SECTION -->
    <section id="solucion" class="section bg-soft-blue">
        <div class="container text-center">
            <div class="badge badge-blue">Nuestra Solución</div>
            <h2 class="section-title">Tecnología diseñada para el entorno clínico</h2>
            <p class="section-subtitle">Hemos desarrollado una plataforma web pensando en la experiencia del oftalmólogo, garantizando fluidez y confianza diagnóstica.</p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="fw-icon blue">⏱️</div>
                    <h3>Diagnóstico en Minutos</h3>
                    <p>Reducimos el tiempo de análisis de retinopatía diabética de semanas a tan solo unos segundos, permitiendo una intervención inmediata.</p>
                </div>
                <div class="feature-card">
                    <div class="fw-icon green">🎯</div>
                    <h3>Alta Precisión Clínica</h3>
                    <p>Modelo CNN validado con dataset ODIR-5K, asegurando una sensibilidad y especificidad clínica superior al <strong>95%</strong>.</p>
                </div>
                <div class="feature-card">
                    <div class="fw-icon purple">💻</div>
                    <h3>Accesibilidad Total</h3>
                    <p>Adaptado a la infraestructura existente en hospitales y clínicas locales, sin necesidad de hardware extremadamente costoso.</p>
                </div>
                <div class="feature-card">
                    <div class="fw-icon orange">🏥</div>
                    <h3>Validación en Entorno Real</h3>
                    <p>Diseñado específicamente para las necesidades del sector clínico de Tacna, evaluado en usabilidad y viabilidad médica.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FUNCIONALIDADES HIGHLIGHT SECTION -->
    <section class="section bg-white text-center">
        <div class="container">
            <h2 class="section-title">Todo lo que necesitas en una sola plataforma</h2>
            <div class="tools-grid">
                <div class="tool-item">
                    <h4>Carga Rápida</h4>
                    <p>Sube imágenes en JPG/PNG y obtén resultados con mapa de calor al instante.</p>
                </div>
                <div class="tool-item">
                    <h4>Historial Clínico</h4>
                    <p>Seguimiento cronológico por paciente mediante un código único o DNI.</p>
                </div>
                <div class="tool-item">
                    <h4>Reportes PDF</h4>
                    <p>Generación automática de informes formales para respaldar tu diagnóstico médico.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA SECTION -->
    <section class="section bg-gradient-dark pt-large">
        <div class="container text-center">
            <h2 class="text-white">Únete a la evolución diagnóstica</h2>
            <p class="text-light-gray" style="max-width: 600px; margin: 0 auto 30px;">Si representas a una clínica u hospital y deseas integrar RetinAI a tus procesos, accede a la plataforma ahora.</p>
            <a href="views/auth/login.php" class="btn btn-primary btn-lg">Iniciar Sesión en el Sistema</a>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 RetinAI. Sistema de Apoyo al Diagnóstico Oftalmológico.</p>
        <p class="footer-sub">Optimizado para el personal médico de la región de Tacna.</p>
    </footer>

</body>
</html>
