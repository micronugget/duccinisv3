/**
 * @file
 * Diagnostic behavior for payment method AJAX radio buttons.
 *
 * Attach this library temporarily to the checkout form to diagnose
 * why "Use a different card" clicks do not trigger AJAX.
 *
 * Remove after debugging is complete.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  const DIAG_PREFIX = '[PaymentAjaxDiag]';
  var panelEl = null;
  var logBuffer = [];

  /**
   * Appends a line to the on-page diagnostic panel.
   */
  function panelWrite(cls, text) {
    logBuffer.push({ cls: cls, text: text });
    if (panelEl) {
      var line = document.createElement('div');
      line.className = 'diag-line diag-' + cls;
      line.textContent = text;
      panelEl.querySelector('.diag-body').appendChild(line);
    }
  }

  /**
   * Creates the visible diagnostic panel at the top of the checkout form.
   */
  function createPanel() {
    panelEl = document.createElement('div');
    panelEl.id = 'payment-ajax-diag-panel';
    panelEl.innerHTML =
      '<style>' +
      '#payment-ajax-diag-panel{background:#1a1a2e;color:#e0e0e0;font-family:monospace;font-size:12px;' +
      'padding:12px;margin:10px 0;border:2px solid #e94560;border-radius:6px;max-height:500px;overflow-y:auto;}' +
      '#payment-ajax-diag-panel h3{color:#e94560;margin:0 0 8px 0;font-size:14px;}' +
      '.diag-line{padding:1px 0;white-space:pre-wrap;word-break:break-all;}' +
      '.diag-log{color:#a8d8ea;}.diag-warn{color:#f5a623;}.diag-error{color:#e94560;font-weight:bold;}' +
      '.diag-pass{color:#0cce6b;}.diag-event{color:#c39bd3;}' +
      '</style>' +
      '<h3>🔍 Payment AJAX Diagnostic Panel</h3>' +
      '<div class="diag-body"></div>';

    var target = document.getElementById('edit-payment-information');
    if (target) {
      target.parentNode.insertBefore(panelEl, target);
    } else {
      var form = document.querySelector('form');
      if (form) form.prepend(panelEl);
    }

    // Flush buffered lines
    logBuffer.forEach(function (item) {
      var line = document.createElement('div');
      line.className = 'diag-line diag-' + item.cls;
      line.textContent = item.text;
      panelEl.querySelector('.diag-body').appendChild(line);
    });
  }

  /**
   * Logs a labeled diagnostic message to the console and panel.
   */
  function log(label, ...args) {
    var text = label + ' ' + args.map(function (a) {
      return typeof a === 'object' ? JSON.stringify(a) : String(a);
    }).join(' ');
    console.log(`${DIAG_PREFIX} ${label}`, ...args);
    panelWrite('log', text);
  }

  function warn(label, ...args) {
    var text = label + ' ' + args.map(function (a) {
      return typeof a === 'object' ? JSON.stringify(a) : String(a);
    }).join(' ');
    console.warn(`${DIAG_PREFIX} ${label}`, ...args);
    panelWrite('warn', text);
  }

  function error(label, ...args) {
    var text = label + ' ' + args.map(function (a) {
      return typeof a === 'object' ? JSON.stringify(a) : String(a);
    }).join(' ');
    console.error(`${DIAG_PREFIX} ${label}`, ...args);
    panelWrite('error', text);
  }

  /**
   * Check 1: drupalSettings.ajax keys for the payment radios.
   */
  function checkAjaxSettings() {
    log('── Check 1: drupalSettings.ajax ──');

    const ajax = drupalSettings.ajax || {};
    const keys = Object.keys(ajax);
    const paymentKeys = keys.filter(function (k) {
      return k.indexOf('payment-method') !== -1;
    });

    if (paymentKeys.length === 0) {
      error('NO payment-method keys found in drupalSettings.ajax');
      log('All ajax keys:', keys);
      return false;
    }

    log('Found payment-method ajax keys:', paymentKeys);
    paymentKeys.forEach(function (k) {
      log('  Key:', k, '→', {
        event: ajax[k].event,
        wrapper: ajax[k].wrapper,
        url: ajax[k].url,
        selector: ajax[k].selector,
        callback: ajax[k].callback,
      });
    });
    return paymentKeys;
  }

  /**
   * Check 2: Do the radio DOM elements actually exist?
   */
  function checkDomElements(paymentKeys) {
    log('── Check 2: DOM element existence ──');

    if (!paymentKeys) return false;

    const results = {};
    paymentKeys.forEach(function (k) {
      const ajax = drupalSettings.ajax[k];
      const selector = ajax.selector || ('#' + k);
      const el = document.querySelector(selector);
      results[k] = {
        selector: selector,
        found: !!el,
        tagName: el ? el.tagName : null,
        type: el ? el.type : null,
        checked: el ? el.checked : null,
        value: el ? el.value : null,
        name: el ? el.name : null,
        parentClasses: el ? el.parentElement.className : null,
      };
      if (el) {
        log('  ✓', k, '→ found:', el.tagName, 'type=' + el.type, 'value=' + el.value);
      } else {
        error('  ✗', k, '→ NOT FOUND with selector:', selector);
      }
    });
    return results;
  }

  /**
   * Check 3: Has once('drupal-ajax') been applied?
   */
  function checkOnceAttribute(paymentKeys) {
    log('── Check 3: once("drupal-ajax") binding status ──');

    if (!paymentKeys) return;

    paymentKeys.forEach(function (k) {
      const ajax = drupalSettings.ajax[k];
      const selector = ajax.selector || ('#' + k);
      const el = document.querySelector(selector);
      if (!el) return;

      const onceAttr = el.getAttribute('data-once');
      if (onceAttr && onceAttr.indexOf('drupal-ajax') !== -1) {
        log('  ✓', k, '→ data-once contains "drupal-ajax":', onceAttr);
      } else {
        warn('  ✗', k, '→ data-once:', onceAttr || '(not set)', '— AJAX behavior may NOT be bound!');
      }
    });
  }

  /**
   * Check 4: Look for Drupal.ajax.instances entries for our elements.
   */
  function checkAjaxInstances(paymentKeys) {
    log('── Check 4: Drupal.ajax.instances ──');

    if (!Drupal.ajax || !Drupal.ajax.instances) {
      error('Drupal.ajax.instances does not exist!');
      return;
    }

    const totalInstances = Drupal.ajax.instances.length;
    log('Total Drupal.ajax.instances:', totalInstances);

    const paymentInstances = Drupal.ajax.instances.filter(function (inst) {
      if (!inst || !inst.element) return false;
      return inst.element.name && inst.element.name.indexOf('payment_method') !== -1;
    });

    if (paymentInstances.length === 0) {
      error('NO Drupal.ajax instances found for payment_method elements!');
      // List all valid instances for debugging
      const validInstances = Drupal.ajax.instances.filter(function (inst) { return inst !== null; });
      log('Valid instances:', validInstances.length);
      validInstances.forEach(function (inst, i) {
        if (inst.element) {
          log('  Instance', i, '→ element:', inst.element.tagName, 'id=' + inst.element.id, 'event=' + inst.event);
        }
      });
    } else {
      log('Payment method AJAX instances:', paymentInstances.length);
      paymentInstances.forEach(function (inst) {
        log('  Instance:', {
          id: inst.element.id,
          event: inst.event,
          url: inst.url,
          wrapper: inst.wrapper,
          ajaxing: inst.ajaxing,
        });
      });
    }

    return paymentInstances;
  }

  /**
   * Check 5: Test label-to-input association.
   */
  function checkLabelAssociation(paymentKeys) {
    log('── Check 5: Label <-> Input association ──');

    if (!paymentKeys) return;

    paymentKeys.forEach(function (k) {
      const ajax = drupalSettings.ajax[k];
      const selector = ajax.selector || ('#' + k);
      const input = document.querySelector(selector);
      if (!input) return;

      const inputId = input.id;
      const labels = document.querySelectorAll('label[for="' + inputId + '"]');

      if (labels.length === 0) {
        error('  ✗', k, '→ No <label for="' + inputId + '"> found!');
      } else if (labels.length > 1) {
        warn('  ⚠', k, '→ MULTIPLE labels for "' + inputId + '":', labels.length);
        labels.forEach(function (l) { log('    Label:', l.outerHTML.substring(0, 150)); });
      } else {
        log('  ✓', k, '→ Label found:', labels[0].className, 'text:', labels[0].textContent.trim().substring(0, 50));
      }

      // Check if there's ALSO a Drupal-generated label that might intercept
      const formElement = input.closest('.js-form-item, .form-item, .saved-card-item');
      if (formElement) {
        const allLabels = formElement.querySelectorAll('label');
        if (allLabels.length > 1) {
          warn('  ⚠', k, '→', allLabels.length, 'labels in container:');
          allLabels.forEach(function (l) {
            log('    for=' + l.getAttribute('for'), 'class=' + l.className, 'text=' + l.textContent.trim().substring(0, 40));
          });
        }
      }
    });
  }

  /**
   * Check 6: Instrument event listeners to see what fires.
   */
  function instrumentEvents(paymentKeys) {
    log('── Check 6: Instrumenting event listeners ──');

    if (!paymentKeys) return;

    paymentKeys.forEach(function (k) {
      const ajax = drupalSettings.ajax[k];
      const selector = ajax.selector || ('#' + k);
      const input = document.querySelector(selector);
      if (!input) return;

      // Add native listeners to track events
      ['click', 'change', 'mousedown', 'mouseup', 'focus', 'input'].forEach(function (eventType) {
        input.addEventListener(eventType, function (e) {
          var msg = '🔔 ' + eventType + ' on ' + input.id +
            ' | checked:' + input.checked +
            ' | defaultPrevented:' + e.defaultPrevented +
            ' | target:' + e.target.tagName + '#' + e.target.id;
          console.log(DIAG_PREFIX, msg);
          panelWrite('event', msg);
        }, true); // Use capture phase to see it before jQuery
      });

      // Also instrument the label
      var label = document.querySelector('label[for="' + input.id + '"]');
      if (label) {
        ['click', 'mousedown', 'mouseup'].forEach(function (eventType) {
          label.addEventListener(eventType, function (e) {
            var msg = '🏷️ LABEL ' + eventType + ' on label[for=' + input.id + ']' +
              ' | target:' + e.target.tagName + '.' + (e.target.className || '').split(' ')[0];
            console.log(DIAG_PREFIX, msg);
            panelWrite('event', msg);
          }, true);
        });
      }

      log('  Instrumented:', input.id);
    });

    log('');
    log('🎯 NOW CLICK "Use a different card" and watch for 🔔 and 🏷️ events above.');
    log('   If no events fire, something is blocking the click (CSS overlay, pointer-events, etc.).');
    log('   If change fires but no AJAX, the Drupal.ajax binding is missing.');
  }

  /**
   * Check 7: CSS computed styles that might block interaction.
   */
  function checkCssBlocking(paymentKeys) {
    log('── Check 7: CSS blocking checks ──');

    if (!paymentKeys) return;

    paymentKeys.forEach(function (k) {
      const ajax = drupalSettings.ajax[k];
      const selector = ajax.selector || ('#' + k);
      const input = document.querySelector(selector);
      if (!input) return;

      const styles = window.getComputedStyle(input);
      const issues = [];

      if (styles.display === 'none') issues.push('display:none');
      if (styles.visibility === 'hidden') issues.push('visibility:hidden');
      if (styles.pointerEvents === 'none') issues.push('pointer-events:none');
      if (styles.opacity === '0') issues.push('opacity:0');
      if (parseFloat(styles.width) === 0 && parseFloat(styles.height) === 0) issues.push('0x0 size');

      const label = document.querySelector('label[for="' + input.id + '"]');
      if (label) {
        const labelStyles = window.getComputedStyle(label);
        if (labelStyles.display === 'none') issues.push('LABEL display:none');
        if (labelStyles.pointerEvents === 'none') issues.push('LABEL pointer-events:none');
        if (labelStyles.visibility === 'hidden') issues.push('LABEL visibility:hidden');

        // Check if something is on top of the label
        const rect = label.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0) {
          const centerX = rect.left + rect.width / 2;
          const centerY = rect.top + rect.height / 2;
          const topEl = document.elementFromPoint(centerX, centerY);
          if (topEl !== label && !label.contains(topEl)) {
            warn('  ⚠', k, '→ Label is obscured by:', topEl.tagName + '.' + topEl.className, '— clicks may not reach label!');
            issues.push('LABEL obscured by ' + topEl.tagName);
          } else {
            log('  ✓', k, '→ Label is topmost at center point');
          }
        } else {
          warn('  ⚠', k, '→ Label has zero bounding rect', rect);
        }
      }

      if (issues.length > 0) {
        warn('  ⚠', k, '→ Potential CSS issues:', issues.join(', '));
      } else {
        log('  ✓', k, '→ No CSS blocking detected on input');
      }
    });
  }

  /**
   * Check 8: jQuery event handlers on the radio inputs.
   */
  function checkJqueryHandlers(paymentKeys) {
    log('── Check 8: jQuery event handlers ──');

    if (!paymentKeys || typeof jQuery === 'undefined') {
      warn('jQuery not available');
      return;
    }

    paymentKeys.forEach(function (k) {
      const ajax = drupalSettings.ajax[k];
      const selector = ajax.selector || ('#' + k);
      const $el = jQuery(selector);

      if ($el.length === 0) {
        error('  ✗', k, '→ jQuery selector found 0 elements');
        return;
      }

      // Get jQuery internal events
      const events = jQuery._data($el[0], 'events') || {};
      const eventTypes = Object.keys(events);

      if (eventTypes.length === 0) {
        error('  ✗', k, '→ NO jQuery event handlers bound!');
      } else {
        log('  ✓', k, '→ jQuery events:', eventTypes.join(', '));
        eventTypes.forEach(function (type) {
          events[type].forEach(function (handler, i) {
            log('    ', type, '[' + i + ']:', 'namespace=' + (handler.namespace || '(none)'), 'handler:', String(handler.handler).substring(0, 100));
          });
        });
      }
    });
  }

  /**
   * Main diagnostic behavior — runs all checks on attach.
   */
  Drupal.behaviors.paymentAjaxDiagnostic = {
    attach: function (context) {
      // Only run once on full page load, not on every AJAX response.
      if (once('payment-ajax-diagnostic', 'body', context).length === 0) {
        return;
      }

      log('');
      log('╔══════════════════════════════════════════════════╗');
      log('║  PAYMENT AJAX DIAGNOSTIC — Starting checks...   ║');
      log('╚══════════════════════════════════════════════════╝');
      log('');

      createPanel();

      var paymentKeys = checkAjaxSettings();
      checkDomElements(paymentKeys);
      checkOnceAttribute(paymentKeys);
      checkAjaxInstances(paymentKeys);
      checkLabelAssociation(paymentKeys);
      checkCssBlocking(paymentKeys);
      checkJqueryHandlers(paymentKeys);
      instrumentEvents(paymentKeys);

      log('');
      log('╔══════════════════════════════════════════════════╗');
      log('║  DIAGNOSTIC COMPLETE — Review output above.     ║');
      log('║  Then click "Use a different card" and check     ║');
      log('║  for 🔔 event messages.                          ║');
      log('╚══════════════════════════════════════════════════╝');
      log('');

      // Expose a manual re-check function on window for console use.
      window.paymentDiag = {
        recheck: function () {
          var keys = checkAjaxSettings();
          checkDomElements(keys);
          checkOnceAttribute(keys);
          checkAjaxInstances(keys);
          checkJqueryHandlers(keys);
        },
        forceClick: function () {
          var el = document.querySelector('#edit-payment-information-payment-method-stripe');
          if (el) {
            log('Force-clicking radio...');
            el.checked = true;
            el.dispatchEvent(new Event('change', { bubbles: true }));
            log('change event dispatched, checked:', el.checked);
          } else {
            error('Stripe radio not found');
          }
        },
        jqueryTrigger: function () {
          if (typeof jQuery !== 'undefined') {
            log('jQuery triggering change on stripe radio...');
            jQuery('#edit-payment-information-payment-method-stripe').prop('checked', true).trigger('change');
          }
        }
      };
      log('Manual helpers available: window.paymentDiag.recheck(), .forceClick(), .jqueryTrigger()');
    },
  };

})(Drupal, drupalSettings, once);
