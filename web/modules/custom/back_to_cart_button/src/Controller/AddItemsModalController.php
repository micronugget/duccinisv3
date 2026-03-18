<?php

declare(strict_types=1);

namespace Drupal\back_to_cart_button\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\views\Views;

/**
 * Controller for the "Add Items" inline menu modal.
 *
 * Issue #11.1 / rework: instead of navigating away to /menu, this controller
 * renders the 'menu_complete' display of the product_variations view and
 * returns it inside a full-screen Bootstrap-sized dialog. Commerce's
 * add-to-cart AJAX still works inside the dialog because the form actions
 * are rendered normally; the cart block refreshes via the
 * back_to_cart_button/add-items-modal JS behavior on close.
 */
class AddItemsModalController extends ControllerBase {

  /**
   * Renders the full menu inside an AJAX dialog.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response containing an OpenDialogCommand.
   */
  public function modal(): AjaxResponse {
    $view = Views::getView('product_variations');

    if (!$view) {
      // Fallback: return a simple link to /menu if the view is not available.
      $content = ['#markup' => '<p><a href="/menu">Browse the full menu →</a></p>'];
    }
    else {
      $view->setDisplay('menu_complete');
      $view->preExecute();
      $view->execute();
      $content = $view->buildRenderable('menu_complete');
      // Force no cache inside the dialog so add-to-cart state is fresh.
      $content['#cache']['max-age'] = 0;
    }

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $html = $renderer->renderRoot($content);

    $response = new AjaxResponse();
    $response->addCommand(new OpenDialogCommand(
      '#add-items-modal',
      $this->t('Add Items to Your Order'),
      (string) $html,
      [
        'width'    => '95vw',
        'maxWidth' => '1100px',
        'height'   => '85vh',
        'dialogClass' => 'add-items-dialog',
        'modal'    => TRUE,
      ]
    ));

    return $response;
  }

}
