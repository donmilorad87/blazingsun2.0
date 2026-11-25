<?php
/**
 * The public page for one language. Every value comes from PageView, which escapes it.
 *
 * @var \BlazingSun\Api\Page\PageView $view
 */
declare(strict_types=1);

use BlazingSun\Api\Page\PageView;

$e = PageView::escape(...);
$current = $view->currentLanguage();
$orbSizes = '(max-width: 700px) min(330px, 72vw), min(500px, 40vw)';
// The orb keeps the image's own aspect ratio (426 × 426 when unknown, e.g. an SVG without width/height).
$orb = static fn (string $field): array => ['loading' => 'lazy', 'class' => 'theme-orb__image', 'alt' => '', 'width' => (string) (($view->content->image('header', $field)['width'] ?? 0) ?: 426), 'height' => (string) (($view->content->image('header', $field)['height'] ?? 0) ?: 426)];
?>
<!DOCTYPE html>
<html lang="<?= $e($view->lang) ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $view->text('labels', 'meta_title') ?></title>
	<meta name="description" content="<?= $view->text('labels', 'meta_description') ?>">
	<meta name="theme-color" content="#07080c">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<meta name="apple-mobile-web-app-title" content="<?= $view->text('header', 'name_light') ?>">
	<link rel="canonical" href="<?= $e($view->url($view->lang)) ?>">
<?php foreach ($view->content->languages as $language) : ?>
	<link rel="alternate" hreflang="<?= $e($language['iso2']) ?>" href="<?= $e($view->url($language['iso2'])) ?>">
<?php endforeach; ?>
	<link rel="alternate" hreflang="x-default" href="<?= $e($view->url($view->content->mainLanguage())) ?>">
	<link rel="icon" type="image/png" href="/favicon.png">
	<link rel="apple-touch-icon" href="/favicon.png">
	<link rel="manifest" href="/manifest.json">
	<!-- Before first paint: apply the saved theme (no moon→sun flash) and hide the orb until ThemeOrb places it (failsafe: 4s). -->
	<script>var d=document.documentElement;try{if(localStorage.getItem('blazing-theme')==='light')d.classList.add('light')}catch(e){}d.classList.add('orb-pending');setTimeout(function(){d.classList.remove('orb-pending')},4000)</script>
<?php foreach ($view->assets->css as $css) : ?>
	<link rel="stylesheet" href="<?= $e($css) ?>">
<?php endforeach; ?>
<?php foreach ($view->assets->js as $js) : ?>
	<script type="module" src="<?= $e($js) ?>"></script>
<?php endforeach; ?>
	<script type="application/ld+json"><?= $view->schemaJson ?></script>
</head>
<body>
	<canvas class="sky sky--stars" aria-hidden="true"></canvas>
	<canvas class="sky sky--clouds" aria-hidden="true"></canvas>
	<div class="theme-orb" data-theme-only="dark" aria-hidden="true"><?= $view->image('header', 'icon_dark', $orbSizes, $orb('icon_dark')) ?></div>
	<div class="theme-orb" data-theme-only="light" aria-hidden="true"><?= $view->image('header', 'icon_light', $orbSizes, $orb('icon_light')) ?></div>
	<header class="site-header">
		<nav class="wrap site-nav" aria-label="<?= $view->text('labels', 'nav_label') ?>">
			<a class="brand" href="#top">
				<?= $view->image('header', 'logo_dark', '45px', ['loading' => 'lazy', 'class' => 'brand__logo', 'alt' => $view->content->text('header', 'name_dark', $view->lang) . ' logo', 'width' => '45', 'height' => '45', 'data-theme-only' => 'dark']) ?>

				<?= $view->image('header', 'logo_light', '45px', ['loading' => 'lazy', 'class' => 'brand__logo', 'alt' => $view->content->text('header', 'name_light', $view->lang) . ' logo', 'width' => '45', 'height' => '45', 'data-theme-only' => 'light']) ?>

				<span class="brand__name" data-theme-only="dark"><?= $view->text('header', 'name_dark') ?></span>
				<span class="brand__name" data-theme-only="light"><?= $view->text('header', 'name_light') ?></span>
			</a>
			<div class="site-nav__links">
