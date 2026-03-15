<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^\\\\Drupal calls should be avoided in classes, use dependency injection instead$#',
	'identifier' => 'globalDrupalDependencyInjection.useDependencyInjection',
	'count' => 1,
	'path' => __DIR__ . '/web/modules/custom/back_to_cart_button/src/Controller/ReturnToMenuController.php',
];
$ignoreErrors[] = [
	'message' => '#^\\\\Drupal calls should be avoided in classes, use dependency injection instead$#',
	'identifier' => 'globalDrupalDependencyInjection.useDependencyInjection',
	'count' => 1,
	'path' => __DIR__ . '/web/modules/custom/duccinis_feeds_fix/src/EventSubscriber/FeedsProductVariationSubscriber.php',
];
$ignoreErrors[] = [
	'message' => '#^\\\\Drupal calls should be avoided in classes, use dependency injection instead$#',
	'identifier' => 'globalDrupalDependencyInjection.useDependencyInjection',
	'count' => 2,
	'path' => __DIR__ . '/web/modules/custom/store_fulfillment/src/EventSubscriber/OrderPlacementDeliveryRadiusValidator.php',
];
$ignoreErrors[] = [
	'message' => '#^\\\\Drupal calls should be avoided in classes, use dependency injection instead$#',
	'identifier' => 'globalDrupalDependencyInjection.useDependencyInjection',
	'count' => 1,
	'path' => __DIR__ . '/web/modules/custom/store_fulfillment/src/Plugin/Commerce/ShippingMethod/StoreDelivery.php',
];
$ignoreErrors[] = [
	'message' => '#^\\\\Drupal calls should be avoided in classes, use dependency injection instead$#',
	'identifier' => 'globalDrupalDependencyInjection.useDependencyInjection',
	'count' => 1,
	'path' => __DIR__ . '/web/modules/custom/store_resolver/src/Form/StoreSelectionForm.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
