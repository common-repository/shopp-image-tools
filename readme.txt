=== Shopp Image Tools ===
Contributors: WebsiteBakery, crunnells
Donate link: http://freshlybakedwebsites.net/say-thanks-with-a-beer/
Tags: shopp, ecommerce, images, performance, converter
Requires at least: 3.4.2
Tested up to: 3.4.2
Stable tag: 1.3.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Quickly convert all of your database-stored Shopp product images to regular disk files - then increase performance
further with Direct Image Mode!

== Description ==

* No limit on the number of images you can convert
* Optional speed boost with Direct Image Mode
* Optionally remove the old images from the database after migration
* Find out more on the [author's blog](http://freshlybakedwebsites.net/wordpress/shopp/product-photo-migration/)

Shopp Image Tools is a utility plugin for users of the [Shopp e-commerce plugin](https://shopplugin.net).
By default, Shopp is configured so that product images are stored in the database, this means additional database
use every time one of these images is fetched.

While this mode can be turned off there is no built-in mechanism to pull the images back out of the database and
store them as regular files - that is what this plugin is designed to help with. It now also includes tools to
clean-up the database that you can optionally enable.

Additionally, it includes an experimental feature called Direct Image Mode. The aim of this mode is to speed
things up even further - by eliminating unnecessary use of the Shopp Image Server. If a cached copy of an image
in the appropriate size is already saved on the file system then Direct Image Mode tries to make it directly
accessible.

Of course, if the cached file does not exist then the direct image code stands down and lets the Shopp Image
Server do its job. It's the best of both worlds, increased performance - particularly on shared hosting packages -
with all of Shopp's flexibility.

*Shopp Image Tools 1.3 will be the next release* and has been designed for compatibility with Shopp 1.3 and
WordPress 3.7.1 or greater. It is largely ready-to-go but will not be released until Shopp 1.3 itself is ready. Thank
you for your patience and support in the meantime!

= Author =

This plugin was written by
[Barry Hughes (Freshly Baked Websites)](http://freshlybakedwebsites.net "PHP and WordPress Design and Development, Vancouver Island")
- feel free to use the donate link to buy him a beer if
this plugin saves you some time or even brings you in more sales by offering your customers a slicker shopping
experience!

== Installation ==

This plugin should be installed as per any other plugin. Upload the .zip archive via the plugin admin page or
upload the uncompressed shopp-image-tools directory via FTP, then activate via the plugin admin page.

== Frequently Asked Questions ==

= Why migrate images from the database to the filesystem =

Very often, especially when Shopp is being used in a shared hosting environment, this allows for far faster
delivery of images and reduces the burden on server resources considerably.

= What Should I Do Before Converting? =

Set up a directory to store your disk based images, something like `wp-content/uploads/shopp` is fine. You will
need to adjust the Shopp System settings to reflect this ... if in doubt, seek advice from the Shopp documentation.

As soon as you're done, get back to Shopp Image Tools and run the conversion tool. No need to worry - if you
haven't set something up properly, it will very likely tell you the problem!

As always, you should back-up before making any major adjustments to your system and remember: a back-up is useless
if you do not know how to restore it.

= What about Direct Mode? =

First and foremost, it is experimental and may not work in all conditions, but the basic idea is that it removes
the need for image requests to pass through the Shopp Image Server. By removing this (sometimes unecessary)
layer images can be served to customers more efficiently - so this in some cases is a good step on the road to
faster page views.

= Removing Old Images =

Once images have successfully been migrated to the file system the old copy, by default, remains in the database.
This is intentional as the plugin aims to be as non-destructive as possible. However, some people may wish to remove
unused copies of images from the database completely for performance reasons or simply as a matter of good housekeeping.

Version 1.1 introduces a toolset to do just this. You can instruct the Conversion Tool to clean up after successfully
migrating images and if you forget to do this you can also use the Orphan Cleanup Tool to locate disused images within
the database and purge them. I strongly recommend that you back-up before using these tools.

== Screenshots ==

1. Shopp Image Tools can be accessed from the Shopp Setup menu - its nestled conveniently under the existing
Setup > Images option. It provides a summary of how many images are stored in the database, the file system or
elsewhere (elsewhere might mean Amazon S3 or another silo). The conversion tool is one click away and switching
Direct Image Mode on and off is just as easy.

== Changelog ==

= 1.0.3 =
* Public release

= 1.1.1 =
* Added cleanup tools to optionally remove images from the database (once they have been successfully moved to the
filesystem)
* Improved feedback: the summary of totals now updates as the conversion job progresses
* Thanks to Albert Boddema for supporting this release

== Upgrade Notice ==

Update either by deleting the existing version and uploading the new version in its stead (reactivate if necessary) or
else use the automatic update process built in to WordPress.
