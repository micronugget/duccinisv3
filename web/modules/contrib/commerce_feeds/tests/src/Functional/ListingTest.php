<?php

namespace Drupal\Tests\commerce_feeds\Functional;

use Drupal\feeds\Event\FeedsEvents;
use Drupal\feeds\Event\ParseEvent;

/**
 * Tests viewing imported items on feed/x/items.
 */
class ListingTest extends CommerceFeedsBrowserTestBase {

  /**
   * Tests listing imported product variations.
   */
  public function testListProductVariations() {
    // @todo Remove when Drupal 9 support needs to be dropped.
    if (version_compare(\Drupal::VERSION, '10.0', '<')) {
      // This test fails on Drupal 9 and is allowed to do so.
      $this->markTestSkipped('This test reveals a bug in Feeds that will not be fixed for Drupal 9.');
    }
    // Create a feed type for importing product variations.
    $feed_type = $this->createProductVariationFeedType();

    // Import.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesPath() . '/product_variations.csv',
    ]);
    $feed->import();

    // Go to the items page and assert that items are shown there.
    $this->drupalGet('/feed/1/list');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('A001');
    $this->assertSession()->pageTextContains('A002');
    $this->assertSession()->pageTextContains('FREE');
    $this->assertSession()->pageTextContains('B001');
    $this->assertSession()->pageTextContains('B002');
  }

  /**
   * Tests listing imported products.
   */
  public function testListProducts() {
    // Create two stores.
    $store_a = $this->createStore('Store A', 'a@example.com');
    $store_b = $this->createStore('Store B', 'b@example.com');

    // Respond to after parse event.
    $this->container->get('event_dispatcher')
      ->addListener(FeedsEvents::PARSE, [$this, 'afterParse'], FeedsEvents::AFTER);

    // Create two feed types, one for importing product variations and one for
    // importing products.
    $product_variation_feed_type = $this->createProductVariationFeedType();
    $product_feed_type = $this->createProductFeedType();

    // Import both.
    $product_variation_feed = $this->createFeed($product_variation_feed_type->id(), [
      'source' => $this->resourcesPath() . '/product_variations.csv',
    ]);
    $product_variation_feed->import();

    $product_feed = $this->createFeed($product_feed_type->id(), [
      'source' => $this->resourcesPath() . '/products.csv',
    ]);
    $product_feed->import();

    // Assert that products are listed.
    $this->drupalGet('/feed/2/list');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Lorem ipsum');
    $this->assertSession()->pageTextContains('Dolor sit amet');

    // @todo Remove when Drupal 9 support needs to be dropped.
    if (version_compare(\Drupal::VERSION, '10.0', '<')) {
      // This test fails on Drupal 9 and is allowed to do so.
      $this->markTestSkipped('This test reveals a bug in Feeds that will not be fixed for Drupal 9.');
    }

    // Assert that product variations are listed.
    $this->drupalGet('/feed/1/list');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Lorem ipsum');
    $this->assertSession()->pageTextContains('Dolor sit amet');
  }

  /**
   * Acts on parser result.
   *
   * @param \Drupal\feeds\Event\ParseEvent $event
   *   The parse event.
   */
  public function afterParse(ParseEvent $event) {
    /** @var \Drupal\feeds\Feeds\Item\ItemInterface $item */
    foreach ($event->getParserResult() as $item) {
      // Make sku multivalued by exploding on '|'.
      $sku = $item->get('sku');
      if (strpos($sku, '|') !== FALSE) {
        $item->set('sku', explode('|', $sku));
      }
    }
  }

}
