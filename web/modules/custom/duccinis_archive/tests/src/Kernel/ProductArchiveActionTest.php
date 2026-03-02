<?php

declare(strict_types=1);

namespace Drupal\Tests\duccinis_archive\Kernel;

use Drupal\Core\Action\ActionInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests the ArchiveProduct and UnarchiveProduct action plugins.
 *
 * @group duccinis_archive
 * @coversDefaultClass \Drupal\duccinis_archive\Plugin\Action\ArchiveProduct
 */
class ProductArchiveActionTest extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'commerce_product',
    'duccinis_archive',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('commerce_product');
    $this->installConfig(['commerce_product']);

    \Drupal::moduleHandler()->loadInclude('duccinis_archive', 'install');
    duccinis_archive_install();
  }

  /**
   * Creates a published product with one variation and returns it.
   */
  protected function createPublishedProduct(): ProductInterface {
    $variation = ProductVariation::create([
      'type'   => 'default',
      'sku'    => 'ACTION-TEST-' . $this->randomMachineName(),
      'title'  => 'Test Variation',
      'status' => 1,
      'price'  => ['number' => '9.99', 'currency_code' => 'USD'],
    ]);
    $variation->save();

    $product = Product::create([
      'type'           => 'default',
      'title'          => 'Test Product',
      'stores'         => [$this->store],
      'variations'     => [$variation],
      'status'         => 1,
      'field_archived' => FALSE,
    ]);
    $product->save();

    return $product;
  }

  /**
   * Builds an action plugin instance by ID.
   */
  protected function buildAction(string $plugin_id): ActionInterface {
    return \Drupal::service('plugin.manager.action')->createInstance($plugin_id);
  }

  /**
   * @covers ::execute
   */
  public function testArchiveActionUnpublishesProductAndVariations(): void {
    $product = $this->createPublishedProduct();
    $product_id = $product->id();

    $action = $this->buildAction('duccinis_archive_product');
    $action->execute($product);

    $product = Product::load($product_id);
    $this->assertFalse($product->isPublished(), 'Product is unpublished after archive action.');
    $this->assertTrue((bool) $product->get('field_archived')->value, 'field_archived is TRUE after archive action.');

    foreach ($product->getVariations() as $variation) {
      $variation = ProductVariation::load($variation->id());
      $this->assertFalse($variation->isPublished(), 'Variation is unpublished after archive action.');
    }
  }

  /**
   * @covers \Drupal\duccinis_archive\Plugin\Action\UnarchiveProduct::execute
   */
  public function testUnarchiveActionRepublishesProductAndVariations(): void {
    $product = $this->createPublishedProduct();
    $product_id = $product->id();

    // Archive first via the action.
    $this->buildAction('duccinis_archive_product')->execute($product);

    // Reload and unarchive.
    $product = Product::load($product_id);
    $this->buildAction('duccinis_unarchive_product')->execute($product);

    $product = Product::load($product_id);
    $this->assertTrue($product->isPublished(), 'Product is re-published after unarchive action.');
    $this->assertFalse((bool) $product->get('field_archived')->value, 'field_archived is FALSE after unarchive action.');

    foreach ($product->getVariations() as $variation) {
      $variation = ProductVariation::load($variation->id());
      $this->assertTrue($variation->isPublished(), 'Variation is re-published after unarchive action.');
    }
  }

}