<?php foreach ($view->menu() as $anchor => $label) : ?>
				<a href="#<?= $e($anchor) ?>"><?= $label ?></a>
<?php endforeach; ?>
			</div>
			<div class="site-nav__controls">
				<div class="lang-picker">
					<button class="control lang-picker__current" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="lang-menu" aria-label="<?= $e($current['name']) ?>">
						<?= $view->flag($current, ['width' => '24', 'height' => '16']) ?>

					</button>
					<div class="lang-picker__menu" id="lang-menu" hidden>
<?php foreach ($view->content->languages as $language) : ?>
						<a href="<?= $e($view->path($language['iso2'])) ?>" hreflang="<?= $e($language['iso2']) ?>" lang="<?= $e($language['iso2']) ?>"<?= $language['iso2'] === $view->lang ? ' class="is-active" aria-current="page"' : '' ?>><?= $view->flag($language, ['width' => '22', 'height' => '15']) ?><?= $e($language['name']) ?></a>
<?php endforeach; ?>
					</div>
				</div>
				<button class="control theme-toggle" type="button" aria-label="<?= $view->text('labels', 'theme_toggle') ?>" aria-pressed="false">
					<?= $view->image('header', 'icon_dark', '24px', ['loading' => 'lazy', 'alt' => '', 'width' => '24', 'height' => '24', 'data-theme-only' => 'dark']) ?>

					<?= $view->image('header', 'icon_light', '24px', ['loading' => 'lazy', 'alt' => '', 'width' => '24', 'height' => '24', 'data-theme-only' => 'light']) ?>

				</button>
			</div>
		</nav>
	</header>
	<main id="top">
		<section class="hero">
			<div class="wrap hero__grid">
				<div>
					<div class="eyebrow"><?= $view->text('section:hero', 'before_title') ?></div>
					<h1><?= $view->rich('section:hero', 'title') ?></h1>
					<p class="hero__lead"><?= $view->text('section:hero', 'description') ?></p>
					<div class="hero__actions">
<?php foreach ($view->items('hero') as $item) : ?>
						<a class="btn btn--<?= $view->value($item, 'style') ?>" href="<?= $view->value($item, 'href') ?>"<?= $view->linkTarget($view->content->value($item, 'href')) ?>><?= $view->text($item, 'label') ?></a>
<?php endforeach; ?>
					</div>
<?php if ($view->text('section:hero', 'info') !== '') : ?>
					<div class="hero__available"><i aria-hidden="true"></i><span><?= $view->text('section:hero', 'info') ?></span></div>
<?php endif; ?>
				</div>
				<div class="hero__glow" aria-hidden="true"></div>
			</div>
		</section>
<?php
// Section heading shared by every section below the home banner.
$head = static function (string $slug) use ($view): string {
    $scope = "section:$slug";
    $description = $view->text($scope, 'description');

    return '<div class="section-head"><div><div class="eyebrow">' . $view->beforeTitle($slug) . '</div><h2>' . $view->text($scope, 'title') . '</h2></div>'
        . ($description === '' ? '' : "<p>$description</p>") . '</div>';
};
?>
		<section id="about">
			<div class="wrap">
				<?= $head('about') ?>

				<div class="stats">
<?php foreach ($view->items('about') as $item) : ?>
					<div class="panel stat"><strong><?= $view->value($item, 'value') ?></strong><span><?= $view->text($item, 'label') ?></span></div>
<?php endforeach; ?>
				</div>
				<div class="about__split">
					<div class="panel quote"><?= $view->text('section:about', 'quote') ?></div>
					<div class="panel about__copy">
						<p><?= $view->text('section:about', 'company_description') ?></p>
						<ul class="tags">
