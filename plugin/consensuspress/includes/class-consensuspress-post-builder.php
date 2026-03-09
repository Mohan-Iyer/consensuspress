<?php
/**
 * DNA Header
 * File:         includes/class-consensuspress-post-builder.php
 * Version:      2.1.0
 * Purpose:      Accepts raw ConsensusResult engine data and creates a WordPress
 *               draft post complete with content HTML, SEO metadata, JSON-LD
 *               schema, and consensus post meta. PHP port of seekrates_publisher
 *               content_formatter.py + seo_optimizer.py.
 *               PUBLIC signature of create_draft() is FROZEN — callers unchanged.
 * Author:       C-C (Session 03, Sprint 2) | Modified: C-C (Session 09, Sprint 7) | Modified: C-C (Session 12, Sprint 8)
 * Spec:         docs/sprint_7_D1_d7_instructions.yaml D4 part_b_post_builder
 * PHP Version:  7.4+
 * Dependencies: WordPress core, Rank Math (optional — graceful if absent)
 * Reusable:     Yes — called by ConsensusPress_Async::process_job()
 *
 * Changes v2.0.0 (Sprint 7 — HAL-008 resolution):
 *   - generate_content_pipeline() NEW — PHP equivalent of content_formatter.py
 *     + seo_optimizer.py. All content generated from raw engine data.
 *   - validate_api_data() UPDATED — new required key set (consensus, providers,
 *     correlation_id) replacing old assumed v1.0 mock fields.
 *   - inject_consensus_meta() UPDATED — reads new field paths:
 *     consensus.convergence_percentage, consensus.champion,
 *     consensus.consensus_confidence, risk_analysis.oracle_recommendation.
 *   - create_draft() UPDATED orchestration — calls generate_content_pipeline()
 *     instead of reading pre-built fields from $api_data.
 *   - All new private helper methods per D4 spec.
 *   - sideload_featured_image() PRESERVED unchanged (deferred Sprint 8).
 *   - inject_rank_math_meta(), assign_taxonomy_terms() PRESERVED unchanged.
 *
 * FROZEN PUBLIC CONTRACT (callers must not change):
 *   create_draft( array $api_data, string $mode = 'create' ): array
 *
 * HAL scan: PASS — no LLM calls, no invented imports, no hardcoded secrets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds WordPress draft posts from raw Seekrates AI consensus engine data.
 *
 * Orchestrates content generation, SEO metadata derivation, JSON-LD schema
 * construction, and post meta injection in a single create_draft() call.
 * All content generation is pure PHP string manipulation — no LLM calls.
 *
 * @since 1.0.0
 * @since 2.0.0 PHP content pipeline — generate_content_pipeline() added.
 */
class ConsensusPress_Post_Builder {

	/**
	 * Rank Math post meta keys (Rank Math must be active for SEO injection).
	 *
	 * @var array<string, string>
	 */
	const RANK_MATH_META_KEYS = array(
		'focus_keyword'    => 'rank_math_focus_keyword',
		'seo_title'        => 'rank_math_title',
		'meta_description' => 'rank_math_description',
		'faq_schema'       => 'rank_math_schema_data',
	);

	/**
	 * ConsensusPress post meta keys for the consensus meta box.
	 * Values MUST NOT change — meta box reads these exact keys.
	 *
	 * @var array<string, string>
	 */
	const CONSENSUS_META_KEYS = array(
		'score'            => '_consensuspress_score',
		'mode'             => '_consensuspress_mode',
		'champion_provider'=> '_consensuspress_champion_provider',
		'agreement_level'  => '_consensuspress_agreement_level',
		'oracle_risk_level'=> '_consensuspress_oracle_risk_level',
		'created_at'       => '_consensuspress_created_at',
	);

	// =========================================================================
	// PUBLIC METHODS
	// =========================================================================

