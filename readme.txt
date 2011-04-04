=== Plugin Name ===
Contributors: niallkennedy
Tags: flickr, shortcode, photography
Requires at least: 3.0
Tested up to: 3.1
Stable tag: 0.1

Display a Flickr photo using a shortcode. Include MediaRSS output in your Atom feed.

== Description ==

Display a photo from Flickr using a simple shortcode.

`[flickr photo="1234"]`
`[flickr photo="1234" w=400]`

This plugin requests information about the photograph, including photo sizes, from Flickr via the Flickr API. Requires a [Flickr API key](http://www.flickr.com/services/api/keys/) to function (free for non-commercial use).

Works with the `content_width` of your theme to load the highest quality Flickr image stretched to the width of your content column.

Includes [Media RSS markup](http://video.search.yahoo.com/mrss) including all available photo sizes and original photographer credits. Helps feed readers better understand your visual content and helps make your content more searchable.

All HTTP requests pass through WP_HTTP for use with your existing filters. It's also more WordPress-y.

== Installation ==

You should be familiar with installing a WordPress plugin.

1. Search for this Flickr plugin from your WordPress administrative interface.
1. Download a compressed package. Uncompress. Activate.
1. Don't forget to activate the plugin.
1. Add your Flickr API key in your site's Media Settings ( Settings -> Media )

== Screenshots ==

1. Flickr photographs automatically expand to your theme's `content_width` including mobile and tablet themes.
2. Save your Flickr API key in your site's Media Settings.

== Frequently Asked Questions ==

= Why do I need an API key? =

Flickr requires an API key for programmatic access to its servers.

= Do you support Flickr videos? =

Not yet. The plugin author does not embed Flickr videos on his blog.

= Do you support Flickr slideshows? =

No. The plugin author does not embed Flickr slideshows on his blog.

== Changelog ==

= 0.1 =

Initial release. Display a photo in the content; include Media RSS in the feed.