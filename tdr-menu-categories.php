<?php
/**
 * Plugin Name: Taverne du Roy – Menu de catégories (page Commander)
 * Description: Barre collante en haut de la carte Orderable : un lien par catégorie, défilement doux vers la section, catégorie en cours mise en évidence. Toute la carte reste affichée.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ==========================================================================
 * Réglages
 * ========================================================================== */

/** Slug(s) des pages où afficher la barre. */
function tdr_menu_pages(): array {
	return array( 'click-collect-de-la-taverne-du-roy' );
}

/**
 * Sélecteur CSS des titres de catégories.
 * Laisser vide : les titres sont détectés automatiquement.
 * Si la détection se trompe, inspecter un titre de catégorie (ex. "ENTRÉES")
 * et indiquer sa classe ici, par exemple '.orderable-xxx__title'.
 */
function tdr_menu_heading_selector(): string {
	return '';
}

/* ==========================================================================
 * Chargement
 * ========================================================================== */

function tdr_menu_is_target_page(): bool {
	return is_page( tdr_menu_pages() );
}

add_action( 'wp_head', function () {
	if ( ! tdr_menu_is_target_page() ) {
		return;
	}
	?>
	<style id="tdr-menu-css">
		.tdr-catnav {
			position: sticky;
			top: var(--tdr-catnav-top, 0px);
			z-index: 90;
			margin: 0 0 1.5em;
			background: #fff;
			border-bottom: 1px solid rgba(0,0,0,.1);
			box-shadow: 0 4px 10px -8px rgba(0,0,0,.25);
		}
		.tdr-catnav__list {
			display: flex; gap: .4em;
			margin: 0; padding: .6em .25em;
			list-style: none;
			overflow-x: auto; white-space: nowrap;
			scrollbar-width: none; -webkit-overflow-scrolling: touch;
		}
		.tdr-catnav__list::-webkit-scrollbar { display: none; }
		.tdr-catnav__list li { margin: 0; padding: 0; flex: 0 0 auto; }
		.tdr-catnav__link {
			display: inline-block; padding: .45em 1em;
			border: 1px solid rgba(0,0,0,.2); border-radius: 999px;
			color: inherit; font-size: .9em; font-weight: 600; text-decoration: none;
			transition: background-color .15s, color .15s, border-color .15s;
		}
		.tdr-catnav__link:hover, .tdr-catnav__link:focus { border-color: currentColor; color: inherit; text-decoration: none; }
		.tdr-catnav__link.is-active { background: #000; border-color: #000; color: #fff; }
	</style>
	<?php
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! tdr_menu_is_target_page() ) {
		return;
	}

	$config = array( 'headingSelector' => tdr_menu_heading_selector() );

	$js = <<<'JS'
(function () {
	var cfg = window.tdrMenu || {};

	function slugify(s) {
		return 'cat-' + s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
			.replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
	}

	function commonAncestor(nodes) {
		if (!nodes.length) { return null; }
		var anc = nodes[0].parentElement;
		while (anc && !nodes.every(function (n) { return anc.contains(n); })) { anc = anc.parentElement; }
		return anc;
	}

	/* ---------- Repérage des titres de catégories ---------- */

	function findCategoryHeadings() {
		if (cfg.headingSelector) {
			return Array.prototype.slice.call(document.querySelectorAll(cfg.headingSelector));
		}

		// Zone de la carte : ancêtre commun des boutons Orderable ("Ajouter", "Sélectionner").
		var buttons = Array.prototype.slice.call(document.querySelectorAll(
			'button[class*="orderable"], a[class*="orderable"], [class*="orderable"] button, [class*="orderable"] .button'
		));
		var root = commonAncestor(buttons);
		if (!root) { return []; }

		// Un titre de produit est seul dans sa carte (le plus petit bloc qui contient un bouton) ;
		// un titre de catégorie partage ce bloc avec les titres des produits.
		var headings = Array.prototype.slice.call(root.querySelectorAll('h1, h2, h3, h4'));
		return headings.filter(function (h) {
			var box = h.parentElement;
			while (box && box !== root.parentElement && !box.querySelector('button, .button, a[class*="button"]')) {
				box = box.parentElement;
			}
			if (!box) { return false; }
			return box.querySelectorAll('h1, h2, h3, h4').length > 1;
		});
	}

	/* ---------- Construction ---------- */

	function build() {
		if (document.querySelector('.tdr-catnav')) { return; }
		var heads = findCategoryHeadings();
		if (heads.length < 2) { return; }

		var nav = document.createElement('nav');
		nav.className = 'tdr-catnav';
		nav.setAttribute('aria-label', 'Catégories de la carte');
		var ul = document.createElement('ul');
		ul.className = 'tdr-catnav__list';

		heads.forEach(function (h) {
			var label = h.textContent.trim();
			if (!h.id) { h.id = slugify(label); }
			var li = document.createElement('li');
			var a = document.createElement('a');
			a.className = 'tdr-catnav__link';
			a.href = '#' + h.id;
			a.textContent = label;
			li.appendChild(a);
			ul.appendChild(li);
		});
		nav.appendChild(ul);

		// Placée dans le bloc qui contient toutes les catégories, pour rester collée sur toute la carte.
		var container = commonAncestor(heads) || heads[0].parentElement;
		container.insertBefore(nav, container.firstChild);

		var links = Array.prototype.slice.call(nav.querySelectorAll('a'));

		/* Hauteur des éléments fixes en haut d'écran (barre d'admin, en-tête Elementor collant). */
		function fixedTop() {
			var top = 0;
			var bar = document.getElementById('wpadminbar');
			if (bar && getComputedStyle(bar).position === 'fixed') { top += bar.offsetHeight; }
			document.querySelectorAll('header, .elementor-location-header, [data-elementor-type="header"], .elementor-sticky--active').forEach(function (el) {
				var pos = getComputedStyle(el).position;
				var r = el.getBoundingClientRect();
				if ((pos === 'fixed' || pos === 'sticky') && r.top <= top + 1 && r.height > 0 && r.height < window.innerHeight / 2) {
					top = Math.max(top, r.bottom);
				}
			});
			return Math.round(top);
		}

		function offset() { return fixedTop() + nav.offsetHeight + 12; }

		function updateTop() { nav.style.setProperty('--tdr-catnav-top', fixedTop() + 'px'); }

		function setActive() {
			var limit = offset() + 5, current = 0;
			heads.forEach(function (h, i) { if (h.getBoundingClientRect().top <= limit) { current = i; } });
			links.forEach(function (a, i) {
				var on = i === current;
				if (on && !a.classList.contains('is-active')) {
					// Garde l'onglet actif visible dans la barre (mobile).
					ul.scrollTo({ left: a.offsetLeft - ul.clientWidth / 2 + a.offsetWidth / 2, behavior: 'smooth' });
				}
				a.classList.toggle('is-active', on);
				if (on) { a.setAttribute('aria-current', 'true'); } else { a.removeAttribute('aria-current'); }
			});
		}

		links.forEach(function (a, i) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				var y = heads[i].getBoundingClientRect().top + window.pageYOffset - offset();
				window.scrollTo({ top: y, behavior: 'smooth' });
				if (history.replaceState) { history.replaceState(null, '', a.hash); }
			});
		});

		var ticking = false;
		function onScroll() {
			if (ticking) { return; }
			ticking = true;
			requestAnimationFrame(function () { updateTop(); setActive(); ticking = false; });
		}
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll);
		updateTop();
		setActive();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', build);
	} else {
		build();
	}
	window.addEventListener('load', build); // si la carte est rendue plus tard
})();
JS;

	wp_register_script( 'tdr-menu-categories', false, array(), '1.0.0', true );
	wp_enqueue_script( 'tdr-menu-categories' );
	wp_add_inline_script( 'tdr-menu-categories', 'window.tdrMenu = ' . wp_json_encode( $config ) . ';', 'before' );
	wp_add_inline_script( 'tdr-menu-categories', $js );
} );
