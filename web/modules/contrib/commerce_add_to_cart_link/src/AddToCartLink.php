<?php

namespace Drupal\commerce_add_to_cart_link;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;

/**
 * Defines a helper class for constructing add to cart links.
 */
class AddToCartLink {

  /**
   * The product variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected ProductVariationInterface $variation;

  /**
   * The cart link token.
   *
   * @var \Drupal\commerce_add_to_cart_link\CartLinkTokenInterface
   */
  protected CartLinkTokenInterface $cartLinkToken;

  /**
   * Constructs a new AddToCartLink object.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The product variation.
   * @param \Drupal\commerce_add_to_cart_link\CartLinkTokenInterface $cart_link_token
   *   The cart link token.
   */
  public function __construct(ProductVariationInterface $variation, CartLinkTokenInterface $cart_link_token) {
    $this->variation = $variation;
    $this->cartLinkToken = $cart_link_token;
  }

  /**
   * AddToCartLink convenience constructor.
   *
   * @param int $id
   *   The product variation ID.
   *
   * @return \Drupal\commerce_add_to_cart_link\AddToCartLink
   *   The constructed object.
   */
  public static function fromVariationId(int $id): static {
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface|null $variation */
    if ($variation = ProductVariation::load($id)) {
      return new static($variation, \Drupal::service('commerce_add_to_cart_link.token'));
    }
    else {
      throw new \UnexpectedValueException('Can not load product variation: %s', $id);
    }
  }

  /**
   * Generate a render array for an add-to-cart link.
   *
   * @return array
   *   The render array.
   */
  public function build(): array {
    $build = [
      '#theme' => 'commerce_add_to_cart_link',
      '#url' => $this->url(),
      '#product_variation' => $this->variation,
    ];
    $metadata = $this->metadata();
    $metadata->applyTo($build);
    return $build;
  }

  /**
   * Generate URL object for an add-to-cart link.
   *
   * @return \Drupal\Core\Url
   *   The URL object.
   */
  public function url(): Url {
    return Url::fromRoute('commerce_add_to_cart_link.page',
      [
        'commerce_product' => $this->variation->getProductId(),
        'commerce_product_variation' => $this->variation->id(),
        'token' => $this->cartLinkToken->generate($this->variation),
      ]
    );
  }

  /**
   * Generate metadata for an add-to-cart link.
   *
   * @return \Drupal\Core\Render\BubbleableMetadata
   *   The metadata object.
   */
  public function metadata(): BubbleableMetadata {
    return BubbleableMetadata::createFromRenderArray([
      '#cache' => [
        'contexts' => ['user.roles'],
        'tags' => ['config:commerce_add_to_cart_link.settings'],
      ],
    ]);
  }

}
