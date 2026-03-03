<?php

declare(strict_types=1);

namespace Drupal\Tests\duccinis_archive\Kernel;

use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\duccinis_archive\ArchiveAuditLogger;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests ArchiveAuditLogger writes correct entries to duccinis_archive_log.
 *
 * Also verifies that ProductArchiveSubscriber calls the logger when the
 * field_archived state changes via a product save, covering the full
 * archive/unarchive event path.
 *
 * @group duccinis_archive
 * @coversDefaultClass \Drupal\duccinis_archive\ArchiveAuditLogger
 */
class ArchiveAuditLoggerTest extends CommerceKernelTestBase {

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

    // Install the field_archived base field storage.
    \Drupal::moduleHandler()->loadInclude('duccinis_archive', 'install');
    duccinis_archive_install();

    // Install the duccinis_archive_log DB table.
    $this->installSchema('duccinis_archive', ['duccinis_archive_log']);
  }

  /**
   * Creates a published product and returns it.
   */
  protected function createPublishedProduct(): ProductInterface {
    $variation = ProductVariation::create([
      'type'   => 'default',
      'sku'    => 'AUDIT-TEST-' . $this->randomMachineName(),
      'title'  => 'Test Variation',
      'status' => 1,
      'price'  => ['number' => '9.99', 'currency_code' => 'USD'],
    ]);
    $variation->save();

    $product = Product::create([
      'type'           => 'default',
      'title'          => 'Audit Test Product',
      'stores'         => [$this->store],
      'variations'     => [$variation],
      'status'         => 1,
      'field_archived' => FALSE,
    ]);
    $product->save();

    return $product;
  }

  /**
   * Queries the log table and returns all rows ordered by id.
   *
   * @return array
   *   Array of stdClass log entries.
   */
  protected function fetchLogEntries(): array {
    return \Drupal::database()
      ->select('duccinis_archive_log', 'l')
      ->fields('l')
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll();
  }

  /**
   * @covers ::log
   */
  public function testLogWritesArchiveEntry(): void {
    /** @var \Drupal\duccinis_archive\ArchiveAuditLogger $logger */
    $logger = $this->container->get('duccinis_archive.audit_logger');
    $this->assertInstanceOf(ArchiveAuditLogger::class, $logger);

    $product = $this->createPublishedProduct();

    $logger->log($product, 'archive');

    $entries = $this->fetchLogEntries();
    $this->assertCount(1, $entries, 'One log entry was written.');
    $this->assertEquals((int) $product->id(), (int) $entries[0]->product_id);
    $this->assertEquals('Audit Test Product', $entries[0]->product_title);
    $this->assertEquals('archive', $entries[0]->action);
  }

  /**
   * @covers ::log
   */
  public function testLogWritesUnarchiveEntry(): void {
    /** @var \Drupal\duccinis_archive\ArchiveAuditLogger $logger */
    $logger = $this->container->get('duccinis_archive.audit_logger');
    $product = $this->createPublishedProduct();

    $logger->log($product, 'archive');
    $logger->log($product, 'unarchive');

    $entries = $this->fetchLogEntries();
    $this->assertCount(2, $entries, 'Two log entries were written.');
    $this->assertEquals('archive', $entries[0]->action);
    $this->assertEquals('unarchive', $entries[1]->action);
  }

  /**
   * Verifies that archiving a product via entity save writes to the log.
   *
   * This covers the ProductArchiveSubscriber → ArchiveAuditLogger integration.
   */
  public function testArchivingProductViaEntitySaveLogsEntry(): void {
    $product = $this->createPublishedProduct();

    // No log entries before archiving.
    $this->assertCount(0, $this->fetchLogEntries());

    // Archive the product by saving it with field_archived = TRUE.
    $product->set('field_archived', TRUE);
    $product->save();

    $entries = $this->fetchLogEntries();
    $this->assertCount(1, $entries, 'Archive action was logged on entity save.');
    $this->assertEquals('archive', $entries[0]->action);
    $this->assertEquals((int) $product->id(), (int) $entries[0]->product_id);
  }

  /**
   * Verifies that unarchiving a product via entity save writes to the log.
   */
  public function testUnarchivingProductViaEntitySaveLogsEntry(): void {
    $product = $this->createPublishedProduct();

    // Archive first.
    $product->set('field_archived', TRUE);
    $product->save();

    // Reload to set $product->original correctly.
    $product = \Drupal::entityTypeManager()
      ->getStorage('commerce_product')
      ->load($product->id());

    // Unarchive.
    $product->set('field_archived', FALSE);
    $product->save();

    $entries = $this->fetchLogEntries();
    $this->assertCount(2, $entries, 'Archive and unarchive actions were both logged.');
    $this->assertEquals('archive', $entries[0]->action);
    $this->assertEquals('unarchive', $entries[1]->action);
  }

  /**
   * Verifies that re-saving an already-archived product does not add a log entry.
   */
  public function testResavingArchivedProductDoesNotDuplicateLog(): void {
    $product = $this->createPublishedProduct();

    // Archive.
    $product->set('field_archived', TRUE);
    $product->save();

    // Reload and re-save without changing the flag.
    $product = \Drupal::entityTypeManager()
      ->getStorage('commerce_product')
      ->load($product->id());
    $product->save();

    $entries = $this->fetchLogEntries();
    $this->assertCount(1, $entries, 'Re-saving an archived product does not add a duplicate log entry.');
  }

}
