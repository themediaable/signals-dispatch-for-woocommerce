<?php
/**
 * Dispatch rules controller.
 *
 * @package TMASD\Signals\Dispatch\Admin
 */

declare(strict_types=1);

namespace TMASD\Signals\Dispatch\Admin;

use TMASD\Signals\Dispatch\Database\MappingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatch rules page controller.
 *
 * Handles template mapping CRUD operations.
 * Single Responsibility: Dispatch rules page only.
 *
 * @final
 */
final class DispatchController extends AbstractAdminController {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	protected string $page_slug = 'tmasd-dispatch';

	/**
	 * Mapping repository.
	 *
	 * @var MappingRepository
	 */
	private MappingRepository $mapping_repo;

	/**
	 * Constructor.
	 *
	 * @param MappingRepository $mapping_repo Mapping repository.
	 */
	public function __construct( MappingRepository $mapping_repo ) {
		$this->mapping_repo = $mapping_repo;
	}

	/**
	 * Render the dispatch rules page.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->assert_access();

		$action     = $this->get_query_param( 'action', 'list' );
		$mapping_id = (int) $this->get_query_param( 'mapping_id', '0' );

		$this->render_notices();
		$this->render_page_header();

		if ( 'add' === $action || ( 'edit' === $action && $mapping_id > 0 ) ) {
			$mapping = $mapping_id > 0 ? $this->mapping_repo->find( $mapping_id ) : null;
			$this->render_mapping_form( $mapping );
		} else {
			$this->render_mapping_list();
		}

		echo '</div>';
	}

	/**
	 * Render page header.
	 *
	 * @return void
	 */
	private function render_page_header(): void {
		echo '<div class="wrap tmasd-admin">';
		echo '<h1 class="wp-heading-inline">';
		echo esc_html__( 'Dispatch Rules', 'signals-dispatch-woocommerce' );
		echo '</h1>';
		echo '<hr class="wp-header-end" />';
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	private function render_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['saved'] ) ) {
			$this->render_notice_success( __( 'Mapping saved.', 'signals-dispatch-woocommerce' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['deleted'] ) ) {
			$this->render_notice_success( __( 'Mapping deleted.', 'signals-dispatch-woocommerce' ) );
		}
	}

	/**
	 * Render mapping list.
	 *
	 * @return void
	 */
	private function render_mapping_list(): void {
		$mappings = $this->mapping_repo->all();

		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=tmasd-dispatch&action=add' ) ) . '" class="button button-primary">';
		echo esc_html__( 'Add New Mapping', 'signals-dispatch-woocommerce' );
		echo '</a></p>';

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'ID', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Event', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Template', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Language', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Enabled', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $mappings ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No mappings found.', 'signals-dispatch-woocommerce' ) . '</td></tr>';
		} else {
			foreach ( $mappings as $mapping ) {
				$this->render_mapping_row( $mapping );
			}
		}

		echo '</tbody></table>';
	}

	/**
	 * Render a single mapping row.
	 *
	 * @param array<string, mixed> $mapping Mapping data.
	 * @return void
	 */
	private function render_mapping_row( array $mapping ): void {
		$edit_url   = admin_url( 'admin.php?page=tmasd-dispatch&action=edit&mapping_id=' . $mapping['id'] );
		$delete_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=tmasd_delete_mapping&mapping_id=' . $mapping['id'] ),
			'tmasd_delete_mapping_' . $mapping['id']
		);

		$enabled_text = ! empty( $mapping['enabled'] )
			? __( 'Yes', 'signals-dispatch-woocommerce' )
			: __( 'No', 'signals-dispatch-woocommerce' );

		echo '<tr>';
		echo '<td>' . esc_html( (string) $mapping['id'] ) . '</td>';
		echo '<td>' . esc_html( (string) $mapping['event_key'] ) . '</td>';
		echo '<td>' . esc_html( (string) $mapping['template_name'] ) . '</td>';
		echo '<td>' . esc_html( (string) $mapping['language'] ) . '</td>';
		$badge_class = ! empty( $mapping['enabled'] ) ? 'tmasd-badge tmasd-badge--yes' : 'tmasd-badge tmasd-badge--no';
		echo '<td><span class="' . esc_attr( $badge_class ) . '">' . esc_html( $enabled_text ) . '</span></td>';
		echo '<td class="tmasd-row-actions">';
		echo '<a class="tmasd-action-edit" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'signals-dispatch-woocommerce' ) . '</a>';
		echo '<span class="tmasd-separator">|</span>';
		echo '<a class="tmasd-action-delete" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this mapping?', 'signals-dispatch-woocommerce' ) ) . '\')">';
		echo esc_html__( 'Delete', 'signals-dispatch-woocommerce' );
		echo '</a>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Render mapping form.
	 *
	 * @param array<string, mixed>|null $mapping Existing mapping data or null.
	 * @return void
	 */
	private function render_mapping_form( ?array $mapping ): void {
		$id            = $mapping['id'] ?? 0;
		$event_key     = $mapping['event_key'] ?? '';
		$template_name = $mapping['template_name'] ?? '';
		$language      = $mapping['language'] ?? 'en_US';
		$mapping_json  = $mapping['mapping_json'] ?? '[]';
		$enabled       = isset( $mapping['enabled'] ) ? (bool) $mapping['enabled'] : true;

		$events    = $this->mapping_repo->get_available_events();
		$variables = $this->mapping_repo->get_available_variables();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'tmasd_save_mapping' );
		echo '<input type="hidden" name="action" value="tmasd_save_mapping" />';
		echo '<input type="hidden" name="mapping_id" value="' . esc_attr( (string) $id ) . '" />';

		echo '<table class="form-table">';

		// Event select.
		echo '<tr><th scope="row"><label for="event_key">' . esc_html__( 'Event', 'signals-dispatch-woocommerce' ) . '</label></th>';
		echo '<td><select id="event_key" name="event_key">';
		foreach ( $events as $key => $label ) {
			$selected = $key === $event_key ? 'selected' : '';
			echo '<option value="' . esc_attr( $key ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		// Template name.
		echo '<tr><th scope="row"><label for="template_name">' . esc_html__( 'Template Name', 'signals-dispatch-woocommerce' ) . '</label></th>';
		echo '<td><input type="text" id="template_name" name="template_name" value="' . esc_attr( $template_name ) . '" class="regular-text" /></td></tr>';

		// Language.
		echo '<tr><th scope="row"><label for="language">' . esc_html__( 'Language', 'signals-dispatch-woocommerce' ) . '</label></th>';
		echo '<td><input type="text" id="language" name="language" value="' . esc_attr( $language ) . '" class="regular-text" /></td></tr>';

		// Mapping JSON.
		echo '<tr><th scope="row"><label for="mapping_json">' . esc_html__( 'Variable Mapping (JSON)', 'signals-dispatch-woocommerce' ) . '</label></th>';
		echo '<td><textarea id="mapping_json" name="mapping_json" rows="4" class="large-text">' . esc_textarea( $mapping_json ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Available variables:', 'signals-dispatch-woocommerce' ) . ' ';
		echo esc_html( implode( ', ', array_keys( $variables ) ) );
		echo '</p></td></tr>';

		// Enabled.
		$checked = $enabled ? 'checked' : '';
		echo '<tr><th scope="row">' . esc_html__( 'Enabled', 'signals-dispatch-woocommerce' ) . '</th>';
		echo '<td><label><input type="checkbox" name="enabled" value="1" ' . esc_attr( $checked ) . ' /> ';
		echo esc_html__( 'Enable this mapping', 'signals-dispatch-woocommerce' );
		echo '</label></td></tr>';

		echo '</table>';

		submit_button( $id > 0 ? __( 'Update Mapping', 'signals-dispatch-woocommerce' ) : __( 'Create Mapping', 'signals-dispatch-woocommerce' ) );
		echo '</form>';
	}

	/**
	 * Handle save mapping form submission.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		$this->assert_access();
		$this->verify_nonce( 'tmasd_save_mapping' );

		$mapping_id    = (int) $this->get_post_param( 'mapping_id', '0' );
		$event_key     = $this->get_post_param( 'event_key' );
		$template_name = $this->get_post_param( 'template_name' );
		$language      = $this->get_post_param( 'language', 'en_US' );
		$mapping_json  = $this->get_post_param( 'mapping_json', '[]' );

		// Validate event_key against allowed values.
		$allowed_events = array_keys( $this->mapping_repo->get_available_events() );
		if ( ! in_array( $event_key, $allowed_events, true ) ) {
			$this->redirect_with_status( 'tmasd-dispatch', 'error' );
			return;
		}

		// Validate template name format (alphanumeric, underscores, hyphens).
		if ( ! preg_match( '/^[a-z0-9_\-]{1,512}$/i', $template_name ) ) {
			$this->redirect_with_status( 'tmasd-dispatch', 'error' );
			return;
		}

		// Validate language code format (e.g. en_US, pt_BR).
		if ( ! preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $language ) ) {
			$this->redirect_with_status( 'tmasd-dispatch', 'error' );
			return;
		}

		// Validate mapping_json is a JSON array of allowed resolver keys only.
		$mapping_json = $this->validate_mapping_json( $mapping_json );

		$data = array(
			'event_key'     => $event_key,
			'template_name' => $template_name,
			'language'      => $language,
			'mapping_json'  => $mapping_json,
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			'enabled'       => isset( $_POST['enabled'] ) ? 1 : 0,
		);

		$this->mapping_repo->upsert( $data, $mapping_id );

		$this->redirect_with_status( 'tmasd-dispatch', 'saved' );
	}

	/**
	 * Sanitise and validate mapping_json — only allow known resolver keys.
	 *
	 * @param string $raw Raw JSON string from POST input.
	 * @return string Safe JSON string (array of resolver key strings).
	 */
	private function validate_mapping_json( string $raw ): string {
		$allowed = array_keys( $this->mapping_repo->get_available_variables() );
		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			return '[]';
		}

		// Only keep values that are scalar strings matching an allowed resolver key.
		$safe = array();
		foreach ( $decoded as $value ) {
			if ( is_string( $value ) && in_array( $value, $allowed, true ) ) {
				$safe[] = $value;
			}
		}

		$encoded = wp_json_encode( $safe );
		return $encoded ? $encoded : '[]';
	}

	/**
	 * Handle delete mapping.
	 *
	 * @return void
	 */
	public function handle_delete(): void {
		$this->assert_access();

		$mapping_id = (int) $this->get_query_param( 'mapping_id', '0' );

		if ( $mapping_id <= 0 ) {
			$this->redirect_with_status( 'tmasd-dispatch', 'error' );
			return;
		}

		check_admin_referer( 'tmasd_delete_mapping_' . $mapping_id );

		$this->mapping_repo->delete( $mapping_id );

		$this->redirect_with_status( 'tmasd-dispatch', 'deleted' );
	}
}
