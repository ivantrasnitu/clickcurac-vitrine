<?php
/*
 * Site du restaurant, réalisé avec ClickCurac (https://clickcurac.fr).
 *
 * Ce fichier est le même pour tous les restaurants : il affiche la vitrine enregistrée dans le backoffice ClickCurac
 * pour ce domaine (Vitrine → Réglages → Domaine). Ne le modifiez pas : textes, photos, horaires, couleurs, pages et
 * anciennes adresses se règlent dans le backoffice. Instructions : https://github.com/ivantrasnitu/clickcurac-vitrine
 */

const CLICKCURAC_API = 'https://backoffice.clickcurac.fr';

// Domaine demandé par le visiteur (sans « www » ni port).
$host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$host = (string) preg_replace('/^www\./', '', $host);
if (! preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $host)) {
    http_response_code(400);
    exit('Adresse invalide.');
}

$path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$lang = isset($_GET['lang']) && preg_match('/^[a-z]{2}$/', (string) $_GET['lang']) ? $_GET['lang'] : null;
if ($path === 'sitemap.xml' || $path === 'robots.txt') {
    $target = '/v/_d/'.$path;
} elseif ($path === '' || preg_match('/^[a-z0-9-]{1,60}$/', $path)) {
    $params = array_filter(['lang' => $lang, 'm' => isset($_GET['m']) && ctype_digit((string) $_GET['m']) ? $_GET['m'] : null]);
    $target = '/v/_d/'.$path.($params ? '?'.http_build_query($params) : '');
} elseif (preg_match('#^[A-Za-z0-9._~/-]{1,120}$#', $path)) {
    // Ancienne adresse du site (QR code imprimé vers menu.php?lang=en…) : la vitrine dit vers quelle page aller.
    $target = '/v/_d/_old?'.http_build_query(array_filter(['path' => $path, 'lang' => $lang]));
} else {
    http_response_code(404);
    exit('Page introuvable.');
}

$headers = ['X-ClickCurac-Vitrine: domain', 'X-ClickCurac-Host: '.$host];
$body = false;
$status = 0;
$type = '';
$location = '';
if (function_exists('curl_init')) {
    $ch = curl_init(CLICKCURAC_API.$target);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_USERAGENT => 'ClickCurac-Vitrine', CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => function ($ch, $h) use (&$location) {
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
            }

            return strlen($h);
        }]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true, 'follow_location' => 0, 'header' => implode("\r\n", $headers)."\r\nUser-Agent: ClickCurac-Vitrine\r\n"]]);
    $body = @file_get_contents(CLICKCURAC_API.$target, false, $ctx);
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+ (\d{3})#', $h, $m)) {
            $status = (int) $m[1];
        } elseif (stripos($h, 'Content-Type:') === 0) {
            $type = trim(substr($h, 13));
        } elseif (stripos($h, 'Location:') === 0) {
            $location = trim(substr($h, 9));
        }
    }
}

if ($body === false || $status === 0) {
    http_response_code(503);
    header('Retry-After: 60');
    exit('Le site est momentanément indisponible. Réessayez dans un instant.');
}
// Redirection vers une page de ce site seulement.
if (($status === 301 || $status === 302) && preg_match('#^https://'.preg_quote($host, '#').'([/?]|$)#', $location)) {
    header('Location: '.$location, true, 301);
    header('Cache-Control: public, max-age=3600');
    exit;
}
http_response_code($status === 200 ? 200 : 404);
header('Content-Type: '.($type ?: 'text/html; charset=utf-8'));
header('Cache-Control: no-cache');
// 404 : la page ClickCurac « introuvable » (domaine pas encore enregistré, vitrine pas publiée) ; autre erreur : texte simple.
echo $status === 200 || ($status === 404 && strpos($type, 'text/html') !== false && $body !== '') ? $body : 'Page introuvable.';
