<?php
/**
 * Widget do Elementor: Carrossel de Preços.
 *
 * @package SKPriceCarousel
 */

namespace SKPriceCarousel\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use SKPriceCarousel\Plugin;
use SKPriceCarousel\Assets;
use SKPriceCarousel\Data\Item_Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Widget arrastável que renderiza o carrossel (Swiper) a partir de itens
 * manuais ou de uma conexão global, com controles completos de estilo.
 */
class Widget_Carousel extends Widget_Base {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'skpc_carousel';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title() {
		return __( 'Carrossel de Preços', 'sk-price-carousel' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon() {
		return 'eicon-slider-push';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_categories() {
		return array( Elementor_Integration::CATEGORY );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_keywords() {
		return array( 'carrossel', 'carousel', 'preço', 'preco', 'promoção', 'produtos', 'slider' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_script_depends() {
		return array( Assets::SWIPER_HANDLE, Assets::CAROUSEL_HANDLE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_style_depends() {
		return array( Assets::SWIPER_HANDLE, Assets::CAROUSEL_HANDLE );
	}

	/**
	 * Registra todos os controles.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_content_controls();
		$this->register_carousel_controls();
		$this->register_live_controls();
		$this->register_style_controls();
	}

	/**
	 * Aba Conteúdo: fonte de dados e itens manuais.
	 *
	 * @return void
	 */
	private function register_content_controls() {
		$this->start_controls_section(
			'section_source',
			array( 'label' => __( 'Fonte de dados', 'sk-price-carousel' ) )
		);

		$this->add_control(
			'data_mode',
			array(
				'label'   => __( 'Origem dos itens', 'sk-price-carousel' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'manual',
				'options' => array(
					'manual'     => __( 'Itens manuais', 'sk-price-carousel' ),
					'connection' => __( 'Conexão de dados', 'sk-price-carousel' ),
				),
			)
		);

		$this->add_control(
			'connection',
			array(
				'label'       => __( 'Conexão', 'sk-price-carousel' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => $this->connection_options(),
				'default'     => '',
				'condition'   => array( 'data_mode' => 'connection' ),
				'description' => __( 'Cadastre conexões em "Carrossel de Preços" no menu do WordPress.', 'sk-price-carousel' ),
			)
		);

		$repeater = new Repeater();
		$repeater->add_control(
			'item_image',
			array(
				'label'   => __( 'Imagem', 'sk-price-carousel' ),
				'type'    => Controls_Manager::MEDIA,
				'default' => array( 'url' => \Elementor\Utils::get_placeholder_image_src() ),
			)
		);
		$repeater->add_control(
			'item_title',
			array(
				'label'   => __( 'Descrição/Título', 'sk-price-carousel' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Produto', 'sk-price-carousel' ),
			)
		);
		$repeater->add_control(
			'item_description',
			array(
				'label' => __( 'Descrição longa', 'sk-price-carousel' ),
				'type'  => Controls_Manager::TEXTAREA,
			)
		);
		$repeater->add_control(
			'item_price',
			array(
				'label'       => __( 'Preço cheio', 'sk-price-carousel' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '199,90',
			)
		);
		$repeater->add_control(
			'item_sale_price',
			array(
				'label'       => __( 'Preço promocional', 'sk-price-carousel' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '149,90',
				'description' => __( 'Deixe em branco se não houver promoção.', 'sk-price-carousel' ),
			)
		);
		$repeater->add_control(
			'item_url',
			array(
				'label' => __( 'Link', 'sk-price-carousel' ),
				'type'  => Controls_Manager::URL,
			)
		);
		$repeater->add_control(
			'item_badge',
			array(
				'label'       => __( 'Selo/Badge', 'sk-price-carousel' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'Se vazio e houver promoção, o desconto (%) é calculado automaticamente.', 'sk-price-carousel' ),
			)
		);

		$this->add_control(
			'manual_items',
			array(
				'label'       => __( 'Itens', 'sk-price-carousel' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ item_title }}}',
				'condition'   => array( 'data_mode' => 'manual' ),
				'default'     => array(
					array( 'item_title' => __( 'Produto 1', 'sk-price-carousel' ) ),
					array( 'item_title' => __( 'Produto 2', 'sk-price-carousel' ) ),
					array( 'item_title' => __( 'Produto 3', 'sk-price-carousel' ) ),
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Aba Conteúdo: configurações do carrossel.
	 *
	 * @return void
	 */
	private function register_carousel_controls() {
		$this->start_controls_section(
			'section_carousel',
			array( 'label' => __( 'Carrossel', 'sk-price-carousel' ) )
		);

		$this->add_responsive_control(
			'slides_per_view',
			array(
				'label'              => __( 'Slides por vez', 'sk-price-carousel' ),
				'type'               => Controls_Manager::NUMBER,
				'min'                => 1,
				'max'                => 12,
				'default'            => 4,
				'tablet_default'     => 2,
				'mobile_default'     => 1,
				'frontend_available' => true,
			)
		);

		$this->add_responsive_control(
			'space_between',
			array(
				'label'      => __( 'Espaço entre slides (px)', 'sk-price-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'default'    => array( 'size' => 16 ),
				'selectors'  => array( '{{WRAPPER}}' => '--skpc-gap: {{SIZE}}px;' ),
			)
		);

		$this->add_control(
			'autoplay',
			array(
				'label'   => __( 'Autoplay', 'sk-price-carousel' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
			)
		);
		$this->add_control(
			'autoplay_delay',
			array(
				'label'     => __( 'Intervalo do autoplay (ms)', 'sk-price-carousel' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 3000,
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);
		$this->add_control(
			'pause_on_hover',
			array(
				'label'     => __( 'Pausar ao passar o mouse', 'sk-price-carousel' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'condition' => array( 'autoplay' => 'yes' ),
			)
		);
		$this->add_control(
			'loop',
			array(
				'label'   => __( 'Loop infinito', 'sk-price-carousel' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
			)
		);
		$this->add_control(
			'speed',
			array(
				'label'   => __( 'Velocidade da transição (ms)', 'sk-price-carousel' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 500,
			)
		);
		$this->add_control(
			'show_arrows',
			array(
				'label'   => __( 'Mostrar setas', 'sk-price-carousel' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);
		$this->add_control(
			'show_dots',
			array(
				'label'   => __( 'Mostrar paginação (dots)', 'sk-price-carousel' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Aba Conteúdo: atualização ao vivo.
	 *
	 * @return void
	 */
	private function register_live_controls() {
		$this->start_controls_section(
			'section_live',
			array(
				'label'     => __( 'Atualização ao vivo', 'sk-price-carousel' ),
				'condition' => array( 'data_mode' => 'connection' ),
			)
		);

		$this->add_control(
			'live_refresh',
			array(
				'label'       => __( 'Atualizar no navegador', 'sk-price-carousel' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => __( 'Re-busca os itens periodicamente sem recarregar a página.', 'sk-price-carousel' ),
			)
		);
		$this->add_control(
			'live_interval',
			array(
				'label'     => __( 'Intervalo (segundos)', 'sk-price-carousel' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => 15,
				'default'   => (int) Plugin::instance()->settings()['default_live_interval'],
				'condition' => array( 'live_refresh' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Aba Estilo: cartão, imagem, textos, preços, badge e navegação.
	 *
	 * @return void
	 */
	private function register_style_controls() {
		// --- Cartão ---
		$this->start_controls_section(
			'style_card',
			array(
				'label' => __( 'Cartão', 'sk-price-carousel' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'card_bg',
			array(
				'label'     => __( 'Fundo', 'sk-price-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--skpc-card-bg: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'card_radius',
			array(
				'label'      => __( 'Arredondamento', 'sk-price-carousel' ),
				'type'       => Controls_Manager::SLIDER,
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--skpc-card-radius: {{SIZE}}px;' ),
			)
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Espaçamento interno', 'sk-price-carousel' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .skpc-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .skpc-card',
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .skpc-card',
			)
		);
		$this->end_controls_section();

		// --- Imagem ---
		$this->start_controls_section(
			'style_image',
			array(
				'label' => __( 'Imagem', 'sk-price-carousel' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'img_height',
			array(
				'label'     => __( 'Altura (px)', 'sk-price-carousel' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 80, 'max' => 500 ) ),
				'default'   => array( 'size' => 200 ),
				'selectors' => array( '{{WRAPPER}}' => '--skpc-img-height: {{SIZE}}px;' ),
			)
		);
		$this->add_control(
			'img_fit',
			array(
				'label'     => __( 'Ajuste', 'sk-price-carousel' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'cover',
				'options'   => array(
					'cover'   => 'cover',
					'contain' => 'contain',
				),
				'selectors' => array( '{{WRAPPER}}' => '--skpc-img-fit: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();

		// --- Título ---
		$this->start_controls_section(
			'style_title',
			array(
				'label' => __( 'Título', 'sk-price-carousel' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Cor', 'sk-price-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--skpc-title-color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .skpc-card__title',
			)
		);
		$this->end_controls_section();

		// --- Descrição ---
		$this->start_controls_section(
			'style_desc',
			array(
				'label' => __( 'Descrição', 'sk-price-carousel' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'desc_color',
			array(
				'label'     => __( 'Cor', 'sk-price-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--skpc-desc-color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'desc_typography',
				'selector' => '{{WRAPPER}} .skpc-card__desc',
			)
		);
		$this->end_controls_section();

		// --- Preços ---
		$this->start_controls_section(
			'style_prices',
			array(
				'label' => __( 'Preços', 'sk-price-carousel' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'price_color',
			array(
				'label'     => __( 'Cor do preço (sem promoção)', 'sk-price-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--skpc-price-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'full_color',
			array(
				'label'     => __( 'Cor do preço cheio (riscado)', 'sk-price-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--skpc-full-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'sale_color',
			array(
				'label'     => __( 'Cor do preço promocional', 'sk-price-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--skpc-sale-color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'price_typography',
				'selector' => '{{WRAPPER}} .skpc-card__prices',
			)
		);
		$this->end_controls_section();

		// --- Badge ---
		$this->start_controls_section(
			'style_badge',
			array(
				'label' => __( 'Selo/Badge', 'sk-price-carousel' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'badge_bg',
			array(
				'label'     => __( 'Fundo', 'sk-price-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--skpc-badge-bg: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'badge_color',
			array(
				'label'     => __( 'Cor do texto', 'sk-price-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--skpc-badge-color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'badge_typography',
				'selector' => '{{WRAPPER}} .skpc-badge',
			)
		);
		$this->end_controls_section();

		// --- Navegação ---
		$this->start_controls_section(
			'style_nav',
			array(
				'label' => __( 'Navegação', 'sk-price-carousel' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'nav_color',
			array(
				'label'     => __( 'Cor das setas', 'sk-price-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--skpc-nav-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'dot_color',
			array(
				'label'     => __( 'Cor dos dots', 'sk-price-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--skpc-dot-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'dot_active',
			array(
				'label'     => __( 'Cor do dot ativo', 'sk-price-carousel' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--skpc-dot-active: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Opções do dropdown de conexões.
	 *
	 * @return array
	 */
	private function connection_options() {
		$options = array( '' => __( '— selecione —', 'sk-price-carousel' ) );
		foreach ( Plugin::instance()->connections()->all() as $conn ) {
			$options[ $conn['id'] ] = $conn['label'];
		}
		if ( 1 === count( $options ) ) {
			$options[''] = __( '(nenhuma conexão cadastrada)', 'sk-price-carousel' );
		}
		return $options;
	}

	/**
	 * Renderiza o widget no frontend (e no preview do editor).
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$mode     = isset( $settings['data_mode'] ) ? $settings['data_mode'] : 'manual';
		$items    = $this->resolve_items( $settings, $mode );

		$config    = $this->build_swiper_config( $settings );
		$is_live   = ( 'connection' === $mode && 'yes' === ( isset( $settings['live_refresh'] ) ? $settings['live_refresh'] : '' ) );
		$interval  = max( 15, (int) ( isset( $settings['live_interval'] ) ? $settings['live_interval'] : 60 ) );
		$conn_id   = ( 'connection' === $mode ) ? ( isset( $settings['connection'] ) ? $settings['connection'] : '' ) : '';
		$dom_id    = 'skpc-' . $this->get_id();

		$slide_tpl = SKPC_PATH . 'templates/slide.php';
		?>
		<div
			class="skpc-carousel swiper"
			id="<?php echo esc_attr( $dom_id ); ?>"
			data-skpc-instance="<?php echo esc_attr( $this->get_id() ); ?>"
			data-skpc-source="<?php echo esc_attr( $mode ); ?>"
			data-skpc-connection="<?php echo esc_attr( $conn_id ); ?>"
			data-skpc-live="<?php echo $is_live ? '1' : '0'; ?>"
			data-skpc-interval="<?php echo esc_attr( $interval * 1000 ); ?>"
			data-skpc-limit="24"
			data-skpc-settings="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
		>
			<div class="swiper-wrapper">
				<?php
				foreach ( $items as $item ) {
					include $slide_tpl;
				}
				?>
			</div>

			<?php if ( 'yes' === $settings['show_dots'] ) : ?>
				<div class="swiper-pagination skpc-pagination"></div>
			<?php endif; ?>

			<?php if ( 'yes' === $settings['show_arrows'] ) : ?>
				<button type="button" class="skpc-arrow skpc-arrow--prev" aria-label="<?php esc_attr_e( 'Anterior', 'sk-price-carousel' ); ?>"></button>
				<button type="button" class="skpc-arrow skpc-arrow--next" aria-label="<?php esc_attr_e( 'Próximo', 'sk-price-carousel' ); ?>"></button>
			<?php endif; ?>

			<div class="skpc-status" role="status" aria-live="polite" <?php echo empty( $items ) ? '' : 'hidden'; ?>>
				<?php
				if ( 'connection' === $mode && '' === $conn_id ) {
					esc_html_e( 'Selecione uma conexão nas configurações do widget.', 'sk-price-carousel' );
				} elseif ( empty( $items ) ) {
					esc_html_e( 'Carregando itens…', 'sk-price-carousel' );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Resolve os itens conforme o modo (manual ou conexão), servindo do cache.
	 *
	 * @param array  $settings Configurações.
	 * @param string $mode     Modo.
	 * @return array
	 */
	private function resolve_items( $settings, $mode ) {
		if ( 'manual' === $mode ) {
			$items = array();
			foreach ( (array) ( isset( $settings['manual_items'] ) ? $settings['manual_items'] : array() ) as $row ) {
				$item = Item_Schema::normalize_manual( (array) $row );
				if ( '' !== $item['image'] || '' !== $item['title'] || null !== $item['price'] ) {
					$items[] = $item;
				}
			}
			return $items;
		}

		$conn_id = isset( $settings['connection'] ) ? $settings['connection'] : '';
		if ( '' === $conn_id || ! Plugin::instance()->connections()->exists( $conn_id ) ) {
			return array();
		}

		$cache = Plugin::instance()->cache();
		$items = $cache->get_items( $conn_id, 24 );

		// No editor, aquece o cache de forma síncrona para o preview mostrar dados.
		if ( empty( $items ) && $this->is_elementor_editor() ) {
			$cache->rebuild( $conn_id );
			$items = $cache->get_items( $conn_id, 24 );
		}

		return $items;
	}

	/**
	 * Monta a configuração do Swiper (com breakpoints responsivos).
	 *
	 * @param array $settings Configurações.
	 * @return array
	 */
	private function build_swiper_config( $settings ) {
		$num = static function ( $key, $default ) use ( $settings ) {
			$v = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
			return ( '' === $v || null === $v ) ? $default : (int) $v;
		};
		$slider = static function ( $key, $default ) use ( $settings ) {
			$v = isset( $settings[ $key ]['size'] ) ? $settings[ $key ]['size'] : '';
			return ( '' === $v || null === $v ) ? $default : (float) $v;
		};

		$spv   = $num( 'slides_per_view', 4 );
		$spv_t = $num( 'slides_per_view_tablet', 2 );
		$spv_m = $num( 'slides_per_view_mobile', 1 );
		$sb    = $slider( 'space_between', 16 );
		$sb_t  = $slider( 'space_between_tablet', $sb );
		$sb_m  = $slider( 'space_between_mobile', $sb );

		$config = array(
			'slidesPerView' => $spv_m,
			'spaceBetween'  => $sb_m,
			'loop'          => ( 'yes' === $settings['loop'] ),
			'speed'         => $num( 'speed', 500 ),
			'arrows'        => ( 'yes' === $settings['show_arrows'] ),
			'dots'          => ( 'yes' === $settings['show_dots'] ),
			'autoplay'      => ( 'yes' === $settings['autoplay'] )
				? array(
					'delay'                => $num( 'autoplay_delay', 3000 ),
					'disableOnInteraction' => false,
					'pauseOnMouseEnter'    => ( 'yes' === $settings['pause_on_hover'] ),
				)
				: false,
			'breakpoints'   => array(
				768  => array(
					'slidesPerView' => $spv_t,
					'spaceBetween'  => $sb_t,
				),
				1025 => array(
					'slidesPerView' => $spv,
					'spaceBetween'  => $sb,
				),
			),
		);

		/**
		 * Permite ajustar a configuração do Swiper (ex.: breakpoints do tema).
		 *
		 * @param array $config   Config do Swiper.
		 * @param array $settings Configurações do widget.
		 */
		return apply_filters( 'skpc_swiper_config', $config, $settings );
	}

	/**
	 * Estamos no editor/preview do Elementor?
	 *
	 * @return bool
	 */
	private function is_elementor_editor() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$elementor = \Elementor\Plugin::$instance;
		if ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() ) {
			return true;
		}
		if ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() ) {
			return true;
		}
		return false;
	}
}
