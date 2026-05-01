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
    try {
        require $resolved;
    } catch (Throwable $e) {
        http_response_code(503);
        $message = $e->getMessage();
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Database Unavailable</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5" style="max-width:860px;"><div class="card shadow-sm"><div class="card-body p-4 p-lg-5"><h4 class="mb-2">Database unavailable</h4><p class="text-muted mb-3">The app could not connect to MySQL. Check your deployment environment variables and database host.</p><div class="alert alert-danger mb-3">' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div><div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="/setup.php">Open Setup</a><a class="btn btn-outline-secondary" href="/login.php">Go to Login</a></div></div></div></div></body></html>';
    }
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
