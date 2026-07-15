<?php
/**
 * Menu administrativo e roteamento das telas do plugin.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Admin;

use SKPriceCarousel\Plugin;
use SKPriceCarousel\Requirements;

defined( 'ABSPATH' ) || exit;

/**
 * Registra o menu, decide entre listagem e formulário e renderiza a tela de
 * configurações globais.
 */
class Admin_Menu {

	const CAP  = 'manage_options';
	const SLUG = 'skpc-connections';

	/**
	 * Engancha os hooks de admin.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Registra o menu e submenus.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Rokais Carrossel WP', 'sk-price-carousel' ),
			__( 'Rokais Carrossel', 'sk-price-carousel' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render_router' ),
			$this->menu_icon(),
			58
		);

		add_submenu_page(
			self::SLUG,
			__( 'Conexões', 'sk-price-carousel' ),
			__( 'Conexões', 'sk-price-carousel' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render_router' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Configurações', 'sk-price-carousel' ),
			__( 'Configurações', 'sk-price-carousel' ),
			self::CAP,
			'skpc-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Ícone do menu como SVG (data URI), que o WordPress dimensiona para 20px e
	 * centraliza igual aos dashicons nativos. Fallback para um dashicon.
	 *
	 * @return string
	 */
	private function menu_icon() {
		$svg = @file_get_contents( SKPC_PATH . 'assets/img/menu-icon.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $svg ) {
			return 'dashicons-images-alt2';
		}
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Registra as configurações globais (Settings API).
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'skpc_settings_group',
			'skpc_settings',
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	/**
	 * Sanitiza as configurações globais.
	 *
	 * @param array $input Valores enviados.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'default_ttl'                => max( 60, absint( isset( $input['default_ttl'] ) ? $input['default_ttl'] : 900 ) ),
			'default_live_interval'      => max( 15, absint( isset( $input['default_live_interval'] ) ? $input['default_live_interval'] : 60 ) ),
			'preserve_data_on_uninstall' => ! empty( $input['preserve_data_on_uninstall'] ),
		);
	}

	/**
	 * Decide entre listagem e formulário conforme ?action.
	 *
	 * @return void
	 */
	public function render_router() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'edit' === $action || 'add' === $action ) {
			$id         = isset( $_GET['connection'] ) ? sanitize_key( wp_unslash( $_GET['connection'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$connection = ( '' !== $id ) ? Plugin::instance()->connections()->get( $id ) : null;
			( new Connection_Form() )->render( $connection );
			return;
		}

		$this->render_list();
	}

	/**
	 * Renderiza a listagem de conexões.
	 *
	 * @return void
	 */
	private function render_list() {
		$table = new Connections_List_Table();
		$table->prepare_items();

		$add_url = add_query_arg(
			array(
				'page'   => self::SLUG,
				'action' => 'add',
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap skpc-admin">
			<?php $this->render_logo(); ?>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Conexões de dados', 'sk-price-carousel' ); ?></h1>
			<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Adicionar conexão', 'sk-price-carousel' ); ?></a>
			<hr class="wp-header-end">
			<?php $this->render_notice(); ?>
			<p class="description">
				<?php esc_html_e( 'Cadastre aqui suas fontes de dados. Depois, escolha a conexão no widget "Carrossel de Preços" dentro do Elementor.', 'sk-price-carousel' ); ?>
			</p>
			<form method="post">
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renderiza a tela de configurações globais.
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$settings = Plugin::instance()->settings();
		?>
		<div class="wrap skpc-admin">
			<?php $this->render_logo(); ?>
			<h1><?php esc_html_e( 'Configurações — Rokais Carrossel WP', 'sk-price-carousel' ); ?></h1>

			<h2 class="title"><?php esc_html_e( 'Ambiente', 'sk-price-carousel' ); ?></h2>
			<table class="widefat striped" style="max-width:640px">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'OpenSSL (cifragem de credenciais)', 'sk-price-carousel' ); ?></td>
						<td><?php echo $this->status_badge( Requirements::has_openssl() ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'mysqli (fonte MySQL)', 'sk-price-carousel' ); ?></td>
						<td><?php echo $this->status_badge( Requirements::has_mysqli() ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Elementor compatível', 'sk-price-carousel' ); ?></td>
						<td><?php echo $this->status_badge( Requirements::is_elementor_compatible() ); ?></td>
					</tr>
				</tbody>
			</table>

			<form action="options.php" method="post">
				<?php settings_fields( 'skpc_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="skpc_default_ttl"><?php esc_html_e( 'Intervalo de atualização padrão (segundos)', 'sk-price-carousel' ); ?></label></th>
						<td>
							<input name="skpc_settings[default_ttl]" id="skpc_default_ttl" type="number" min="60" step="30"
								value="<?php echo esc_attr( $settings['default_ttl'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Usado como sugestão ao criar novas conexões. Mínimo de 60s.', 'sk-price-carousel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="skpc_live_interval"><?php esc_html_e( 'Intervalo do refresh ao vivo (segundos)', 'sk-price-carousel' ); ?></label></th>
						<td>
							<input name="skpc_settings[default_live_interval]" id="skpc_live_interval" type="number" min="15" step="5"
								value="<?php echo esc_attr( $settings['default_live_interval'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Frequência com que o navegador re-busca os itens no widget. Mínimo de 15s.', 'sk-price-carousel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ao desinstalar', 'sk-price-carousel' ); ?></th>
						<td>
							<label>
								<input name="skpc_settings[preserve_data_on_uninstall]" type="checkbox" value="1" <?php checked( ! empty( $settings['preserve_data_on_uninstall'] ) ); ?>>
								<?php esc_html_e( 'Preservar conexões e configurações ao remover o plugin.', 'sk-price-carousel' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2 class="title"><?php esc_html_e( 'Atualização automática (WP-Cron)', 'sk-price-carousel' ); ?></h2>
			<p class="description" style="max-width:640px">
				<?php esc_html_e( 'O WP-Cron depende de visitas ao site. Em sites de baixo tráfego, para uma atualização pontual, recomenda-se definir DISABLE_WP_CRON como true no wp-config.php e configurar um cron real do servidor chamando wp-cron.php no intervalo desejado.', 'sk-price-carousel' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Renderiza o cabeçalho com o logo do plugin.
	 *
	 * @return void
	 */
	private function render_logo() {
		printf(
			'<div class="skpc-logo-header"><img class="skpc-logo" src="%s" alt="%s"></div>',
			esc_url( SKPC_ASSETS_URL . 'img/logo.png' ),
			esc_attr__( 'Rokais Carrossel WP', 'sk-price-carousel' )
		);
	}

	/**
	 * Renderiza o aviso de resultado (salvo/excluído/erro).
	 *
	 * @return void
	 */
	private function render_notice() {
		$notice = isset( $_GET['skpc_notice'] ) ? sanitize_key( wp_unslash( $_GET['skpc_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $notice ) {
			return;
		}
		$map = array(
			'saved'   => array( 'success', __( 'Conexão salva com sucesso.', 'sk-price-carousel' ) ),
			'deleted' => array( 'success', __( 'Conexão removida.', 'sk-price-carousel' ) ),
			'error'   => array( 'error', __( 'Não foi possível salvar a conexão. Verifique os campos.', 'sk-price-carousel' ) ),
		);
		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $map[ $notice ][0] ),
			esc_html( $map[ $notice ][1] )
		);
	}

	/**
	 * Retorna um selo de status (sim/não) escapado.
	 *
	 * @param bool $ok Estado.
	 * @return string
	 */
	private function status_badge( $ok ) {
		if ( $ok ) {
			return '<span class="skpc-badge skpc-badge--ok">' . esc_html__( 'Disponível', 'sk-price-carousel' ) . '</span>';
		}
		return '<span class="skpc-badge skpc-badge--err">' . esc_html__( 'Indisponível', 'sk-price-carousel' ) . '</span>';
	}
}
