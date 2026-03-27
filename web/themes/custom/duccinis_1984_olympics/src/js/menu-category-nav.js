/**
 * @file
 * menu-category-nav.js
 *
 * Drupal behavior that builds a sticky horizontal pill nav from the menu
 * accordion category headings and wires up scroll-spy + click-to-expand.
 *
 * Only active at ≤ 860px (mobile/tablet). At desktop the full accordion is
 * visible and the nav is hidden via CSS.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.menuCategoryNav = {
    attach(context) {
      // Guard: only run once per accordion wrapper.
      const accordions = once(
        'menu-category-nav',
        '.menu-accordion',
        context,
      );
      if (!accordions.length) {
        return;
      }

      accordions.forEach(function (accordion) {
        const buttons = accordion.querySelectorAll('.menu-accordion__button');
        if (!buttons.length) {
          return;
        }

        // ── Build nav ──────────────────────────────────────────────────────
        const nav = document.createElement('nav');
        nav.className = 'menu-category-nav';
        nav.setAttribute('aria-label', 'Menu categories');

        const track = document.createElement('div');
        track.className = 'menu-category-nav__track';
        nav.appendChild(track);

        buttons.forEach(function (btn, idx) {
          const pill = document.createElement('button');
          pill.className = 'menu-category-nav__pill';
          pill.type = 'button';
          pill.textContent = btn.textContent.trim();
          pill.dataset.navIndex = idx;

          pill.addEventListener('click', function () {
            // Scroll accordion button into view.
            btn.scrollIntoView({ behavior: 'smooth', block: 'start' });

            // Expand the collapse panel if it is currently collapsed.
            const targetId = btn.getAttribute('data-bs-target') ||
              btn.getAttribute('href');
            if (targetId) {
              const panel = document.querySelector(targetId);
              if (panel && panel.classList.contains('collapse') &&
                  !panel.classList.contains('show')) {
                // Bootstrap 5 collapse API.
                if (window.bootstrap && window.bootstrap.Collapse) {
                  window.bootstrap.Collapse.getOrCreateInstance(panel).show();
                }
                else {
                  panel.classList.add('show');
                }
              }
            }

            setActive(pill);
            scrollPillIntoView(pill);
          });

          track.appendChild(pill);
        });

        accordion.insertAdjacentElement('beforebegin', nav);

        // ── Scroll-spy via IntersectionObserver ────────────────────────────
        const pills = track.querySelectorAll('.menu-category-nav__pill');

        const observer = new IntersectionObserver(
          function (entries) {
            entries.forEach(function (entry) {
              if (entry.isIntersecting) {
                const idx = Array.from(buttons).indexOf(entry.target);
                if (idx !== -1 && pills[idx]) {
                  setActive(pills[idx]);
                  scrollPillIntoView(pills[idx]);
                }
              }
            });
          },
          { threshold: 0.4 },
        );

        buttons.forEach(function (btn) {
          observer.observe(btn);
        });

        // Activate first pill on load.
        if (pills[0]) {
          setActive(pills[0]);
        }
      });

      // ── Helpers ────────────────────────────────────────────────────────
      function setActive(activePill) {
        const track = activePill.closest('.menu-category-nav__track');
        if (!track) { return; }
        track.querySelectorAll('.menu-category-nav__pill').forEach(function (p) {
          p.classList.remove('active');
          p.removeAttribute('aria-current');
        });
        activePill.classList.add('active');
        activePill.setAttribute('aria-current', 'true');
      }

      function scrollPillIntoView(pill) {
        const nav = pill.closest('.menu-category-nav');
        if (!nav) { return; }
        const navLeft = nav.scrollLeft;
        const navWidth = nav.offsetWidth;
        const pillLeft = pill.offsetLeft;
        const pillWidth = pill.offsetWidth;
        if (pillLeft < navLeft || pillLeft + pillWidth > navLeft + navWidth) {
          nav.scrollTo({
            left: pillLeft - navWidth / 2 + pillWidth / 2,
            behavior: 'smooth',
          });
        }
      }
    },
  };
}(Drupal, once));
