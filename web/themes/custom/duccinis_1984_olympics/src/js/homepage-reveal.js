/**
 * @file
 * homepage-reveal.js
 *
 * Two Drupal behaviors for the homepage:
 *  1. homepageReveal  — IntersectionObserver scroll-reveal (.reveal → .in-view)
 *  2. navScroll       — passive scroll listener toggling .scrolled on .site-nav
 */

(function (Drupal) {
  'use strict';

  /**
   * Scroll-reveal behavior.
   *
   * Observes all .reveal elements not yet marked .in-view and adds .in-view
   * when they cross the 8 % viewport threshold. Idempotent across AJAX rebuilds
   * because elements already carrying .in-view are excluded via :not(.in-view).
   */
  Drupal.behaviors.homepageReveal = {
    attach(context) {
      const els = context.querySelectorAll('.reveal:not(.in-view)');
      if (!els.length) {
        return;
      }

      const obs = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            e.target.classList.add('in-view');
            obs.unobserve(e.target);
          }
        });
      }, { threshold: 0.08 });

      els.forEach((el) => obs.observe(el));
    },
  };

  /**
   * Nav scroll behavior.
   *
   * Adds .scrolled to .site-nav when the page is scrolled past 60 px,
   * brightening the nav border-bottom as defined in _homepage.scss.
   *
   * A dataset guard prevents duplicate listeners when attach() is called
   * multiple times (e.g. on AJAX-rebuilt pages).
   */
  Drupal.behaviors.navScroll = {
    attach(context) {
      const nav =
        context.querySelector('.site-nav') ||
        document.querySelector('.site-nav');

      if (!nav || nav.dataset.navScrollAttached === '1') {
        return;
      }

      nav.dataset.navScrollAttached = '1';

      window.addEventListener(
        'scroll',
        () => {
          nav.classList.toggle('scrolled', window.scrollY > 60);
        },
        { passive: true },
      );
    },
  };
}(Drupal));