<?php foreach ($view->items('about', 'company_items') as $item) : ?>
							<li class="tag"><?= $view->text($item, 'text') ?></li>
<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
		</section>
		<section id="stack">
			<div class="wrap">
				<?= $head('stack') ?>

				<div class="grid grid--3">
<?php foreach ($view->items('stack') as $item) : ?>
					<article class="panel card"><h3><?= $view->text($item, 'title') ?></h3><p><?= $view->rich($item, 'text') ?></p></article>
<?php endforeach; ?>
				</div>
			</div>
		</section>
		<section id="experience">
			<div class="wrap">
				<?= $head('experience') ?>

				<div class="timeline">
<?php foreach ($view->items('experience') as $item) : ?>
					<article class="panel job"><div class="job__date"><?= $view->text($item, 'period') ?></div><div><h3><?= $view->text($item, 'title') ?></h3><div class="job__company"><?= $view->value($item, 'company') ?></div><p><?= $view->text($item, 'text') ?></p></div></article>
<?php endforeach; ?>
				</div>
			</div>
		</section>
		<section id="projects">
			<div class="wrap">
				<?= $head('projects') ?>

				<div class="grid grid--2">
<?php foreach ($view->items('projects') as $index => $item) : ?>
<?php $category = $view->text($item, 'category'); $url = $view->content->value($item, 'url'); ?>
					<article class="panel project"><div class="project__number"><?= sprintf('%02d', $index + 1) . ($category === '' ? '' : " / $category") ?></div><h3><?= $url === '' ? $view->text($item, 'name') : '<a href="' . $e($url) . '"' . $view->linkTarget($url) . '>' . $view->text($item, 'name') . '</a>' ?></h3><p><?= $view->text($item, 'text') ?></p><?= $view->value($item, 'tech') === '' ? '' : '<div class="project__tech">' . $view->value($item, 'tech') . '</div>' ?></article>
<?php endforeach; ?>
				</div>
			</div>
		</section>
		<section id="cv-highlights">
			<div class="wrap">
				<?= $head('cv') ?>

				<div class="grid grid--3">
<?php foreach ($view->items('cv') as $item) : ?>
					<article class="panel cv-card"><h3><?= $view->text($item, 'title') ?></h3><p><?= $view->text($item, 'text') ?></p></article>
<?php endforeach; ?>
				</div>
			</div>
		</section>
		<section id="contact" class="contact">
			<div class="wrap">
				<div class="panel contact__box">
					<div class="eyebrow"><?= $view->beforeTitle('contact') ?></div>
					<h2><?= $view->text('section:contact', 'title') ?></h2>
					<p><?= $view->text('section:contact', 'description') ?></p>
					<div class="contact__links">
<?php foreach ($view->items('contact') as $item) : ?>
						<?= $view->contactLink($item) ?>

<?php endforeach; ?>
					</div>
				</div>
			</div>
		</section>
	</main>
	<footer class="site-footer">
		<div class="wrap">
			<span>© <?= $view->year ?> <?= $view->value('footer', 'name') ?> · <?= $view->value('footer', 'company') ?></span>
			<span><?= $view->text('footer', 'position') ?> · <?= $view->text('footer', 'country') ?></span>
			<!-- All-time page visits from /api/visits. Hidden on purpose: check it in DevTools. -->
			<span class="site-footer__visits" data-visit-counter hidden></span>
		</div>
	</footer>
	<!-- Glass back-to-top button; the gold ring fills with scroll progress (BackToTop.js). -->
	<button class="back-to-top" type="button" aria-label="<?= $view->text('labels', 'back_to_top') ?>">
		<svg class="back-to-top__ring" viewBox="0 0 48 48" aria-hidden="true"><circle class="back-to-top__track" cx="24" cy="24" r="22.5"/><circle class="back-to-top__progress" cx="24" cy="24" r="22.5" pathLength="100"/></svg>
		<svg class="back-to-top__arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M5.5 11.5 12 5l6.5 6.5"/></svg>
	</button>
</body>
</html>
