<?php
/**
 * Builds the markdown body of llms.txt and llms-full.txt.
 *
 * Output follows the llmstxt.org structure:
 *   # Title
 *   > One-line summary (blockquote)
 *   Optional intro paragraph(s)
 *   ## Section (one per post type)
 *   - [Title](absolute-url): description
 *   ## Optional   (extra/manual links, may be skipped by LLMs with small context)
 *
 * @package LLMs_Txt_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderer for the two generated files.
 */
class LLMs_Txt_Renderer {

	/**
	 * Settings handler.
	 *
	 * @var LLMs_Txt_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param LLMs_Txt_Settings $settings Settings handler.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Render the standard llms.txt (curated map of links).
	 *
	 * @return string
	 */
	public function render_basic() {
		$out  = $this->render_header();
		$out .= $this->render_sections( false );
		$out .= $this->render_optional_section();
		return $this->finalize( $out );
	}

	/**
	 * Render llms-full.txt (links + full post content inline).
	 *
	 * @return string
	 */
	public function render_full() {
		$out  = $this->render_header();
		$out .= $this->render_sections( true );
		return $this->finalize( $out );
	}

	/**
	 * H1 title + blockquote summary + optional intro.
	 *
	 * @return string
	 */
	private function render_header() {
		$title = $this->settings->get( 'site_title' );
		if ( empty( $title ) ) {
			$title = get_bloginfo( 'name' );
		}

		$summary = $this->settings->get( 'site_summary' );
		if ( empty( $summary ) ) {
			$summary = get_bloginfo( 'description' );
		}

		$out = '# ' . $this->clean( $title ) . "\n\n";

		if ( ! empty( $summary ) ) {
			$out .= '> ' . $this->clean( $summary ) . "\n\n";
		}

		$intro = trim( (string) $this->settings->get( 'intro' ) );
		if ( '' !== $intro ) {
			$out .= $this->clean( $intro ) . "\n\n";
		}

		return $out;
	}

	/**
	 * One H2 section per enabled post type.
	 *
	 * @param bool $with_content Whether to inline full content (llms-full.txt).
	 * @return string
	 */
	private function render_sections( $with_content ) {
		$out        = '';
		$post_types = (array) $this->settings->get( 'post_types', array( 'page', 'post' ) );
		$per_type   = (int) $this->settings->get( 'max_per_type', 100 );

		foreach ( $post_types as $post_type ) {
			$type_obj = get_post_type_object( $post_type );
			if ( ! $type_obj ) {
				continue;
			}

			$query = new WP_Query(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'publish',
					'posts_per_page'         => $per_type > 0 ? $per_type : -1,
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'update_post_term_cache' => false,
				)
			);

			if ( ! $query->have_posts() ) {
				continue;
			}

			$lines = array();
			foreach ( $query->posts as $post ) {
				if ( $this->is_noindex( $post->ID ) ) {
					continue;
				}
				$lines[] = $this->render_entry( $post, $with_content );
			}
			wp_reset_postdata();

			if ( empty( $lines ) ) {
				continue;
			}

			$label = isset( $type_obj->labels->name ) ? $type_obj->labels->name : ucfirst( $post_type );
			$out  .= '## ' . $this->clean( $label ) . "\n\n";
			$out  .= implode( $with_content ? "\n" : '', $lines );
			$out  .= "\n";
		}

