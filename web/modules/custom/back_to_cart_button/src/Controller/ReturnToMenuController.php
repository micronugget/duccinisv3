<?php

namespace Drupal\back_to_cart_button\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for handling the Return to Menu AJAX request.
 */
class ReturnToMenuController extends ControllerBase {

  /**
   * The block manager service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a ReturnToMenuController object.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(BlockManagerInterface $block_manager, RendererInterface $renderer) {
    $this->blockManager = $block_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('renderer')
    );
  }

  /**
   * Handles the AJAX request to return to menu.
   *
   * Refreshes the cart block and redirects to the menu page.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function returnToMenu() {
    $response = new AjaxResponse();

    // Refresh the cart block.
    try {
      $cart_block = $this->blockManager->createInstance('commerce_cart', []);
      $cart_render = $cart_block->build();
      $cart_render['#cache']['max-age'] = 0;
      $cart_html = $this->renderer->renderRoot($cart_render);

      // Replace the cart block content (using multiple selectors for compatibility).
      $response->addCommand(new ReplaceCommand(
        '.block-commerce-cart, #block-cart, [data-block-plugin-id="commerce_cart"]',
        $cart_html
      ));
    }
    catch (\Exception $e) {
      // Log the error but continue with redirect.
      \Drupal::logger('back_to_cart_button')->error('Failed to refresh cart block: @message', ['@message' => $e->getMessage()]);
    }

    // Redirect to the menu page.
    $response->addCommand(new RedirectCommand('/menu'));

    return $response;
  }

}
