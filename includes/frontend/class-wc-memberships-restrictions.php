<?php
/**
 * WooCommerce Memberships
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Memberships to newer
 * versions in the future. If you wish to customize WooCommerce Memberships for your
 * needs please refer to http://docs.woothemes.com/document/woocommerce-memberships/ for more information.
 *
 * @package   WC-Memberships/Frontend/Checkout
 * @author    SkyVerge
 * @category  Frontend
 * @copyright Copyright (c) 2014-2016, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Restriction class, handles content restriction on frontend
 *
 * @since 1.1.0
 */
class WC_Memberships_Restrictions {


	/** @var array associative array of content conditions for current user **/
	private $user_content_access_conditions;

	/** @var array of post IDs that content restriction has been applied to **/
	private $content_restriction_applied = array();

	/** @var string Product content restriction password helper **/
	private $product_restriction_password = null;

	/** @var bool Product thumbnail removed helper **/
	private $product_thumbnail_restricted = false;


	/**
	 * Constructor
	 *
	 * @since 1.1.0
	 */
	public function __construct() {

		// Exclude restricted content (hide restriction mode)
		add_filter( 'pre_get_posts',  array( $this, 'exclude_restricted_posts' ) );
		add_filter( 'posts_clauses',  array( $this, 'posts_clauses' ), 10, 2 );
		add_filter( 'get_terms_args', array( $this, 'get_terms_args' ), 10, 2 );
		add_filter( 'terms_clauses',  array( $this, 'terms_clauses' ), 10, 3 );

		// Exclude restricted pages
		add_filter( 'get_pages', array( $this, 'exclude_restricted_pages' ), 10, 2 );

		// Redirect content & products (redirect restriction mode)
		add_filter( 'wp', array( $this, 'redirect_restricted_content' ) );
		add_filter( 'wp', array( $this, 'hide_restricted_content_comments' ) );

		// Restrict (filter) content (hide_content restriction mode)
		add_filter( 'the_content',   array( $this, 'restrict_content' ) );
		add_filter( 'the_excerpt',   array( $this, 'restrict_content' ) );
		add_filter( 'comments_open', array( $this, 'maybe_close_comments' ) );

		// Remove restricted comments from comment feeds
		add_filter( 'the_posts', array( $this, 'exclude_restricted_comments' ), 10, 2 );

		// Hide prices & thumbnails for view-restricted products
		add_filter( 'woocommerce_get_price_html', array( $this, 'hide_restricted_product_price' ), 10, 2 );
		add_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'maybe_remove_product_thumbnail' ), 5 );
		add_action( 'woocommerce_after_shop_loop_item_title',  array( $this, 'restore_product_thumbnail' ), 5 );

		// Restrict product viewing by hijacking WooCommerce product password protection (hide_content restriction mode)
		add_action( 'woocommerce_before_single_product', array( $this, 'maybe_password_protect_product' ) );

		// Restrict product visibility
		add_filter( 'woocommerce_product_is_visible',   array( $this, 'product_is_visible' ), 10, 2 );
		add_filter( 'woocommerce_variation_is_visible', array( $this, 'variation_is_visible' ), 10, 2 );
		if ( SV_WC_Plugin_Compatibility::is_wc_version_gte_2_4() ) {
			add_filter( 'woocommerce_hide_invisible_variations', array( $this, 'hide_invisible_variations' ), 10, 3 );
		}

		// Restrict product purchasing
		add_filter( 'woocommerce_is_purchasable',           array( $this, 'product_is_purchasable' ), 10, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', array( $this, 'product_is_purchasable' ), 10, 2 );

		// Show product purchasing restriction message
		add_action( 'woocommerce_single_product_summary', array( $this, 'single_product_purchasing_restricted_message' ), 30 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'single_product_member_discount_message' ), 31 );

		// show product category restriction message
		add_action( 'woocommerce_before_template_part', array( $this, 'maybe_render_product_category_restricted_message' ) );

		// Fix WC_Bookings
		add_action( 'woocommerce_booking_add_to_cart', array( $this, 'fix_wc_bookings' ), 1 );
	}


	/**
	 * Hide restricted posts/products based on content/product restriction rules
	 *
	 * This method works by modifying the $query object directly.
	 * Since WP_Query does not support excluding whole post types or
	 * taxonomies, we need to use custom SQL clauses for them.
	 * Also, tax_query is not respected on is_singular(), so we need
	 * to use custom SQL for specific term restrictions as well.
	 *
	 * @since 1.0.0
	 * @param \WP_Query $wp_query Instance of WP_Query
	 */
	public function exclude_restricted_posts( WP_Query $wp_query ) {

		// Restriction mode is "hide completely"
		if ( 'hide' == get_option( 'wc_memberships_restriction_mode' ) ) {

			$restricted_posts = $this->get_user_restricted_posts();

			// Exclude restricted posts and products from queries
			if ( ! empty( $restricted_posts ) ) {

				$exclude = array_merge( $wp_query->get('post__not_in'), $restricted_posts );
				$wp_query->set('post__not_in', $exclude );
			}

		// Products should be hidden in the catalog && search
		} elseif ( 'yes' == get_option( 'wc_memberships_hide_restricted_products' ) && 'product_query' == $wp_query->get( 'wc_query' ) ) {

			$conditions = $this->get_user_content_access_conditions();

			if ( isset( $conditions['restricted']['posts']['product'] ) ) {

				$exclude = array_merge( $wp_query->get('post__not_in'), $conditions['restricted']['posts']['product'] );
				$wp_query->set('post__not_in', $exclude );
			}
		}
	}


	/**
	 * Exclude restricted pages from get_pages calls
	 *
	 * @since 1.0.0
	 * @param array $pages
	 * @return array
	 */
	public function exclude_restricted_pages( $pages ) {

		// Sanity check: if restriction mode is not "hide", return all pages
		if ( 'hide' != get_option( 'wc_memberships_restriction_mode' ) ) {
			return $pages;
		}

		foreach ( $pages as $key => $page ) {

			if ( ! current_user_can( 'wc_memberships_view_restricted_post_content', $page->ID ) && ! current_user_can( 'wc_memberships_view_delayed_post_content', $page->ID ) ) {
				unset( $pages[ $key ] );
			}
		}

		return array_values( $pages );
	}


	/**
	 * Exclude restricted post types, taxonomies & terms by altering posts query clauses
	 *
	 * @since 1.0.0
	 * @param array $pieces SQL clause pieces
	 * @param WP_Query $wp_query Instance of WP_Query
	 * @return array Modified pieces
	 */
	public function posts_clauses( $pieces, WP_Query $wp_query ) {

		// Sanity check: if restriction mode is not "hide", return all posts
		if ( 'hide' != get_option( 'wc_memberships_restriction_mode' ) && ! ( 'yes' == get_option( 'wc_memberships_hide_restricted_products' ) && 'product_query' == $wp_query->get('wc_query') ) ) {
			return $pieces;
		}

		global $wpdb;

		$conditions = $this->get_user_content_access_conditions();

		// Exclude restricted post types
		if ( ! empty( $conditions['restricted']['post_types'] ) ) {

			$post_type_taxonomies = $this->get_taxonomies_for_post_types( $conditions['restricted']['post_types'] );

			$granted_posts      = $this->get_user_granted_posts( $conditions['restricted']['post_types'] );
			$granted_terms      = $this->get_user_granted_terms( $post_type_taxonomies );
			$granted_taxonomies = array_intersect( $conditions['granted']['taxonomies'], $post_type_taxonomies );

			// Phew! that was easy - no special cases here. Simply restrict access to all the restricted post types
			if ( empty( $granted_posts ) && empty( $granted_terms ) && empty( $granted_taxonomies ) ) {

				$placeholder = implode( ', ', array_fill( 0, count( $conditions['restricted']['post_types'] ), '%s' ) );
				$pieces['where'] .= $wpdb->prepare( " AND $wpdb->posts.post_type NOT IN ($placeholder) ", $conditions['restricted']['post_types'] );
			}

			// It looks like while general access to these post types is restricted, there are some
			// rules that grant the user access to some taxonomies, terms or posts in one or more
			// restricted post types
			else {

				// Prepare main subquery, which gets all post IDs with the restricted post types.
				// The main idea behind the following queries is as follows:
				// 1. Instead of excluding post types, use a subquery to get IDs
				//    of all posts of the restricted post types and exclude them from the results
				// 2. If user has access to specific posts, taxonomies or terms that would be restricted
				//    by the post type, use subqueries to exclude posts that user should have access to
				//   from the exclusin list
				$placeholder = implode( ', ', array_fill( 0, count( $conditions['restricted']['post_types'] ), '%s' ) );

				$subquery = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_type IN ($placeholder)", $conditions['restricted']['post_types'] );

				// Allow access to whole taxonomies
				$subquery .= $this->get_taxonomy_access_where_clause( $granted_taxonomies );

				// Allow access to specific terms
				$subquery .= $this->get_term_access_where_clause( $granted_terms );

				// Allow access to specific posts
				$subquery .= $this->get_post_access_where_clause( $granted_posts );

				// Put it all together now. We are checking that post ID is not one of the
				// restricted post IDs.
				$pieces['where'] .= " AND $wpdb->posts.ID NOT IN ( " . $subquery . " ) ";

			}
		}

		// Exclude taxonomies
		if ( ! empty( $conditions['restricted']['taxonomies'] ) ) {

			$taxonomy_post_types = array();
			foreach ( $conditions['restricted']['taxonomies'] as $taxonomy ) {
				$taxonomy_post_types[ $taxonomy ] = $this->get_post_types_for_taxonomies( (array) $taxonomy );
			}

			$all_taxonomy_post_types = $this->get_post_types_for_taxonomies( $conditions['restricted']['taxonomies'] );

			$granted_posts = $this->get_user_granted_posts( $all_taxonomy_post_types );
			$granted_terms = $this->get_user_granted_terms( $conditions['restricted']['taxonomies'] );

			// Main taxonomy query is always the same, regardless if user has access to specific terms or posts under
			// these taxonomies.
			$placeholder = implode( ', ', array_fill( 0, count( $conditions['restricted']['taxonomies'] ), '%s' ) );

			// Use case statement to check if the post type for the object is registered for the restricted taxonomy
			// If it is not, then don't restrict (this fixes issues when a taxonomy was once registered for a post
			// type but is not anymore, but restriction rules still apply to that post type via term relationships
			// in DB )
			$case = '';
			foreach ( $taxonomy_post_types as $tax => $post_types ) {
				$args = array_merge( array( $tax ), $post_types );
				$post_types_placeholder = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
				$case .= $wpdb->prepare( " WHEN $wpdb->term_taxonomy.taxonomy = %s THEN $wpdb->posts.post_type IN ( $post_types_placeholder )", $args );
			}

			$subquery = $wpdb->prepare("
				SELECT object_id FROM $wpdb->term_relationships
				LEFT JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
				LEFT JOIN $wpdb->term_taxonomy ON $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
				WHERE CASE $case END
				AND $wpdb->term_taxonomy.taxonomy IN ($placeholder)
			", $conditions['restricted']['taxonomies'] );

			// It looks like while general access to these taxonomies is restricted, there are some
			// rules that grant the user access to some terms or posts in one or more restricted taxonomies
			if ( ! empty( $granted_terms ) || ! empty( $granted_posts ) ) {

				// Allow access to specific terms
				$subquery .= $this->get_term_access_where_clause( $granted_terms );

				// Allow access to specific posts
				$subquery .= $this->get_post_access_where_clause( $granted_posts );
			}

			// Put it all together now
			$pieces['where'] .= " AND $wpdb->posts.ID NOT IN ( " . $subquery . " ) ";

		}

		// Exclude taxonomy terms
		if ( ! empty( $conditions['restricted']['terms'] ) ) {

			$term_ids   = array();
			$taxonomies = array_keys( $conditions['restricted']['terms'] );

			foreach ( $conditions['restricted']['terms'] as $taxonomy => $terms ) {
				$term_ids = array_merge( $term_ids, $terms );
			}

			if ( ! empty( $term_ids ) ) {

				$taxonomy_post_types = array();
				foreach ( $taxonomies as $taxonomy ) {
					$taxonomy_post_types[ $taxonomy ] = $this->get_post_types_for_taxonomies( (array) $taxonomy );
				}

				$all_taxonomy_post_types = $this->get_post_types_for_taxonomies( $taxonomies );
				$granted_posts           = $this->get_user_granted_posts( $all_taxonomy_post_types );

				// Main term query is always the same, regardless if user has access to specific posts under with these terms
				$placeholder = implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) );

				// Use case statement to check if the post type for the object is registered for the restricted taxonomy
				// If it is not, then don't restrict (this fixes issues when a taxonomy was once registered for a post
				// type but is not anymore, but restriction rules still apply to that post type via term relationships
				// in DB )
				$case = '';
				foreach ( $taxonomy_post_types as $tax => $post_types ) {
					$args = array_merge( array( $tax ), $post_types );
					$post_types_placeholder = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
					$case .= $wpdb->prepare( " WHEN $wpdb->term_taxonomy.taxonomy = %s THEN $wpdb->posts.post_type IN ( $post_types_placeholder )", $args );
				}

				$subquery = $wpdb->prepare("
					SELECT object_id FROM $wpdb->term_relationships
					LEFT JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->term_relationships.object_id
					LEFT JOIN $wpdb->term_taxonomy ON $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
					WHERE CASE $case END
					AND $wpdb->term_relationships.term_taxonomy_id IN ($placeholder)
				", $term_ids );

				// It looks like while general access to these terms is restricted, there are some
				// rules that grant the user access to some posts in one or more restricted terms
				if ( ! empty( $granted_posts ) ) {
					$subquery .= $this->get_post_access_where_clause( $granted_posts );
				}

				// Put it all together now
				$pieces['where'] .= " AND $wpdb->posts.ID NOT IN ( " . $subquery . " ) ";
			}

		}

		return $pieces;
	}


	/**
	 * Adjust get_terms arguments, exclude restricted terms
	 *
	 * @since 1.0.0
	 * @param array $args
	 * @param string|array $taxonomies
	 * @return array
	 */
	public function get_terms_args( $args, $taxonomies ) {

		// Sanity check: if restriction mode is not "hide", return all posts
		if ( 'hide' != get_option( 'wc_memberships_restriction_mode' ) ) {
			return $args;
		}

		$conditions = $this->get_user_content_access_conditions();

		if ( ! empty( $conditions['restricted']['terms'] ) && array_intersect( array_keys( $conditions['restricted']['terms'] ), $taxonomies ) ) {

			$args['exclude'] = $args['exclude'] ? wp_parse_id_list( $args['exclude'] ) : array();

			foreach ( $conditions['restricted']['terms'] as $tax => $terms ) {

				$args['exclude'] = array_unique( array_merge( $terms, $args['exclude'] ) );
			}
		}

		return $args;
	}


	/**
	 * Exclude restricted taxonomies by filtering terms_clauses
	 *
	 * @since 1.0.0
	 * @param array        $pieces     Terms query SQL clauses.
	 * @param string|array $taxonomies A taxonomy or array of taxonomies.
	 * @param array        $args       An array of terms query arguments.
	 * @return array
	 */
	public function terms_clauses( $pieces, $taxonomies, $args ) {

		// Sanity check: if restriction mode is not "hide", return all posts
		if ( 'hide' != get_option( 'wc_memberships_restriction_mode' ) ) {
			return $pieces;
		}

		global $wpdb;
		$conditions = $this->get_user_content_access_conditions();

		if ( ! empty( $conditions['restricted']['taxonomies'] ) ) {

			$granted_terms = $this->get_user_granted_terms( $conditions['restricted']['taxonomies'] );

			// Main taxonomy query is always the same, regardless if user has access to specific terms under
			// these taxonomies.
			$placeholder = implode( ', ', array_fill( 0, count( $conditions['restricted']['taxonomies'] ), '%s' ) );

			$subquery = $wpdb->prepare("
				SELECT sub_t.term_id FROM $wpdb->terms AS sub_t
				INNER JOIN $wpdb->term_taxonomy AS sub_tt ON sub_t.term_id = sub_tt.term_id
				WHERE sub_tt.taxonomy IN ($placeholder)
			", $conditions['restricted']['taxonomies'] );

			// It looks like while general access to these taxonomies is restricted, there are some
			// rules that grant the user access to some terms or posts in one or more restricted taxonomies
			if ( ! empty( $granted_terms ) ) {

				// Allow access to specific terms
				$subquery .= $this->get_term_access_where_clause( $granted_terms, 'taxonomies' );
			}

			// Put it all together now
			$pieces['where'] .= " AND t.term_id NOT IN ( " . $subquery . " ) ";
		}

		return $pieces;
	}


	/**
	 * Exclude view-restricted variations
	 *
	 * @since 1.0.0
	 * @param bool $is_visible
	 * @param int $variation_id
	 * @return bool
	 */
	public function variation_is_visible( $is_visible, $variation_id ) {

		// Exclude restricted variations
		if ( ! current_user_can( 'wc_memberships_view_restricted_product', $variation_id ) && ! current_user_can( 'wc_memberships_view_delayed_product', $variation_id ) ) {
			$is_visible = false;
		}

		return $is_visible;
	}


	/**
	 * Exclude view-restricted variations
	 *
	 * @since 1.4.0
	 * @param bool $is_visible
	 * @param int $product_id
	 * @param WC_Product_Variation $variation
	 * @return bool
	 */
	public function hide_invisible_variations( $is_visible, $product_id, $variation ) {

		// Exclude restricted variations
		if ( ! current_user_can( 'wc_memberships_view_restricted_product', $variation->id ) && ! current_user_can( 'wc_memberships_view_delayed_product', $variation->id ) ) {
			$is_visible = false;
		}

		return $is_visible;
	}


	/**
	 * Redirect restricted content/products based on content/product restriction rules
	 *
	 * @since 1.0.0
	 * @param string $content The content
	 * @return string
	 */
	public function redirect_restricted_content( $content ) {

		if ( 'redirect' !== get_option( 'wc_memberships_restriction_mode' ) ) {
			return;
		}

		if ( is_singular() ) {
			global $post;

			$restricted = ( in_array( $post->post_type, array( 'product', 'product_variation' ) ) )
									? wc_memberships_is_product_viewing_restricted() && ! current_user_can( 'wc_memberships_view_restricted_product', $post->ID )
									: wc_memberships_is_post_content_restricted() && ! current_user_can( 'wc_memberships_view_restricted_post_content', $post->ID );

			if ( $restricted ) {

				$redirect_page_id = get_option( 'wc_memberships_redirect_page_id' );

				$redirect_url = $redirect_page_id ? get_permalink( $redirect_page_id ) : home_url();
				$redirect_url = add_query_arg( 'r', $post->ID, $redirect_url );

				wp_redirect( $redirect_url );
				exit;
			}

		}
	}


	/**
	 * Hide restricted content/product comments
	 *
	 * @since 1.0.0
	 * @param string $content The content
	 * @return string
	 */
	public function hide_restricted_content_comments( $content ) {

		if ( 'hide_content' !== get_option( 'wc_memberships_restriction_mode' ) ) {
			return;
		}

		if ( is_singular() ) {
			global $post, $wp_query;

			$restricted = ( in_array( $post->post_type, array( 'product', 'product_variation' ) ) )
									? wc_memberships_is_product_viewing_restricted() && ! current_user_can( 'wc_memberships_view_restricted_product',      $post->ID )
									: wc_memberships_is_post_content_restricted()    && ! current_user_can( 'wc_memberships_view_restricted_post_content', $post->ID );

			if ( $restricted ) {

				$wp_query->comment_count   = 0;
				$wp_query->current_comment = 999999;
			}

		}
	}


	/**
	 * Restrict (post) content based on content restriction rules
	 *
	 * @since 1.0.0
	 * @param string $content The content
	 * @return string
	 */
	public function restrict_content( $content ) {

		// Check if content is restricted - and this function is not being recursively called
		// from `get_the_excerpt`, which internally applies `the_content` to the excerpt, which
		// then calls this function, ... until the stack is full and I want to go home and not
		// deal with this anymore...
		if ( wc_memberships_is_post_content_restricted() && ! doing_filter( 'get_the_excerpt' ) ) {
			global $post;

			// Check if user has access to restricted content
			if ( ! current_user_can( 'wc_memberships_view_restricted_post_content', $post->ID ) ) {

				// User does not have access, filter the content
				$content = '';

				if ( ! in_array( $post->ID, $this->content_restriction_applied ) ) {

					if ( 'yes' == get_option( 'wc_memberships_show_excerpts' ) ) {
						$content = get_the_excerpt();
					}

					$content .= '<div class="woocommerce"><div class="woocommerce-info wc-memberships-restriction-message wc-memberships-message wc-memberships-content-restricted-message">' . wc_memberships()->frontend->get_content_restricted_message( $post->ID ) . '</div></div>';
				}

			// Check if user has access to delayed content
			} elseif ( ! current_user_can( 'wc_memberships_view_delayed_post_content', $post->ID ) ) {

				// User does not have access, filter the content
				$content = '';

				if ( ! in_array( $post->ID, $this->content_restriction_applied ) ) {

					if ( 'yes' == get_option( 'wc_memberships_show_excerpts' ) ) {
						$content = get_the_excerpt();
					}

					$content .= '<div class="woocommerce"><div class="woocommerce-info wc-memberships-restriction-message wc-memberships-content-delayed-message">' . wc_memberships()->frontend->get_content_delayed_message( get_current_user_id(), $post->ID ) . '</div></div>';
				}

			}

			// Indicates that the content for this post has already been filtered
			$this->content_restriction_applied[] = $post->ID;
		}

		return $content;
	}


	/**
	 * Close comments when post content is restricted
	 *
	 * @since 1.0.0
	 * @param bool $comments_open
	 * @return bool
	 */
	public function maybe_close_comments( $comments_open ) {
		global $post;

		if ( $comments_open && is_object( $post ) ) {

			// Are we looking at a product? Products get special treatment ಠ‿ಠ
			$is_product = in_array( $post->post_type, array( 'product', 'product_variation' ) );

			// Determine if user can view the post at all
			$comments_open = $is_product
				? current_user_can( 'wc_memberships_view_restricted_product', $post->ID )
				: current_user_can( 'wc_memberships_view_restricted_post_content', $post->ID );
		}

		return $comments_open;
	}


	/**
	 * Exclude restricted comments from comment feed
	 *
	 * @since 1.0.0
	 * @param array $posts
	 * @param WP_Query $query
	 * @return array
	 */
	public function exclude_restricted_comments( $posts, WP_Query $query ) {

		if ( is_comment_feed() && $query->comment_count ) {

			foreach ( $query->comments as $key => $comment ) {

				$post_id = $comment->comment_post_ID;

				$is_product = in_array( get_post_type( $post_id ), array( 'product', 'product_variation' ) );

				// Determine if user can view the comment post
				$can_view = $is_product
					? current_user_can( 'wc_memberships_view_restricted_product', $post_id )
					: current_user_can( 'wc_memberships_view_restricted_post_content', $post_id );

				// If not, exclude this comment from the feed
				if ( ! $can_view ) {
					unset( $query->comments[ $key ] );
				}
			}

			// Re-index and re-count comments
			$query->comments = array_values( $query->comments );
			$query->comment_count = count( $query->comments );
		}

		return $posts;
	}


	/**
	 * Hide price if a product is view-restricted in "hide content" mode
	 *
	 * @since 1.0.0
	 * @param string $price
	 * @param WC_Product $product
	 * @return string
	 */
	public function hide_restricted_product_price ( $price, WC_Product $product ) {

		if ( 'hide_content' == get_option( 'wc_memberships_restriction_mode' ) && ! current_user_can( 'wc_memberships_view_restricted_product', $product->id ) ) {
			$price = '';
		}

		return $price;
	}


	/**
	 * Remove product thumbnail in "hide content" mode
	 *
	 * @since 1.0.0
	 */
	public function maybe_remove_product_thumbnail () {
		global $post;

		$this->product_thumbnail_restricted = false;

		// Skip if the product thumbnail is not shown anyway
		if ( ! has_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail' ) ) {
			return;
		}

		// If in hide content mode and curretn user is not allowed to see the
		// product thumbnail, remove it
		if ( 'hide_content' == get_option( 'wc_memberships_restriction_mode' ) && ( ! current_user_can( 'wc_memberships_view_restricted_product', $post->ID ) || ! current_user_can( 'wc_memberships_view_delayed_product', $post->ID ) ) ) {

			// Indicate that we removed the product thumbnail
			$this->product_thumbnail_restricted = true;

			// Remove the product thumbnail and replace it with the placeholder image
			remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail' );
			add_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'template_loop_product_thumbnail_placeholder' ), 10 );
		}

	}


	/**
	 * Re-enable product thumbnail for the next product in the loop
	 *
	 * @since 1.1.0
	 */
	public function restore_product_thumbnail() {

		if ( $this->product_thumbnail_restricted && ! has_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail' ) ) {

			add_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
			remove_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'template_loop_product_thumbnail_placeholder' ) );
		}
	}


	/**
	 * Output the product image placeholder in shop loop
	 *
	 * @since 1.0.0
	 */
	public function template_loop_product_thumbnail_placeholder() {

		if ( wc_placeholder_img_src() ) {
			echo wc_placeholder_img( 'shop_catalog' );
		}
	}


	/**
	 * Maybe password-protect a product page
	 *
	 * WP/WC gives us very few tools to restrict product viewing, so we
	 * hijack the password protection to achieve what we want.
	 *
	 * @since 1.0.0
	 */
	public function maybe_password_protect_product() {

		global $post;

		// if the product is to be restricted, and doesn't already have a password,
		// set a password so as to perform the actions we want
		if ( wc_memberships_is_product_viewing_restricted() && ! post_password_required() ) {

			if ( ! current_user_can( 'wc_memberships_view_restricted_product', $post->ID ) ) {

				$post->post_password = $this->product_restriction_password = uniqid( 'wc_memberships_restricted_' );
				add_filter( 'the_password_form', array( $this, 'restrict_product_content' ) );

			} elseif ( ! current_user_can( 'wc_memberships_view_delayed_product', $post->ID ) ) {

				$post->post_password = $this->product_restriction_password = uniqid( 'wc_memberships_delayed_' );
				add_filter( 'the_password_form', array( $this, 'restrict_product_content' ) );

			}

		}
	}


	/**
	 * Restrict product content
	 *
	 * @since 1.0.0
	 * @param string $output
	 * @return string $output
	 */
	public function restrict_product_content( $output ) {

		global $post;

		if ( $this->product_restriction_password && $this->product_restriction_password === $post->post_password ) {


			// User does not have access, filter the content
			$output = '';

			if ( 'yes' == get_option( 'wc_memberships_show_excerpts' ) ) {
				ob_start();
				echo '<div class="summary entry-summary">';
				wc_get_template( 'single-product/title.php' );
				wc_get_template( 'single-product/short-description.php' );
				echo '</div>';
				$output = ob_get_clean();
			}

			$message = ( strpos( $post->post_password, 'wc_memberships_restricted_' ) !== false )
							 ? wc_memberships()->frontend->get_product_viewing_restricted_message( $post->ID )
							 : wc_memberships()->frontend->get_content_delayed_message( get_current_user_id(), $post->ID, 'view' );

			$output .= '<div class="woocommerce"><div class="woocommerce-info wc-memberships-restriction-message wc-memberships-restricted-content-message">' . wp_kses_post( $message ) . '</div></div>';

			$post->post_password = null;
		}

		return $output;
	}


	/**
	 * Restrict product purchasing based on restriction rules
	 *
	 * @since 1.0.0
	 * @param boolean $purchasable whether the product is purchasable
	 * @param WC_Product $product the product
	 * @return boolean true if $product is purchasable, false otherwise
	 */
	public function product_is_purchasable( $purchasable, $product ) {

		$product_id = $product->is_type('variation') ? $product->variation_id : $product->id;

		// Product is not purchasable if the current user can't view or purchase the product,
		// or they do not have access yet (due to dripping).
		if ( ! current_user_can( 'wc_memberships_view_restricted_product', $product_id ) || ! current_user_can( 'wc_memberships_purchase_restricted_product', $product_id ) || ! current_user_can( 'wc_memberships_purchase_delayed_product', $product_id ) ) {
			$purchasable = false;
		}

		// Double-check for variations: if parent is not purchasable, then
		// variation is neither
		if ( $purchasable && $product->is_type( 'variation' ) ) {
			$purchasable = $product->parent->is_purchasable();
		}

		return $purchasable;
	}


	/**
	 * Restrict product visibility in catalog based on restriction rules
	 *
	 * @since 1.0.0
	 * @param boolean $visible whether the product is visible
	 * @param int $product_id the product id
	 * @return boolean true if product is visible, false otherwise
	 */
	public function product_is_visible( $visible, $product_id ) {

		if ( 'yes' == get_option( 'wc_memberships_hide_restricted_products' ) ) {

			if ( ! current_user_can( 'wc_memberships_view_restricted_product', $product_id ) ) {
				$visible = false;
			}
		}

		return $visible;
	}


	/**
	 * Display product purchasing restricted message
	 *
	 * @since 1.0.0
	 */
	public function single_product_purchasing_restricted_message() {

		global $product;

		// Purchasing is restricted
		if ( ! current_user_can( 'wc_memberships_purchase_restricted_product', $product->id ) ) {
			echo '<div class="woocommerce"><div class="woocommerce-info wc-memberships-restriction-message wc-memberships-product-purchasing-restricted-message">' . wp_kses_post( wc_memberships()->frontend->get_product_purchasing_restricted_message( $product->id ) ) . '</div></div>';
		}

		// Purchasing is delayed
		else if ( ! current_user_can( 'wc_memberships_purchase_delayed_product', $product->id ) ) {
			echo '<div class="woocommerce"><div class="woocommerce-info wc-memberships-restriction-message wc-memberships-product-purchasing-delayed-message">' . wp_kses_post( wc_memberships()->frontend->get_content_delayed_message( get_current_user_id(), $product->id, 'purchase' ) ) . '</div></div>';
		}

		// Variation-specific messages
		else if ( $product->is_type( 'variable' ) && $product->has_child() ) {

			$variations_restricted = false;

			foreach ( $product->get_available_variations() as $variation ) {

				if ( ! $variation['is_purchasable'] ) {

					$variation_id = $variation['variation_id'];

					if ( ! current_user_can( 'wc_memberships_purchase_restricted_product', $variation_id ) ) {
						$variations_restricted = true;
						echo '<div class="woocommerce"><div class="woocommerce-info wc-memberships-restriction-message wc-memberships-product-purchasing-restricted-message wc-memberships-variation-message js-variation-' . sanitize_html_class( $variation_id ) . '">' . wp_kses_post( wc_memberships()->frontend->get_product_purchasing_restricted_message( $variation_id ) ) . '</div></div>';
					}

					else if ( ! current_user_can( 'wc_memberships_purchase_delayed_product', $variation['variation_id'] ) ) {
						$variations_restricted = true;
						echo '<div class="woocommerce"><div class="woocommerce-info wc-memberships-restriction-message wc-memberships-product-purchasing-delayed-message wc-memberships-variation-message js-variation-' . sanitize_html_class( $variation_id ) . '">' . wp_kses_post( wc_memberships()->frontend->get_content_delayed_message( get_current_user_id(), $variation_id, 'purchase' ) ) . '</div></div>';
					}
				}
			}

			if ( $variations_restricted ) {
				wc_enqueue_js("
					jQuery('.variations_form')
						.on( 'woocommerce_variation_select_change', function( event ) {
							jQuery('.wc-memberships-variation-message').hide();
						})
						.on( 'found_variation', function( event, variation ) {
							jQuery('.wc-memberships-variation-message').hide();
							if ( ! variation.is_purchasable ) {
								jQuery( '.wc-memberships-variation-message.js-variation-' + variation.variation_id ).show();
							}
						})
						.find( '.variations select' ).change();
				");
			}
		}
	}


	/**
	 * Display member discount message for a product or variation.
	 *
	 * @since 1.0.0
	 */
	public function single_product_member_discount_message() {

		global $product;

		// If the main/parent product needs the message, just display it normally
		if ( wc_memberships_product_has_member_discount() && ! wc_memberships_user_has_member_discount() ) {

			if ( $message = wc_memberships()->frontend->get_member_discount_message( $product->id ) ) {
				echo '<div class="woocommerce"><div class="woocommerce-info wc-memberships-member-discount-message">' . wp_kses_post( $message ) . '</div></div>';
			}

		// If this is a variable product, set the messages up for display per-variation
		} else if ( $product->is_type( 'variable' ) && $product->has_child() ) {

			$variations_discounted = false;

			foreach ( $product->get_available_variations() as $variation ) {

				$variation_id = $variation['variation_id'];

				if ( wc_memberships_product_has_member_discount( $variation_id ) && ! wc_memberships_user_has_member_discount( $variation_id ) ) {

					$variations_discounted = true;

					if ( $message = wc_memberships()->frontend->get_member_discount_message( $variation_id ) ) {
						echo '<div class="woocommerce"><div class="woocommerce-info wc-memberships-member-discount-message wc-memberships-variation-message js-variation-' . sanitize_html_class( $variation_id ) . '">' . wp_kses_post( $message ) . '</div></div>';
					}
				}
			}

			if ( $variations_discounted ) {
				wc_enqueue_js("
					jQuery( '.variations_form' )
						.on( 'woocommerce_variation_select_change', function( event ) {
							jQuery( '.wc-memberships-variation-message.wc-memberships-member-discount-message' ).hide();
						})
						.on( 'found_variation', function( event, variation ) {
							jQuery( '.wc-memberships-variation-message.wc-memberships-member-discount-message' ).hide();
							jQuery( '.wc-memberships-variation-message.wc-memberships-member-discount-message.js-variation-' + variation.variation_id ).show();
						})
						.find( '.variations select' ).change();
				");
			}
		}
	}


	/**
	 * Maybe render a restriction/delayed message if a user is viewing the
	 * a product category page
	 *
	 * @since 1.4.0
	 * @param string $template_name
	 */
	public function maybe_render_product_category_restricted_message( $template_name ) {

		// target no-products-found template
		if ( 'loop/no-products-found.php' !== $template_name  ){
			return;
		}

		$term = get_queried_object();

		// target 'product_cat' taxonomy
		if ( ! property_exists( $term, 'taxonomy' ) || $term->taxonomy !== 'product_cat' ){
			return;
		}

		// viewing is restricted
		if ( ! current_user_can( 'wc_memberships_view_restricted_product_taxonomy_term', 'product_cat', $term->term_id ) ) {
			echo '<div class="woocommerce"><div class="woocommerce-info wc-memberships-restriction-message wc-memberships-product-category-viewing-restricted-message">' . wp_kses_post( wc_memberships()->frontend->get_product_taxonomy_term_viewing_restricted_message( 'product_cat', $term->term_id ) ) . '</div></div>';
		}

		// viewing is delayed
		elseif ( ! current_user_can( 'wc_memberships_view_delayed_product_taxonomy_term', 'product_cat', $term->term_id ) ) {
			echo '<div class="woocommerce"><div class="woocommerce-info wc-memberships-restriction-message wc-memberships-product-category-viewing-delayed-message">' . wp_kses_post( wc_memberships()->frontend->get_product_taxonomy_term_delayed_message( get_current_user_id(), 'product_cat', $term->term_id ) ) . '</div></div>';
		}

	}


	/**
	 * Get content access conditions for the current user
	 *
	 * Returns an array of restricted and granted content based on
	 * the content and product restriction rules.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	private function get_user_content_access_conditions() {

		if ( ! isset( $this->user_content_access_conditions ) ) {

			// Avoid filter loops
			remove_filter( 'pre_get_posts', array( $this, 'exclude_restricted_posts' ) );

			// Find restricted posts from restriction rules
			$rules = wc_memberships()->rules->get_rules( array(
				'rule_type' => array( 'content_restriction', 'product_restriction' ),
			) );

			$restricted = $granted = array(
				'posts'      => array(),
				'post_types' => array(),
				'terms'      => array(),
				'taxonomies' => array(),
			);

			$conditions = array(
				'restricted' => $restricted,
				'granted'    => $granted,
			);

			// shop managers/admins can access everything
			if ( is_user_logged_in() && current_user_can( 'wc_memberships_access_all_restricted_content' ) ) {

				return $this->user_content_access_conditions = $conditions;
			}

			// Get all the content that is either restricted or granted for the user
			if ( ! empty( $rules ) ) {

				foreach ( $rules as $rule ) {

					// Skip rule if the plan is not published
					if ( 'publish' != get_post_status( $rule->get_membership_plan_id() ) ) {
						continue;
					}

					// Skip non-view product restriction rules
					if ( 'product_restriction' == $rule->get_rule_type() && 'view' != $rule->get_access_type() ) {
						continue;
					}

					// Check if user is an active member of the plan
					$is_member  = is_user_logged_in() && wc_memberships()->user_memberships->is_user_active_member( get_current_user_id(), $rule->get_membership_plan_id() );
					$has_access = false;

					// Check if user has scheduled access to the content
					if ( $is_member ) {
						$user_membership = wc_memberships()->user_memberships->get_user_membership( get_current_user_id(), $rule->get_membership_plan_id() );

						/** This filter is documented in includes/class-wc-memberships-capabilities.php **/
						$from_time = apply_filters( 'wc_memberships_access_from_time', $user_membership->get_start_date( 'timestamp' ), $rule, $user_membership );

						// If there is no time to calculate the access time from, simply
						// use the current time as access start time
						if ( ! $from_time ) {
							$access_time = current_time( 'timestamp', true );
							break; // Can't get any earlier, break the loop
						}

						$rule_access_time = $rule->get_access_start_time( $from_time );

						$has_access = $rule_access_time <= current_time( 'timestamp', true );
					}

					$condition = $has_access ? 'granted' : 'restricted';

					// Find posts that are either restricted or granted access to
					if ( 'post_type' == $rule->get_content_type() && $rule->has_objects() ) {

						$post_type = $rule->get_content_type_name();
						$post_ids  = array();

						// Leave out posts that have restrictions disabled
						foreach ( $rule->get_object_ids() as $post_id ) {
							if ( 'yes' != get_post_meta( $post_id, '_wc_memberships_force_public', true ) ) {
								$post_ids[] = $post_id;
							}
						}

						// If there are no posts left, continue to next rule
						if ( empty( $post_ids ) ) {
							continue;
						}

						if ( ! isset( $conditions[ $condition ][ 'posts' ][ $post_type ] ) ) {
							$conditions[ $condition ][ 'posts' ][ $post_type ] = array();
						}

						$conditions[ $condition ][ 'posts' ][ $post_type ] = array_unique( array_merge( $conditions[ $condition ][ 'posts' ][ $post_type ], $post_ids ) );
					}

					// Find post types that are either restricted or granted access to
					elseif ( 'post_type' == $rule->get_content_type() ) {

						$conditions[ $condition ][ 'post_types' ] = array_unique( array_merge( $conditions[ $condition ][ 'post_types' ], (array) $rule->get_content_type_name() ) );
					}

					// Find taxonomy terms that are either restricted or granted access to
					elseif ( 'taxonomy' == $rule->get_content_type() && $rule->has_objects() ) {

						$taxonomy = $rule->get_content_type_name();

						if ( ! isset( $conditions[ $condition ][ 'terms' ][ $taxonomy ] ) ) {
							$conditions[ $condition ][ 'terms' ][ $taxonomy ] = array();
						}

						$conditions[ $condition ][ 'terms' ][ $taxonomy ] = array_unique( array_merge( $conditions[ $condition ][ 'terms' ][ $taxonomy ], $rule->get_object_ids() ) );
					}

					elseif ( 'taxonomy' == $rule->get_content_type() ) {

						$conditions[ $condition ][ 'taxonomies' ] = array_unique( array_merge( $conditions[ $condition ][ 'taxonomies' ], (array) $rule->get_content_type_name() ) );
					}

				}
			}

			// Loop over granted content and check if the user has access to delayed content
			foreach ( $conditions['granted'] as $content_type => $values ) {

				if ( empty( $values ) ) {
					continue;
				}

				foreach ( $values as $key => $value ) {

					switch ( $content_type ) {

						case 'posts':

							foreach ( $value as $post_key => $post_id ) {

								if ( ! current_user_can( 'wc_memberships_view_delayed_post_content', $post_id ) ) {
									unset( $conditions['granted'][ $content_type ][ $key ][ $post_key ] );
								}
							}

						break;

						case 'post_types':

							if ( ! current_user_can( 'wc_memberships_view_delayed_post_type', $value ) ) {
								unset( $conditions['granted'][ $content_type ][ $key ] );
							}

						break;

						case 'taxonomies':

							if ( ! current_user_can( 'wc_memberships_view_delayed_taxonomy', $value ) ) {
								unset( $conditions['granted'][ $content_type ][ $key ] );
							}

						break;

						case 'terms':

							foreach ( $value as $term_key => $term ) {

								if ( ! current_user_can( 'wc_memberships_view_delayed_taxonomy_term', $key, $term ) ) {
									unset( $conditions['granted'][ $content_type ][ $key ][ $term_key ] );
								}
							}

						break;

					}
				}
			}

			// Remove restricted items that should be granted for the current user
			// content types are high-level restriction items - posts, post_types, terms, and taxonomies
			foreach ( $conditions['restricted'] as $content_type => $object_types ) {

				if ( empty( $conditions['granted'][ $content_type ] ) || empty( $object_types ) ) {
					continue;
				}

				// object types are child elements of a content type, e.g. for the posts content type, object types are post_types( post and product)
				// for a term content type, object types are taxonomy names (e.g. category)
				foreach ( $object_types as $object_type_name => $object_ids ) {

					if ( empty( $conditions['granted'][ $content_type ][ $object_type_name ] ) || empty( $object_ids ) ) {
						continue;
					}

					if ( is_array( $object_ids ) ) {

						// if the restricted object ID is also granted, remove it from restrictions
						foreach ( $object_ids as $object_id_index => $object_id ) {

							if ( in_array( $object_id, $conditions['granted'][ $content_type ][ $object_type_name ] ) ) {
								unset( $conditions['restricted'][ $content_type ][ $object_type_name ][ $object_id_index ] );
							}
						}

					} else {

						// post type handling
						if ( in_array( $object_ids, $conditions['granted'][ $content_type ] ) ) {
							unset( $conditions['restricted'][ $content_type ][ array_search( $object_ids, $conditions['restricted'][ $content_type ] ) ] );
						}
					}
				}
			}

			// Grant access to posts that have restrictions disabled
			global $wpdb;

			$public_posts = $wpdb->get_results( "
				SELECT p.ID, p.post_type FROM $wpdb->posts p
				LEFT JOIN $wpdb->postmeta pm
				ON p.ID = pm.post_id
				WHERE pm.meta_key = '_wc_memberships_force_public'
				AND pm.meta_value = 'yes'
			" );

			if ( ! empty( $public_posts ) ) {

				foreach ( $public_posts as $post ) {

					if ( ! isset( $conditions['granted']['posts'][ $post->post_type ] ) ) {
						$conditions['granted']['posts'][ $post->post_type ] = array();
					}

					$conditions['granted']['posts'][ $post->post_type ][] = $post->ID;
				}
			}

			// gather da results
			$this->user_content_access_conditions = $conditions;

			// Add the filter back
			add_filter( 'pre_get_posts', array( $this, 'exclude_restricted_posts' ) );
		}

		return $this->user_content_access_conditions;
	}


	/**
	 * Get a list of object IDs for the specified access condition
	 *
	 * General method to get a list of object IDs (posts or terms)
	 * that are either restricted or granted for the current user.
	 * The list can be limited to specific post types or taxonomies.
	 *
	 * @since 1.1.0
	 * @param $condition string Condition. One of 'restricted' or 'granted'
	 * @param $content_type string Content Type. One of 'posts' or 'terms'
	 * @param $content_type_name string|array Optional.
	 *                           Post type / taxonomy or array of post types or taxonomies to get object IDs for,
	 *                           if empty, will return all object IDs
	 * @return array|null Array of object IDs or null if none found
	 */
	private function get_user_content_for_access_condition( $condition, $content_type, $content_type_name = null ) {

		$conditions = $this->get_user_content_access_conditions();

		if ( is_string( $content_type_name ) ) {

			return isset( $conditions[ $condition ][ $content_type ][ $content_type_name ] )
					 ? $conditions[ $condition ][ $content_type ][ $content_type_name ]
					 : null;
		}

		$objects = array();

		if ( ! empty( $conditions[ $condition ][ $content_type ] ) ) {

			foreach ( $conditions[ $condition ][ $content_type ] as $restricted_content_type_name => $restricted_objects ) {

				if ( ! $content_type_name || in_array( $restricted_content_type_name, $content_type_name ) ) {
					$objects = array_merge( $restricted_objects, $objects );
				}
			}
		}

		return ! empty( $objects ) ? $objects : null;
	}


	/**
	 * Get a list of restricted post IDs for the current user
	 *
	 * @since 1.1.0
	 * @param $post_type string Optional. Post type to get restricted post IDs for,
	 *                          if empty, will return all post IDs
	 * @return array|null Array of post IDs or null if none found
	 */
	private function get_user_restricted_posts( $post_type = null ) {
		return $this->get_user_content_for_access_condition( 'restricted', 'posts', $post_type );
	}


	/**
	 * Get a list of granted post IDs for the current user
	 *
	 * @since 1.1.0
	 * @param $post_type string Optional. Post type to get granted post IDs for,
	 *                                    if empty, will return all post IDs
	 * @return array|null Array of post IDs or null if none found
	 */
	private function get_user_granted_posts( $post_type = null ) {
		return $this->get_user_content_for_access_condition( 'granted', 'posts', $post_type );
	}


	/**
	 * Get a list of restricted term IDs for the current user
	 *
	 * @since 1.1.0
	 * @param $taxonomy string|array Optional. Taxonomy or array of taxonomies to get term IDs for,
	 *                               if empty, will return all term IDs
	 * @return array|null Array of term IDs or null if none found
	 */
	private function get_user_restricted_terms( $taxonomy = null ) {
		return $this->get_user_content_for_access_condition( 'restricted', 'terms', $taxonomy );
	}


	/**
	 * Get a list of granted term IDs for the current user
	 *
	 * @since 1.1.0
	 * @param $taxonomy string|array Optional. Taxonomy or array of taxonomies to get term IDs for,
	 *                               if empty, will return all term IDs
	 * @return array|null Array of term IDs or null if none found
	 */
	private function get_user_granted_terms( $taxonomy = null ) {
		return $this->get_user_content_for_access_condition( 'granted', 'terms', $taxonomy );
	}


	/**
	 * Construct exclude taxonomies WHERE SQL clause
	 *
	 * @since 1.0.0
	 * @param array $taxonomies Array of taxonomies
	 * @return string
	 */
	private function get_taxonomy_access_where_clause( $taxonomies ) {

		global $wpdb;

		if ( empty( $taxonomies ) ) {
			return '';
		}

		$placeholder = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );

		$subquery = $wpdb->prepare("
			SELECT object_id FROM $wpdb->term_relationships
			LEFT JOIN $wpdb->term_taxonomy ON $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
			WHERE $wpdb->term_taxonomy.taxonomy IN ($placeholder)
		", $taxonomies );

		return " AND $wpdb->posts.ID NOT IN ( " . $subquery . " ) ";
	}


	/**
	 * Construct exclude term IDs WHERE SQL clause
	 *
	 * @since 1.0.0
	 * @param array $term_ids Array of term IDs
	 * @param string $query_type Optional. Query type.
	 *                           One of 'posts' or 'taxonomies'. Defaults to 'posts'.
	 * @return string
	 */
	private function get_term_access_where_clause( $term_ids, $query_type = 'posts' ) {

		global $wpdb;

		if ( empty( $term_ids ) ) {
			return '';
		}

		$placeholder = implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) );

		if ( 'posts' == $query_type ) {

			$subquery = $wpdb->prepare("
				SELECT object_id FROM $wpdb->term_relationships
				WHERE term_taxonomy_id IN ($placeholder)
			", $term_ids );

			return " AND $wpdb->posts.ID NOT IN ( " . $subquery . " ) ";

		} elseif ( 'taxonomies' == $query_type ) {

			return $wpdb->prepare( " AND sub_t.term_id NOT IN ($placeholder) ", $term_ids );
		}

	}


	/**
	 * Construct exclude post IDs WHERE SQL clause
	 *
	 * @since 1.0.0
	 * @param array $post_ids Array of post IDs
	 * @return string
	 */
	private function get_post_access_where_clause( $post_ids ) {

		global $wpdb;

		if ( empty( $post_ids ) ) {
			return '';
		}

		$placeholder = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );

		return $wpdb->prepare( " AND ID NOT IN ($placeholder)", $post_ids );
	}


	/**
	 * Get taxonomies that apply to provided post types
	 *
	 * @since 1.0.0
	 * @param array $post_types
	 * @return array Array with taxonomy names
	 */
	private function get_taxonomies_for_post_types( $post_types ) {

		$taxonomies = array();

		foreach ( $post_types as $post_type ) {
			$taxonomies = array_merge( $taxonomies, get_object_taxonomies( $post_type ) );
		}

		return array_unique( $taxonomies );
	}


	/**
	 * Get post_types that the provided taxonomies are registered for
	 *
	 * @since 1.0.0
	 * @param array $taxonomies
	 * @return array Array with post types
	 */
	private function get_post_types_for_taxonomies( $taxonomies ) {

		$post_types = array();

		foreach ( $taxonomies as $taxonomy ) {
			$tax = get_taxonomy( $taxonomy );
			$post_types = array_merge( $post_types, $tax->object_type );
		}

		return array_unique( $post_types );
	}


	/**
	 * Remove add to cart button for nun-purchasable booking products
	 *
	 * TODO: remove this once WC Bookings fixes their is_purchasable implementation
	 *
	 * @since 1.0.0
	 */
	public function fix_wc_bookings() {
		global $wp_filter, $product;

		if ( ! $this->product_is_purchasable( true, $product ) ) {

			$tag = 'woocommerce_booking_add_to_cart';

			if ( isset( $wp_filter[ $tag ] ) && ! empty( $wp_filter[ $tag ] ) ) {

				foreach ( $wp_filter[ $tag ] as $priority => $filters ) {

					foreach ( $filters as $key => $filter ) {
						if ( is_array( $filter['function'] ) && is_a( $filter['function'][0], 'WC_Booking_Cart_Manager' ) && 'add_to_cart' == $filter['function'][1] ) {

							unset( $wp_filter[ $tag ][ $priority ][ $key ] );
							unset( $GLOBALS['merged_filters'][ $tag ] );
						}
					}
				}
			}
		}
	}

}
