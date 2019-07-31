<?php

namespace Drupal\media_entity_libsyn\Plugin\media\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Component\Utility\Crypt;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use DOMDocument;

/**
 * Provides media type plugin for Libsyn.
 *
 * @MediaSource(
 *   id = "libsyn",
 *   label = @Translation("Libsyn Podcast"),
 *   description = @Translation("Provides business logic and metadata for Libsyn."),
 *   allowed_field_types = {"link", "string", "string_long"},
 *   default_thumbnail_filename = "libsyn.png",
 *   default_name_metadata_attribute = @Translation("Podcast")
 * )
 */
class Libsyn extends MediaSourceBase {

  use LoggerChannelTrait;

  /**
   * Libsyn data.
   *
   * @var array
   */
  protected $libsyn;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Our channel logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->logger = $this->getLogger('media_entity_libsyn');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $source = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
      $container->get('http_client')
    );
    $source->setLoggerFactory($container->get('logger.factory'));
    return $source;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [
      'podcast_date' => $this->t('Date of the podcast as a string'),
      'podcast_date_date' => $this->t('Date of the podcast for date fields'),
      'episode_id' => $this->t('The episode id'),
      'html' => $this->t('HTML embed code'),
      'thumbnail_uri' => $this->t('URI of the thumbnail')
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $name) {
    if (($url = $this->getMediaUrl($media)) && ($data = $this->getData($url))) {
      switch ($name) {
        case 'thumbnail_uri':
          $local_uri = isset($data['thumbnail_url']) ? $this->getLocalThumbnailUri($data['thumbnail_url']) : NULL;
          return $local_uri ? $local_uri : parent::getMetadata($media, 'thumbnail_url');

        case 'episode_id':
          // Extract the src attribute from the html code.
          preg_match('/src="([^"]+)"/', $data['html'], $src_matches);
          if (!count($src_matches)) {
            return;
          }

          // Extract the id from the src.
          preg_match('/\/episode\/id\/(\d*)/', urldecode($src_matches[1]), $matches);
          if (!count($matches)) {
            return;
          }

          return $matches[1];

        case 'html':
          return isset($data[$name]) ? $data[$name] : '';

        case 'podcast_date':
          return $data['podcast_date'];

        case 'podcast_date_date':
          $date = new \DateTime($data['podcast_date']);
          return $date->format('Y-m-d');
      }
    }

    return parent::getMetadata($media, $name);
  }

  /**
   * Returns the episode id from the source_field.
   *
   * @param \Drupal\media_entity\MediaInterface $media
   *   The media entity.
   *
   * @return string|bool
   *   The episode if from the source_field if found. False otherwise.
   */
  protected function getMediaUrl(MediaInterface $media) {
    if (isset($this->configuration['source_field'])) {
      $source_field = $this->configuration['source_field'];

      if ($media->hasField($source_field)) {
        if (!empty($media->{$source_field}->first())) {
          $property_name = $media->{$source_field}->first()
            ->mainPropertyName();
          return $media->{$source_field}->{$property_name};
        }
      }
    }
    return FALSE;
  }

  /**
   * Returns data for the podcast.
   *
   * @param string $url
   *   The Libsyn URL. This is the URL to the page on libsyn.com where you can
   *   listen to a podcast episode.
   *
   * @return array
   *   An array of embed data.
   */
  protected function getData($url) {
    if (!isset($this->libsyn)) {
      /* @var $response Psr\Http\Message\ResponseInterface */
      $response = $this->httpClient->get($url);
      $data = (string) $response->getBody();

      $dom = new DOMDocument();
      $dom->loadHTML($data, LIBXML_NOERROR);

      // Search for the embed.
      $nodes = $dom->getElementsByTagName('iframe');
      foreach ($nodes as $node) {
        $src = $node->getAttribute('src');
        if (strpos($src, 'player.libsyn.com') !== FALSE) {
          $this->libsyn['html'] = $dom->saveHTML($node);
        }
      }

      // Attributes.
      $nodes = $dom->getElementsByTagName('meta');
      foreach ($nodes as $node) {
        $property = $node->getAttribute('property');
        switch ($property) {
          case 'og:image':
            // Remove the query string from the image URL.
            $this->libsyn['thumbnail_url'] = strtok($node->getAttribute('content'), '?');
            break;
        }
      }

      // Date.
      $this->libsyn['podcast_date'] = '';
      $xpath = new \DOMXPath($dom);
      $nodes = $xpath->query("//p[contains(concat(' ', normalize-space(@class), ' '), ' date ')]");
      foreach ($nodes as $date_node) {
        $this->libsyn['podcast_date'] = $date_node->textContent;
      }
    }

    return $this->libsyn;
  }

  /**
   * Returns the local URI for a thumbnail.
   *
   * If the thumbnail is not already locally stored, this method will attempt
   * to download it.
   *
   * Appropriated from the OEmbed plugin.
   *
   * @param string $remote_thumbnail_url
   *   The URL to the remote thumbnail image.
   *
   * @return string|null
   *   The local thumbnail URI, or NULL if it could not be downloaded, or if the
   *   resource has no thumbnail at all.
   */
  protected function getLocalThumbnailUri($remote_thumbnail_url) {
    // If there is no remote thumbnail, there's nothing for us to fetch here.
    if (!$remote_thumbnail_url) {
      return NULL;
    }

    // Compute the local thumbnail URI, regardless of whether or not it exists.
    $configuration = $this->getConfiguration();
    $directory = $this->configFactory->get('media_entity_libsyn.settings')->get('thumbnail_destination');
    // Libsyn uses PNG files, but the files don't have extensions.
    $extension = pathinfo($remote_thumbnail_url, PATHINFO_EXTENSION);
    if (empty($extension)) {
      $extension = 'png';
    }
    $local_thumbnail_uri = "$directory/" . Crypt::hashBase64($remote_thumbnail_url) . '.' . $extension;

    // If the local thumbnail already exists, return its URI.
    if (file_exists($local_thumbnail_uri)) {
      return $local_thumbnail_uri;
    }

    // The local thumbnail doesn't exist yet, so try to download it. First,
    // ensure that the destination directory is writable, and if it's not,
    // log an error and bail out.
    if (!file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      $this->logger->warning('Could not prepare thumbnail destination directory @dir for Libsyn Podcast media.', [
        '@dir' => $directory,
      ]);
      return NULL;
    }

    $error_message = 'Could not download remote thumbnail from {url}.';
    $error_context = [
      'url' => $remote_thumbnail_url,
    ];
    try {
      $response = $this->httpClient->get($remote_thumbnail_url);
      if ($response->getStatusCode() === 200) {
        $success = file_unmanaged_save_data((string) $response->getBody(), $local_thumbnail_uri, FILE_EXISTS_REPLACE);

        if ($success) {
          return $local_thumbnail_uri;
        }
        else {
          $this->logger->warning($error_message, $error_context);
        }
      }
    }
    catch (RequestException $e) {
      $this->logger->warning($e->getMessage());
    }
    return NULL;
  }

}
