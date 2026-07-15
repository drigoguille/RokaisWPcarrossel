/**
 * Admin do SK Carrossel de Preços.
 *
 * Alterna os grupos de campos por tipo de fonte e conversa com os handlers AJAX
 * (testar conexão, atualizar agora, detectar colunas, listar tabelas).
 */
( function ( $ ) {
	'use strict';

	var cfg = window.SKPCAdmin || {};

	function collectForm( $form ) {
		// Serializa apenas os campos relevantes (type, config[*], mapping[*], id).
		var data = {
			action: '',
			nonce: cfg.nonce,
			connection_id: $form.find( '[name="connection_id"]' ).val() || '',
			type: $form.find( '[name="type"]' ).val() || ''
		};
		$form.find( '[name^="config["], [name^="mapping["]' ).each( function () {
			var el = this;
			if ( el.type === 'checkbox' && ! el.checked ) {
				return;
			}
			data[ el.name ] = $( el ).val();
		} );
		return data;
	}

	function toggleGroups() {
		var type = $( '#skpc-type' ).val();
		$( '.skpc-group' ).each( function () {
			var $g = $( this );
			$g.toggle( $g.data( 'skpc-type' ) === type );
		} );
		toggleSheetsApi();
	}

	function toggleSheetsApi() {
		var isApi = $( '#skpc-gs-mode' ).val() === 'api';
		$( '.skpc-gs-api-only' ).toggle( isApi );
	}

	function setBusy( $btn, busyText ) {
		$btn.data( 'label', $btn.text() ).prop( 'disabled', true ).text( busyText );
	}

	function clearBusy( $btn ) {
		$btn.prop( 'disabled', false ).text( $btn.data( 'label' ) );
	}

	function ajax( action, data ) {
		data.action = action;
		data.nonce = cfg.nonce;
		return $.post( cfg.ajaxUrl, data );
	}

	// --- Testar conexão -----------------------------------------------------
	function onTest() {
		var $btn = $( this );
		var $form = $( '#skpc-connection-form' );
		var $result = $( '#skpc-test-result' );
		setBusy( $btn, cfg.i18n.testing );
		$result.removeAttr( 'hidden' ).html( '<p>' + cfg.i18n.testing + '</p>' );

		ajax( 'skpc_test_connection', collectForm( $form ) )
			.done( function ( res ) {
				if ( ! res || ! res.success ) {
					$result.html( '<div class="notice notice-error inline"><p>' + escapeHtml( ( res && res.data && res.data.message ) || cfg.i18n.error ) + '</p></div>' );
					return;
				}
				renderPreview( $result, res.data );
			} )
			.fail( function () {
				$result.html( '<div class="notice notice-error inline"><p>' + escapeHtml( cfg.i18n.error ) + '</p></div>' );
			} )
			.always( function () {
				clearBusy( $btn );
			} );
	}

	function renderPreview( $result, data ) {
		var html = '<div class="notice notice-success inline"><p>' + escapeHtml( data.message ) + '</p></div>';
		if ( data.items && data.items.length ) {
			html += '<div class="skpc-preview">';
			data.items.forEach( function ( item ) {
				html += '<div class="skpc-preview__card">';
				if ( item.image ) {
					html += '<img src="' + escapeAttr( item.image ) + '" alt="">';
				}
				html += '<strong>' + escapeHtml( item.title ) + '</strong>';
				html += '<div class="skpc-preview__prices">';
				if ( item.sale_display ) {
					html += '<del>' + escapeHtml( item.price_display ) + '</del> <ins>' + escapeHtml( item.sale_display ) + '</ins>';
				} else {
					html += '<span>' + escapeHtml( item.price_display ) + '</span>';
				}
				html += '</div>';
				if ( item.badge ) {
					html += '<span class="skpc-preview__badge">' + escapeHtml( item.badge ) + '</span>';
				}
				html += '</div>';
			} );
			html += '</div>';
		}
		$result.html( html );
	}

	// --- Detectar colunas ---------------------------------------------------
	function onDetect() {
		var $btn = $( this );
		var $form = $( '#skpc-connection-form' );
		setBusy( $btn, cfg.i18n.loading );
		ajax( 'skpc_detect_columns', collectForm( $form ) )
			.done( function ( res ) {
				if ( res && res.success ) {
					fillDatalist( '#skpc-columns-list', res.data.columns );
				} else {
					window.alert( ( res && res.data && res.data.message ) || cfg.i18n.error );
				}
			} )
			.fail( function () {
				window.alert( cfg.i18n.error );
			} )
			.always( function () {
				clearBusy( $btn );
			} );
	}

	// --- Listar tabelas (MySQL) --------------------------------------------
	function onListTables() {
		var $btn = $( this );
		var $form = $( '#skpc-connection-form' );
		setBusy( $btn, cfg.i18n.loading );
		ajax( 'skpc_list_tables', collectForm( $form ) )
			.done( function ( res ) {
				if ( res && res.success ) {
					fillDatalist( '#skpc-tables-list', res.data.tables );
				} else {
					window.alert( ( res && res.data && res.data.message ) || cfg.i18n.error );
				}
			} )
			.fail( function () {
				window.alert( cfg.i18n.error );
			} )
			.always( function () {
				clearBusy( $btn );
			} );
	}

	// --- Atualizar agora (linha da lista) ----------------------------------
	function onRowRefresh( e ) {
		e.preventDefault();
		var $link = $( this );
		var id = $link.data( 'id' );
		var original = $link.text();
		$link.text( cfg.i18n.refreshing );
		ajax( 'skpc_refresh_now', { id: id } )
			.done( function ( res ) {
				if ( res && res.success ) {
					$( '.skpc-count-' + id ).text( res.data.count );
					$( '.skpc-when-' + id ).text( res.data.when );
					var $badge = $( '.skpc-status-' + id );
					$badge.removeClass( 'skpc-badge--ok skpc-badge--err skpc-badge--neutral' );
					$badge.addClass( res.data.status === 'ok' ? 'skpc-badge--ok' : 'skpc-badge--err' );
				} else {
					window.alert( ( res && res.data && res.data.message ) || cfg.i18n.error );
				}
			} )
			.fail( function () {
				window.alert( cfg.i18n.error );
			} )
			.always( function () {
				$link.text( original );
			} );
	}

	function fillDatalist( selector, values ) {
		var $dl = $( selector );
		$dl.empty();
		( values || [] ).forEach( function ( v ) {
			$dl.append( $( '<option>' ).attr( 'value', v ) );
		} );
	}

	function escapeHtml( str ) {
		return $( '<div>' ).text( str == null ? '' : String( str ) ).html();
	}

	function escapeAttr( str ) {
		return String( str == null ? '' : str ).replace( /"/g, '&quot;' );
	}

	$( function () {
		toggleGroups();
		$( document ).on( 'change', '#skpc-type', toggleGroups );
		$( document ).on( 'change', '#skpc-gs-mode', toggleSheetsApi );
		$( document ).on( 'click', '#skpc-test-connection', onTest );
		$( document ).on( 'click', '#skpc-detect-columns', onDetect );
		$( document ).on( 'click', '#skpc-list-tables', onListTables );
		$( document ).on( 'click', '.skpc-row-refresh', onRowRefresh );
	} );
} )( jQuery );
