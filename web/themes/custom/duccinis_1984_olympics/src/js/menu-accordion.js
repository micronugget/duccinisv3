(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.menuAccordion = {
    attach: function (context) {
      // Handle pre-built accordion structures (from grouped Views templates)
      once('menu-accordion', '.menu-accordion', context).forEach(function () {
        // Bootstrap 5 accordion behavior is driven by data attributes.
        // This behavior is intentionally minimal as a future hook point.
      });

      // Transform Layout Builder menu blocks into accordion
      once('menu-accordion-transform', '.layout', context).forEach(function (layout) {
        // Find all product variation view blocks within this layout
        var menuBlocks = layout.querySelectorAll('[class*="block-views-blockproduct-variations-"]');

        if (menuBlocks.length === 0) {
          return;
        }

        // Create accordion container
        var accordion = document.createElement('div');
        accordion.id = 'menu-blocks-accordion';
        accordion.className = 'accordion menu-accordion';

        // Get the parent of the first block to insert accordion
        var firstBlock = menuBlocks[0];
        var parent = firstBlock.parentNode;

        // Insert accordion before the first block
        parent.insertBefore(accordion, firstBlock);

        // Transform each block into an accordion item
        menuBlocks.forEach(function (block, index) {
          var titleEl = block.querySelector('.block__title');
          var contentEl = block.querySelector('.block__content');

          if (!titleEl || !contentEl) {
            return;
          }

          var title = titleEl.textContent.trim();
          var safeId = 'menu-item-' + index;
          var headingId = 'heading-' + safeId;
          var collapseId = 'collapse-' + safeId;

          // Create accordion item
          var item = document.createElement('div');
          item.className = 'accordion-item menu-accordion__item';

          // Create accordion header
          var header = document.createElement('h2');
          header.className = 'accordion-header menu-accordion__header';
          header.id = headingId;

          // Create accordion button
          var button = document.createElement('button');
          button.className = 'accordion-button collapsed menu-accordion__button';
          button.type = 'button';
          button.setAttribute('data-bs-toggle', 'collapse');
          button.setAttribute('data-bs-target', '#' + collapseId);
          button.setAttribute('aria-expanded', 'false');
          button.setAttribute('aria-controls', collapseId);
          button.textContent = title;

          header.appendChild(button);
          item.appendChild(header);

          // Create collapse container
          var collapse = document.createElement('div');
          collapse.id = collapseId;
          collapse.className = 'accordion-collapse collapse menu-accordion__collapse';
          collapse.setAttribute('aria-labelledby', headingId);
          collapse.setAttribute('data-bs-parent', '#menu-blocks-accordion');

          // Create accordion body and move content
          var body = document.createElement('div');
          body.className = 'accordion-body menu-accordion__body';

          // Clone the content and append to body
          while (contentEl.firstChild) {
            body.appendChild(contentEl.firstChild);
          }

          collapse.appendChild(body);
          item.appendChild(collapse);

          // Add item to accordion
          accordion.appendChild(item);

          // Remove original block
          block.parentNode.removeChild(block);
        });

        // Style the rows within the accordion
        styleAccordionRows(accordion);
      });

      /**
       * Style accordion rows: wrap prices in spans and add Bootstrap button classes.
       */
      function styleAccordionRows(accordion) {
        var rows = accordion.querySelectorAll('.views-row');

        rows.forEach(function (row) {
          // Add Bootstrap button classes to "Add to cart" links
          var cartLinks = row.querySelectorAll('a[href^="/add-to-cart"]');
          cartLinks.forEach(function (link) {
            link.classList.add('btn', 'btn-sm', 'btn-primary');
          });

          // Wrap price text (matches $XX.XX pattern) in a span with bold styling
          // Process text nodes to find and wrap prices
          var childNodes = Array.from(row.childNodes);
          childNodes.forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE) {
              var text = node.textContent;
              // Match price pattern like $1.29, $59.95, etc.
              var priceMatch = text.match(/(\$\d+\.\d{2})/);
              if (priceMatch) {
                var parts = text.split(priceMatch[1]);
                var fragment = document.createDocumentFragment();

                if (parts[0]) {
                  // Text before price (variation title)
                  var titleSpan = document.createElement('span');
                  titleSpan.className = 'variation-title';
                  titleSpan.textContent = parts[0].trim();
                  fragment.appendChild(titleSpan);
                  fragment.appendChild(document.createTextNode(' '));
                }

                // Price wrapped in span
                var priceSpan = document.createElement('span');
                priceSpan.className = 'variation-price';
                priceSpan.textContent = priceMatch[1];
                fragment.appendChild(priceSpan);

                if (parts[1] && parts[1].trim()) {
                  fragment.appendChild(document.createTextNode(' '));
                  fragment.appendChild(document.createTextNode(parts[1]));
                }

                node.parentNode.replaceChild(fragment, node);
              }
            }
          });
        });
      }
    }
  };
})(Drupal, once);
