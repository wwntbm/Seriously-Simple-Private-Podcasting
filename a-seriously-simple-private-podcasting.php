<?php
/**
 * Plugin Name: Seriously Simple Private Podcasting
 * Plugin URL: https://github.com/wwntbm/Seriously-Simple-Private-Podcasting/
 * Description: Include posts marked “private” in podcast feed. Note: activate this <strong>first</strong>, then the Seriously Simple Podcasting plugin.
 * Version: 1.2.0
 * Author: AndrewRMinion Design
 * Author URI: https://andrewrminion.com
 *
 * @author AndrewRMinion Design
 * @package Seriously Simple Private Podcasting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function get_ssp_date_query() {
	$today    = new DateTime();
	$tomorrow = $today->add( new DateInterval( 'P1D' ) );

	return array(
		'before' => $tomorrow->format( 'Y-m-d' ),
	);
}

if ( ! function_exists( 'ssp_episode_ids' ) ) {
	/**
	 * Get post IDs of all podcast episodes for all post types.
	 *
	 * Overrides ssp_episode_ids function in main plugin.
	 *
	 * @return array
	 */
	function ssp_episode_ids() {
		global $ss_podcasting;

		// Remove action to prevent infinite loop.
		remove_action( 'pre_get_posts', array( $ss_podcasting, 'add_all_post_types' ) );

		// Setup the default args.
		$args = array(
			'post_type'      => array( 'podcast' ),
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		// Prevent non-admins from seeing scheduled episodes.
		if ( ! current_user_can( 'manage_options' ) ) {
			$args['date_query'] = get_ssp_date_query();
		}

		// Do we have any additional post types to add?
		$podcast_post_types = ssp_post_types( false );

		if ( ! empty( $podcast_post_types ) ) {
			$args['post_type']  = ssp_post_types();
			$args['meta_query'] = array(
				array(
					'key'     => apply_filters( 'ssp_audio_file_meta_key', 'audio_file' ),
					'compare' => '!=',
					'value'   => '',
				),
			);
		}

		// Do we have this stored in the cache?
		$key              = 'episode_ids';
		$group            = 'ssp';
		$podcast_episodes = wp_cache_get( $key, $group );

		// If nothing in cache then fetch episodes again and store in cache.
		if ( false === $podcast_episodes ) {
			$query = new WP_Query( $args );
			$podcast_episodes = $query->get_posts();
			wp_cache_set( $key, $podcast_episodes, $group, HOUR_IN_SECONDS * 12 );
		}

		// Reinstate action for future queries.
		add_action( 'pre_get_posts', array( $ss_podcasting, 'add_all_post_types' ) );

		return $podcast_episodes;
	}
}

/**
 * Add private posts to the podcast list.
 *
 * @param  array $args WPQuery args.
 *
 * @return array       WPQuery args.
 */
function ssp_private_add_private_podcast_episodes( $args ) {
	$args['post_status'] = array(
		'publish',
		'private',
	);

	// Prevent non-admins from seeing scheduled episodes.
	if ( ! current_user_can( 'manage_options' ) ) {
		$args['date_query'] = get_ssp_date_query();
	}

	return $args;
}
add_filter( 'ssp_episode_query_args', 'ssp_private_add_private_podcast_episodes' );

/**
 * Include private podcasts in search.
 *
 * @param object $query WP_Query object.
 *
 * @return  void        Sets WP_Query args.
 */
function ssp_private_include_private_in_search( $query ) {
	if ( ! is_admin() && is_user_logged_in() && 'podcast' === $query->get( 'post_type' ) ) {
		$query->set(
			'post_status', array(
				'publish',
				'private',
			)
		);

		// Prevent non-admins from seeing scheduled episodes.
		if ( ! current_user_can( 'manage_options' ) ) {
			$query->set( 'date_query', get_ssp_date_query() );
		}
	}
}
add_action( 'pre_get_posts', 'ssp_private_include_private_in_search' );

/**
 * Remove “Private:” from private posts.
 *
 * @param  string $content Title.
 *
 * @return string          Title.
 */
function ssp_private_private_title_format( $content ) {
	return '%s';
}
add_filter( 'private_title_format', 'ssp_private_private_title_format' );
add_filter( 'protected_title_format', 'ssp_private_private_title_format' );

/**
 * Allow subscribers to view private podcasts.
 */
function ssp_private_allow_subscribers_private_access() {
	$subscriber = get_role( 'subscriber' );
	$subscriber->add_cap( 'read_private_posts' );
	$subscriber->add_cap( 'read_private_pages' );
}
add_action( 'init', 'ssp_private_allow_subscribers_private_access' );

/**
 * Add date to post columns.
 *
 * @param string[] $post_columns Defined post columns.
 *
 * @return string[]              Defined post columns.
 * @since 1.2.0
 */
function ssp_private_posts_columns( $post_columns ) {
	$post_columns['publish_date'] = __( 'Publish Date', 'ssp-private-podcasts' );
	return $post_columns;
}
add_filter( 'manage_podcast_posts_columns', 'ssp_private_posts_columns', 10 );

/**
 * Display content for the custom column.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 *
 * @return void           Prints content.
 * @since 1.2.0
 */
function ssp_private_custom_column( $column, $post_id ) {
	if ( 'publish_date' === $column ) {
		echo get_the_date( '', $post_id );
	}
}
add_action( 'manage_podcast_posts_custom_column', 'ssp_private_custom_column', 10, 2 );

/**
 * Set publish date to be sortable.
 *
 * @param string[] $columns Sortable columns.
 *
 * @return string[]         Sortable columns.
 * @since 1.2.0
 */
function ssp_private_sortable_columns( $columns ) {
	$columns['publish_date'] = 'date';
	return $columns;
}
add_filter( 'manage_edit-podcast_sortable_columns', 'ssp_private_sortable_columns' );
