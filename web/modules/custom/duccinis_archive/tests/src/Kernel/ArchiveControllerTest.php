<?php

declare(strict_types=1);

namespace Drupal\Tests\duccinis_archive\Kernel;

use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\duccinis_archive\Controller\ArchiveController;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Tests the ArchiveController JSON AJAX endpoint methods.
 *
 * These tests call the controller methods directly (bypassing routing and
 * CSRF checks) to verify that the endpoint logic correctly archives and
 * unarchives products and returns the expected JSON structure.
 *
 * @group duccinis_archive
 * @coversDefaultClass \Drupal\duccinis_archive\Controller\ArchiveController
 */
class ArchiveControllerTest extends CommerceKernelTestBase {

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
   * Creates a published product with one variation and returns it.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface
   *   The saved, published product entity.
   */
  protected function createPublishedProduct(): ProductInterface {
    $variation = ProductVariation::create([
      'type'   => 'default',
      'sku'    => 'CTRL-TEST-' . $this->randomMachineName(),
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
   * @covers ::archive
   */
  public function testArchiveReturnsJsonWithArchivedStatus(): void {
    $product = $this->createPublishedProduct();
    $product_id = (int) $product->id();

    $controller = new ArchiveController();
    $response = $controller->archive($product);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame('archived', $data['status'], 'Response status is "archived".');
    $this->assertSame($product_id, $data['product_id'], 'Response product_id matches.');
    $this->assertFalse($data['published'], 'Response reports product as unpublished after archive.');

    $reloaded = Product::load($product_id);
    $this->assertTrue((bool) $reloaded->get('field_archived')->value, 'field_archived is TRUE after archive.');
    $this->assertFalse($reloaded->isPublished(), 'Product is unpublished in database after archive.');
  }

  /**
   * @covers ::unarchive
   */
  public function testUnarchiveReturnsJsonWithActiveStatus(): void {
    $product = $this->createPublishedProduct();
    $product_id = (int) $product->id();

    // Archive first so we have something to unarchive.
    $controller = new ArchiveController();
    $controller->archive($product);

    // Reload the entity to pick up the persisted archived state.
    $product = Product::load($product_id);
    $response = $controller->unarchive($product);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $data = json_decode($response->getContent(), TRUE);

    $this->assertSame('active', $data['status'], 'Response status is "active".');
    $this->assertSame($product_id, $data['product_id'], 'Response product_id matches.');
    $this->assertTrue($data['published'], 'Response reports product as published after unarchive.');

    $reloaded = Product::load($product_id);
    $this->assertFalse((bool) $reloaded->get('field_archived')->value, 'field_archived is FALSE after unarchive.');
    $this->assertTrue($reloaded->isPublished(), 'Product is re-published in database after unarchive.');
  }

  /**
   * @covers ::archive
   * @covers ::unarchive
   */
  public function testResponseContainsProductTitle(): void {
    $product = $this->createPublishedProduct();

    $controller = new ArchiveController();

    $archive_data = json_decode($controller->archive($product)->getContent(), TRUE);
    $this->assertSame('Test Product', $archive_data['title'], 'Archive response contains correct product title.');

    $product = Product::load($product->id());
    $unarchive_data = json_decode($controller->unarchive($product)->getContent(), TRUE);
    $this->assertSame('Test Product', $unarchive_data['title'], 'Unarchive response contains correct product title.');
  }

}
