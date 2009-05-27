<?php
/**!info**
{
  "Plugin Name"  : "Lightbox Gallery",
  "Plugin URI"   : "http://enanocms.org/plugin/lightboxgallery",
  "Description"  : "Adds a <lightboxgallery> tag that lets you have a gallery triggered on click of a thumbnail or something. Documentation at provided URL.",
  "Author"       : "Dan Fuhry",
  "Version"      : "0.1",
  "Author URI"   : "http://enanocms.org/"
}
**!*/

// Hook into wikitext render flow
$plugins->attachHook('render_wikiformat_posttemplates', 'lbgallery_process_tags($text);');
$plugins->attachHook('html_attribute_whitelist', '$whitelist["lightboxgallery"] = array("maxwidth"); $whitelist["trigger"] = array(); $whitelist["randomimage"] = array("/");');

function lbgallery_process_tags(&$text)
{
  // if there are no galleries in this blob, just get out here. also pulls all the matches we need.
  if ( !preg_match_all('#<lightboxgallery(?: maxwidth="?([0-9]+)"?)?>(.+?)</lightboxgallery>#s', $text, $matches) )
    return true;
  
  lbgallery_add_headers();
  
  foreach ( $matches[0] as $i => $match )
  {
    // actual parser loop is pretty simple.
    $gallery = lbgallery_build_gallery($matches[2][$i], $matches[1][$i]);
    $text = str_replace($match, $gallery, $text);
  }
}

function lbgallery_add_headers()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $template->add_header('<script type="text/javascript" src="' . cdnPath . '/includes/clientside/static/jquery.js"></script>');
  $template->add_header('<script type="text/javascript" src="' . cdnPath . '/includes/clientside/static/jquery-ui.js"></script>');
  $template->add_header('<script type="text/javascript" src="' . scriptPath . '/plugins/lightboxgallery/jquery.lightbox-0.5.pack.js"></script>');
  $template->add_header('<link rel="stylesheet" type="text/css" href="' . scriptPath . '/plugins/lightboxgallery/jquery.lightbox-0.5.css" />');
  $template->add_header('<script type="text/javascript">
      var loaded_components = loaded_components || {};
      loaded_components["jquery.js"] = true;
      loaded_components["jquery-ui.js"] = true;
      if ( window.pref_disable_js_fx )
      {
        jQuery.fx.off = true;
      }
      function lbgallery_construct(selector)
      {
        var settings = {
          // Configuration related to images
          imageLoading:			\'' . cdnPath . '/images/loading-big.gif\',		// (string) Path and the name of the loading icon
          imageBtnPrev:			\'' . scriptPath . '/plugins/lightboxgallery/images/lightbox-btn-prev.gif\',			// (string) Path and the name of the prev button image
          imageBtnNext:			\'' . scriptPath . '/plugins/lightboxgallery/images/lightbox-btn-next.gif\',			// (string) Path and the name of the next button image
          imageBtnClose:		\'' . scriptPath . '/plugins/lightboxgallery/images/lightbox-btn-close.gif\',		// (string) Path and the name of the close btn
          imageBlank:				\'' . cdnPath . '/images/spacer.gif\',			// (string) Path and the name of a blank image (one pixel)
        };
        jQuery(selector).lightBox(settings);
      }
    </script>');
}

// The actual function to build the HTML behind a gallery.

function lbgallery_build_gallery($gallerytag, $width)
{
  // parse out any text sections
  $text = preg_replace('#^.*<trigger>(.+?)</trigger>.*$#s', '$1', $gallerytag);
  if ( $text == $gallerytag )
    $text = '';
  $gallerytag = preg_replace('#<trigger>(.+?)</trigger>#s', '', $gallerytag);
  
  $images = explode("\n", $gallerytag);
  if ( empty($images) )
  {
    return '<div class="error-box-mini">' . $lang->get('lboxgal_err_no_images') . '</div>';
  }
  
  $id = 'lbgal' . md5(microtime() . mt_rand());
  $inner = '';
  $width = intval($width);
  if ( empty($width) )
    $width = 640;
  
  $imagelist = array();
  foreach ( $images as $line )
  {
    $line = trim($line);
    if ( empty($line) )
      continue;
    
    list($image) = explode('|', $line);
    $image = sanitize_page_id(trim($image));
    if ( ($alt = strstr($line, '|')) )
    {
      $alt = trim(substr($alt, 1));
    }
    else
    {
      $alt = str_replace('_', ' ', dirtify_page_id($image));
    }
    $imagelist[] = array($image, $alt);
    $tag = '<a class="' . $id . '" href="' . makeUrlNS('Special', "DownloadFile/$image", "preview&width=$width&height=9999", true) . '" title="' . trim(htmlspecialchars(RenderMan::render($alt))) . '">';
    if ( !isset($firstimageid) )
    {
      $firstimagetag = $tag;
      $firstimageid = $image;
      $firstimagealt = $alt;
    }
    else
    {
      $inner .= $tag . '.</a>';
    }
  }
  
  if ( $text )
  {
    $trigger = trim($text);
  }
  else
  {
    $trigger = '<a><randomimage /></a>';
  }
  
  $trigger = str_replace('<a>', $firstimagetag, $trigger);
  
  list($image, $alt) = $imagelist[ array_rand($imagelist) ];
  $randomimage = '<img alt="' . htmlspecialchars($alt) . '" src="' . makeUrlNS('Special', "DownloadFile/$image", "preview", true) . '" />';
  $trigger = str_replace(array('<randomimage>', '<randomimage/>', '<randomimage />'), $randomimage, $trigger);
  
  return "$trigger<nowiki>
    <div style=\"display: none;\">$inner</div>
    <script type=\"text/javascript\">
      addOnloadHook(function()
        {
          lbgallery_construct('a.$id');
        });
    </script></nowiki>";
}

/**!language**

The following text up to the closing comment tag is JSON language data.
It is not PHP code but your editor or IDE may highlight it as such. This
data is imported when the plugin is loaded for the first time; it provides
the strings displayed by this plugin's interface.

You should copy and paste this block when you create your own plugins so
that these comments and the basic structure of the language data is
preserved. All language data is in the same format as the Enano core
language files in the /language/* directories. See the Enano Localization
Guide and Enano API Documentation for further information on the format of
language files.

The exception in plugin language file format is that multiple languages
may be specified in the language block. This should be done by way of making
the top-level elements each a JSON language object, with elements named
according to the ISO-639-1 language they are representing. The path should be:

  root => language ID => categories array, ( strings object => category \
  objects => strings )

All text leading up to first curly brace is stripped by the parser; using
a code tag makes jEdit and other editors do automatic indentation and
syntax highlighting on the language data. The use of the code tag is not
necessary; it is only included as a tool for development.

<code>
{
  eng: {
    categories: [ 'meta', 'lboxgal' ],
    strings: {
      meta: {
        lboxgal: 'Lightbox gallery plugin'
      },
      lboxgal: {
        msg_docs: 'See <a href="http://enanocms.org/plugin/lightboxgallery">lightboxgallery on enanocms.org</a> for usage information.',
        err_no_images: 'No images specified in gallery. %this.lboxgal_msg_docs%',
      }
    }
  }
}
</code>

**!*/
