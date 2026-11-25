<?php
// Served by .htaccess while scripts/deploy.sh uploads a release (the .maintenance flag file exists). Self-contained:
// it must not depend on assets that are being replaced at the same time.
http_response_code(503);
header('Retry-After: 60');
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title>Updating — blazingsun.space</title>
	<style>
		body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #0f1115; color: #e8e8e8; }
		main { padding: 1.5rem; text-align: center; }
		h1 { font-size: 1.5rem; margin: 0 0 .5rem; }
		p { margin: 0; color: #b8b8b8; }
	</style>
</head>
<body>
	<main>
		<h1>Updating the site</h1>
		<p>Back in a minute. Please refresh shortly.</p>
	</main>
</body>
</html>
