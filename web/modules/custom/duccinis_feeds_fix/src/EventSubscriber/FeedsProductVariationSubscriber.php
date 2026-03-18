<?php

declare(strict_types=1);

namespace Drupal\duccinis_feeds_fix\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\feeds\Event\EntityEvent;
use Drupal\feeds\Event\FeedsEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to preserve product variations during Feeds import.
 *
 * The Feeds module clears all field values before mapping each CSV row,
 * which causes previously imported variations to be lost when processing
 * multiple rows for the same product. This subscriber preserves existing
 * variations and merges them with newly mapped ones.
 */
class FeedsProductVariationSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a FeedsProductVariationSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    // Subscribe to the event that fires after mapping but before validation.
    $events[FeedsEvents::PROCESS_ENTITY_PREVALIDATE][] = ['onPrevalidate', 100];
    return $events;
  }

  /**
   * Acts on entity prevalidate event.
   *
   * @param \Drupal\feeds\Event\EntityEvent $event
   *   The entity event.
   */
  public function onPrevalidate(EntityEvent $event) {
    $entity = $event->getEntity();

    // Only process commerce_product entities.
    if ($entity->getEntityTypeId() !== 'commerce_product') {
      return;
    }

    // Only process fresh_submarines products.
    if ($entity->bundle() !== 'fresh_submarines') {
      return;
    }

    // Check if this is an update (entity already exists in DB).
    if ($entity->isNew()) {
      // New entity, no existing variations to preserve.
      return;
    }

    // Get the current variations on the entity (just mapped from CSV).
    $current_variations = $entity->get('variations')->getValue();

    // Load the original entity from the database to get existing variations.
    $storage = $this->entityTypeManager->getStorage('commerce_product');
    $original = $storage->load($entity->id());

    if (!$original) {
      return;
    }

    // Get existing variations from the original entity.
    $existing_variations = $original->get('variations')->getValue();

    // Create a map of existing variation IDs for quick lookup.
    $existing_ids = [];
    foreach ($existing_variations as $variation) {
      $existing_ids[$variation['target_id']] = $variation;
    }

    // Add current variations to the map (will overwrite if same ID).
    foreach ($current_variations as $variation) {
      $existing_ids[$variation['target_id']] = $variation;
    }

    // Merge: keep all existing variations plus new ones.
    $merged_variations = array_values($existing_ids);

    // Set the merged variations back to the entity.
    $entity->get('variations')->setValue($merged_variations);
  }

}
