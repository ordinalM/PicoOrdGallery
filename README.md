# PicoOrdGallery

A Pico CMS plugin to generate and display a gallery from a directory of images, using one small shortcode.

## Installation

Place `PicoOrdGallery.php` inside the `plugins` directory in your Pico installation. You can clone the whole repository into `plugins` and it should be fine.

## Usage

Once the plugin is installed, in your markdown files, use the shortcode:

```
%pico_ord_gallery <directory>%
```

to generate a gallery from all the images in `<directory>`. For example:

```
%pico_ord_gallery assets/galleries/my_example_gallery%
```

Optionally you may specify a gallery style defined in your configuration as a third argument - see below.

## Configuration

PicoOrdGallery reads from the `pico_ord_gallery` Pico config variable, in `config.yml` or other configuration file.

### Basic configuration example

The following in your `config.yml` will set the default options:

```
##
# PicoOrdGallery
#
pico_ord_gallery:
  default:
    thumbnail_size:
      y: 200
    cache_dir: cache/PicoOrdGallery
    gallery_class: pico-ord-gallery
    gallery_item_class: pico-ord-gallery-item
    thumbnail_quality: 75
```

### Gallery styles

More items in the `pico_ord_gallery` variable will create new styles, which can then be used with shortcodes. Any options not specified will default to the values for the "default" style. The following will create a style called "large" which creates thumbnails with a maximum dimension of 800px:

```
pico_ord_gallery:
  large:
    thumbnail_size:
      x: 800
      y: 800
```

The style name can then be specified as the third argument in the gallery shortcode e.g.

```
%pico_ord_gallery assets/galleries/my_example_gallery large%
```

Any configuration options can be used in a gallery style.

### Available options

#### `thumbnail_size`

Has two properties, `x` and `y`, specifying maximum X and Y sizes (in pixels) of thumbnail images. If only one is set, the other dimension is not restricted. For example:

- y = 200 will make every thumbnail at most 200px in height, but width will be variable depending on the original width. A 1000x500px image will have a 400x200px thumbnail, whereas a 500x1000px one will have a 100x200px thumbnail. Good for use in rows.
- x = 200 will do the same as the above, swapping X for Y. Good for use in columns.
- x = 200 y = 200 will make sure every thumbnail is no more than 200px in any dimension. Good for use in a strict grid.

#### `cache_dir`

Directory used to generate thumbnail files in. This needs to be writable, but the plugin will create intermediary directories as required if it can.

#### `gallery_class` and `gallery_item_class`

HTML classes applied to the whole gallery and each individual item respectively.

#### `thumbnail_quality`

JPEG quality of generated thumbnail files.
