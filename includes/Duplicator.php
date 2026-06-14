<?php
/**
 * Duplicator — the single clone path for posts, pages, and public CPTs.
 *
 * Deep-copies a source post into a new draft: core fields, every taxonomy,
 * every post meta value (minus an internal exclusion list), and — because they
 * are stored as post meta — the featured image (`_thumbnail_id`) and page
 * template (`_wp_page_template`) ride along automatically.
 *
 * WooCommerce products are intentionally NOT handled here; cloning a variable
 * product's parent alone yields a broken product, so products are excluded from
 * the supported list until ProductDuplicator lands (see BUILD-PLAN.md, Phase 4).
 *
 * Settings (default status, title suffix, excluded keys, allowed roles) become
 * configurable in the Settings phase; for now they use the documented defaults.
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate;

defined( 'ABSPATH' ) || exit;

class Duplicator {

	/** Appended to the cloned title (configurable in the Settings phase). */
	const TITLE_SUFFIX = ' (copy)';

	/** Default status of a freshly cloned post. */
	const DEFAULT_STATUS = 'draft';

	/**
	 * Resolve the right duplicator for a post: ProductDuplicator for WooCommerce
	 * products, the base Duplicator for everything else.
	 *
	 * @param int $post_id Post to duplicate.
	 * @return Duplicator
	 */
	public static function for_post( $post_id ) {
		$post = get_post( $post_id );
		if ( $post instanceof \WP_Post && 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			return new ProductDuplicator();
		}
		return new self();
	}

	/**
	 * Clone a post and return the new post ID.
	 *
	 * @param int $source_post_id Post being duplicated.
	 * @return int|\WP_Error New draft ID on success, WP_Error on failure.
	 */
	public function clone_post( $source_post_id ) {
		$source_post = get_post( $source_post_id );
		if ( ! $source_post instanceof \WP_Post ) {
			return new \WP_Error(
				'cdup_source_missing',
				__( 'The item to duplicate could not be found.', 'chada-duplicate' )
			);
		}

		$new_post_args = array(
			'post_title'            => $source_post->post_title . $this->title_suffix(),
			'post_content'          => $source_post->post_content,
			'post_content_filtered' => $source_post->post_content_filtered,
			'post_excerpt'          => $source_post->post_excerpt,
			'post_status'           => $this->resolved_status( $source_post->post_status ),
			'post_type'             => $source_post->post_type,
			'post_author'           => $this->resolved_author( $source_post->post_author ),
			'post_parent'           => $source_post->post_parent,
			'menu_order'            => $source_post->menu_order,
			'post_password'         => $source_post->post_password,
			'comment_status'        => $source_post->comment_status,
			'ping_status'           => $source_post->ping_status,
			// Leave the slug empty so WordPress derives a fresh, unique one from
			// the new title — copying the original slug only forces a -2 suffix.
			'post_name'             => '',
		);

		/**
		 * Filter the arguments used to insert the cloned post.
		 *
		 * @param array    $new_post_args Args passed to wp_insert_post().
		 * @param \WP_Post $source_post   The post being duplicated.
		 */
		$new_post_args = apply_filters( 'cdup_new_post_args', $new_post_args, $source_post );

		$new_post_id = wp_insert_post( wp_slash( $new_post_args ), true );
		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		$this->copy_taxonomies( $source_post, $new_post_id );
		$this->copy_meta( $source_post->ID, $new_post_id );

		if ( Settings::get( 'copy_comments' ) ) {
			$this->copy_comments( $source_post->ID, $new_post_id );
		}

		/**
		 * Fires after a post has been cloned (before any product-specific work).
		 *
		 * @param int      $new_post_id   The newly created draft.
		 * @param \WP_Post $source_post   The post that was duplicated.
		 */
		do_action( 'cdup_post_cloned', $new_post_id, $source_post );

		return $new_post_id;
	}

	/**
	 * Configured title suffix (e.g. " (copy)").
	 *
	 * @return string
	 */
	protected function title_suffix() {
		$suffix = Settings::get( 'title_suffix' );
		return is_string( $suffix ) ? $suffix : self::TITLE_SUFFIX;
	}

	/**
	 * Status for the clone: the source status when "same as original" is set,
	 * otherwise the default (draft).
	 *
	 * @param string $source_status Status of the source post.
	 * @return string
	 */
	protected function resolved_status( $source_status ) {
		return ( 'inherit' === Settings::get( 'default_status' ) ) ? $source_status : self::DEFAULT_STATUS;
	}

	/**
	 * Author for the clone: the source author when "copy author" is on, otherwise
	 * the current user.
	 *
	 * @param int $source_author Source post author ID.
	 * @return int
	 */
	protected function resolved_author( $source_author ) {
		return Settings::get( 'copy_author' ) ? (int) $source_author : get_current_user_id();
	}

	/**
	 * Copy comments from the source post to the clone, preserving threading.
	 *
	 * @param int $source_post_id Source post ID.
	 * @param int $new_post_id    Destination post ID.
	 * @return void
	 */
	private function copy_comments( $source_post_id, $new_post_id ) {
		$comments = get_comments(
			array(
				'post_id' => $source_post_id,
				'orderby' => 'comment_ID',
				'order'   => 'ASC',
				'status'  => 'all',
			)
		);

		$old_to_new = array();
		foreach ( $comments as $comment ) {
			$parent_id = (int) $comment->comment_parent;
			$new_comment_id = wp_insert_comment(
				wp_slash(
					array(
						'comment_post_ID'      => $new_post_id,
						'comment_author'       => $comment->comment_author,
						'comment_author_email' => $comment->comment_author_email,
						'comment_author_url'   => $comment->comment_author_url,
						'comment_author_IP'    => $comment->comment_author_IP,
						'comment_content'      => $comment->comment_content,
						'comment_type'         => $comment->comment_type,
						'comment_parent'       => isset( $old_to_new[ $parent_id ] ) ? $old_to_new[ $parent_id ] : 0,
						'comment_date'         => $comment->comment_date,
						'comment_approved'     => $comment->comment_approved,
						'comment_agent'        => $comment->comment_agent,
						'user_id'              => $comment->user_id,
					)
				)
			);
			if ( $new_comment_id ) {
				$old_to_new[ (int) $comment->comment_ID ] = $new_comment_id;
			}
		}
	}

	/**
	 * Copy every taxonomy term from the source post to the clone.
	 *
	 * @param \WP_Post $source_post Source post.
	 * @param int      $new_post_id Destination post ID.
	 * @return void
	 */
	private function copy_taxonomies( $source_post, $new_post_id ) {
		$taxonomies = get_object_taxonomies( $source_post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$term_ids = wp_get_object_terms( $source_post->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
				continue;
			}
			wp_set_object_terms( $new_post_id, $term_ids, $taxonomy );
		}
	}

	/**
	 * Copy all post meta except the internal/excluded keys.
	 *
	 * `get_post_meta( $id )` returns raw (still-serialized) values, so each is
	 * run through maybe_unserialize() then re-slashed for add_post_meta().
	 *
	 * @param int $source_post_id Source post ID.
	 * @param int $new_post_id    Destination post ID.
	 * @return void
	 */
	private function copy_meta( $source_post_id, $new_post_id ) {
		$excluded_meta_keys = self::excluded_meta_keys();
		$all_meta           = get_post_meta( $source_post_id );

		foreach ( $all_meta as $meta_key => $meta_values ) {
			if ( in_array( $meta_key, $excluded_meta_keys, true ) ) {
				continue;
			}
			foreach ( (array) $meta_values as $raw_meta_value ) {
				add_post_meta( $new_post_id, $meta_key, wp_slash( maybe_unserialize( $raw_meta_value ) ) );
			}
		}
	}

	/**
	 * Internal meta keys that must NOT be copied — copying them corrupts the new
	 * post. Note `_thumbnail_id` (featured image) and `_wp_page_template` are
	 * deliberately absent so they DO carry over.
	 *
	 * @return string[]
	 */
	public static function excluded_meta_keys() {
		$excluded_meta_keys = Settings::get( 'excluded_meta' );
		$excluded_meta_keys = is_array( $excluded_meta_keys ) ? $excluded_meta_keys : array();

		// Always protect trash-state keys regardless of the user's list.
		$excluded_meta_keys = array_merge( $excluded_meta_keys, array( '_wp_trash_meta_status', '_wp_trash_meta_time' ) );

		/**
		 * Filter the meta keys skipped when cloning.
		 *
		 * @param string[] $excluded_meta_keys Keys to skip.
		 */
		return apply_filters( 'cdup_excluded_meta_keys', array_values( array_unique( $excluded_meta_keys ) ) );
	}

	/**
	 * Every post type that MAY be enabled on the settings page: public, UI-visible
	 * types minus attachments and product variations. Returned as objects keyed
	 * by slug so the settings UI can show labels.
	 *
	 * @return array<string,\WP_Post_Type>
	 */
	public static function candidate_post_types() {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		unset(
			$post_types['attachment'],
			// Variations are child posts of a product, never duplicated standalone.
			$post_types['product_variation']
		);

		return $post_types;
	}

	/**
	 * Post types the duplicate action actually applies to: the candidate types
	 * narrowed to the ones enabled on the settings page.
	 *
	 * @return string[]
	 */
	public static function supported_post_types() {
		$candidates    = array_keys( self::candidate_post_types() );
		$enabled_types = Settings::get( 'post_types' );

		$post_types = is_array( $enabled_types )
			? array_values( array_intersect( $candidates, $enabled_types ) )
			: $candidates;

		/**
		 * Filter the post types that get the Duplicate action.
		 *
		 * @param string[] $post_types Supported post-type slugs.
		 */
		return apply_filters( 'cdup_supported_post_types', $post_types );
	}

	/**
	 * Whether a post type is eligible for duplication.
	 *
	 * @param string $post_type Post-type slug.
	 * @return bool
	 */
	public static function is_supported_post_type( $post_type ) {
		return in_array( $post_type, self::supported_post_types(), true );
	}

	/**
	 * Whether the current user may duplicate the given post: the post type must
	 * be supported, the user must be able to edit it (the type's edit capability,
	 * `edit_posts` by default), AND hold one of the allowed roles.
	 *
	 * @param int $source_post_id Post to test.
	 * @return bool
	 */
	public static function current_user_can_duplicate( $source_post_id ) {
		$source_post = get_post( $source_post_id );
		if ( ! $source_post instanceof \WP_Post || ! self::is_supported_post_type( $source_post->post_type ) ) {
			return false;
		}

		$post_type_object = get_post_type_object( $source_post->post_type );
		$create_capability = ( $post_type_object && isset( $post_type_object->cap->edit_posts ) )
			? $post_type_object->cap->edit_posts
			: 'edit_posts';

		$user_can_duplicate = current_user_can( $create_capability )
			&& current_user_can( 'edit_post', $source_post->ID )
			&& self::current_user_has_allowed_role();

		/**
		 * Filter the per-post duplicate permission check.
		 *
		 * @param bool $user_can_duplicate Result of the capability check.
		 * @param int  $source_post_id     Post being tested.
		 */
		return (bool) apply_filters( 'cdup_current_user_can_duplicate', $user_can_duplicate, $source_post->ID );
	}

	/**
	 * Whether the current user holds one of the allowed roles. Administrators
	 * always pass; an empty allow-list imposes no role restriction.
	 *
	 * @return bool
	 */
	protected static function current_user_has_allowed_role() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$allowed_roles = Settings::get( 'allowed_roles' );
		if ( ! is_array( $allowed_roles ) || empty( $allowed_roles ) ) {
			return true;
		}

		$user = wp_get_current_user();
		return (bool) array_intersect( (array) $user->roles, $allowed_roles );
	}
}
