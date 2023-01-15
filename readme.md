# RSS to Indie Likes

Takes items from an RSS feed and turns them into Indie Likes.

You probably don't have the same setup as me (you might use a different plugin or post type for IndieWeb Likes), but perhaps you can reuse some of this code to fit your purposes.

## Description

This plugin fetches posts from an RSS feed and creates new Indie Likes posts for them on your WordPress site. The plugin sets up a cron job to fetch new posts every hour.

The way I use it is to take posts I've saved to a particular folder in my bookmarking app of choice, which I make available via RSS, and post them as likes on my site. 

The pubDate for the items on my RSS feed is the time when I bookmarked them, so I take that and make it the published date.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/rss-to-indie-likes` directory, or download a zip of this repo and install the plugin through the WordPress plugins screen.
2. Activate the plugin.
3. Use the Settings->RSS to Indie Likes screen to configure the plugin.
4. Make sure the Indieblocks plugin is installed and activated

## Requirements

This plugin requires the [IndieBlocks](https://wordpress.org/plugins/indieblocks/) plugin by Jan Boddez to be installed and activated. It is what I prefer to power my website's Likes.

## Configuration

1. Go to the plugin settings page at Tools->RSS to Indie Likes
2. Enter your RSS feed URL.
3. Select the author you want to attribute the likes to from the dropdown menu of existing authors
4. Click 'Save Changes'

## Changelog

### 0.0.1
* Initial release, 2023-01-15