	/**
	 * Create a WordPress draft post from raw consensus engine data.
	 *
	 * PUBLIC SIGNATURE FROZEN — ConsensusPress_Async and all callers depend on
	 * this exact interface. Do not change parameter names or types.
	 *
	 * @param array  $api_data Raw data dict from ConsensusResult.model_dump()
	 *                         with query and mode injected by endpoint.
	 * @param string $mode     'create' or 'rescue'.
	 * @return array{
	 *     success:  bool,
	 *     post_id:  int,
	 *     edit_url: string,
	 *     message:  string
	 * }
	 */
	public function create_draft( array $api_data, string $mode = 'create' ): array {
		// ------------------------------------------------------------------
		// 1. Validate incoming API data shape.
		// ------------------------------------------------------------------
		if ( ! $this->validate_api_data( $api_data ) ) {
			return array(
				'success'  => false,
				'post_id'  => 0,
				'edit_url' => '',
				'message'  => 'Invalid API response structure.',
			);
		}

		// ------------------------------------------------------------------
		// 2. Generate all content, metadata, and schema from raw engine data.
		// ------------------------------------------------------------------
		$generated = $this->generate_content_pipeline( $api_data );

		// ------------------------------------------------------------------
		// 3. Build post title from generated metadata.
		// ------------------------------------------------------------------
		$post_title = sanitize_text_field( $generated['metadata']['suggested_title'] );

		// ------------------------------------------------------------------
		// 4. Insert WordPress draft.
		// ------------------------------------------------------------------
		$post_args = array(
			'post_title'   => $post_title,
			'post_content' => wp_kses_post( $generated['content_html'] ),
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'post_author'  => get_current_user_id(),
		);

		$post_id = wp_insert_post( $post_args, true );

		if ( is_wp_error( $post_id ) ) {
			return array(
				'success'  => false,
				'post_id'  => 0,
				'edit_url' => '',
				'message'  => $post_id->get_error_message(),
			);
		}

		// ------------------------------------------------------------------
		// 5. Inject Rank Math SEO metadata and JSON-LD schema.
		// ------------------------------------------------------------------
		$this->inject_rank_math_meta( $post_id, $generated['metadata'], $generated['schema_markup'] );

		// ------------------------------------------------------------------
		// 6. Inject consensus meta box data (updated field paths v2.0.0).
		// ------------------------------------------------------------------
		$this->inject_consensus_meta( $post_id, $api_data, $mode );

		// ------------------------------------------------------------------
		// 7. Featured image — Sprint 8: fetch from Unsplash using focus keyword.
		//    Graceful degradation: if fetch fails, empty array skips sideload.
		// ------------------------------------------------------------------
		$featured_image = $this->fetch_unsplash_image( $generated['metadata']['focus_keyword'] ?? '' );
		if ( ! empty( $featured_image['url'] ) ) {
			$this->sideload_featured_image( $post_id, $featured_image );
		}

		// ------------------------------------------------------------------
		// 8. Assign taxonomy terms (categories and tags).
		// ------------------------------------------------------------------
		$this->assign_taxonomy_terms( $post_id, $generated['metadata'] );

		return array(
			'success'  => true,
			'post_id'  => (int) $post_id,
			'edit_url' => (string) get_edit_post_link( $post_id, 'raw' ),
			'message'  => 'Draft created successfully.',
		);
	}

	// =========================================================================
	// PRIVATE METHODS — CONTENT PIPELINE (NEW v2.0.0)
	// =========================================================================

	/**
	 * Master content generation pipeline.
	 *
	 * PHP equivalent of seekrates_publisher content_formatter.py +
	 * seo_optimizer.py. Derives ALL content from raw engine data.
	 * Makes zero LLM calls and zero HTTP requests.
	 *
	 * @param array $api_data Validated engine data (D1 api_data_input shape).
	 * @return array{
	 *     content_html:  string,
	 *     metadata:      array{
	 *         focus_keyword: string,
	 *         seo_title: string,
	 *         meta_description: string,
	 *         suggested_title: string,
	 *         categories: list<string>,
	 *         tags: list<string>
	 *     },
	 *     schema_markup: array{faq: array, article: array},
	 *     featured_image: array
	 * }
	 */
	private function generate_content_pipeline( array $api_data ): array {
		$query           = sanitize_text_field( $api_data['query'] ?? '' );
		$synthesis       = $api_data['consensus']['consensus_text'] ?? '';
		$consensus_pct   = (float) ( $api_data['consensus']['convergence_percentage'] ?? 0 );
		$champion_name   = sanitize_text_field( $api_data['consensus']['champion'] ?? '' );
		$champion_score  = (int) ( $api_data['consensus']['champion_score'] ?? 0 );
		$agreement_level = sanitize_text_field( $api_data['consensus']['consensus_confidence'] ?? 'LOW' );
		$agent_count     = count( $api_data['providers'] ?? array() );

		// Derive SEO metadata.
		$focus_keyword    = $this->extract_focus_keyword( $query );
		$seo_title        = $this->generate_seo_title( $focus_keyword );
		$meta_description = $this->generate_meta_description( $synthesis, $consensus_pct, $focus_keyword );
		$suggested_title  = $seo_title;

		// Content pipeline.
		$paragraphs     = $this->chunk_paragraphs( $synthesis );
		$champion_answer = $this->get_champion_answer( $api_data['providers'] ?? array(), $champion_name );
		$insights       = $this->extract_insights( $synthesis, $paragraphs );
		$agreement_pts  = array_slice( $api_data['divergence']['common_themes'] ?? array(), 0, 5 );
		$divergence_pts = array_slice( $api_data['divergence']['outliers'] ?? array(), 0, 3 );

		// JSON-LD schema.
		$faq_schema     = $this->generate_faq_schema( $query, $synthesis, $focus_keyword );
		$article_schema = $this->generate_article_schema( $seo_title, $meta_description );

		// Average provider confidence (float 0–1 → integer %).
		$confidences    = array_column( $api_data['providers'] ?? array(), 'confidence' );
		$avg_confidence = ! empty( $confidences )
			? array_sum( $confidences ) / count( $confidences )
			: 0.0;

		// Build HTML.
		$content_html = $this->build_content_html( array(
			'focus_keyword'   => $focus_keyword,
			'query'           => $query,
			'consensus_pct'   => round( $consensus_pct ),
			'agreement_level' => $agreement_level,
			'agent_count'     => $agent_count,
			'avg_confidence'  => round( $avg_confidence * 100 ),
			'champion_name'   => strtoupper( $champion_name ),
			'champion_score'  => $champion_score,
			'champion_answer' => $this->convert_plain_to_html_paragraphs( $champion_answer ),
			'paragraphs'      => $paragraphs,
			'insights'        => $insights,
			'points_agreement'  => $agreement_pts,
			'points_divergence' => $divergence_pts,
			'category'        => 'ai-insights',
			'post_date'       => gmdate( 'F j, Y' ),
			'correlation_id'  => sanitize_text_field( $api_data['correlation_id'] ?? '' ),
		) );

		// Tags from focus keyword words + taxonomy constants.
		$tags   = array_filter( explode( ' ', $focus_keyword ) );
		$tags[] = 'ai-consensus';
		$tags[] = 'seekrates';

		return array(
			'content_html'  => $content_html,
			'metadata'      => array(
				'focus_keyword'    => $focus_keyword,
				'seo_title'        => $seo_title,
				'meta_description' => $meta_description,
				'suggested_title'  => $suggested_title,
				'categories'       => array( 'ai-insights' ),
				'tags'             => array_values( array_unique( $tags ) ),
			),
			'schema_markup' => array(
				'faq'     => $faq_schema,
				'article' => $article_schema,
			),
			'featured_image' => array(),  // Unsplash deferred — Sprint 8 (D-08-07).
		);
	}

