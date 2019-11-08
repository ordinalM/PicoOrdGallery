<?php
/**
 * PicoOrdGallery
 *
 * A gallery plugin for the Pico CMS.
 *
 * @author  OrdinalM
 * @link    https://github.com/ordinalM/PicoOrdGallery
 * @license http://opensource.org/licenses/MIT The MIT License
 * @version 0.1
 */
class PicoOrdGallery extends AbstractPicoPlugin
{
  /**
   * API version used by this plugin
   *
   * @var int
   */
  const API_VERSION = 2;


  /**
   * Triggered after Pico has read its configuration
   *
   * @see Pico::getConfig()
   * @see Pico::getBaseUrl()
   * @see Pico::getBaseThemeUrl()
   * @see Pico::isUrlRewritingEnabled()
   *
   * @param array &$config array of config variables
   *
   * @return void
   */
  public function onConfigLoaded(array &$config)
  {
    $default_config = array(
      'default' => array(
        'thumbnail_size' => array('y' => 200),
        'gallery_class' => 'pico-ord-gallery',
        'gallery_item_class' => 'pico-ord-gallery-item',
        'thumbnail_quality' => 75,
        'cache_dir' => 'cache/PicoOrdGallery',
      ),
    );
    $this->config = $default_config;
    foreach ($config['pico_ord_gallery'] as $style => $style_options) {
      $this->config[$style] = $this->config['default'];
      foreach ($style_options as $opt => $value) {
        if (isset($this->config[$style][$opt])) {
          $this->config[$style][$opt] = $value;
        }
      }
      // Initialise cache directory if necessary
      $this->config[$style]['cache_dir'] = trim($this->config[$style]['cache_dir'], '/ ') . '/';
      if (!is_dir($this->config[$style]['cache_dir'])) {
        mkdir($this->config[$style]['cache_dir'], '0755', TRUE);
      }
      // Check that cache dir is valid
      if (!is_writable($this->config[$style]['cache_dir'])) {
        throw new RuntimeException('Cache directory for PicoOrdGallery is not writable');
      }
      // Sort thumbnail size to maintain cache keys
      ksort($this->config[$style]['thumbnail_size']);
      // Validate thumbnail quality
      $this->config[$style]['thumbnail_quality'] = (int)$this->config[$style]['thumbnail_quality'];
      if ($this->config[$style]['thumbnail_quality'] <= 0) {
        $this->config[$style]['thumbnail_quality'] = $this->config['default']['thumbnail_quality'];
      }
    }
  }

  /**
   * Triggered after Pico has prepared the raw file contents for parsing
   *
   * @see DummyPlugin::onContentParsing()
   * @see Pico::parseFileContent()
   * @see DummyPlugin::onContentParsed()
   *
   * @param string &$markdown Markdown contents of the requested page
   *
   * @return void
   */
  public function onContentPrepared(&$markdown)
  {
    // Replace gallery shortcode "%pico_ord_gallery <dir>%" with appropriate HTML
    $markdown = preg_replace_callback('/%pico_ord_gallery (.+?)%/', function ($matches) {
      // See if there are any style names here
      $gallery_style = 'default';
      $components = explode(' ', $matches[1]);
      if (count($components) > 1) {
        if (!empty($this->config[$components[1]])) {
          $gallery_style = $components[1];
        }
        $matches[1] = $components[0];
      }
      // Process
      $dir = trim($matches[1], '/ ') . '/';
      if (is_dir($dir)) {
        $images = array();
        $dh = opendir($dir);
        while (($filename = readdir($dh)) !== FALSE) {
          if (is_file($dir . $filename)) {
            $images[] = (object)array(
              'thumb' => $this->createImageThumbnail($dir . $filename, $gallery_style),
              'source' => $dir . $filename,
            );
          }
        }
        if (count($images) > 0) {
          $image_html = array_reduce(
            $images,
            function ($carry, $image) use ($gallery_style) {
              $image_name = basename($image->source);
              $image_name = preg_replace('/\.[a-zA-Z0-9]+$/', '', $image_name);
              $image_name = preg_replace('/[-_]+/', ' ', $image_name);
              return $carry . sprintf(
                '<span class="%s"><a href="/%s"><img src="/%s" alt="%s"></a></span>',
                $this->config[$gallery_style]['gallery_item_class'],
                $image->source,
                $image->thumb,
                $image_name
              ) . "\n";
            }
          );
          return sprintf(
            "\n\n<div class=\"%s\">\n%s</div>\n\n",
            $this->config[$gallery_style]['gallery_class'],
            $image_html
          );
        }
      }
      // If unable to process, just remove the shortcode.
      return '';
    }, $markdown);
  }

  /**
   * Utility function to create an image thumbnail
   */
  public function createImageThumbnail($filepath, $style = 'default') {
    if (empty($this->config[$style])) {
      $style = 'default';
    }
    // Create an appropriate unique filename for this thumbnail
    $thumb_filename = md5(serialize($this->config[$style]['thumbnail_size']) . ':'. $filepath) . '.jpg';
    // Just in case we are generating an enormous number of thumbnails, put this in a subdir of the cache dir
    $thumb_subdir = $this->config[$style]['cache_dir'] . substr($thumb_filename, 0, 1) . '/';
    if (!is_dir($thumb_subdir)) {
      mkdir($thumb_subdir, '0755');
    }
    $thumb_filepath =  $thumb_subdir . $thumb_filename;
    // Has it not already been created, or is the source file now newer?
    // If so make a new thumbnail.
    if (!file_exists($thumb_filepath) || filemtime($thumb_filepath) < filemtime($filepath)) {
      list($x, $y, $type) = getimagesize($filepath);
      $source = FALSE;
      // We can deal with JPEGs, GIFs and PNGs.
      switch ($type) {
        case IMG_JPG:
        case IMG_JPEG:
          $source = imagecreatefromjpeg($filepath);
          break;
        case IMG_GIF:
          $source = imagecreatefromgif($filepath);
          break;
        case IMG_PNG:
          $source = imagecreatefrompng($filepath);
          break;
      }
      // If the image can be loaded...
      if ($source) {
        // Calculate the image ratio that we want to scale to
        $ratio = 1;
        foreach (array('x', 'y') as $dimension) {
          if (isset($this->config[$style]['thumbnail_size'][$dimension])) {
            $ratio = min($ratio, $this->config[$style]['thumbnail_size'][$dimension] / ${$dimension});
          }
        }
        $tx = round($x * $ratio);
        $ty = round($y * $ratio);
        $thumb = imagecreatetruecolor($tx, $ty);
        imagecopyresized($thumb, $source, 0, 0, 0, 0, $tx, $ty, $x, $y);
        // Try to output the thumbnail as a JPEG
        imagejpeg($thumb, $thumb_filepath, $this->config[$style]['thumbnail_quality']);
      }
    }
    return $thumb_filepath;
  }
}
