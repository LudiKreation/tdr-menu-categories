<?php
/**
 * Plugin Name: Taverne du Roy – Menu de catégories (page Commander)
 * Description: Barre de catégories au-dessus de la carte Orderable, qui s'accroche sous l'en-tête du site au défilement. Défilement doux vers la catégorie, catégorie en cours mise en évidence. Toute la carte reste affichée.
 * Version: 1.2.1
 */

defined( 'ABSPATH' ) || exit;

/* ==========================================================================
 * Réglages
 * ========================================================================== */

/** Slug(s) des pages où afficher la barre. */
function tdr_menu_pages(): array {
	return array( 'click-collect-de-la-taverne-du-roy' );
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
		.tdr-catnav-wrap { margin: 0 0 1.5em; }
		.tdr-catnav { z-index: 9990; background: transparent; }
		.tdr-catnav.is-pinned a.tdr-catnav__link { box-shadow: 0 2px 6px rgba(0,0,0,.15); }
		.tdr-catnav__list {
			display: flex; gap: .4em;
			margin: 0; padding: .6em .25em;
			list-style: none;
			overflow-x: auto; white-space: nowrap;
			scrollbar-width: none; -webkit-overflow-scrolling: touch;
		}
		.tdr-catnav__list::-webkit-scrollbar { display: none; }
		.tdr-catnav__list li { margin: 0; padding: 0; flex: 0 0 auto; list-style: none; }
		.tdr-catnav__list li::before { display: none; }
		a.tdr-catnav__link {
			display: inline-block; padding: .45em 1em;
			border: 1px solid #B99272; border-radius: 999px; background: #fff;
			color: #000; font-size: .9em; font-weight: 600; line-height: 1.2; text-decoration: none;
			transition: background-color .15s, color .15s;
		}
		a.tdr-catnav__link:hover, a.tdr-catnav__link:focus { background: #f6efe9; color: #000; text-decoration: none; }
		a.tdr-catnav__link.is-active { background: #B99272; color: #fff; }
	</style>
	<?php
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! tdr_menu_is_target_page() ) {
		return;
	}

	$js = <<<'JS'
(function () {
	function build() {
		if (document.querySelector('.tdr-catnav')) { return; }

		var main = document.querySelector('.orderable-main');
		if (!main) { return; }
		var heads = Array.prototype.slice.call(main.querySelectorAll('.orderable-category-heading__title'));
		if (heads.length < 2) { return; }

		/* ---------- Construction : juste au-dessus de la première catégorie ---------- */

		var wrap = document.createElement('div');
		wrap.className = 'tdr-catnav-wrap';
		var nav = document.createElement('nav');
		nav.className = 'tdr-catnav';
		nav.setAttribute('aria-label', 'Catégories de la carte');
		var ul = document.createElement('ul');
		ul.className = 'tdr-catnav__list';

		heads.forEach(function (h, i) {
			var group = h.closest('.orderable-main__group');
			var id = (group && group.id) || h.id || ('tdr-cat-' + i);
			if (!group && !h.id) { h.id = id; }
			var li = document.createElement('li');
			var a = document.createElement('a');
			a.className = 'tdr-catnav__link';
			a.href = '#' + id;
			a.textContent = h.textContent.trim();
			li.appendChild(a);
			ul.appendChild(li);
		});
		nav.appendChild(ul);
		wrap.appendChild(nav);

		var sections = main.querySelector('.orderable-main__sections');
		main.insertBefore(wrap, sections || main.firstChild);

		var links = Array.prototype.slice.call(nav.querySelectorAll('a'));

		/* ---------- Hauteur occupée en haut d'écran par les éléments fixes (barre d'admin, en-tête) ---------- */

		function fixedAncestor(el) {
			for (var n = el; n && n !== document.body && n !== document.documentElement; n = n.parentElement) {
				var pos = getComputedStyle(n).position;
				if (pos === 'fixed' || pos === 'sticky') { return n; }
			}
			return null;
		}

		function fixedTop() {
			var y = 0;
			var candidates = Array.prototype.slice.call(document.querySelectorAll(
				'#wpadminbar, .kf-header, .kf-navbar, .kf-topline, header, [data-elementor-type="header"], .elementor-sticky--active'
			));
			// Éléments réellement présents en haut de l'écran (3 points sur la largeur).
			for (var pass = 0; pass < 5; pass++) {
				var found = y;
				[0.15, 0.5, 0.85].forEach(function (fx) {
					document.elementsFromPoint(Math.round(window.innerWidth * fx), y + 1).forEach(function (el) {
						if (!el.closest('.tdr-catnav')) { candidates.push(el); }
					});
				});
				candidates.forEach(function (el) {
					var f = fixedAncestor(el);
					if (!f || f.closest('.tdr-catnav')) { return; }
					var r = f.getBoundingClientRect();
					var st = getComputedStyle(f);
					if (st.display === 'none' || st.visibility === 'hidden' || parseFloat(st.opacity) === 0) { return; }
					if (r.top <= y + 1 && r.bottom > y + 1 && r.height > 0 && r.height < window.innerHeight / 2) {
						found = Math.max(found, r.bottom);
					}
				});
				if (found > y) { y = found; } else { break; }
			}
			return Math.round(y);
		}

		/* ---------- Accroche : la barre se fixe quand elle atteint le bas de l'en-tête ---------- */

		var pinned = false;

		function unpin() {
			if (!pinned) { return; }
			pinned = false;
			nav.classList.remove('is-pinned');
			nav.style.position = nav.style.top = nav.style.left = nav.style.width = '';
			wrap.appendChild(nav);
			wrap.style.height = '';
		}

		function updatePin(top) {
			var w = wrap.getBoundingClientRect();
			var m = main.getBoundingClientRect();
			var h = nav.offsetHeight;
			var shouldPin = w.top <= top && m.bottom - h > top;
			if (shouldPin) {
				if (!pinned) {
					wrap.style.height = h + 'px';
					document.body.appendChild(nav);
				}
				pinned = true;
				nav.classList.add('is-pinned');
				nav.style.position = 'fixed';
				nav.style.top = top + 'px';
				nav.style.left = w.left + 'px';
				nav.style.width = w.width + 'px';
			} else {
				unpin();
			}
		}

		/* ---------- Catégorie en cours ---------- */

		function setActive(top) {
			var limit = top + nav.offsetHeight + 20, current = 0;
			heads.forEach(function (h, i) { if (h.getBoundingClientRect().top <= limit) { current = i; } });
			links.forEach(function (a, i) {
				var on = i === current;
				if (on && !a.classList.contains('is-active') && pinned) {
					ul.scrollTo({ left: a.offsetLeft - ul.clientWidth / 2 + a.offsetWidth / 2, behavior: 'smooth' });
				}
				a.classList.toggle('is-active', on);
				if (on) { a.setAttribute('aria-current', 'true'); } else { a.removeAttribute('aria-current'); }
			});
		}

		/* ---------- Clic : défilement doux sous l'en-tête et la barre ---------- */

		links.forEach(function (a, i) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				var target = heads[i].closest('.orderable-main__group') || heads[i];
				var y = target.getBoundingClientRect().top + window.pageYOffset - fixedTop() - nav.offsetHeight - 10;
				window.scrollTo({ top: y, behavior: 'smooth' });
				if (history.replaceState) { history.replaceState(null, '', a.hash); }
			});
		});

		var ticking = false;
		function refresh() {
			if (ticking) { return; }
			ticking = true;
			requestAnimationFrame(function () {
				var top = fixedTop();
				updatePin(top);
				setActive(top);
				ticking = false;
			});
		}
		window.addEventListener('scroll', refresh, { passive: true });
		window.addEventListener('resize', function () { unpin(); refresh(); });
		refresh();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', build);
	} else {
		build();
	}
	window.addEventListener('load', build);
})();
JS;

	wp_register_script( 'tdr-menu-categories', false, array(), '1.2.1', true );
	wp_enqueue_script( 'tdr-menu-categories' );
	wp_add_inline_script( 'tdr-menu-categories', $js );
} );