	/**
	 * Extract a 2–3 word focus keyword from the query.
	 *
	 * Strips stop words, returns first three unique content words.
	 * Falls back to first 30 chars of query if no content words found.
	 *
	 * @param string $query Raw topic query.
	 * @return string Focus keyword (2–3 words).
	 */
	private function extract_focus_keyword( string $query ): string {
		$stop_words = array(
			'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
			'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be',
			'what', 'where', 'when', 'how', 'why', 'which', 'who', 'will', 'can',
			'should', 'would', 'could', 'do', 'does', 'did', 'has', 'have', 'had',
			'most', 'best', 'top', 'key', 'effective', 'important', 'about', 'that',
		);

		$clean = preg_replace( '/[^\w\s]/', ' ', strtolower( $query ) );
		$words = array_filter( explode( ' ', (string) $clean ) );

		$content_words = array_values(
			array_filter(
				$words,
				function( string $w ) use ( $stop_words ): bool {
					return strlen( $w ) > 3 && ! in_array( $w, $stop_words, true );
				}
			)
		);

		$unique = array_slice( array_unique( $content_words ), 0, 3 );
		return implode( ' ', $unique ) ?: substr( $query, 0, 30 );
	}

	/**
	 * Generate an SEO-optimised post title from the focus keyword.
	 *
	 * Tries three progressively shorter patterns to stay under 60 chars.
	 *
	 * @param string $focus_keyword 2–3 word focus keyword.
	 * @return string SEO title (<=60 chars).
	 */
	private function generate_seo_title( string $focus_keyword ): string {
		$title = ucwords( $focus_keyword ) . ': 5 AIs Reveal Key Insights';
		if ( strlen( $title ) > 60 ) {
			$title = ucwords( $focus_keyword ) . ': AI Consensus Insights';
		}
		if ( strlen( $title ) > 60 ) {
			$title = ucwords( $focus_keyword );
		}
		return $title;
	}

	/**
	 * Generate a Rank Math meta description (<=155 chars).
	 *
	 * Prefixes the first sentence of synthesis with keyword + consensus %.
	 *
	 * @param string $synthesis     Raw consensus_text from engine.
	 * @param float  $consensus_pct convergence_percentage (0–100).
	 * @param string $focus_keyword Focus keyword.
	 * @return string Meta description (<=155 chars).
	 */
	private function generate_meta_description(
		string $synthesis,
		float $consensus_pct,
		string $focus_keyword
	): string {
		$sentences = preg_split( '/(?<=[.!?])\s+/', $synthesis, 2 );
		$first     = trim( $sentences[0] ?? $synthesis );
		$prefix    = ucwords( $focus_keyword ) . ' — ' . round( $consensus_pct ) . '% AI consensus: ';
		$desc      = $prefix . $first;

		if ( strlen( $desc ) > 155 ) {
			$desc = substr( $desc, 0, 152 ) . '...';
		}
		return $desc;
	}

