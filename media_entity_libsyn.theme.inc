<?php

/**
 * @file
 * Theme functions for the media_entity_libsyn module.
 */

/**
 * Prepares variables for media_entity_libsyn templates.
 *
 * Default template: media-libsyn-embed.html.twig.
 */
function template_preprocess_media_libsyn_embed(&$variables) {

  // Build the embed url.
  $url = 'https://html5-player.libsyn.com/embed/episode/id/' . $variables['episode_id'];

  foreach (['embed_theme' => 'theme', 'direction' => 'direction', 'custom_color' => 'custom-color'] as $key => $slug) {
    if (isset($variables[$key]) && !empty($variables[$key])) {
      $url .= '/' . $slug . '/' . $variables[$key];
    }
  }

  // Add options.
  foreach ($variables['options'] as $option => $value) {
    $slug = $option;
    if ($option == 'render_playlist') {
      $slug = 'render-playlist';
    }
    $url .= '/' . $slug . '/' . ($value? 'yes': 'no');
  }

  $variables['url'] = $url;
}
