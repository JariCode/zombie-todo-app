<?php
// ================================
// session-config.php
//
// Asettaa turvalliset session asetukset
//
// Turvallisuus:
// - Käyttää vain evästeitä (ei URL-session ID:tä)
// - Vain HTTPS:llä secure-cookie
// - HttpOnly ja SameSite=Lax suojaavat evästettä
// - Istunnon elinikä 1 tunti
// ================================

// ===================================================================
// SESSION SECURITY CONFIGURATION
// MUST be loaded BEFORE session_start() is called
// ===================================================================

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 3600); // 1 hour
}

?>
