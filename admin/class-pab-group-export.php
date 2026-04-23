<?php
defined( 'ABSPATH' ) || exit;

/**
 * JSON export for addon groups (admin list actions + download handler).
 */
class PAB_Group_Export {

	public const NONCE_ACTION = 'pab_export_groups';

	public function __construct() {
		add_action( 'admin_init', [ $this, 'maybe_send_export' ], 1 );
		add_action( 'load-edit.php', [ $this, 'register_list_hooks' ] );
	}

	public function register_list_hooks(): void {
		$screen = get_current_screen();
		if ( ! $screen || PAB_Group_Resolver::GROUP_POST_TYPE !== $screen->post_type ) {
			return;
		}

		add_filter( 'bulk_actions-' . $screen->id, [ $this, 'register_bulk_action' ] );
		add_filter( 'handle_bulk_actions-' . $screen->id, [ $this, 'handle_bulk_export' ], 10, 3 );
		add_filter( 'post_row_actions', [ $this, 'register_row_action' ], 10, 2 );
		add_action( 'restrict_manage_posts', [ $this, 'render_export_all_button' ], 20, 2 );
	}

	private function user_can_export(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * @param int[] $ids
	 */
	private function user_can_export_ids( array $ids ): bool {
		if ( ! $this->user_can_export() ) {
			return false;
		}
		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				return false;
			}
		}
		return true;
	}

	public function maybe_send_export(): void {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below
		if ( empty( $_GET['page'] ) || 'pab-settings' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['pab_action'] ) || 'export_addon_groups' !== sanitize_key( wp_unslash( $_GET['pab_action'] ) ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid export link.', 'pab' ), '', [ 'response' => 403 ] );
		}

		if ( ! $this->user_can_export() ) {
			wp_die( esc_html__( 'You do not have permission to export addon groups.', 'pab' ), '', [ 'response' => 403 ] );
		}

		$ids = [];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['ids'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw  = sanitize_text_field( wp_unslash( $_GET['ids'] ) );
			$ids  = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) ) );
		}

		if ( ! empty( $ids ) && ! $this->user_can_export_ids( $ids ) ) {
			wp_die( esc_html__( 'You do not have permission to export one or more of these groups.', 'pab' ), '', [ 'response' => 403 ] );
		}

		$payload_groups = [];

		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				$g = PAB_Group_Resolver::get_group( $id );
				if ( $g ) {
					$payload_groups[] = $this->enrich_group_for_export( $g );
				}
			}
		} else {
			foreach ( PAB_Group_Resolver::get_all_groups() as $g ) {
				$gid = (int) ( $g['id'] ?? 0 );
				if ( $gid && current_user_can( 'edit_post', $gid ) ) {
					$payload_groups[] = $this->enrich_group_for_export( $g );
				}
			}
		}

		$document = [
			'schema_version' => '1.0',
			'plugin_version' => PAB_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'site_url'       => home_url(),
			'groups'         => $payload_groups,
		];

		$filename = 'pab-addon-groups-' . gmdate( 'Y-m-d-His' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo wp_json_encode( $document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * @param array<string,mixed> $g
	 * @return array<string,mixed>
	 */
	private function enrich_group_for_export( array $g ): array {
		$g['export_id'] = (int) ( $g['id'] ?? 0 );
		return $g;
	}

	/**
	 * @param array<string,string> $actions
	 * @return array<string,string>
	 */
	public function register_bulk_action( array $actions ): array {
		if ( $this->user_can_export() ) {
			$actions['pab_export_groups'] = __( 'Export', 'pab' );
		}
		return $actions;
	}

	/**
	 * @param string $redirect_url
	 * @param string $action
	 * @param int[]  $post_ids
	 * @return string
	 */
	public function handle_bulk_export( $redirect_url, $action, $post_ids ) {
		if ( 'pab_export_groups' !== $action ) {
			return $redirect_url;
		}

		if ( ! $this->user_can_export() ) {
			return $redirect_url;
		}

		$post_ids = array_values( array_unique( array_map( 'absint', (array) $post_ids ) ) );
		$post_ids = array_values( array_filter( $post_ids ) );

		foreach ( $post_ids as $pid ) {
			if ( PAB_Group_Resolver::GROUP_POST_TYPE !== get_post_type( $pid ) || ! current_user_can( 'edit_post', $pid ) ) {
				return $redirect_url;
			}
		}

		if ( empty( $post_ids ) ) {
			return $redirect_url;
		}

		$url = add_query_arg(
			[
				'page'       => 'pab-settings',
				'pab_action' => 'export_addon_groups',
				'_wpnonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'ids'        => implode( ',', $post_ids ),
			],
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * @param array<string,string> $actions
	 * @param WP_Post               $post
	 * @return array<string,string>
	 */
	public function register_row_action( array $actions, $post ): array {
		if ( ! $post instanceof WP_Post || PAB_Group_Resolver::GROUP_POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		if ( ! $this->user_can_export() || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = add_query_arg(
			[
				'page'       => 'pab-settings',
				'pab_action' => 'export_addon_groups',
				'_wpnonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'ids'        => (string) (int) $post->ID,
			],
			admin_url( 'admin.php' )
		);

		$actions['pab_export'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Export', 'pab' ) . '</a>';

		return $actions;
	}

	/**
	 * @param string $post_type
	 */
	public function render_export_all_button( $post_type ): void {
		if ( PAB_Group_Resolver::GROUP_POST_TYPE !== $post_type || ! $this->user_can_export() ) {
			return;
		}

		$url = add_query_arg(
			[
				'page'       => 'pab-settings',
				'pab_action' => 'export_addon_groups',
				'_wpnonce'   => wp_create_nonce( self::NONCE_ACTION ),
			],
			admin_url( 'admin.php' )
		);

		echo '<a href="' . esc_url( $url ) . '" class="button">' . esc_html__( 'Export all groups', 'pab' ) . '</a> ';
		echo '<span class="description">' . esc_html__( 'Product and taxonomy IDs in the file are site-specific.', 'pab' ) . '</span>';
	}
}