	/**
	 * Chunk synthesis text into 40–60 word paragraphs for GEO/LLMO optimisation.
	 *
	 * Groups sentences until target word count reached, then starts a new
	 * paragraph. Returns 3–7 chunks maximum.
	 *
	 * @param string $text         Raw synthesis text.
	 * @param int    $target_words Target words per paragraph (default 50).
	 * @return array<int, string> Array of paragraph strings.
	 */
	private function chunk_paragraphs( string $text, int $target_words = 50 ): array {
		$sentences  = preg_split( '/(?<=[.!?])\s+/', $text );
		$sentences  = array_filter( array_map( 'trim', (array) $sentences ) );
		$paragraphs = array();
		$current    = array();
		$word_count = 0;

		foreach ( $sentences as $sentence ) {
			$words       = str_word_count( $sentence );
			$current[]   = $sentence;
			$word_count += $words;

			if ( $word_count >= $target_words ) {
				$paragraphs[] = implode( ' ', $current );
				$current      = array();
				$word_count   = 0;
			}
		}

		// Append any remaining sentences as the final paragraph.
		if ( ! empty( $current ) ) {
			$paragraphs[] = implode( ' ', $current );
		}

		// Cap at 7 paragraphs to keep posts to a reasonable length.
		return array_slice( $paragraphs, 0, 7 );
	}

	/**
	 * Extract 3 key insight bullets from synthesis text.
	 *
	 * Takes up to 3 sentences from the first paragraph as extracted
	 * insights. Falls back to empty array if text is too short.
	 *
	 * @param string         $synthesis  Raw synthesis text.
	 * @param array<string>  $paragraphs Pre-chunked paragraphs.
	 * @return array<int, string> Up to 3 insight strings.
	 */
	private function extract_insights( string $synthesis, array $paragraphs ): array {
		$source    = ! empty( $paragraphs ) ? $paragraphs[0] : $synthesis;
		$sentences = preg_split( '/(?<=[.!?])\s+/', $source );
		$sentences = array_filter( array_map( 'trim', (array) $sentences ) );
		return array_values( array_slice( $sentences, 0, 3 ) );
	}

	/**
	 * Find the champion provider's raw answer text.
	 *
	 * Matches $champion_name against providers[].provider (case-insensitive).
	 * Falls back to the highest-scoring provider's answer if no exact match.
	 *
	 * @param array  $providers     providers[] array from engine data.
	 * @param string $champion_name Provider name from consensus.champion.
	 * @return string Champion's raw answer text.
	 */
	private function get_champion_answer( array $providers, string $champion_name ): string {
		foreach ( $providers as $provider ) {
			if ( strtolower( $provider['provider'] ?? '' ) === strtolower( $champion_name ) ) {
				return (string) ( $provider['answer'] ?? '' );
			}
		}

		// Fallback: return highest-scoring non-refusal provider's answer.
		$best       = '';
		$best_score = -1;
		foreach ( $providers as $provider ) {
			if ( empty( $provider['is_refusal'] ) && (int) ( $provider['score'] ?? 0 ) > $best_score ) {
				$best       = (string) ( $provider['answer'] ?? '' );
				$best_score = (int) ( $provider['score'] ?? 0 );
			}
		}
		return $best;
	}

	/**
	 * Wrap each line of plain text in an HTML paragraph tag.
	 *
	 * Used to format the champion provider's raw answer text for display.
	 * Sentences are split on terminal punctuation boundaries.
	 *
	 * @param string $text Plain text.
	 * @return string HTML string of <p> elements.
	 */
	private function convert_plain_to_html_paragraphs( string $text ): string {
		$sentences = preg_split( '/(?<=[.!?])\s+/', $text );
		$sentences = array_filter( array_map( 'trim', (array) $sentences ) );
		$html      = '';
		foreach ( $sentences as $sentence ) {
			$html .= '<p>' . esc_html( $sentence ) . '</p>';
		}
		return $html;
	}

