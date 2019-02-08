<?php

namespace Drupal\Tests\media_entity_libsyn\Functional;

use Drupal\Tests\media\Functional\MediaFunctionalTestBase;

/**
 * Basic module test.
 *
 * @group media_entity_libsyn
 */
class ModuleTest extends MediaFunctionalTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['media_entity_libsyn'];

  public function testEnable() {
    $this->markTestIncomplete('Unable to complete due to missing schema.');
    // Create a test libsyn media bundle.
    $values['id'] = 'libsyn';
    $this->bundle = $this->createMediaType('libsyn', $values);
    $this->drupalGet('<front>');
  }

}
