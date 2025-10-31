<?php
// Configures the GRead app for iOS

// This adds the PWA to the website's header, which is required for making everything work on iOS/Andriod.
function add_pwa_to_head()
{
	echo '<link rel="manifest" href="/manifest.json">';

	echo '<meta name="theme-color" content="#000000">';

	echo '<link rel="apple-touch-icon" href="/apple-touch-icon.png">';

	echo '<meta name="apple-mobile-web-app-capable" content="yes">';
	echo '<meta name="apple-mobile-web-app-status-bar-style" content="black">';
	echo '<meta name="apple-mobile-web-app-title" content="GRead">';
}

add_action('wp_head', 'add_pwa_to_head');