	/**
	 * Generate FAQPage JSON-LD schema for AI-search citation optimisation.
	 *
	 * Two FAQ entries: the original query + a "What do AI models say about X?" question.
	 * Both answers drawn from the synthesis first sentence (capped at 155 chars).
	 *
	 * @param string $query         Original query.
	 * @param string $synthesis     Consensus text.
	 * @param string $focus_keyword Focus keyword.
	 * @return array FAQPage JSON-LD schema array.
	 */
	private function generate_faq_schema(
		string $query,
		string $synthesis,
		string $focus_keyword
	): array {
		$answer_text = strlen( $synthesis ) > 155 ? substr( $synthesis, 0, 152 ) . '...' : $synthesis;

		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array(
				array(
					'@type'          => 'Question',
					'name'           => sanitize_text_field( $query ),
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => sanitize_text_field( $answer_text ),
					),
				),
				array(
					'@type'          => 'Question',
					'name'           => 'What do AI models say about ' . sanitize_text_field( $focus_keyword ) . '?',
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => 'According to Seekrates AI consensus analysis, ' . sanitize_text_field( $answer_text ),
					),
				),
			),
		);
	}

	/**
	 * Generate Article JSON-LD schema.
	 *
	 * Publisher name sourced from get_bloginfo('name') to avoid hardcoded
	 * seekrates-ai.com URLs on customer sites.
	 *
	 * @param string $seo_title        Generated SEO title.
	 * @param string $meta_description Generated meta description.
	 * @return array Article JSON-LD schema array.
	 */
	private function generate_article_schema( string $seo_title, string $meta_description ): array {
		return array(
			'@context'      => 'https://schema.org',
			'@type'         => 'Article',
			'headline'      => sanitize_text_field( $seo_title ),
			'description'   => sanitize_text_field( $meta_description ),
			'author'        => array( '@type' => 'Organization', 'name' => 'Seekrates AI' ),
			'publisher'     => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url(),
			),
			'datePublished' => gmdate( 'c' ),  // ISO 8601.
		);
	}

	/**
	 * Build the full blog post HTML from generated variables.
	 *
	 * PHP equivalent of seekrates_blog_post_template.html v6.0.0.
	 * Elementor shortcodes STRIPPED (customer sites — not seekrates-ai.com).
	 * Internal links use home_url() — never seekrates-ai.com.
	 * All dynamic values escaped before insertion into HTML.
	 * Output passed through wp_kses_post() before return.
	 *
	 * Agreement level colours match template v6.0.0 exactly:
	 *   HIGH    → #d4edda  |  MODERATE → #fff3cd  |  LOW → #f8d7da
	 *
	 * @param array $vars Compiled template variables from generate_content_pipeline().
	 * @return string Sanitised post HTML (wp_kses_post applied).
	 */
	private function build_content_html( array $vars ): string {
		// Escape all template variables before use in HTML context.
		$fk   = esc_html( (string) ( $vars['focus_keyword'] ?? '' ) );
		$q    = esc_html( (string) ( $vars['query'] ?? '' ) );
		$pct  = (int) ( $vars['consensus_pct'] ?? 0 );
		$al   = esc_html( strtoupper( (string) ( $vars['agreement_level'] ?? 'LOW' ) ) );
		$ac   = (int) ( $vars['agent_count'] ?? 5 );
		$conf = (int) ( $vars['avg_confidence'] ?? 0 );
		$cn   = esc_html( (string) ( $vars['champion_name'] ?? '' ) );
		$cs   = (int) ( $vars['champion_score'] ?? 0 );
		$ca   = wp_kses_post( (string) ( $vars['champion_answer'] ?? '' ) );
		$date = esc_html( (string) ( $vars['post_date'] ?? '' ) );
		$cid  = esc_html( (string) ( $vars['correlation_id'] ?? '' ) );

		// Agreement level badge background colour.
		$badge_colour_map = array(
			'HIGH'     => '#d4edda',
			'MODERATE' => '#fff3cd',
			'LOW'      => '#f8d7da',
		);
		$badge_bg = $badge_colour_map[ $al ] ?? '#f8d7da';

		// Paragraphs — already plain text from chunk_paragraphs(), wrap in <p>.
		$paragraphs_html = '';
		foreach ( (array) ( $vars['paragraphs'] ?? array() ) as $para ) {
			$paragraphs_html .= '<p>' . esc_html( $para ) . '</p>' . "\n";
		}

		// Key insights.
		$insights_html = '';
		if ( ! empty( $vars['insights'] ) ) {
			$insights_html .= '<div class="seekrates-insights-box" style="background:#f0f4ff;border-left:4px solid #3a5a9f;padding:16px 20px;margin:24px 0;">';
			$insights_html .= '<h3 style="margin-top:0;">Key Insights</h3><ul>';
			foreach ( (array) $vars['insights'] as $insight ) {
				$insights_html .= '<li>' . esc_html( $insight ) . '</li>';
			}
			$insights_html .= '</ul></div>';
		}

		// Points of agreement.
		$agreement_html = '';
		if ( ! empty( $vars['points_agreement'] ) ) {
			$agreement_html .= '<h2 id="agreement">Points of Agreement</h2><ul>';
			foreach ( (array) $vars['points_agreement'] as $point ) {
				$agreement_html .= '<li>' . esc_html( $point ) . '</li>';
			}
			$agreement_html .= '</ul>';
		}

		// Points of divergence.
		$divergence_html = '';
		if ( ! empty( $vars['points_divergence'] ) ) {
			$divergence_html .= '<h2 id="divergence">Points of Divergence</h2><ul>';
			foreach ( (array) $vars['points_divergence'] as $point ) {
				$divergence_html .= '<li>' . esc_html( $point ) . '</li>';
			}
			$divergence_html .= '</ul>';
		}

		// Internal category link — home_url() never seekrates-ai.com.
		$category_url    = esc_url( home_url( '/category/ai-insights/' ) );
		$home_url        = esc_url( home_url() );

		// Build complete HTML.
		$html  = '<article class="seekrates-consensus-post">' . "\n\n";

		// 1. Intro paragraph.
		$html .= '<p>In the rapidly evolving landscape of AI search, understanding <strong>' . $fk . '</strong> ';
		$html .= 'has become essential for content creators and businesses. ';
		$html .= 'This analysis presents the consensus view from ' . $ac . ' leading AI models.</p>' . "\n\n";

		// 2. Consensus badge.
		$html .= '<div class="seekrates-consensus-badge" style="background:' . esc_attr( $badge_bg ) . ';';
		$html .= 'border:1px solid #ccc;border-radius:6px;padding:16px 20px;margin:24px 0;text-align:center;">' . "\n";
		$html .= '<strong style="font-size:1.2em;">' . $pct . '% AI Consensus</strong>';
		$html .= ' &mdash; Agreement Level: <strong>' . $al . '</strong>' . "\n";
		$html .= '</div>' . "\n\n";

		// 3. The Question Asked.
		$html .= '<div class="seekrates-question-block" style="background:#f9f9f9;border:1px solid #e0e0e0;';
		$html .= 'border-radius:4px;padding:14px 18px;margin:20px 0;">' . "\n";
		$html .= '<p><strong>The Question Asked:</strong></p><p><em>' . $q . '</em></p>' . "\n";
		$html .= '</div>' . "\n\n";

		// 4. Metrics table.
		$html .= '<table class="seekrates-metrics-table" style="width:100%;border-collapse:collapse;margin:20px 0;">' . "\n";
		$html .= '<thead><tr style="background:#3a5a9f;color:#fff;">';
		$html .= '<th style="padding:10px;text-align:center;">AI Agents</th>';
		$html .= '<th style="padding:10px;text-align:center;">Avg Confidence</th>';
		$html .= '<th style="padding:10px;text-align:center;">Champion Score</th>';
		$html .= '<th style="padding:10px;text-align:center;">Agreement Level</th>';
		$html .= '</tr></thead><tbody><tr>';
		$html .= '<td style="padding:10px;text-align:center;">' . $ac . '</td>';
		$html .= '<td style="padding:10px;text-align:center;">' . $conf . '%</td>';
		$html .= '<td style="padding:10px;text-align:center;">' . $cs . '/100</td>';
		$html .= '<td style="padding:10px;text-align:center;">' . $al . '</td>';
		$html .= '</tr></tbody></table>' . "\n\n";

		// 5. Main consensus content heading.
		$html .= '<h2 id="consensus">What ' . $ac . ' Leading AI Models Say About ' . $fk . '</h2>' . "\n\n";

		// 6. Synthesis paragraphs.
		$html .= $paragraphs_html . "\n";

		// 7. Key insights box (conditional).
		$html .= $insights_html . "\n";

		// 8. Champion response.
		$html .= '<div class="seekrates-champion-box" style="background:#fff8e1;border:2px solid #f9a825;';
		$html .= 'border-radius:6px;padding:20px 24px;margin:28px 0;">' . "\n";
		$html .= '<h2 id="champion" style="margin-top:0;">Champion Response: ' . $cn . '</h2>' . "\n";
		$html .= '<p style="font-style:italic;font-size:0.85em;">Highest quality score: ' . $cs . '/100</p>' . "\n";
		$html .= $ca . "\n";
		$html .= '</div>' . "\n\n";

		// 9. Points of agreement (conditional).
		$html .= $agreement_html . "\n";

		// 10. Points of divergence (conditional).
		$html .= $divergence_html . "\n";

		// 11. Why [focus_keyword] matters — internal link via home_url().
		$html .= '<h2 id="why-it-matters">Why ' . $fk . ' Matters</h2>' . "\n";
		$html .= '<p>Understanding ' . $fk . ' is critical for anyone publishing content in today\'s AI-powered search environment. ';
		$html .= 'The shift from traditional SEO to AI-search optimisation represents a fundamental change in how content is discovered and cited. ';
		$html .= 'Explore more analysis at <a href="' . $category_url . '">our AI Insights hub</a>.</p>' . "\n\n";

		// 12. Pull quote blockquote.
		$html .= '<blockquote style="border-left:4px solid #3a5a9f;padding:12px 20px;margin:24px 0;';
		$html .= 'font-style:italic;background:#f0f4ff;">' . "\n";
		$html .= '<p>' . $pct . '% of AI models converged on this analysis — one of the highest consensus scores recorded for this topic.</p>' . "\n";
		$html .= '</blockquote>' . "\n\n";

		// 13. Next steps.
		$html .= '<h2 id="next-steps">Next Steps</h2>' . "\n";
		$html .= '<p>To apply these insights to your content strategy:</p><ul>' . "\n";
		$html .= '<li>Implement FAQ schema markup on your highest-traffic posts</li>' . "\n";
		$html .= '<li>Restructure headings as direct questions matching AI query patterns</li>' . "\n";
		$html .= '<li>Aim for 40–60 word paragraph chunks for optimal LLM extraction</li>' . "\n";
		$html .= '<li>Validate key claims across multiple AI sources before publishing</li>' . "\n";
		$html .= '</ul>' . "\n\n";

		// 14. Champion info.
		$html .= '<p>This consensus was led by <strong>' . $cn . '</strong> with a quality score of <strong>' . $cs . '/100</strong>, ';
		$html .= 'reflecting the highest alignment with cross-model consensus standards.</p>' . "\n\n";

		// 15. Internal link back to category.
		$html .= '<p>Read more AI consensus analyses at <a href="' . $category_url . '">' . esc_html( get_bloginfo( 'name' ) ) . ' AI Insights</a>.</p>' . "\n\n";

		// 16. Methodology footer.
		$html .= '<p style="font-size:0.75em;color:#666;border-top:1px solid #e0e0e0;padding-top:12px;margin-top:24px;">';
		$html .= 'Methodology: ' . $ac . ' AI models queried simultaneously via Seekrates AI consensus engine. ';
		$html .= 'Responses scored by quality metrics. Consensus reached at ' . $pct . '% convergence. ';
		$html .= 'Correlation ID: ' . $cid . '. Published: ' . $date . '.</p>' . "\n\n";

		$html .= '</article>' . "\n";

		return wp_kses_post( $html );
	}

	// =========================================================================
	// PRIVATE METHODS — META INJECTION (UPDATED v2.0.0)
	// =========================================================================

	/**
	 * Validate incoming API data has the required engine output structure.
	 *
	 * Updated v2.0.0 to check real engine field paths.
	 * Old fields (consensus_score, champion.provider, etc.) removed.
	 *
	 * @param array $api_data Raw engine data.
	 * @return bool True if valid, false if missing required keys.
	 */
	private function validate_api_data( array $api_data ): bool {
		$required_top = array( 'consensus', 'providers', 'correlation_id' );
		foreach ( $required_top as $key ) {
			if ( ! array_key_exists( $key, $api_data ) ) {
				return false;
			}
		}

		$required_consensus = array(
			'champion',
			'champion_score',
			'convergence_percentage',
			'consensus_confidence',
			'reached',
			'consensus_text',
		);
		foreach ( $required_consensus as $key ) {
			if ( ! isset( $api_data['consensus'][ $key ] ) ) {
				return false;
			}
		}

		if ( ! is_array( $api_data['providers'] ) || empty( $api_data['providers'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Write consensus metadata to post meta for display in the meta box.
	 *
	 * Updated v2.0.0: reads from new engine field paths.
	 * Meta key NAMES unchanged — meta box code needs no modification.
	 *
	 * @param int    $post_id  WordPress post ID.
	 * @param array  $api_data Raw engine data.
	 * @param string $mode     'create' or 'rescue'.
	 * @return void
	 */
	private function inject_consensus_meta( int $post_id, array $api_data, string $mode ): void {
		// Consensus score — from consensus.convergence_percentage (float 0–100).
		update_post_meta(
			$post_id,
			self::CONSENSUS_META_KEYS['score'],
			(float) ( $api_data['consensus']['convergence_percentage'] ?? 0.0 )
		);

		// Mode.
		update_post_meta(
			$post_id,
			self::CONSENSUS_META_KEYS['mode'],
			sanitize_text_field( $mode )
		);

		// Champion provider name — from consensus.champion (string).
		update_post_meta(
			$post_id,
			self::CONSENSUS_META_KEYS['champion_provider'],
			sanitize_text_field( $api_data['consensus']['champion'] ?? '' )
		);

		// Agreement level — from consensus.consensus_confidence (HIGH|MODERATE|LOW).
		update_post_meta(
			$post_id,
			self::CONSENSUS_META_KEYS['agreement_level'],
			sanitize_text_field( $api_data['consensus']['consensus_confidence'] ?? '' )
		);

		// Oracle risk — derived from risk_analysis.oracle_recommendation (null for seeker/acolyte).
		$risk_recommendation = $api_data['risk_analysis']['oracle_recommendation'] ?? '';
		$risk_level          = '';
		if ( ! empty( $risk_recommendation ) ) {
			$risk_level = str_contains( strtolower( $risk_recommendation ), 'caution' ) ? 'medium' : 'low';
		}
		update_post_meta(
			$post_id,
			self::CONSENSUS_META_KEYS['oracle_risk_level'],
			sanitize_text_field( $risk_level )
		);

		// Created timestamp.
		update_post_meta(
			$post_id,
			self::CONSENSUS_META_KEYS['created_at'],
			current_time( 'mysql' )
		);
	}

	// =========================================================================
	// PRIVATE METHODS — PRESERVED UNCHANGED (Sprint 1–2)
	// =========================================================================

	/**
	 * Inject Rank Math SEO metadata and JSON-LD schema into post meta.
	 *
	 * Gracefully skips if Rank Math is not active (class does not exist).
	 * Method signature PRESERVED unchanged from Sprint 2.
	 *
	 * @param int    $post_id      WordPress post ID.
	 * @param array  $metadata     Generated metadata array from pipeline.
	 * @param array  $schema_markup Schema markup array (faq + article).
	 * @return void
	 */
	private function inject_rank_math_meta( int $post_id, array $metadata, array $schema_markup ): void {
		update_post_meta( $post_id, self::RANK_MATH_META_KEYS['focus_keyword'],    $metadata['focus_keyword'] );
		update_post_meta( $post_id, self::RANK_MATH_META_KEYS['seo_title'],        $metadata['seo_title'] );
		update_post_meta( $post_id, self::RANK_MATH_META_KEYS['meta_description'], $metadata['meta_description'] );

		// JSON-LD schema — Rank Math stores as serialised array.
		if ( ! empty( $schema_markup['faq'] ) ) {
			update_post_meta( $post_id, self::RANK_MATH_META_KEYS['faq_schema'], $schema_markup['faq'] );
		}
	}

	/**
	 * Fetch a relevant featured image from Unsplash.
	 *
	 * Requires consensuspress_unsplash_key to be set in wp_options.
	 * Returns empty array on any failure — post creation is never blocked.
	 * Uses key 'alt' (not 'alt_text') to match sideload_featured_image() contract.
	 *
	 * @param string $keyword Focus keyword used as Unsplash search query.
	 * @return array{url: string, alt: string, attribution: string}|array
	 */
	private function fetch_unsplash_image( string $keyword ): array {
		// 1. Check key configured.
		$key = get_option( 'consensuspress_unsplash_key', '' );
		if ( empty( $key ) ) {
			return array();
		}

		// 2. Validate keyword.
		$keyword = sanitize_text_field( $keyword );
		if ( empty( $keyword ) ) {
			return array();
		}

		// 3. Build request URL.
		$url = add_query_arg(
			array(
				'query'       => rawurlencode( $keyword ),
				'orientation' => 'landscape',
				'client_id'   => $key,
			),
			'https://api.unsplash.com/photos/random'
		);

		// 4. Make request.
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		// 5. Handle WP_Error.
		if ( is_wp_error( $response ) ) {
			return array();
		}

		// 6. Check HTTP status.
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return array();
		}

		// 7. Decode body.
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['urls']['regular'] ) ) {
			return array();
		}

		// 8. Build and return image array.
		$photo_url   = esc_url_raw( $body['urls']['regular'] );
		$alt         = sanitize_text_field( $body['alt_description'] ?? $keyword );
		$author_name = sanitize_text_field( $body['user']['name'] ?? '' );
		$attribution = $author_name
			? 'Photo by ' . $author_name . ' on Unsplash'
			: 'Photo from Unsplash';

		return array(
			'url'         => $photo_url,
			'alt'         => $alt,
			'attribution' => sanitize_text_field( $attribution ),
		);
	}

	/**
	 * Sideload a featured image from URL and set as post thumbnail.
	 *
	 * PRESERVED UNCHANGED from Sprint 2. Called only when featured_image
	 * array contains a non-empty 'url' key. In Sprint 7 this is never
	 * called because generate_content_pipeline() returns featured_image=[].
	 * Sprint 8: now called with result of fetch_unsplash_image().
	 *
	 * @param int   $post_id        WordPress post ID.
	 * @param array $featured_image Array with 'url' and optional 'alt' keys.
	 * @return bool True on success, false on failure.
	 */
	private function sideload_featured_image( int $post_id, array $featured_image ): bool {
		if ( empty( $featured_image['url'] ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$url         = esc_url_raw( $featured_image['url'] );
		$description = sanitize_text_field( $featured_image['alt'] ?? '' );
		$attachment_id = media_sideload_image( $url, $post_id, $description, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		set_post_thumbnail( $post_id, (int) $attachment_id );
		return true;
	}

	/**
	 * Assign categories and tags to the post from generated metadata.
	 *
	 * Creates terms if they do not exist (wp_set_post_categories handles this
	 * for categories; wp_set_post_tags creates tags automatically).
	 * Method signature PRESERVED unchanged from Sprint 2.
	 *
	 * @param int   $post_id  WordPress post ID.
	 * @param array $metadata Generated metadata array from pipeline.
	 * @return void
	 */
	private function assign_taxonomy_terms( int $post_id, array $metadata ): void {
		// Categories.
		if ( ! empty( $metadata['categories'] ) ) {
			$category_ids = array();
			foreach ( (array) $metadata['categories'] as $cat_name ) {
				$cat = get_term_by( 'slug', sanitize_title( $cat_name ), 'category' );
				if ( $cat ) {
					$category_ids[] = (int) $cat->term_id;
				} else {
					$new_cat = wp_insert_term( sanitize_text_field( $cat_name ), 'category' );
					if ( ! is_wp_error( $new_cat ) ) {
						$category_ids[] = (int) $new_cat['term_id'];
					}
				}
			}
			if ( ! empty( $category_ids ) ) {
				wp_set_post_categories( $post_id, $category_ids );
			}
		}

		// Tags.
		if ( ! empty( $metadata['tags'] ) ) {
			$clean_tags = array_map( 'sanitize_text_field', (array) $metadata['tags'] );
			wp_set_post_tags( $post_id, $clean_tags );
		}
	}
}