		return $out;
	}

	/**
	 * Render a single entry line (and content block for the full variant).
	 *
	 * @param WP_Post $post         Post object.
	 * @param bool    $with_content Inline content?
	 * @return string
	 */
	private function render_entry( $post, $with_content ) {
		$title = $this->clean( get_the_title( $post ) );
		$url   = get_permalink( $post );
		$desc  = $this->get_description( $post );

		$line = '- [' . $title . '](' . esc_url_raw( $url ) . ')';
		if ( '' !== $desc ) {
			$line .= ': ' . $desc;
		}
		$line .= "\n";

		if ( ! $with_content ) {
			return $line;
		}

		$content = $this->get_plain_content( $post );
		if ( '' !== $content ) {
			$line .= "\n" . $content . "\n";
		}

		return $line;
	}

	/**
	 * The "Optional" section holds manually configured extra links.
	 * Per the spec, LLMs may skip this when context is tight.
	 *
	 * @return string
	 */
	private function render_optional_section() {
		$raw = trim( (string) $this->settings->get( 'optional_links' ) );
		if ( '' === $raw ) {
			return '';
		}

		$out   = "## Optional\n\n";
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			// Accept either "Title | URL | description" or a raw markdown line.
			if ( false !== strpos( $line, '|' ) ) {
				$parts = array_map( 'trim', explode( '|', $line ) );
				$title = isset( $parts[0] ) ? $this->clean( $parts[0] ) : '';
				$url   = isset( $parts[1] ) ? esc_url_raw( $parts[1] ) : '';
				$desc  = isset( $parts[2] ) ? $this->clean( $parts[2] ) : '';
				if ( '' === $url ) {
					continue;
				}
				$entry = '- [' . $title . '](' . $url . ')';
				if ( '' !== $desc ) {
					$entry .= ': ' . $desc;
				}
				$out .= $entry . "\n";
			} else {
				$out .= ( 0 === strpos( $line, '-' ) ? '' : '- ' ) . $line . "\n";
			}
		}
		$out .= "\n";

		return $out;
	}

	/**
	 * Best-effort meta description: SEO plugin meta → excerpt → trimmed content.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function get_description( $post ) {
		// Yoast SEO.
		$desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		// Rank Math.
		if ( empty( $desc ) ) {
			$desc = get_post_meta( $post->ID, 'rank_math_description', true );
		}
		// All in One SEO (modern schema).
		if ( empty( $desc ) ) {
			$desc = get_post_meta( $post->ID, '_aioseo_description', true );
		}
		if ( empty( $desc ) ) {
			$desc = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
		}
		if ( empty( $desc ) ) {
			$desc = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '…' );
		}

		return $this->clean( $desc );
	}

	/**
	 * Full content as plain text (for llms-full.txt).
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function get_plain_content( $post ) {
		$content = $post->post_content;
		$content = strip_shortcodes( $content );
		$content = excerpt_remove_blocks( $content ); // strips block comments cleanly when available.
		$content = wp_strip_all_tags( $content, true );
		$content = preg_replace( "/\n{3,}/", "\n\n", $content );
		return trim( $content );
	}

	/**
	 * Detect noindex flags from the common SEO plugins.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_noindex( $post_id ) {
		// Yoast: '1' means noindex.
		if ( '1' === get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			return true;
		}
		// Rank Math stores a serialized array of robots flags.
		$rm = get_post_meta( $post_id, 'rank_math_robots', true );
		if ( is_array( $rm ) && in_array( 'noindex', $rm, true ) ) {
			return true;
		}
		// All in One SEO.
		if ( '0' === (string) get_post_meta( $post_id, '_aioseo_robots_default', true )
			&& get_post_meta( $post_id, '_aioseo_robots_noindex', true ) ) {
			return true;
		}

		/**
		 * Allow site owners / other plugins to force-exclude a post.
		 *
		 * @param bool $noindex Whether the post should be excluded.
		 * @param int  $post_id Post ID.
		 */
		return (bool) apply_filters( 'llms_txt_is_noindex', false, $post_id );
	}

	/**
	 * Collapse whitespace / newlines so a value stays on one line.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function clean( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = preg_replace( '/\s+/', ' ', $value );
		return trim( $value );
	}

	/**
	 * Append a generated-at footer and run a final filter.
	 *
	 * @param string $out Assembled body.
	 * @return string
	 */
	private function finalize( $out ) {
		$out  = rtrim( $out ) . "\n";
		$out .= "\n<!-- Generated by LLMs.txt Generator on " . gmdate( 'Y-m-d H:i' ) . " UTC -->\n";

		/**
		 * Filter the final file body before it is served/cached.
		 *
		 * @param string $out The complete markdown output.
		 */
		return apply_filters( 'llms_txt_output', $out );
	}
}
