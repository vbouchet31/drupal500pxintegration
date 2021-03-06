<?php

/**
 * @file
 * Contains Drupal\D500px\D500pxIntegration.
 */

namespace Drupal\D500px;

use Drupal\Core\Config\ConfigFactory;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

/**
 * Primary 500px API implementation class.
 *
 * @package Drupal\D500px
 */
class D500pxIntegration {

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * @var string
   */
  protected $request_token_url;

  /**
   * @var string
   */
  protected $authorize_url;

  /**
   * @var string
   */
  protected $authenticate_url;

  /**
   * @var string
   */
  protected $access_token_url;

  /**
   * @var string
   */
  protected $generic_url;

  /**
   * @var string
   */
  protected $website_url;


  /**
   * Constructor for the 500px class.
   */
  public function __construct(ConfigFactory $config_factory) {
    // Get the config.
    $this->config = $config_factory->get('d500px.settings');

    // Add 500px config.
    $this->request_token_url = $this->config->get('api_uri') . '/v1/oauth/request_token';
    $this->authorize_url = $this->config->get('api_uri') . '/v1/oauth/authorize';
    $this->authenticate_url = $this->config->get('api_uri') . '/v1/oauth/authenticate';
    $this->access_token_url = $this->config->get('api_uri') . '/v1/oauth/access_token';
    $this->generic_url = $this->config->get('api_uri') . '/v1/';
    $this->website_url = $this->config->get('host_uri');

    // Guzzle oAuth client.
    $stack = HandlerStack::create();

    $middleware = new Oauth1([
      'consumer_key'      => $this->config->get('oauth_consumer_key'),
      'consumer_secret'   => $this->config->get('oauth_consumer_secret'),

      // TODO investigate how to fetch tokens from 500px.
      // Until then set the token_secret to null.
      'token_secret'      => ''
    ]);

    $stack->push($middleware);

    $this->client = new Client([
      'base_uri' => $this->generic_url,
      'handler' => $stack,
      'auth' => 'oauth',
      //'debug' => true
    ]);

  }

  /**
   * Generic method to perform a request to 500px servers.
   *
   * @param $url
   * @param array $parameters
   * @param string $method
   * @return mixed
   */
  public function requestD500px($url, $parameters = array(), $method = 'GET') {
    $response = $this->client->request($method, $url, ['query' => $parameters]);

    // TODO Add some checking.
    $body = $response->getBody();

    // TODO Add some checking.
    return json_decode((string) $body);
  }

  /**
   * Helper method to get photos.
   *
   * @param array $parameters
   * @return array
   *
   * TODO Figure out a better place for these helper functions.
   */
  public function getPhotos($parameters = array()) {
    $photos = $this->requestD500px('photos', $parameters)->photos;
    $themed_photos = NULL;

    foreach ($photos as $photo_obj) {
      $themed_photos[] = array(
        '#theme' => 'd500px_photo',
        '#photo' => $this->preparePhoto($photo_obj),
        '#photo_page_url' => $this->website_url . $photo_obj->url,
      );
    }

    return array('#theme' => 'd500px_photos', '#photos' => $themed_photos);
  }

  /**
   * Helper method to prepare a photo just after we retrieved it from 500px.
   *
   * @param $photo_obj
   * @return array
   *
   * TODO Figure out a better place for these helper functions.
   */
  public function preparePhoto($photo_obj) {
    $size = $photo_obj->images[0]->size;
    $title = $photo_obj->name;
    $img_url = $photo_obj->image_url;

    // TODO Add NSFW image logic.
    $nsfw = $photo_obj->nsfw;

    $d500px_photo_sizes_array = $this->d500px_photo_get_sizes();
    $d500px_photo_size_array = $d500px_photo_sizes_array[$size];

    $attributes['class'][] = 'd500px_photo_size_'. $size;
    $attributes['class'][] = 'd500px_photo';
    $attributes['class'] = implode(' ', $attributes['class']);

    $image = array(
      '#theme' => 'image',
      '#style_name' => NULL,
      '#uri' => $img_url,
      '#alt' => $title,
      '#title' => $title,
      '#width' => $d500px_photo_size_array['height'],
      '#height' => $d500px_photo_size_array['width'],
      '#attributes' => array('class' => $attributes['class']),
    );

    return $image;
  }

  // TODO Figure out a better place for these helper functions.
  // TODO Update naming conventions to match accorgingly.
  public function d500px_photo_get_sizes() {
    $d500px_photo_sizes_array = array(
      1 => array('height' => 70, 'width' => 70),
      2 => array('height' => 140, 'width' => 140),
      3 => array('height' => 280, 'width' => 280),
      100 => array('height' => 100, 'width' => 100),
      200 => array('height' => 200, 'width' => 200),
      440 => array('height' => 440, 'width' => 440),
      600 => array('height' => 600, 'width' => 600),
    );

    return $d500px_photo_sizes_array;
  }

  // TODO Figure out a better place for these helper functions.
  // TODO Update naming conventions to match accorgingly.
  public function d500px_available_features() {
    $features_array = array(
      'popular' => t('Popular Photos.'),
      'highest_rated' => t('Highest rated photos.'),
      'upcoming' => t('Upcoming photos.'),
      'editors' => t('Editors Choice.'),
      'fresh_today' => t('Fresh Today.'),
      'fresh_yesterday' => t('Fresh Yesterday.'),
      'fresh_week' => t('Fresh This Week.'),
      'user' => t('Photos by specified user.'),
      'user_friends' => t('Photos by users the specified user is following.'),
     );

    return $features_array;
  }

  // TODO Figure out a better place for these helper functions.
  // TODO Update naming conventions to match accorgingly.
  public function d500px_available_sort_options() {
    $sort_options_array = array(
      'created_at' => t('Time of upload, most recent first'),
      'rating' => t('Rating, highest rated first'),
      'times_viewed' => t('View count, most viewed first'),
      'votes_count' => t('Votes count, most voted first'),
      'favorites_count' => t('Favorites count, most favorited first'),
      'comments_count' => t('Comments count, most commented first'),
      'taken_at' => t('Metadata date, most recent first'),
    );

    return $sort_options_array;
  }

  // TODO Figure out a better place for these helper functions.
  // TODO Update naming conventions to match accorgingly.
  function d500px_available_categories() {
    $categories_array = array(
      '- All -' => '- All -',
      0 => 'Uncategorized',
      10 => 'Abstract',
      11 => 'Animals',
      5 => 'Black and White',
      1 => 'Celebrities',
      9 => 'City and Architecture',
      15 => 'Commercial',
      16 => 'Concert',
      20 => 'Family',
      14 => 'Fashion',
      2 => 'Film',
      24 => 'Fine Art',
      23 => 'Food',
      3 => 'Journalism',
      8 => 'Landscapes',
      12 => 'Macro',
      18 => 'Nature',
      4 => 'Nude',
      7 => 'People',
      19 => 'Performing Arts',
      17 => 'Sport',
      6 => 'Still Life',
      21 => 'Street',
      26 => 'Transportation',
      13 => 'Travel',
      22 => 'Underwater',
      27 => 'Urban Exploration',
      25 => 'Wedding',
    );

    return $categories_array;
  }

}
