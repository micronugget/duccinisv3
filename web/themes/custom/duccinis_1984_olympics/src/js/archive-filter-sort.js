/**
 * @file
 * Archive filter & sort — Drupal behavior.
 *
 * Provides client-side filtering (by category) and sorting (by name, price)
 * for accordion-based menu views and table-based order history.
 *
 * Markup contract:
 *   Filter: <button class="archive-filter-option" data-filter-value="pizza">
 *   Sort:   <button class="archive-sort-option" data-sort-value="name-asc">
 *
 * The filter hides/shows .accordion-item elements whose data-category does not
 * match the selected value. The sort reorders elements within their container.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.archiveFilterSort = {
    attach: function (context) {
      // ── Filter buttons ──
      var filterBtns = once('archive-filter', '.archive-filter-option', context);
      for (var i = 0; i < filterBtns.length; i++) {
        filterBtns[i].addEventListener('click', handleFilter);
      }

      // ── Sort buttons ──
      var sortBtns = once('archive-sort', '.archive-sort-option', context);
      for (var j = 0; j < sortBtns.length; j++) {
        sortBtns[j].addEventListener('click', handleSort);
      }
    }
  };

  /**
   * Handles category filter selection.
   *
   * @param {Event} event
   */
  function handleFilter(event) {
    var btn = event.currentTarget;
    var value = btn.getAttribute('data-filter-value');
    var dropdown = btn.closest('.archive-controls-dropdown');

    // Update active state on siblings.
    var siblings = btn.closest('.dropdown-menu').querySelectorAll('.archive-filter-option');
    for (var i = 0; i < siblings.length; i++) {
      siblings[i].classList.remove('active');
    }
    btn.classList.add('active');

    // Update dropdown button text.
    if (dropdown) {
      var textEl = dropdown.querySelector('.archive-controls-dropdown__text');
      if (textEl) {
        textEl.textContent = value === 'all' ? (textEl.getAttribute('data-default-label') || 'Filter') : btn.textContent.trim();
      }
    }

    // Filter accordion items by data-category.
    var container = btn.closest('.view') || btn.closest('.menu-accordion') || document;
    var items = container.querySelectorAll('.accordion-item[data-category], .menu-accordion__item[data-category]');

    for (var j = 0; j < items.length; j++) {
      if (value === 'all') {
        items[j].style.display = '';
      }
      else {
        var cat = items[j].getAttribute('data-category');
        items[j].style.display = cat === value ? '' : 'none';
      }
    }
  }

  /**
   * Handles sort selection.
   *
   * @param {Event} event
   */
  function handleSort(event) {
    var btn = event.currentTarget;
    var value = btn.getAttribute('data-sort-value');
    var dropdown = btn.closest('.archive-controls-dropdown');

    // Update active state on siblings.
    var siblings = btn.closest('.dropdown-menu').querySelectorAll('.archive-sort-option');
    for (var i = 0; i < siblings.length; i++) {
      siblings[i].classList.remove('active');
    }
    btn.classList.add('active');

    // Update dropdown button text.
    if (dropdown) {
      var textEl = dropdown.querySelector('.archive-controls-dropdown__text');
      if (textEl) {
        textEl.textContent = btn.textContent.trim();
      }
    }

    // Sort accordion items or table rows.
    var container = btn.closest('.view') || document;
    sortAccordionItems(container, value);
    sortTableRows(container, value);
  }

  /**
   * Sorts accordion items within .menu-accordion by data attribute.
   *
   * @param {HTMLElement} container
   * @param {string} sortKey
   */
  function sortAccordionItems(container, sortKey) {
    var accordion = container.querySelector('.menu-accordion, .accordion');
    if (!accordion) {
      return;
    }

    var items = Array.prototype.slice.call(
      accordion.querySelectorAll(':scope > .accordion-item')
    );

    if (items.length < 2) {
      return;
    }

    items.sort(function (a, b) {
      return compareItems(a, b, sortKey);
    });

    // Re-append in sorted order (DOM move, no clone).
    for (var i = 0; i < items.length; i++) {
      accordion.appendChild(items[i]);
    }
  }

  /**
   * Sorts table rows within a views table.
   *
   * @param {HTMLElement} container
   * @param {string} sortKey
   */
  function sortTableRows(container, sortKey) {
    var tbody = container.querySelector('table tbody');
    if (!tbody) {
      return;
    }

    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
    if (rows.length < 2) {
      return;
    }

    rows.sort(function (a, b) {
      return compareTableRows(a, b, sortKey);
    });

    for (var i = 0; i < rows.length; i++) {
      tbody.appendChild(rows[i]);
    }
  }

  /**
   * Compares two accordion items for sorting.
   *
   * @param {HTMLElement} a
   * @param {HTMLElement} b
   * @param {string} key  e.g. 'name-asc', 'name-desc', 'default'
   * @return {number}
   */
  function compareItems(a, b, key) {
    if (key === 'default') {
      var oa = parseInt(a.getAttribute('data-original-index') || '0', 10);
      var ob = parseInt(b.getAttribute('data-original-index') || '0', 10);
      return oa - ob;
    }

    var textA = (a.querySelector('.accordion-button') || a).textContent.trim().toLowerCase();
    var textB = (b.querySelector('.accordion-button') || b).textContent.trim().toLowerCase();

    if (key === 'name-asc') {
      return textA.localeCompare(textB);
    }
    if (key === 'name-desc') {
      return textB.localeCompare(textA);
    }

    return 0;
  }

  /**
   * Compares two table rows for sorting.
   *
   * @param {HTMLElement} a
   * @param {HTMLElement} b
   * @param {string} key  e.g. 'date-desc', 'date-asc', 'total-desc', 'total-asc'
   * @return {number}
   */
  function compareTableRows(a, b, key) {
    if (key === 'default') {
      return 0;
    }

    var colIndex;
    if (key.indexOf('date') === 0) {
      colIndex = getColumnIndex(a, 'placed') || 1;
    }
    else if (key.indexOf('total') === 0) {
      colIndex = getColumnIndex(a, 'total') || 2;
    }
    else if (key.indexOf('order') === 0) {
      colIndex = 0;
    }
    else {
      return 0;
    }

    var cellA = a.cells[colIndex];
    var cellB = b.cells[colIndex];

    if (!cellA || !cellB) {
      return 0;
    }

    var valA = cellA.textContent.trim();
    var valB = cellB.textContent.trim();

    // Try numeric comparison for prices / order numbers.
    var numA = parseFloat(valA.replace(/[^0-9.-]/g, ''));
    var numB = parseFloat(valB.replace(/[^0-9.-]/g, ''));

    var result;
    if (!isNaN(numA) && !isNaN(numB)) {
      result = numA - numB;
    }
    else {
      result = valA.localeCompare(valB);
    }

    return key.indexOf('-desc') !== -1 ? -result : result;
  }

  /**
   * Finds column index by header text (partial match).
   *
   * @param {HTMLElement} row
   * @param {string} headerText
   * @return {number|null}
   */
  function getColumnIndex(row, headerText) {
    var table = row.closest('table');
    if (!table) {
      return null;
    }
    var headers = table.querySelectorAll('thead th');
    for (var i = 0; i < headers.length; i++) {
      if (headers[i].textContent.trim().toLowerCase().indexOf(headerText) !== -1) {
        return i;
      }
    }
    return null;
  }

})(Drupal, once);
