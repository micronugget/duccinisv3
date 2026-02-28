<?php

declare(strict_types=1);

namespace Drupal\Tests\store_fulfillment\Kernel;

use Drupal\address\AddressInterface;
use Drupal\commerce_store\Entity\Store;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Drupal\store_fulfillment\DeliveryRadiusCalculator;
use Drupal\store_fulfillment\DeliveryRadiusValidator;

/**
 * Tests the DeliveryRadiusValidator service.
 *
 * @group store_fulfillment
 * @coversDefaultClass \Drupal\store_fulfillment\DeliveryRadiusValidator
 */
class DeliveryRadiusValidatorTest extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'profile',
    'state_machine',
    'entity_reference_revisions',
    'geofield',
    'geocoder',
    'store_resolver',
    'store_fulfillment',
  ];

  /**
   * The delivery radius validator service.
   *
   * @var \Drupal\store_fulfillment\DeliveryRadiusValidator
   */
  protected DeliveryRadiusValidator $validator;

  /**
   * Test store entity (overrides parent's untyped $store).
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected $store;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install store_fulfillment config.
    $this->installConfig(['store_fulfillment']);
    // Run the install hook to create delivery_radius and store_location fields
    // on the commerce_store entity. The fields are created programmatically in
    // hook_install, so we must call it explicitly in kernel tests.
    \Drupal::moduleHandler()->loadInclude('store_fulfillment', 'install');
    store_fulfillment_install();

    // Create test store with coordinates.
    $this->store = Store::create([
      'type' => 'online',
      'name' => 'Test Store',
      'mail' => 'test@example.com',
      'address' => [
        'country_code' => 'US',
        'administrative_area' => 'CA',
        'locality' => 'San Francisco',
        'postal_code' => '94102',
        'address_line1' => '123 Market St',
      ],
      'timezone' => 'America/Los_Angeles',
    ]);
    $this->store->save();

    // Create validator with mocked calculator.
    $calculator = $this->createMockCalculator();
    $this->validator = new DeliveryRadiusValidator(
      $calculator,
      $this->container->get('string_translation'),
      $this->container->get('geocoder'),
      $this->container->get('logger.factory'),
      $this->container->get('entity_type.manager')
    );
  }

  /**
   * Creates a mock delivery radius calculator.
   *
   * @return \Drupal\store_fulfillment\DeliveryRadiusCalculator
   *   The mocked calculator.
   */
  protected function createMockCalculator(): DeliveryRadiusCalculator {
    $calculator = $this->getMockBuilder(DeliveryRadiusCalculator::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['calculateDistance'])
      ->getMock();

    // Mock returns a fixed distance.
    $calculator->expects($this->any())
      ->method('calculateDistance')
      ->willReturn(5.0);

    return $calculator;
  }

  /**
   * Creates a mock address with geocoding capabilities.
   *
   * @param bool $has_coords
   *   Whether the address should have coordinates.
   *
   * @return \Drupal\address\AddressInterface
   *   The mocked address.
   */
  protected function createMockAddress(bool $has_coords = TRUE): AddressInterface {
    $address = $this->getMockBuilder(AddressInterface::class)
      ->getMock();

    $address->method('getCountryCode')->willReturn('US');
    $address->method('getAdministrativeArea')->willReturn('CA');
    $address->method('getLocality')->willReturn('San Francisco');
    $address->method('getPostalCode')->willReturn('94103');
    $address->method('getAddressLine1')->willReturn('456 Mission St');

    return $address;
  }

  /**
   * Creates a validator with custom geocoding behavior.
   *
   * @param bool $store_has_coords
   *   Whether store geocoding should succeed.
   * @param bool $address_has_coords
   *   Whether address geocoding should succeed.
   * @param float $distance
   *   The distance to return from calculation.
   *
   * @return \Drupal\store_fulfillment\DeliveryRadiusValidator
   *   The validator with mocked methods.
   */
  protected function createValidatorWithMockedGeocoding(
    bool $store_has_coords,
    bool $address_has_coords,
    float $distance,
  ): DeliveryRadiusValidator {
    $calculator = $this->getMockBuilder(DeliveryRadiusCalculator::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['calculateDistance'])
      ->getMock();

    $calculator->method('calculateDistance')->willReturn($distance);

    $validator = $this->getMockBuilder(DeliveryRadiusValidator::class)
      ->setConstructorArgs([
        $calculator,
        $this->container->get('string_translation'),
        $this->container->get('geocoder'),
        $this->container->get('logger.factory'),
        $this->container->get('entity_type.manager'),
      ])
      ->onlyMethods(['getStoreCoordinates', 'getAddressCoordinates'])
      ->getMock();

    $store_coords = $store_has_coords ? ['lat' => 37.7749, 'lon' => -122.4194] : NULL;
    $address_coords = $address_has_coords ? ['lat' => 37.7849, 'lon' => -122.4094] : NULL;

    $validator->method('getStoreCoordinates')->willReturn($store_coords);
    $validator->method('getAddressCoordinates')->willReturn($address_coords);

    return $validator;
  }

  /**
   * Tests validation when address is within radius.
   *
   * @covers ::validateDeliveryAddress
   */
  public function testAddressWithinRadius(): void {
    // Set delivery radius to 10 miles.
    $this->store->set('delivery_radius', 10.0);
    $this->store->save();

    // Create validator that returns distance of 5 miles.
    $validator = $this->createValidatorWithMockedGeocoding(TRUE, TRUE, 5.0);
    $address = $this->createMockAddress();

    $result = $validator->validateDeliveryAddress($this->store, $address);

    $this->assertTrue($result['valid']);
    $this->assertStringContainsString('within our service area', $result['message']);
    $this->assertStringContainsString('5.00 miles', $result['message']);
    $this->assertStringContainsString('10.00 miles', $result['message']);
    $this->assertEquals(5.0, $result['distance']);
  }

  /**
   * Tests validation when address is outside radius.
   *
   * @covers ::validateDeliveryAddress
   */
  public function testAddressOutsideRadius(): void {
    // Set delivery radius to 10 miles.
    $this->store->set('delivery_radius', 10.0);
    $this->store->save();

    // Create validator that returns distance of 15 miles.
    $validator = $this->createValidatorWithMockedGeocoding(TRUE, TRUE, 15.0);
    $address = $this->createMockAddress();

    $result = $validator->validateDeliveryAddress($this->store, $address);

    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('outside our service area', $result['message']);
    $this->assertStringContainsString('15.00 miles', $result['message']);
    $this->assertStringContainsString('10.00 miles', $result['message']);
    $this->assertStringContainsString('pickup', strtolower($result['message']));
    $this->assertEquals(15.0, $result['distance']);
  }

  /**
   * Tests validation when no radius is configured.
   *
   * @covers ::validateDeliveryAddress
   */
  public function testNoRadiusConfigured(): void {
    // Explicitly clear the delivery_radius field (the install hook sets 10.00
    // as a default, so we must remove it for this test).
    $this->store->set('delivery_radius', NULL);
    $this->store->save();
    $address = $this->createMockAddress();

    $result = $this->validator->validateDeliveryAddress($this->store, $address);

    $this->assertTrue($result['valid']);
    $this->assertStringContainsString('No delivery radius restriction', $result['message']);
    $this->assertNull($result['distance']);
  }

  /**
   * Tests validation when store has no coordinates.
   *
   * @covers ::validateDeliveryAddress
   */
  public function testStoreWithNoCoordinates(): void {
    $this->store->set('delivery_radius', 10.0);
    $this->store->save();

    // Create validator where store geocoding fails.
    $validator = $this->createValidatorWithMockedGeocoding(FALSE, TRUE, 0.0);
    $address = $this->createMockAddress();

    $result = $validator->validateDeliveryAddress($this->store, $address);

    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('Unable to determine store location', $result['message']);
    $this->assertStringContainsString('contact support', $result['message']);
    $this->assertNull($result['distance']);
  }

  /**
   * Tests validation when address cannot be geocoded.
   *
   * @covers ::validateDeliveryAddress
   */
  public function testAddressCannotBeGeocoded(): void {
    $this->store->set('delivery_radius', 10.0);
    $this->store->save();

    // Create validator where address geocoding fails.
    $validator = $this->createValidatorWithMockedGeocoding(TRUE, FALSE, 0.0);
    $address = $this->createMockAddress();

    $result = $validator->validateDeliveryAddress($this->store, $address);

    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('Unable to verify your delivery address', $result['message']);
    $this->assertStringContainsString('valid address', $result['message']);
    $this->assertNull($result['distance']);
  }

  /**
   * Tests exact boundary condition (address exactly at radius limit).
   *
   * @covers ::validateDeliveryAddress
   */
  public function testExactBoundaryCondition(): void {
    $this->store->set('delivery_radius', 10.0);
    $this->store->save();

    // Create validator that returns exactly 10.0 miles.
    $validator = $this->createValidatorWithMockedGeocoding(TRUE, TRUE, 10.0);
    $address = $this->createMockAddress();

    $result = $validator->validateDeliveryAddress($this->store, $address);

    // Exactly at boundary should be valid (distance <= max_radius).
    $this->assertTrue($result['valid']);
    $this->assertStringContainsString('within our service area', $result['message']);
    $this->assertEquals(10.0, $result['distance']);
  }

  /**
   * Tests boundary condition just over the limit.
   *
   * @covers ::validateDeliveryAddress
   */
  public function testJustOverBoundary(): void {
    $this->store->set('delivery_radius', 10.0);
    $this->store->save();

    // Create validator that returns 10.01 miles (just over).
    $validator = $this->createValidatorWithMockedGeocoding(TRUE, TRUE, 10.01);
    $address = $this->createMockAddress();

    $result = $validator->validateDeliveryAddress($this->store, $address);

    // Just over boundary should be invalid.
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('outside our service area', $result['message']);
    $this->assertEquals(10.01, $result['distance']);
  }

  /**
   * Tests message strings are user-friendly.
   *
   * @covers ::validateDeliveryAddress
   */
  public function testMessageStringsAreUserFriendly(): void {
    $this->store->set('delivery_radius', 10.0);
    $this->store->save();

    // Test success message.
    $validator_success = $this->createValidatorWithMockedGeocoding(TRUE, TRUE, 5.0);
    $result_success = $validator_success->validateDeliveryAddress($this->store, $this->createMockAddress());
    $this->assertStringNotContainsString('Error', $result_success['message']);
    $this->assertStringNotContainsString('error', $result_success['message']);

    // Test failure message.
    $validator_fail = $this->createValidatorWithMockedGeocoding(TRUE, TRUE, 15.0);
    $result_fail = $validator_fail->validateDeliveryAddress($this->store, $this->createMockAddress());
    $this->assertStringContainsString('Sorry', $result_fail['message']);
    $this->assertStringContainsString('pickup', strtolower($result_fail['message']));
  }

  /**
   * Tests that distance is included in all results where calculable.
   *
   * @covers ::validateDeliveryAddress
   */
  public function testDistanceIncludedInResults(): void {
    $this->store->set('delivery_radius', 10.0);
    $this->store->save();

    // Test with valid address.
    $validator_valid = $this->createValidatorWithMockedGeocoding(TRUE, TRUE, 7.5);
    $result_valid = $validator_valid->validateDeliveryAddress($this->store, $this->createMockAddress());
    $this->assertArrayHasKey('distance', $result_valid);
    $this->assertEquals(7.5, $result_valid['distance']);

    // Test with invalid address (outside radius).
    $validator_invalid = $this->createValidatorWithMockedGeocoding(TRUE, TRUE, 12.0);
    $result_invalid = $validator_invalid->validateDeliveryAddress($this->store, $this->createMockAddress());
    $this->assertArrayHasKey('distance', $result_invalid);
    $this->assertEquals(12.0, $result_invalid['distance']);

    // Test when geocoding fails.
    $validator_no_coords = $this->createValidatorWithMockedGeocoding(FALSE, TRUE, 0.0);
    $result_no_coords = $validator_no_coords->validateDeliveryAddress($this->store, $this->createMockAddress());
    $this->assertArrayHasKey('distance', $result_no_coords);
    $this->assertNull($result_no_coords['distance']);
  }

  /**
   * Tests validation result structure.
   *
   * @covers ::validateDeliveryAddress
   */
  public function testValidationResultStructure(): void {
    $this->store->set('delivery_radius', 10.0);
    $this->store->save();

    $validator = $this->createValidatorWithMockedGeocoding(TRUE, TRUE, 5.0);
    $result = $validator->validateDeliveryAddress($this->store, $this->createMockAddress());

    // Verify result has required keys.
    $this->assertArrayHasKey('valid', $result);
    $this->assertArrayHasKey('message', $result);
    $this->assertArrayHasKey('distance', $result);

    // Verify types.
    $this->assertIsBool($result['valid']);
    $this->assertIsString($result['message']);
    $this->assertTrue($result['distance'] === NULL || is_float($result['distance']));
  }

}
