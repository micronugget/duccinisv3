<?php

declare(strict_types=1);

namespace Drupal\Tests\duccinis_archive\Kernel;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests the archive flag lifecycle on Commerce products.
 *
 * Verifies that:
 *  - Archiving a product unpublishes it and all its variations.
 *  - Unarchiving re-publishes the product and its variations.
 *  - Saving a product without changing the archived flag leaves
 *    variation published-state untouched.
 *
 * @group duccinis_archive
 * @coversDefaultClass \Drupal\duccinis_archive\EventSubscriber\ProductArchiveSubscriber
 */
class ProductArchiveSubscriberTest extends CommerceKernelTestBase {

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

    // Run the install hook so that the field_archived DB column is created.
    \Drupal::moduleHandler()->loadInclude('duccinis_archive', 'install');
    duccinis_archive_install();
  }

  /**
   * Creates a published product with two published variations and returns it.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface
   *   The saved product entity.
   */
  protected function createActiveProduct(): ProductInterface {
    $variation1 = ProductVariation::create([
      'type'   => 'default',
      'sku'    => 'TEST-VAR-1',
      'title'  => 'Variation 1',
      'status' => 1,
      'price'  => ['number' => '10.00', 'currency_code' => 'USD'],
    ]);
    $variation1->save();

    $variation2 = ProductVariation::create([
      'type'   => 'default',
      'sku'    => 'TEST-VAR-2',
      'title'  => 'Variation 2',
      'status' => 1,
      'price'  => ['number' => '12.00', 'currency_code' => 'USD'],
    ]);
    $variation2->save();

    $product = Product::create([
      'type'           => 'default',
      'title'          => 'Test Pizza',
      'stores'         => [$this->store],
      'variations'     => [$variation1, $variation2],
      'status'         => 1,
      'field_archived' => FALSE,
    ]);
    $product->save();

    return $product;
  }

  /**
   * @covers ::onProductPresave
   * @covers ::onProductPostSave
   */
  public function testArchivingUnpublishesProductAndVariations(): void {
    $product = $this->createActiveProduct();
    $product_id = $product->id();

    // Sanity check: product and both variations are published.
    $this->assertTrue($product->isPublished(), 'Product is initially published.');
    foreach ($product->getVariations() as $variation) {
      $this->assertTrue($variation->isPublished(), 'Variation is initially published.');
    }

    // Archive the product.
    $product->set('field_archived', TRUE);
    $product->save();

    // Reload from storage to confirm DB values.
    $product = Product::load($product_id);
    $this->assertFalse($product->isPublished(), 'Product is unpublished after archiving.');
    $this->assertTrue((bool) $product->get('field_archived')->value, 'field_archived is TRUE after archiving.');

    foreach ($product->getVariations() as $variation) {
      $variation = ProductVariation::load($variation->id());
      $this->assertFalse($variation->isPublished(), 'Variation is unpublished after archiving.');
    }
  }

  /**
   * @covers ::onProductPresave
   * @covers ::onProductPostSave
   */
  public function testUnarchivingRepublishesProductAndVariations(): void {
    $product = $this->createActiveProduct();
    $product_id = $product->id();

    // Archive first.
    $product->set('field_archived', TRUE);
    $product->save();

    // Reload and unarchive.
    $product = Product::load($product_id);
    $product->set('field_archived', FALSE);
    $product->save();

    // Reload again and assert everything is re-published.
    $product = Product::load($product_id);
    $this->assertTrue($product->isPublished(), 'Product is re-published after unarchiving.');
    $this->assertFalse((bool) $product->get('field_archived')->value, 'field_archived is FALSE after unarchiving.');

    foreach ($product->getVariations() as $variation) {
      $variation = ProductVariation::load($variation->id());
      $this->assertTrue($variation->isPublished(), 'Variation is re-published after unarchiving.');
    }
  }

  /**
   * Saving an active (non-archived) product must not change variation status.
   *
   * @covers ::onProductPostSave
   */
  public function testSavingActiveProductDoesNotChangeVariations(): void {
    $product = $this->createActiveProduct();
    $product_id = $product->id();

    // Manually unpublish one variation (simulating an admin action).
    $variations = $product->getVariations();
    $first = reset($variations);
    $first->setUnpublished()->save();

    // Save the product without touching field_archived.
    $product = Product::load($product_id);
    $product->set('title', 'Updated Title');
    $product->save();

    // The manually unpublished variation must remain unpublished.
    $first = ProductVariation::load($first->id());
    $this->assertFalse($first->isPublished(), 'Manually unpublished variation stays unpublished after unrelated product save.');
  }

}
