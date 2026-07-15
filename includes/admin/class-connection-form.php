<?php
/**
 * Formulário de criação/edição de conexão.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Admin;

use SKPriceCarousel\Plugin;
use SKPriceCarousel\Data\Connection_Repository;
use SKPriceCarousel\Data\Item_Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Renderiza o formulário com o seletor de tipo, os grupos de campos dinâmicos
 * (Sheets/JSON/MySQL) e a tabela de mapeamento de colunas.
 */
class Connection_Form {

	/**
	 * Renderiza o formulário.
	 *
	 * @param array|null $connection Conexão em edição, ou null para nova.
	 * @return void
	 */
	public function render( $connection ) {
		$is_edit  = is_array( $connection );
		$id       = $is_edit ? $connection['id'] : '';
		$label    = $is_edit ? $connection['label'] : '';
		$type     = $is_edit ? $connection['type'] : 'google_sheets';
		$ttl      = $is_edit ? (int) $connection['ttl'] : (int) Plugin::instance()->settings()['default_ttl'];
		$config   = ( $is_edit && isset( $connection['config'] ) ) ? $connection['config'] : array();
		$mapping  = ( $is_edit && isset( $connection['mapping'] ) ) ? $connection['mapping'] : array();
		$repo     = Plugin::instance()->connections();
		$back_url = add_query_arg( array( 'page' => 'skpc-connections' ), admin_url( 'admin.php' ) );
		?>
		<div class="wrap skpc-admin skpc-form">
			<div class="skpc-logo-header"><img class="skpc-logo" src="<?php echo esc_url( SKPC_ASSETS_URL . 'img/logo.png' ); ?>" alt="<?php esc_attr_e( 'Rokais Carrossel WP', 'sk-price-carousel' ); ?>"></div>
			<h1><?php echo $is_edit ? esc_html__( 'Editar conexão', 'sk-price-carousel' ) : esc_html__( 'Adicionar conexão', 'sk-price-carousel' ); ?></h1>
			<a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Voltar às conexões', 'sk-price-carousel' ); ?></a>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="skpc-connection-form" id="skpc-connection-form">
				<input type="hidden" name="action" value="skpc_save_connection">
				<input type="hidden" name="connection_id" value="<?php echo esc_attr( $id ); ?>">
				<?php wp_nonce_field( 'skpc_save_connection' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="skpc-label"><?php esc_html_e( 'Nome da conexão', 'sk-price-carousel' ); ?></label></th>
						<td><input name="label" id="skpc-label" type="text" class="regular-text" required value="<?php echo esc_attr( $label ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="skpc-type"><?php esc_html_e( 'Tipo de fonte', 'sk-price-carousel' ); ?></label></th>
						<td>
							<select name="type" id="skpc-type" class="skpc-type-select">
								<?php foreach ( Connection_Repository::types() as $value => $tlabel ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $tlabel ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="skpc-ttl"><?php esc_html_e( 'Atualizar a cada (segundos)', 'sk-price-carousel' ); ?></label></th>
						<td>
							<input name="ttl" id="skpc-ttl" type="number" min="60" step="30" class="small-text" value="<?php echo esc_attr( $ttl ); ?>">
							<p class="description"><?php esc_html_e( 'Intervalo do cache no servidor (WP-Cron). Mínimo de 60s.', 'sk-price-carousel' ); ?></p>
						</td>
					</tr>
				</table>

				<?php $this->render_group_sheets( $config ); ?>
				<?php $this->render_group_json( $config ); ?>
				<?php $this->render_group_mysql( $config, $repo, $id ); ?>

				<h2 class="title"><?php esc_html_e( 'Mapeamento de colunas', 'sk-price-carousel' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Associe cada campo do item à coluna/caminho da sua fonte. Use "Detectar colunas" para preencher as sugestões.', 'sk-price-carousel' ); ?>
					<button type="button" class="button skpc-detect" id="skpc-detect-columns"><?php esc_html_e( 'Detectar colunas', 'sk-price-carousel' ); ?></button>
				</p>
				<datalist id="skpc-columns-list"></datalist>
				<table class="form-table skpc-mapping" role="presentation">
					<?php foreach ( Item_Schema::field_labels() as $field => $flabel ) : ?>
						<tr>
							<th scope="row"><label for="skpc-map-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $flabel ); ?></label></th>
							<td>
								<input type="text" list="skpc-columns-list"
									name="mapping[<?php echo esc_attr( $field ); ?>]"
									id="skpc-map-<?php echo esc_attr( $field ); ?>"
									class="regular-text"
									value="<?php echo esc_attr( isset( $mapping[ $field ] ) ? $mapping[ $field ] : '' ); ?>">
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<p class="submit skpc-actions">
					<button type="button" class="button" id="skpc-test-connection"><?php esc_html_e( 'Testar conexão', 'sk-price-carousel' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Salvar conexão', 'sk-price-carousel' ); ?></button>
				</p>

				<div id="skpc-test-result" class="skpc-test-result" hidden></div>
			</form>
		</div>
		<?php
	}

	/**
	 * Grupo de campos do Google Sheets.
	 *
	 * @param array $config Config atual.
	 * @return void
	 */
	private function render_group_sheets( $config ) {
		$mode = isset( $config['mode'] ) ? $config['mode'] : 'csv';
		?>
		<div class="skpc-group" data-skpc-type="google_sheets">
			<h2 class="title"><?php esc_html_e( 'Google Sheets', 'sk-price-carousel' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="skpc-gs-mode"><?php esc_html_e( 'Modo de leitura', 'sk-price-carousel' ); ?></label></th>
					<td>
						<select name="config[mode]" id="skpc-gs-mode" class="skpc-gs-mode">
							<option value="csv" <?php selected( $mode, 'csv' ); ?>><?php esc_html_e( 'CSV público (recomendado)', 'sk-price-carousel' ); ?></option>
							<option value="gviz" <?php selected( $mode, 'gviz' ); ?>><?php esc_html_e( 'gviz JSON', 'sk-price-carousel' ); ?></option>
							<option value="api" <?php selected( $mode, 'api' ); ?>><?php esc_html_e( 'Sheets API v4 (com chave)', 'sk-price-carousel' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Para CSV/gviz, a planilha precisa estar compartilhada como "qualquer pessoa com o link".', 'sk-price-carousel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="skpc-gs-id"><?php esc_html_e( 'ID da planilha', 'sk-price-carousel' ); ?></label></th>
					<td>
						<input type="text" name="config[sheet_id]" id="skpc-gs-id" class="regular-text" value="<?php echo esc_attr( isset( $config['sheet_id'] ) ? $config['sheet_id'] : '' ); ?>">
						<p class="description"><?php esc_html_e( 'A parte entre /d/ e /edit na URL da planilha.', 'sk-price-carousel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="skpc-gs-gid"><?php esc_html_e( 'gid da aba', 'sk-price-carousel' ); ?></label></th>
					<td><input type="text" name="config[gid]" id="skpc-gs-gid" class="small-text" value="<?php echo esc_attr( isset( $config['gid'] ) ? $config['gid'] : '0' ); ?>"></td>
				</tr>
				<tr class="skpc-gs-api-only">
					<th scope="row"><label for="skpc-gs-range"><?php esc_html_e( 'Intervalo (range)', 'sk-price-carousel' ); ?></label></th>
					<td><input type="text" name="config[range]" id="skpc-gs-range" class="regular-text" value="<?php echo esc_attr( isset( $config['range'] ) ? $config['range'] : 'A1:Z1000' ); ?>"></td>
				</tr>
				<tr class="skpc-gs-api-only">
					<th scope="row"><label for="skpc-gs-key"><?php esc_html_e( 'Chave de API', 'sk-price-carousel' ); ?></label></th>
					<td><input type="password" name="config[api_key]" id="skpc-gs-key" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $this->secret_placeholder() ); ?>"></td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Grupo de campos do JSON.
	 *
	 * @param array $config Config atual.
	 * @return void
	 */
	private function render_group_json( $config ) {
		?>
		<div class="skpc-group" data-skpc-type="json">
			<h2 class="title"><?php esc_html_e( 'Link JSON', 'sk-price-carousel' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="skpc-json-url"><?php esc_html_e( 'URL do JSON', 'sk-price-carousel' ); ?></label></th>
					<td><input type="url" name="config[url]" id="skpc-json-url" class="large-text code" value="<?php echo esc_attr( isset( $config['url'] ) ? $config['url'] : '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="skpc-json-root"><?php esc_html_e( 'Caminho da lista (root path)', 'sk-price-carousel' ); ?></label></th>
					<td>
						<input type="text" name="config[root_path]" id="skpc-json-root" class="regular-text code" value="<?php echo esc_attr( isset( $config['root_path'] ) ? $config['root_path'] : '' ); ?>" placeholder="data.items">
						<p class="description"><?php esc_html_e( 'Use notação com pontos até o array de itens. Ex.: data.items. Deixe em branco se a raiz já for uma lista.', 'sk-price-carousel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="skpc-json-token"><?php esc_html_e( 'Token (Bearer) opcional', 'sk-price-carousel' ); ?></label></th>
					<td><input type="password" name="config[auth_token]" id="skpc-json-token" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $this->secret_placeholder() ); ?>"></td>
				</tr>
			</table>
			<p class="description"><?php esc_html_e( 'No mapeamento, use caminhos relativos a cada item (ex.: images.0.src, price.amount).', 'sk-price-carousel' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Grupo de campos do MySQL.
	 *
	 * @param array                 $config Config atual.
	 * @param Connection_Repository $repo   Repositório (para checar segredo salvo).
	 * @param string                $id     Id da conexão (edição).
	 * @return void
	 */
	private function render_group_mysql( $config, $repo, $id ) {
		$has_pw = ( '' !== $id ) && $repo->has_secret( $id, 'password' );
		?>
		<div class="skpc-group" data-skpc-type="mysql">
			<h2 class="title"><?php esc_html_e( 'MySQL externo', 'sk-price-carousel' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="skpc-my-host"><?php esc_html_e( 'Host', 'sk-price-carousel' ); ?></label></th>
					<td><input type="text" name="config[host]" id="skpc-my-host" class="regular-text" value="<?php echo esc_attr( isset( $config['host'] ) ? $config['host'] : '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="skpc-my-port"><?php esc_html_e( 'Porta', 'sk-price-carousel' ); ?></label></th>
					<td><input type="number" name="config[port]" id="skpc-my-port" class="small-text" value="<?php echo esc_attr( isset( $config['port'] ) ? $config['port'] : 3306 ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="skpc-my-db"><?php esc_html_e( 'Banco de dados', 'sk-price-carousel' ); ?></label></th>
					<td><input type="text" name="config[database]" id="skpc-my-db" class="regular-text" value="<?php echo esc_attr( isset( $config['database'] ) ? $config['database'] : '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="skpc-my-user"><?php esc_html_e( 'Usuário', 'sk-price-carousel' ); ?></label></th>
					<td><input type="text" name="config[username]" id="skpc-my-user" class="regular-text" autocomplete="off" value="<?php echo esc_attr( isset( $config['username'] ) ? $config['username'] : '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="skpc-my-pass"><?php esc_html_e( 'Senha', 'sk-price-carousel' ); ?></label></th>
					<td>
						<input type="password" name="config[password]" id="skpc-my-pass" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_pw ? $this->secret_placeholder() : '' ); ?>">
						<?php if ( $has_pw ) : ?>
							<p class="description"><?php esc_html_e( 'Deixe em branco para manter a senha atual.', 'sk-price-carousel' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'SSL', 'sk-price-carousel' ); ?></th>
					<td><label><input type="checkbox" name="config[use_ssl]" value="1" <?php checked( ! empty( $config['use_ssl'] ) ); ?>> <?php esc_html_e( 'Usar conexão SSL', 'sk-price-carousel' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="skpc-my-table"><?php esc_html_e( 'Tabela', 'sk-price-carousel' ); ?></label></th>
					<td>
						<input type="text" name="config[table]" id="skpc-my-table" list="skpc-tables-list" class="regular-text" value="<?php echo esc_attr( isset( $config['table'] ) ? $config['table'] : '' ); ?>">
						<button type="button" class="button" id="skpc-list-tables"><?php esc_html_e( 'Listar tabelas', 'sk-price-carousel' ); ?></button>
						<datalist id="skpc-tables-list"></datalist>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="skpc-my-limit"><?php esc_html_e( 'Limite de linhas', 'sk-price-carousel' ); ?></label></th>
					<td><input type="number" name="config[limit]" id="skpc-my-limit" class="small-text" min="1" max="200" value="<?php echo esc_attr( isset( $config['limit'] ) ? $config['limit'] : 100 ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="skpc-my-order"><?php esc_html_e( 'Ordenar por', 'sk-price-carousel' ); ?></label></th>
					<td>
						<input type="text" name="config[order_by]" id="skpc-my-order" list="skpc-columns-list" class="regular-text" value="<?php echo esc_attr( isset( $config['order_by'] ) ? $config['order_by'] : '' ); ?>">
						<select name="config[order_dir]">
							<option value="ASC" <?php selected( isset( $config['order_dir'] ) ? $config['order_dir'] : 'ASC', 'ASC' ); ?>>ASC</option>
							<option value="DESC" <?php selected( isset( $config['order_dir'] ) ? $config['order_dir'] : 'ASC', 'DESC' ); ?>>DESC</option>
						</select>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Placeholder mascarado para campos de segredo já preenchidos.
	 *
	 * @return string
	 */
	private function secret_placeholder() {
		return '••••••••';
	}
}
