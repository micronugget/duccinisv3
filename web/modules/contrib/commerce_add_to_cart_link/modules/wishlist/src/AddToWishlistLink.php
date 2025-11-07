<?php

namespace Drupal\commerce_add_to_wishlist_link;

use Drupal\commerce_add_to_cart_link\AddToCartLink;
use Drupal\Core\Url;

/**
 * Defines a helper class for constructing add to wishlist links.
 */
class AddToWishlistLink extends AddToCartLink {

  /**
   * Generate a render array for an add-to-wishlist link.
   *
   * @return array
   *   The render array.
   */
  public function build(): array {
    $build = [
      '#theme' => 'commerce_add_to_wishlist_link',
      '#url' => $this->url(),
      '#product_variation' => $this->variation,
    ];
    $metadata = $this->metadata();
    $metadata->applyTo($build);
    return $build;
  }

  /**
   * Generate a URL object for an add-to-wishlist link.
   *
   * @return \Drupal\Core\Url
   *   The URL object.
   */
  public function url(): Url {
    return Url::fromRoute('commerce_add_to_wishlist_link.page',
      [
        'commerce_product' => $this->variation->getProductId(),
        'commerce_product_variation' => $this->variation->id(),
        'token' => $this->cartLinkToken->generate($this->variation),
      ]
    );
  }

}
