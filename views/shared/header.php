<?php
/**
 * views/shared/header.php
 * Encabezado común para todos los dashboards (ADM, MED, SAD).
 *
 * Variables esperadas en el contexto que lo incluye:
 *   $base        – ruta base de la aplicación (get_base_path())
 *   $user        – array con 'nombre', 'rol_codigo'
 *   $initials    – iniciales del usuario (get_initials())
 *   $logout_url  – URL de logout
 *   $role_label  – texto para la píldora de rol  (p.ej. '🛡️ Administrador')
 *   $role_class  – clase CSS de la píldora       (p.ej. 'role-adm')
 *   $avatar_class– clase CSS del avatar          (p.ej. 'avatar-adm')
 *   $header_sub  – subtítulo bajo el nombre (establecimiento, 'Control Global', 'Médico Oftalmólogo', etc.)
 */
?>
<header class="top-header">
    <div class="header-brand">
        <div class="logo-mark" style="background:transparent; border:none; padding:0;">
            <img src="<?= $base ?>assets/images/logo_retinai_fondo_color.png" alt="Logo RetinAI" style="height: 28px; width: auto; display: block; border-radius: 4px;">
        </div>
        <span class="logo-text">Retin<em>AI</em></span>
    </div>
    <span class="role-pill <?= htmlspecialchars($role_class ?? '') ?>"><?= htmlspecialchars($role_label ?? '') ?></span>
    <div class="header-user">
        <div class="avatar <?= htmlspecialchars($avatar_class ?? '') ?>"><?= htmlspecialchars($initials ?? '') ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($user['nombre'] ?? '') ?></span>
            <span class="user-role"><?= htmlspecialchars($header_sub ?? '') ?></span>
        </div>
        <a href="<?= $logout_url ?>" class="btn-logout" id="btn-logout">
            <svg viewBox="0 0 24 24" fill="none" width="16" height="16"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Salir
        </a>
    </div>
</header>
