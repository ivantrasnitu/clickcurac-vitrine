<?php

/*
 * Site du restaurant, réalisé avec ClickCurac (https://clickcurac.fr).
 *
 * Ce fichier est le même pour tous les restaurants : il affiche la vitrine enregistrée dans le backoffice ClickCurac
 * pour ce domaine (Vitrine → Réglages → Domaine). Ne le modifiez pas : textes, photos, horaires, couleurs, pages et
 * anciennes adresses se règlent dans le backoffice. Instructions : https://github.com/ivantrasnitu/clickcurac-vitrine
 */

const CLICKCURAC_API = 'https://backoffice.clickcurac.fr';
// Version de ce fichier : annoncée à ClickCurac (Vitrine → Réglages indique si une mise à jour existe).
const CLICKCURAC_RELAY = 2;

// Domaine demandé par le visiteur (sans « www » ni port).
$host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$host = (string) preg_replace('/^www\./', '', $host);
if (! preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $host)) {
    http_response_code(400);
    exit('Adresse invalide.');
}

$path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
// Paramètres de l'adresse (?lang=en, ?id=60&slug=…) : tous transmis (noms et valeurs simples) ; ClickCurac décide quoi en
// faire. Ainsi, les nouvelles possibilités arrivent sans redéposer ce fichier.
$query = [];
foreach ($_GET as $key => $value) {
    if (is_string($key) && preg_match('/^[a-z0-9_]{1,30}$/i', $key) && is_scalar($value) && strlen((string) $value) <= 300 && count($query) < 20) {
        $query[$key] = (string) $value;
    }
}
if (in_array($path, ['sitemap.xml', 'robots.txt', 'llms.txt', 'llms-full.txt', 'rss.xml'], true)) {
    $target = '/v/_d/'.$path;
} elseif ($path === '' || preg_match('/^[a-z0-9-]{1,60}$/', $path)) {
    $target = '/v/_d/'.$path.($query ? '?'.http_build_query($query) : '');
} elseif (preg_match('#^[A-Za-z0-9._~/-]{1,120}$#', $path)) {
    // Ancienne adresse du site (QR code imprimé vers menu.php?lang=en, article blog.php?id=60&slug=…) : la vitrine dit
    // vers quelle page aller.
    $target = '/v/_d/_old?'.http_build_query(['path' => $path] + $query);
} else {
    http_response_code(404);
    exit('Page introuvable.');
}

$headers = ['X-ClickCurac-Vitrine: domain', 'X-ClickCurac-Host: '.$host, 'X-ClickCurac-Relay: '.CLICKCURAC_RELAY];
$body = false;
$status = 0;
$type = '';
$location = '';
if (function_exists('curl_init')) {
    $ch = curl_init(CLICKCURAC_API.$target);
    // Sécurité : https seulement, certificat vérifié, pas de redirection suivie, attente et taille limitées.
    $max = 5 * 1024 * 1024;
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 6, CURLOPT_USERAGENT => 'ClickCurac-Vitrine', CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_MAXFILESIZE => $max,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => fn ($ch, $total, $received) => $received > $max ? 1 : 0,
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
    $ctx = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true, 'follow_location' => 0, 'header' => implode("\r\n", $headers)."\r\nUser-Agent: ClickCurac-Vitrine\r\n"],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $body = @file_get_contents(CLICKCURAC_API.$target, false, $ctx, 0, 5 * 1024 * 1024);
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
// Types de contenu permis seulement (pages, plan du site, flux, textes pour les IA) ; sinon texte simple.
$type = preg_match('#^(text/html|text/plain|text/xml|application/xml|application/rss\+xml)(;\s*charset=[a-z0-9-]+)?$#i', trim($type)) ? trim($type) : 'text/plain; charset=utf-8';
header('Content-Type: '.$type);
header('Cache-Control: no-cache');
// Protections du navigateur : pas d'affichage dans le cadre d'un autre site, type de contenu respecté.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
// 404 : la page ClickCurac « introuvable » (domaine pas encore enregistré, vitrine pas publiée) ; autre erreur : texte simple.
echo $status === 200 || ($status === 404 && strpos($type, 'text/html') !== false && $body !== '') ? $body : 'Page introuvable.';
