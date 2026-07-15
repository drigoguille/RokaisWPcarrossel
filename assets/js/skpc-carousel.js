/**
 * Frontend do SK Carrossel de Preços.
 *
 * Inicializa uma instância Swiper por elemento (multi-instância), faz o refresh
 * ao vivo via REST com ETag/304 + backoff + Page Visibility, respeita
 * prefers-reduced-motion e desliga o polling no editor do Elementor.
 */
( function () {
	'use strict';

	var CFG = window.SKPCConfig || { restBase: '', nonce: '', i18n: {} };
	var instances = [];
	var shared = {}; // Dedup de requisições concorrentes por conexão+limit+etag.

	function reducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function isEditMode() {
		return !! ( window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode() );
	}

	function parseJSON( str ) {
		try {
			return JSON.parse( str );
		} catch ( e ) {
			return {};
		}
	}

	function breakpointsMax( bp ) {
		var m = 1;
		for ( var k in bp ) {
			if ( Object.prototype.hasOwnProperty.call( bp, k ) && bp[ k ] && bp[ k ].slidesPerView ) {
				m = Math.max( m, bp[ k ].slidesPerView );
			}
		}
		return m;
	}

	// --- Requisição compartilhada (dedup) ----------------------------------
	function fetchItems( connection, limit, etag ) {
		var key = connection + '|' + limit + '|' + ( etag || '' );
		var now = Date.now();
		if ( shared[ key ] && ( now - shared[ key ].ts ) < 1500 ) {
			return shared[ key ].promise;
		}

		var url = CFG.restBase + '?connection=' + encodeURIComponent( connection ) + '&limit=' + encodeURIComponent( limit );
		var headers = { 'X-WP-Nonce': CFG.nonce };
		if ( etag ) {
			headers[ 'If-None-Match' ] = '"' + etag + '"';
		}

		var promise = fetch( url, { headers: headers, credentials: 'same-origin' } ).then( function ( res ) {
			if ( res.status === 304 ) {
				return { notModified: true };
			}
			if ( ! res.ok ) {
				throw new Error( 'HTTP ' + res.status );
			}
			return res.json();
		} );

		shared[ key ] = { ts: now, promise: promise };
		return promise;
	}

	// --- Construção de um slide (espelha o SSR) ----------------------------
	function elem( tag, cls ) {
		var e = document.createElement( tag );
		if ( cls ) {
			e.className = cls;
		}
		return e;
	}

	function buildSlide( item ) {
		var slide = elem( 'div', 'swiper-slide skpc-slide' );
		var card = elem( 'article', 'skpc-card' );
		var link = elem( item.url ? 'a' : 'div', 'skpc-card__link' );
		if ( item.url ) {
			link.setAttribute( 'href', item.url );
		}

		var media = elem( 'figure', 'skpc-card__media' );
		if ( item.image ) {
			var img = elem( 'img', 'skpc-card__img' );
			img.src = item.image;
			img.alt = item.title || '';
			img.loading = 'lazy';
			img.decoding = 'async';
			media.appendChild( img );
		}
		if ( item.badge ) {
			var badge = elem( 'span', 'skpc-badge' );
			badge.textContent = item.badge;
			media.appendChild( badge );
		}
		link.appendChild( media );

		var body = elem( 'div', 'skpc-card__body' );
		if ( item.title ) {
			var title = elem( 'h3', 'skpc-card__title' );
			title.textContent = item.title;
			body.appendChild( title );
		}
		if ( item.description ) {
			var desc = elem( 'div', 'skpc-card__desc' );
			// Descrição já sanitizada no servidor (wp_kses_post).
			desc.innerHTML = item.description;
			body.appendChild( desc );
		}

		var prices = elem( 'div', 'skpc-card__prices' );
		if ( item.has_promo ) {
			var del = elem( 'del', 'skpc-price skpc-price--full' );
			del.textContent = item.price_display;
			var ins = elem( 'ins', 'skpc-price skpc-price--sale' );
			ins.textContent = item.sale_display;
			prices.appendChild( del );
			prices.appendChild( ins );
		} else if ( item.price_display ) {
			var span = elem( 'span', 'skpc-price' );
			span.textContent = item.price_display;
			prices.appendChild( span );
		}
		body.appendChild( prices );

		link.appendChild( body );
		card.appendChild( link );
		slide.appendChild( card );
		return slide;
	}

	// --- Instância ----------------------------------------------------------
	function Carousel( el ) {
		this.el = el;
		this.cfg = parseJSON( el.getAttribute( 'data-skpc-settings' ) ) || {};
		this.source = el.getAttribute( 'data-skpc-source' );
		this.connection = el.getAttribute( 'data-skpc-connection' );
		this.live = el.getAttribute( 'data-skpc-live' ) === '1';
		this.interval = parseInt( el.getAttribute( 'data-skpc-interval' ), 10 ) || 60000;
		this.limit = parseInt( el.getAttribute( 'data-skpc-limit' ), 10 ) || 24;
		this.etag = null;
		this.timer = null;
		this.failures = 0;
		this.swiper = this.createSwiper();

		if ( 'connection' === this.source && this.connection && ! isEditMode() ) {
			var slideCount = el.querySelectorAll( '.swiper-slide' ).length;
			if ( this.live ) {
				this.poll();
			} else if ( 0 === slideCount ) {
				this.fetchOnce();
			}
		}
	}

	Carousel.prototype.createSwiper = function () {
		if ( ! window.Swiper ) {
			return null;
		}
		var cfg = this.cfg;
		var slideCount = this.el.querySelectorAll( '.swiper-slide' ).length;
		var maxSpv = Math.max( cfg.slidesPerView || 1, breakpointsMax( cfg.breakpoints || {} ) );

		var opts = {
			slidesPerView: cfg.slidesPerView || 1,
			spaceBetween: cfg.spaceBetween || 0,
			loop: !! cfg.loop && slideCount > maxSpv,
			speed: cfg.speed || 500,
			breakpoints: cfg.breakpoints || {},
			watchOverflow: true,
			a11y: {
				prevSlideMessage: CFG.i18n.prev || 'Previous',
				nextSlideMessage: CFG.i18n.next || 'Next'
			}
		};

		if ( cfg.autoplay && ! reducedMotion() ) {
			opts.autoplay = cfg.autoplay;
		}
		if ( cfg.dots ) {
			var pag = this.el.querySelector( '.skpc-pagination' );
			if ( pag ) {
				opts.pagination = { el: pag, clickable: true };
			}
		}
		if ( cfg.arrows ) {
			var next = this.el.querySelector( '.skpc-arrow--next' );
			var prev = this.el.querySelector( '.skpc-arrow--prev' );
			if ( next && prev ) {
				opts.navigation = { nextEl: next, prevEl: prev };
			}
		}

		return new window.Swiper( this.el, opts );
	};

	Carousel.prototype.render = function ( items ) {
		if ( ! this.swiper || ! items ) {
			return;
		}
		var status = this.el.querySelector( '.skpc-status' );
		if ( status ) {
			status.setAttribute( 'hidden', '' );
		}
		var slides = items.map( buildSlide );
		this.swiper.removeAllSlides();
		this.swiper.appendSlide( slides );
		this.swiper.update();
	};

	Carousel.prototype.fetchOnce = function () {
		var self = this;
		fetchItems( this.connection, this.limit, null ).then( function ( data ) {
			if ( data && data.items && data.items.length ) {
				self.etag = data.etag || null;
				self.render( data.items );
			}
		} ).catch( function () {} );
	};

	Carousel.prototype.poll = function () {
		if ( document.hidden ) {
			this.scheduleNext();
			return;
		}
		var self = this;
		fetchItems( this.connection, this.limit, this.etag ).then( function ( data ) {
			self.failures = 0;
			if ( ! data || data.notModified ) {
				return;
			}
			if ( 'warming' === data.status ) {
				return;
			}
			if ( data.etag && data.etag === self.etag ) {
				return;
			}
			if ( data.items ) {
				self.etag = data.etag || self.etag;
				self.render( data.items );
			}
		} ).catch( function () {
			self.failures++;
		} ).then( function () {
			self.scheduleNext();
		} );
	};

	Carousel.prototype.scheduleNext = function () {
		var delay = this.interval;
		if ( this.failures > 0 ) {
			delay = Math.min( this.interval * Math.pow( 2, this.failures ), 300000 );
		}
		delay = delay * ( 0.85 + Math.random() * 0.3 ); // jitter ±15%.
		clearTimeout( this.timer );
		this.timer = setTimeout( this.poll.bind( this ), delay );
	};

	// --- Bootstrap ----------------------------------------------------------
	function initEl( el ) {
		if ( ! el || el.__skpcInit ) {
			return;
		}
		el.__skpcInit = true;
		instances.push( new Carousel( el ) );
	}

	function initAll( root ) {
		var scope = root || document;
		var list = scope.querySelectorAll ? scope.querySelectorAll( '.skpc-carousel' ) : [];
		Array.prototype.forEach.call( list, initEl );
	}

	// Retoma o polling imediatamente ao voltar para a aba.
	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden ) {
			return;
		}
		instances.forEach( function ( inst ) {
			if ( inst.live ) {
				inst.poll();
			}
		} );
	} );

	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/skpc_carousel.default', function ( $scope ) {
			var root = $scope && $scope[ 0 ] ? $scope[ 0 ] : document;
			var el = root.classList && root.classList.contains( 'skpc-carousel' ) ? root : root.querySelector( '.skpc-carousel' );
			initEl( el );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll( document );
		} );
	} else {
		initAll( document );
	}
} )();
