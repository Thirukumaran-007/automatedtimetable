<?php
$publicDir = realpath(__DIR__ . '/../public');
if ($publicDir === false) {
    http_response_code(500);
    echo 'Public directory not found.';
    exit;
}

$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$requestPath = parse_url($requestUri, PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
if ($requestPath === '') {
    $requestPath = '/';
}

$targetPath = $requestPath === '/' ? '/index.php' : $requestPath;
if ($targetPath[0] !== '/') {
    $targetPath = '/' . $targetPath;
}

// Support extension-less URLs like /login by trying /login.php.
$basename = basename($targetPath);
if (!str_contains($basename, '.') && !str_ends_with($targetPath, '/')) {
    $candidate = $targetPath . '.php';
    if (is_file($publicDir . $candidate)) {
        $targetPath = $candidate;
    }
}

$resolved = realpath($publicDir . $targetPath);
$publicPrefix = str_replace('\\', '/', $publicDir) . '/';
$resolvedNorm = $resolved !== false ? str_replace('\\', '/', $resolved) : '';
$isInsidePublic = $resolved !== false && str_starts_with($resolvedNorm, $publicPrefix);

if (!$isInsidePublic || !is_file($resolved)) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$ext = strtolower((string)pathinfo($resolved, PATHINFO_EXTENSION));
if ($ext === 'php') {
    $fileName = basename($targetPath);
    if (str_starts_with($fileName, '_')) {
        http_response_code(404);
        echo 'Not Found';
        exit;
    }

    // Keep generated links rooted at domain root instead of /api.
    $_SERVER['SCRIPT_NAME'] = $targetPath;
    $_SERVER['PHP_SELF'] = $targetPath;
    require $resolved;
    exit;
}

// Fallback static file serving (useful in local tests / safety net).
$mime = function_exists('mime_content_type') ? (string)mime_content_type($resolved) : '';
if ($mime === '') {
    $mime = 'application/octet-stream';
}
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=3600');
readfile($resolved);
