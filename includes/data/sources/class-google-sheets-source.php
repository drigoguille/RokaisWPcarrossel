<?php
/**
 * Fonte de dados Google Sheets (CSV público, gviz JSON ou Sheets API v4).
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Data\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Lê uma planilha Google. O cabeçalho (primeira linha) vira a lista de colunas
 * mapeáveis.
 */
class Google_Sheets_Source extends Abstract_Source {

	/**
	 * {@inheritDoc}
	 */
	public function get_type() {
		return 'google_sheets';
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate() {
		if ( empty( $this->config['sheet_id'] ) ) {
			return new \WP_Error( 'skpc_no_sheet', __( 'Informe o ID da planilha.', 'sk-price-carousel' ) );
		}
		$mode = isset( $this->config['mode'] ) ? $this->config['mode'] : 'csv';
		if ( 'api' === $mode && empty( $this->config['api_key'] ) ) {
			return new \WP_Error( 'skpc_no_key', __( 'A chave de API é obrigatória no modo Sheets API v4.', 'sk-price-carousel' ) );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function fetch_rows() {
		$valid = $this->validate();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$mode = isset( $this->config['mode'] ) ? $this->config['mode'] : 'csv';
		switch ( $mode ) {
			case 'gviz':
				return $this->fetch_gviz();
			case 'api':
				return $this->fetch_api();
			case 'csv':
			default:
				return $this->fetch_csv();
		}
	}

	/**
	 * Lista os cabeçalhos (colunas) da planilha, para o mapeamento no admin.
	 *
	 * @return array|\WP_Error
	 */
	public function get_columns() {
		$rows = $this->fetch_rows();
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		if ( empty( $rows ) ) {
			return array();
		}
		return array_keys( (array) $rows[0] );
	}

	/**
	 * Modo CSV (planilha publicada/pública).
	 *
	 * @return array|\WP_Error
	 */
	private function fetch_csv() {
		$url  = sprintf(
			'https://docs.google.com/spreadsheets/d/%s/export?format=csv&gid=%s',
			rawurlencode( $this->config['sheet_id'] ),
			rawurlencode( isset( $this->config['gid'] ) ? $this->config['gid'] : '0' )
		);
		$body = $this->http_get( $url );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		return $this->parse_csv( $body );
	}

	/**
	 * Modo gviz (JSON com wrapper JS).
	 *
	 * @return array|\WP_Error
	 */
	private function fetch_gviz() {
		$url  = sprintf(
			'https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:json&gid=%s',
			rawurlencode( $this->config['sheet_id'] ),
			rawurlencode( isset( $this->config['gid'] ) ? $this->config['gid'] : '0' )
		);
		$body = $this->http_get( $url );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$start = strpos( $body, '{' );
		$end   = strrpos( $body, '}' );
		if ( false === $start || false === $end || $end < $start ) {
			return new \WP_Error( 'skpc_parse', __( 'Resposta do Google Sheets (gviz) em formato inesperado.', 'sk-price-carousel' ) );
		}
		$json = substr( $body, $start, $end - $start + 1 );
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || empty( $data['table']['cols'] ) ) {
			return new \WP_Error( 'skpc_parse', __( 'Não foi possível ler a tabela do Google Sheets.', 'sk-price-carousel' ) );
		}

		$cols = array();
		foreach ( $data['table']['cols'] as $i => $col ) {
			$label       = ( isset( $col['label'] ) && '' !== $col['label'] ) ? $col['label'] : ( isset( $col['id'] ) ? $col['id'] : 'col' . $i );
			$cols[ $i ]  = (string) $label;
		}

		$rows = array();
		foreach ( (array) $data['table']['rows'] as $r ) {
			if ( empty( $r['c'] ) ) {
				continue;
			}
			$assoc = array();
			foreach ( $r['c'] as $i => $cell ) {
				if ( ! isset( $cols[ $i ] ) ) {
					continue;
				}
				$value = '';
				if ( is_array( $cell ) ) {
					if ( isset( $cell['f'] ) ) {
						$value = $cell['f'];
					} elseif ( array_key_exists( 'v', $cell ) && null !== $cell['v'] ) {
						$value = $cell['v'];
					}
				}
				$assoc[ $cols[ $i ] ] = $value;
			}
			$rows[] = $assoc;
		}
		return $rows;
	}

	/**
	 * Modo Sheets API v4 (com chave de API).
	 *
	 * @return array|\WP_Error
	 */
	private function fetch_api() {
		$range = isset( $this->config['range'] ) && '' !== $this->config['range'] ? $this->config['range'] : 'A1:Z1000';
		$url   = sprintf(
			'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?key=%s',
			rawurlencode( $this->config['sheet_id'] ),
			rawurlencode( $range ),
			rawurlencode( $this->config['api_key'] )
		);
		$body = $this->http_get( $url );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['values'] ) ) {
			return new \WP_Error( 'skpc_parse', __( 'A Sheets API não retornou valores.', 'sk-price-carousel' ) );
		}
		return $this->matrix_to_rows( $data['values'] );
	}

	/**
	 * Converte CSV (texto) em linhas associativas pelo cabeçalho.
	 *
	 * @param string $body Conteúdo CSV.
	 * @return array|\WP_Error
	 */
	private function parse_csv( $body ) {
		// Remove o BOM UTF-8 que o export do Google costuma incluir, senão o nome
		// da primeira coluna vira "\xEF\xBB\xBFColuna" e não casa com o mapeamento.
		$body = preg_replace( '/^\xEF\xBB\xBF/', '', $body );

		$handle = fopen( 'php://temp', 'r+' );
		if ( false === $handle ) {
			return new \WP_Error( 'skpc_csv', __( 'Falha ao processar o CSV.', 'sk-price-carousel' ) );
		}
		fwrite( $handle, $body );
		rewind( $handle );

		$matrix = array();
		while ( ( $row = fgetcsv( $handle ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$matrix[] = $row;
		}
		fclose( $handle );

		return $this->matrix_to_rows( $matrix );
	}

	/**
	 * Converte uma matriz (primeira linha = cabeçalho) em linhas associativas.
	 *
	 * @param array $matrix Matriz de valores.
	 * @return array
	 */
	private function matrix_to_rows( array $matrix ) {
		if ( empty( $matrix ) ) {
			return array();
		}
		$header = array_map( 'strval', array_shift( $matrix ) );
		$rows   = array();
		foreach ( $matrix as $line ) {
			$assoc = array();
			foreach ( $header as $i => $name ) {
				$assoc[ $name ] = isset( $line[ $i ] ) ? $line[ $i ] : '';
			}
			$rows[] = $assoc;
		}
		return $rows;
	}
}
