<?php

namespace Drupal\media_entity_libsyn\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests for Libsyn embed formatter.
 *
 * @group media_entity_libsyn
 */
class LibsynEmbedFormatterTest extends WebTestBase {

  use MediaTypeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'media_entity_libsyn',
    'media',
    'node',
    'field_ui',
    'views_ui',
    'block',
    'link',
  ];

  /**
   * The test user.
   *
   * @var \Drupal\User\UserInterface
   */
  protected $adminUser;

  /**
   * The test media bundle.
   *
   * @var \Drupal\media\MediaBundleInterface
   */
  protected $bundle;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a test libsyn media bundle.
    $this->bundle = $this->createMediaType('libsyn');

    // Create an admin user with permissions to administer and create media.
    $this->adminUser = $this->drupalCreateUser([
      'administer media',
      'administer media bundles',
      'administer media fields',
      'administer media form display',
      'administer media display',
      // Media entity permissions.
      'view media',
      'create media',
      'update media',
      'update any media',
      'delete media',
      'delete any media',
      // Other permissions.
      'administer views',
    ]);

    // Login the user.
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests adding and editing a libsyn embed formatter.
   */
  public function testLibsynEmbedFormatter() {
    // Assert that the media bundle has the expected values before proceeding.
    $this->drupalGet('admin/structure/media/manage/' . $this->bundle->id());
    $this->assertFieldByName('label', $this->bundle->label());
    $this->assertFieldByName('type', 'libsyn');

    // Add a Url field to the bundle.
    $this->drupalGet('admin/structure/media/manage/' . $this->bundle->id() . '/fields/add-field');
    $config = [
      'new_storage_type' => 'link',
      'label' => 'Url',
      'field_name' => 'media_url',
    ];
    $this->drupalPostForm(NULL, $config, t('Save and continue'));

    // Check that the settings has been saved.
    $this->assertText('These settings apply to the ' . $config['label'] . ' field everywhere it is used.');

    // Set the field instance settings.
    $edit = [
      'cardinality' => 'number',
      'cardinality_number' => '1',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save field settings'));
    $this->assertText('Updated field ' . $config['label'] . ' field settings.');

    $edit = [
      'settings[link_type]' => 16,
      'settings[title]' => 0,
    ];
    $this->drupalPostForm(NULL, $edit, t('Save settings'));

    // Check if the field has been saved successfully.
    $this->assertText('Saved ' . $config['label'] . ' configuration.');
    $this->assertText('field_media_url');

    // Set the field_media_url format to soundcloud_embed.
    $this->drupalGet('admin/structure/media/manage/' . $this->bundle->id() . '/display');
    $edit = [
      'fields[field_media_url][label]' => 'above',
      'fields[field_media_url][type]' => 'libsyn_embed',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertText('Your settings have been saved.');

    // Set the source_url_field.
    $this->drupalGet('admin/structure/media/manage/' . $this->bundle->id());
    $edit = [
      'type_configuration[libsyn][source_url_field]' => 'field_media_url'
    ];
    $this->drupalPostForm(NULL, $edit, t('Save media bundle'));

    // Create a soundcloud media entity.
    $this->drupalGet('media/add/' . $this->bundle->id());

    // Asset that the field_media_url is found.
    $this->assertFieldByName('field_media_url[0][uri]');

    $edit = [
      'name[0][value]' => 'Libsyn',
      'field_media_url[0][uri]' => 'http://behindtheblue.libsyn.com/may-5-2017-dr-christian-lattermann-dr-carl-mattacola-equine-rider-injury-research',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save and publish'));

    // Asset that soundcloud entity has been created.
    $this->assertText('Url');

    // Assert that the formatter exists on this page.
    $this->assertFieldByXPath('//iframe');
  }
}
