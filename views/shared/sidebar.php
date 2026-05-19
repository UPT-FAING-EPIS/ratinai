<?php
$_rol = $_SESSION['rol_codigo'] ?? '';
?>
<aside class="sidebar">
    <nav class="sidebar-nav">

        <?php if ($_rol === 'ADM'):
            $_page = basename($_SERVER['PHP_SELF']); ?>
        <!-- ── ADMIN: Gestión ── -->
        <div class="nav-section">
            <span class="nav-section-label">Gestión</span>
            <a href="<?= $base ?>views/admin/index.php"
               class="nav-item <?= $_page === 'index.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><rect x="2" y="4" width="16" height="12" rx="2" stroke="currentColor" stroke-width="1.3"/><path d="M6 8h8M6 12h5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                Dashboard
                <?php if (!empty($cnt_pendientes) && $cnt_pendientes > 0): ?>
                    <span class="nav-badge"><?= $cnt_pendientes ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $base ?>views/admin/doctor.php"
               class="nav-item <?= $_page === 'doctor.php' || $_page === 'create_doctor.php' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 14a6 6 0 10-12 0" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                Médicos
            </a>
        </div>

        <?php elseif ($_rol === 'MED'): ?>
        <!-- ── MÉDICO: Análisis y historial ── -->
        <div class="nav-section">
            <span class="nav-section-label">Análisis</span>
            <a href="#analisis" class="nav-item active" onclick="showPanel('analisis',this)">
                <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.2"/><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.2"/><circle cx="8" cy="8" r="1" fill="currentColor"/></svg>
                Nuevo análisis
            </a>
            <a href="#historial" class="nav-item" onclick="showPanel('historial',this)">
                <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><path d="M3 4h10M3 8h7M3 12h5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                Historial de pacientes
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Sistema</span>
            <a href="#modelo" class="nav-item" onclick="showPanel('modelo',this)">
                <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.2"/><path d="M8 5v4l2.5 2.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                Información del modelo
            </a>
        </div>

        <?php elseif ($_rol === 'SAD'): ?>
        <!-- ── SUPER ADMIN: Sistema global ── -->
        <div class="nav-section">
            <span class="nav-section-label">Sistema Global</span>
            <a href="#establecimientos" class="nav-item active" data-section="establecimientos">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="1.3"/></svg>
                Establecimientos
            </a>
            <a href="#usuarios" class="nav-item" data-section="usuarios">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="1.3"/></svg>
                Usuarios del Sistema
            </a>
        </div>
        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
        <div class="session-info">
            <svg viewBox="0 0 20 20" fill="none" width="13" height="13"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.3"/><path d="M10 6v4l2.5 2.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
            Sesión: <span id="session-countdown">05:00</span>
        </div>
    </div>
</aside>
