<?php
// Basic configuration for CTMS.
// Local defaults match XAMPP; production deployments should use environment variables.

function env_str(string $key): ?string {
	$v = getenv($key);
	if ($v !== false && $v !== '') {
		return (string)$v;
	}
	if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
		return (string)$_ENV[$key];
	}
	if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
		return (string)$_SERVER[$key];
	}
	return null;
}

define('DB_HOST', env_str('DB_HOST') ?? '127.0.0.1');
define('DB_PORT', (int)(env_str('DB_PORT') ?? 3306));
define('DB_NAME', env_str('DB_NAME') ?? 'ctms');
define('DB_USER', env_str('DB_USER') ?? 'root');
define('DB_PASS', env_str('DB_PASS') ?? '');

define('APP_NAME', env_str('APP_NAME') ?? 'College Timetable Management System');

/**
 * Base path where the public/ app is served.
 * - Local XAMPP default: /timetable/public
 * - Deployed at domain root: ''
 * Can be overridden with APP_BASE_PATH (example: /timetable/public)
 */
function app_base_path(): string {
	$explicit = (string)(env_str('APP_BASE_PATH') ?? '');
	if ($explicit !== '') {
		$explicit = str_replace('\\', '/', $explicit);
		return rtrim($explicit, '/');
	}

	$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
	if ($scriptName === '') {
		return '';
	}

	$dir = str_replace('\\', '/', dirname($scriptName));
	$dir = $dir === '/' ? '' : rtrim($dir, '/');
	return $dir;
}

/** Build an absolute path URL within this app (no domain). */
function url_for(string $path): string {
	$base = app_base_path();
	if ($path === '') {
		return $base !== '' ? $base : '/';
	}
	if ($path[0] !== '/') {
		$path = '/' . $path;
	}
	return ($base !== '' ? $base : '') . $path;
}
