<?php
/**
 * Plugin Name: Caricove — Vendor Storefront Page v2.0.0 (MU)
 * Description: Public vendor storefront (marketplace-first) powered by Caricove Store Settings metas. URL: /storefront/?vendor={slug|id}. Hardens against stray [dokan_dashboard] and other content bleed. Includes compact hero banner with fallback gradient, trust strip, media carousel, product grid with variation mini-ui, filters, about, policy+location, support close.
 *
 * Install:
 *  - Put this file at /wp-content/mu-plugins/vendor-store.php (replace existing vendor-store.php)
 *  - Create a WP page with slug "storefront" and content: [caricove_vendor_storefront]
 *    (or this plugin will attempt to create it once).
 */

if ( ! defined('ABSPATH') ) { exit; }

final class Caricove_Vendor_Storefront_V2 {

  const PAGE_SLUG  = 'storefront';
  const SHORTCODE  = 'caricove_vendor_storefront';

  // Store Settings metas (canonical)
  const M_NAME      = '_cc_store_name';
  const M_TAGLINE   = '_cc_store_tagline';
  const M_ABOUT     = '_cc_store_about';
  const M_LOGO_ID   = '_cc_store_logo_id';
  const M_BANNER_ID = '_cc_store_banner_id';

  const M_PUBLIC_EMAIL = '_cc_store_public_email';
  const M_PUBLIC_PHONE = '_cc_store_public_phone';
  const M_SUPPORT_EMAIL= '_cc_store_support_email';
  const M_AVAIL_NOTICE = '_cc_store_availability_notice';

  const M_INSTAGRAM = '_cc_store_instagram';
  const M_WEBSITE   = '_cc_store_website';

  const M_VAC_ENABLED = '_cc_store_vacation_enabled';
  const M_VAC_MESSAGE = '_cc_store_vacation_message';

  const M_CITY     = '_cc_store_city';
  const M_SUBDIV   = '_cc_store_subdivision';
  const M_SUB_LABEL= '_cc_store_subdivision_label';
  const M_COUNTRY  = '_cc_store_country_code';

  // ------------ Boot ------------
  public static function boot(): void {
    add_action('init', [__CLASS__, 'maybe_create_page']);
    add_filter('the_content', [__CLASS__, 'harden_storefront_content'], 1);
    add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);

    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 20);
  }

  // Create storefront page once if missing (safe)
  public static function maybe_create_page(): void {
    if ( get_option('cc_storefront_v2_page_created') === 'yes' ) return;

    $existing = get_page_by_path(self::PAGE_SLUG);
    if ( $existing && $existing->ID ) {
      update_option('cc_storefront_v2_page_created', 'yes');
      return;
    }

    $page_id = wp_insert_post([
      'post_type'    => 'page',
      'post_status'  => 'publish',
      'post_title'   => 'Storefront',
      'post_name'    => self::PAGE_SLUG,
      'post_content' => '[' . self::SHORTCODE . ']',
    ], true);

    if ( ! is_wp_error($page_id) && $page_id ) {
      update_option('cc_storefront_v2_page_created', 'yes');
    }
  }

  // Remove stray dokan dashboard shortcode (and ensure our shortcode exists)
  public static function harden_storefront_content(string $content): string {
    if ( ! is_singular('page') ) return $content;
    $p = get_queried_object();
    if ( ! $p || empty($p->post_name) || $p->post_name !== self::PAGE_SLUG ) return $content;

    // Strip raw dokan_dashboard occurrences
    $content = preg_replace('/\[dokan_dashboard[^\]]*\]/i', '', $content);

    // Ensure our storefront shortcode exists
    if ( stripos($content, '[' . self::SHORTCODE) === false ) {
      $content = '[' . self::SHORTCODE . ']' . "\n" . $content;
    }

    return $content;
  }

  public static function enqueue_assets(): void {
    if ( ! is_singular('page') ) return;
    $p = get_queried_object();
    if ( ! $p || empty($p->post_name) || $p->post_name !== self::PAGE_SLUG ) return;

    $css = self::css();
    wp_register_style('cc-storefront-v2', false, [], '2.0.0');
    wp_enqueue_style('cc-storefront-v2');
    wp_add_inline_style('cc-storefront-v2', $css);

    $js = self::js();
    wp_register_script('cc-storefront-v2', false, [], '2.0.0', true);
    wp_enqueue_script('cc-storefront-v2');
    wp_add_inline_script('cc-storefront-v2', $js);
  }

  // ------------ Shortcode entry ------------
  public static function shortcode(array $atts = []): string {
    $vendor_raw = '';
    if ( isset($_GET['vendor']) ) $vendor_raw = (string) wp_unslash($_GET['vendor']);
    if ( $vendor_raw === '' && isset($_GET['cc_vendor']) ) $vendor_raw = (string) wp_unslash($_GET['cc_vendor']);
    if ( $vendor_raw === '' && ! empty($atts['vendor']) ) $vendor_raw = (string) $atts['vendor'];

    $vendor = self::resolve_vendor($vendor_raw);
    if ( ! $vendor ) {
      return self::render_not_found();
    }

    return self::render_storefront((int)$vendor->ID);
  }

  // ------------ Vendor resolution ------------
  private static function resolve_vendor(string $raw) {
    $raw = trim($raw);
    if ($raw === '') return null;

    if (ctype_digit($raw)) {
      $u = get_user_by('id', (int)$raw);
      return ($u && $u->ID) ? $u : null;
    }

    $slug = sanitize_title($raw);
    $u = get_user_by('slug', $slug);
    if ($u && $u->ID) return $u;

    $u = get_user_by('login', sanitize_user($raw));
    if ($u && $u->ID) return $u;

    return null;
  }

  // ------------ Meta helpers ------------
  private static function umeta_str(int $uid, string $key): string {
    return trim((string) get_user_meta($uid, $key, true));
  }

  private static function umeta_int(int $uid, string $key): int {
    return (int) get_user_meta($uid, $key, true);
  }

  private static function img_url($id_or_url, string $size = 'full'): string {
    if (is_string($id_or_url)) {
      $v = trim($id_or_url);
      if ($v === '') return '';
      if (preg_match('~^https?://~i', $v)) return $v;
      if (ctype_digit($v)) $id_or_url = (int)$v;
    }
    $id = (int) $id_or_url;
    if ($id <= 0) return '';
    $u = wp_get_attachment_image_url($id, $size);
    if (!$u) $u = wp_get_attachment_url($id);
    return $u ? (string)$u : '';
  }

  private static function normalize_url(string $u): string {
    $u = trim($u);
    if ($u === '') return '';
    if (stripos($u, 'http://') === 0 || stripos($u, 'https://') === 0) return $u;
    return 'https://' . ltrim($u, '/');
  }

  private static function normalize_instagram(string $h): string {
    $h = trim($h);
    if ($h === '') return '';
    if (stripos($h, 'http://') === 0 || stripos($h, 'https://') === 0) return $h;
    $h = ltrim($h, '@');
    return 'https://www.instagram.com/' . rawurlencode($h) . '/';
  }

  // ------------ Rendering ------------
  private static function render_not_found(): string {
    $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
    return '<div class="ccsf2-wrap"><div class="ccsf2-card ccsf2-center"><div class="ccsf2-h">Storefront not found</div><div class="ccsf2-p">This seller may not exist, or the link is missing a vendor.</div><a class="ccsf2-btn" href="'.esc_url($shop).'">Browse products</a></div></div>';
  }

  private static function render_storefront(int $vendor_id): string {
    $u = get_userdata($vendor_id);

    // Store name must come from Store Settings (fallback only if empty)
    $store_name = self::umeta_str($vendor_id, self::M_NAME);
    if ($store_name === '' && $u && !empty($u->display_name)) $store_name = (string)$u->display_name;
    if ($store_name === '') $store_name = 'Storefront';

    $tagline = self::umeta_str($vendor_id, self::M_TAGLINE);
    $about   = self::umeta_str($vendor_id, self::M_ABOUT);

    $logo_id   = self::umeta_int($vendor_id, self::M_LOGO_ID);
    $banner_id = self::umeta_int($vendor_id, self::M_BANNER_ID);
    $logo_url  = self::img_url($logo_id, 'full');
    $banner_url= self::img_url($banner_id, 'full');

    $public_email = self::umeta_str($vendor_id, self::M_PUBLIC_EMAIL);
    $public_phone = self::umeta_str($vendor_id, self::M_PUBLIC_PHONE);
    $support_email= self::umeta_str($vendor_id, self::M_SUPPORT_EMAIL);
    $avail_notice = self::umeta_str($vendor_id, self::M_AVAIL_NOTICE);

    $instagram = self::umeta_str($vendor_id, self::M_INSTAGRAM);
    $website   = self::umeta_str($vendor_id, self::M_WEBSITE);

    $vac_enabled = strtolower(self::umeta_str($vendor_id, self::M_VAC_ENABLED)) === 'yes';
    $vac_message = self::umeta_str($vendor_id, self::M_VAC_MESSAGE);

    $city    = self::umeta_str($vendor_id, self::M_CITY);
    $subdiv  = self::umeta_str($vendor_id, self::M_SUBDIV);
    $country = self::umeta_str($vendor_id, self::M_COUNTRY);

    $loc_bits = array_filter([$city, $subdiv, $country]);
    $loc_line = !empty($loc_bits) ? implode(', ', $loc_bits) : '';

    // Trust strip: use rating if available (best-effort)
    $rating_avg = self::vendor_rating_avg($vendor_id);
    $rating_cnt = self::vendor_rating_count($vendor_id);

    // Media carousel: use up to 6 product thumbnails from this vendor
    $media = self::vendor_media($vendor_id, 6);

    // Products grid: latest 12 (vendor's published products)
    $products = self::vendor_products($vendor_id, 12);

    // Policy text should mirror customer message (platform copy)
    $policy_copy = self::policy_copy();

    $seller_away_url = home_url('/seller-away/');

    ob_start(); ?>
    <div class="ccsf2-wrap" data-vendor="<?php echo esc_attr($vendor_id); ?>" data-vac="<?php echo $vac_enabled ? '1' : '0'; ?>">
      <!-- CC STOREFRONT DEBUG: vendor_id=<?php echo (int)$vendor_id; ?> store_name="<?php echo esc_html($store_name); ?>" logo_id=<?php echo (int)$logo_id; ?> banner_id=<?php echo (int)$banner_id; ?> logo_url="<?php echo esc_url($logo_url); ?>" banner_url="<?php echo esc_url($banner_url); ?>" -->

      <!-- HERO -->
      <section class="ccsf2-hero" style="<?php echo $banner_url ? 'background-image:url('.esc_url($banner_url).')' : ''; ?>">
        <div class="ccsf2-hero-overlay"></div>

        <div class="ccsf2-hero-inner">
          <div class="ccsf2-hero-box">
            <div class="ccsf2-brand">
              <div class="ccsf2-logo">
                <?php if ($logo_url): ?>
                  <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($store_name); ?>">
                <?php else: ?>
                  <div class="ccsf2-logo-fallback"><?php echo esc_html(mb_substr($store_name, 0, 1)); ?></div>
                <?php endif; ?>
              </div>
              <div class="ccsf2-brand-text">
                <div class="ccsf2-store-name"><?php echo esc_html($store_name); ?></div>
                <?php if ($tagline): ?><div class="ccsf2-tagline"><?php echo esc_html($tagline); ?></div><?php endif; ?>
              </div>
            </div>

            <div class="ccsf2-hero-actions">
              <?php if ($public_phone): ?>
                <a class="ccsf2-pill" href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $public_phone)); ?>">Call</a>
              <?php endif; ?>
              <?php if ($instagram): ?>
                <a class="ccsf2-pill" href="<?php echo esc_url(self::normalize_instagram($instagram)); ?>" target="_blank" rel="noopener">Instagram</a>
              <?php endif; ?>
              <?php if ($website): ?>
                <a class="ccsf2-pill" href="<?php echo esc_url(self::normalize_url($website)); ?>" target="_blank" rel="noopener">Website</a>
              <?php endif; ?>
              <a class="ccsf2-pill ccsf2-primary" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/')); ?>">Browse</a>
            </div>

            <div class="ccsf2-status">
              <?php if ($vac_enabled): ?>
                <span class="ccsf2-status-pill is-vac">😔 On vacation</span>
                <span class="ccsf2-status-sub"><?php echo esc_html($vac_message ?: 'Please enjoy the rest of our products.'); ?></span>
              <?php elseif ($avail_notice): ?>
                <span class="ccsf2-status-pill">Open</span>
                <span class="ccsf2-status-sub"><?php echo esc_html($avail_notice); ?></span>
              <?php else: ?>
                <span class="ccsf2-status-pill">Open</span>
                <span class="ccsf2-status-sub">Ready to serve.</span>
              <?php endif; ?>
            </div>

          </div>
        </div>
      </section>

      <!-- TRUST STRIP -->
      <section class="ccsf2-trust">
        <div class="ccsf2-trust-item">
          <span class="ccsf2-ico medal" aria-hidden="true"></span>
          <div class="ccsf2-trust-text">
            <div class="ccsf2-trust-top">Verified</div>
            <div class="ccsf2-trust-sub">Seller</div>
          </div>
        </div>

        <div class="ccsf2-trust-item">
          <span class="ccsf2-ico star" aria-hidden="true"></span>
          <div class="ccsf2-trust-text">
            <div class="ccsf2-trust-top"><?php echo $rating_avg !== '' ? esc_html($rating_avg) : '—'; ?></div>
            <div class="ccsf2-trust-sub"><?php echo $rating_cnt !== '' ? esc_html($rating_cnt.' reviews') : 'No reviews'; ?></div>
          </div>
        </div>

        <div class="ccsf2-trust-item">
          <span class="ccsf2-ico recycle" aria-hidden="true"></span>
          <div class="ccsf2-trust-text">
            <div class="ccsf2-trust-top">Easy</div>
            <div class="ccsf2-trust-sub">Returns</div>
          </div>
        </div>

        <div class="ccsf2-trust-item">
          <span class="ccsf2-ico bolt" aria-hidden="true"></span>
          <div class="ccsf2-trust-text">
            <div class="ccsf2-trust-top">Fast</div>
            <div class="ccsf2-trust-sub">Response</div>
          </div>
        </div>
      </section>

      <!-- MEDIA CAROUSEL -->
      <section class="ccsf2-media">
        <div class="ccsf2-media-head">
          <div class="ccsf2-h3">Highlights</div>
          <div class="ccsf2-muted">Photos or video from this store.</div>
        </div>
        <div class="ccsf2-carousel" data-ccsf2-carousel>
          <?php if (!empty($media)): ?>
            <?php foreach ($media as $m): ?>
              <div class="ccsf2-slide">
                <img src="<?php echo esc_url($m); ?>" alt="" loading="lazy">
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="ccsf2-slide ccsf2-slide-empty">
              <div class="ccsf2-empty">No media yet</div>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- PRODUCTS + FILTERS -->
      <section class="ccsf2-products">

        <div class="ccsf2-products-head">
          <div class="ccsf2-h3">Products</div>

          <div class="ccsf2-filterbar">
            <input class="ccsf2-search" type="search" placeholder="Search in this store" data-ccsf2-search>
            <select class="ccsf2-sort" data-ccsf2-sort>
              <option value="new">Newest</option>
              <option value="price_asc">Price: Low to High</option>
              <option value="price_desc">Price: High to Low</option>
              <option value="rating">Rating</option>
            </select>
          </div>
        </div>

        <div class="ccsf2-grid" data-ccsf2-grid>
          <?php if (empty($products)): ?>
            <div class="ccsf2-card ccsf2-center">
              <div class="ccsf2-h">No products found</div>
              <div class="ccsf2-p">This store hasn’t listed products yet.</div>
            </div>
          <?php else: ?>
            <?php foreach ($products as $pr): ?>
              <?php echo self::render_product_card($pr, $vac_enabled ? $seller_away_url : ''); ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <!-- ABOUT STORE -->
      <?php if ($about !== ''): ?>
      <section class="ccsf2-about">
        <div class="ccsf2-h3">About this store</div>
        <div class="ccsf2-about-box" data-ccsf2-about>
          <div class="ccsf2-about-text"><?php echo wp_kses_post(wpautop($about)); ?></div>
          <button class="ccsf2-about-more" type="button" data-ccsf2-about-toggle>Read more</button>
        </div>
      </section>
      <?php endif; ?>

      <!-- POLICY & LOCATION -->
      <section class="ccsf2-policy">
        <div class="ccsf2-h3">Policy & location</div>
        <div class="ccsf2-policy-grid">
          <div class="ccsf2-panel">
            <div class="ccsf2-panel-title">Policy</div>
            <div class="ccsf2-panel-body"><?php echo wp_kses_post($policy_copy); ?></div>
            <?php if ($avail_notice): ?>
              <div class="ccsf2-panel-note"><strong>Availability:</strong> <?php echo esc_html($avail_notice); ?></div>
            <?php endif; ?>
          </div>

          <div class="ccsf2-panel">
            <div class="ccsf2-panel-title">Location</div>
            <div class="ccsf2-panel-body">
              <?php if ($loc_line): ?>
                <div class="ccsf2-row"><span>Ships from</span><span><?php echo esc_html($loc_line); ?></span></div>
              <?php else: ?>
                <div class="ccsf2-muted">Location not provided.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>

      <!-- SUPPORT CLOSE -->
      <?php if ($support_email || $public_phone || $public_email || $avail_notice): ?>
      <section class="ccsf2-support">
        <div class="ccsf2-support-card">
          <div class="ccsf2-support-head">
            <div class="ccsf2-h3">Need help with this store?</div>
            <?php if ($avail_notice): ?><div class="ccsf2-muted"><?php echo esc_html($avail_notice); ?></div><?php endif; ?>
          </div>
          <div class="ccsf2-support-actions">
            <?php if ($public_phone): ?>
              <a class="ccsf2-pill ccsf2-primary" href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $public_phone)); ?>">Call</a>
            <?php endif; ?>
            <?php if ($public_email): ?>
              <a class="ccsf2-pill" href="mailto:<?php echo esc_attr($public_email); ?>">Email</a>
            <?php endif; ?>
            <?php if ($support_email): ?>
              <a class="ccsf2-pill" href="mailto:<?php echo esc_attr($support_email); ?>">Support</a>
            <?php endif; ?>
          </div>
        </div>
      </section>
      <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
  }

  // ---------- Products helpers ----------
  private static function vendor_products(int $vendor_id, int $limit): array {
    $out = [];
    if (!class_exists('WP_Query')) return $out;

    $q = new WP_Query([
      'post_type'      => 'product',
      'post_status'    => 'publish',
      'author'         => $vendor_id,
      'posts_per_page' => $limit,
      'orderby'        => 'date',
      'order'          => 'DESC',
      'no_found_rows'  => true,
    ]);

    if (!$q->have_posts()) return $out;

    while ($q->have_posts()) {
      $q->the_post();
      $pid = get_the_ID();
      if (!function_exists('wc_get_product')) continue;
      $p = wc_get_product($pid);
      if (!$p) continue;

      $img = get_the_post_thumbnail_url($pid, 'woocommerce_single');
      if (!$img) $img = wc_placeholder_img_src('woocommerce_single');

      $out[] = [
        'id'      => $pid,
        'title'   => get_the_title(),
        'url'     => get_permalink($pid),
        'img'     => $img,
        'price'   => $p->get_price_html(),
        'type'    => $p->get_type(),
        'rating'  => (float) $p->get_average_rating(),
        'rating_count' => (int) $p->get_rating_count(),
        'p' => $p,
      ];
    }
    wp_reset_postdata();
    return $out;
  }

  private static function vendor_media(int $vendor_id, int $limit): array {
    $media = [];
    $products = self::vendor_products($vendor_id, max(6, $limit*2));
    foreach ($products as $pr) {
      if (!empty($pr['img'])) $media[] = $pr['img'];
      if (count($media) >= $limit) break;
    }
    return $media;
  }

  private static function render_product_card(array $pr, string $away_url): string {
    $id = (int) $pr['id'];
    $title = (string) $pr['title'];
    $img = (string) $pr['img'];
    $price = (string) $pr['price'];
    $url = (string) $pr['url'];
    $rating = isset($pr['rating']) ? (float)$pr['rating'] : 0.0;
    $rcount = isset($pr['rating_count']) ? (int)$pr['rating_count'] : 0;

    $p = $pr['p'] ?? null;
    $type = $pr['type'] ?? 'simple';
    $can_simple = ($type === 'simple' && $p && $p->is_purchasable() && $p->is_in_stock());

    // Variation mini-UI data (best-effort, not full variation add-to-cart)
    $variations = [];
    $sizes = [];
    if ($p && $type === 'variable') {
      $attrs = $p->get_variation_attributes(); // e.g. ['attribute_pa_color'=>['red','blue']]
      // Pick first attribute as "swatches"
      foreach ($attrs as $tax => $vals) {
        if (is_array($vals) && !empty($vals)) {
          $variations = array_slice(array_values($vals), 0, 4);
          break;
        }
      }
      // Pick a "size" attribute if present
      foreach ($attrs as $tax => $vals) {
        if (stripos($tax, 'size') !== false && is_array($vals) && !empty($vals)) {
          $sizes = array_values($vals);
          break;
        }
      }
    }

    $add_href = $can_simple ? home_url('/?add-to-cart=' . $id) : $url;
    $cta_label = $can_simple ? 'Add to cart' : 'View options';

    // If away_url provided, force all clicks to away page
    if ($away_url) {
      $url = $away_url;
      $add_href = $away_url;
      $cta_label = 'Seller away';
    }

    $data_price = '';
    $raw_price = $p && method_exists($p,'get_price') ? (string)$p->get_price() : '';
    if ($raw_price !== '') $data_price = ' data-price="'.esc_attr($raw_price).'"';

    $data_rating = ' data-rating="'.esc_attr((string)$rating).'"';

    ob_start(); ?>
      <article class="ccsf2-pcard" data-title="<?php echo esc_attr(mb_strtolower($title)); ?>"<?php echo $data_price; ?><?php echo $data_rating; ?>>
        <a class="ccsf2-pimg" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr($title); ?>">
          <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
        </a>

        <div class="ccsf2-pmeta">
          <div class="ccsf2-ptitle"><?php echo esc_html($title); ?></div>
          <div class="ccsf2-prating">
            <span class="ccsf2-star" aria-hidden="true">★</span>
            <span class="ccsf2-rval"><?php echo $rating > 0 ? esc_html(number_format($rating,1)) : '—'; ?></span>
            <span class="ccsf2-rcnt"><?php echo $rcount ? esc_html('(' . $rcount . ')') : ''; ?></span>
          </div>
          <div class="ccsf2-price"><?php echo wp_kses_post($price); ?></div>

          <!-- Variation mini strip -->
          <div class="ccsf2-varrow">
            <div class="ccsf2-vars">
              <?php if (!empty($variations)): foreach ($variations as $v): ?>
                <span class="ccsf2-var" title="<?php echo esc_attr($v); ?>"></span>
              <?php endforeach; else: ?>
                <span class="ccsf2-var is-empty"></span>
                <span class="ccsf2-var is-empty"></span>
                <span class="ccsf2-var is-empty"></span>
                <span class="ccsf2-var is-empty"></span>
              <?php endif; ?>
            </div>

            <div class="ccsf2-sizebox" data-sizes='<?php echo esc_attr(wp_json_encode($sizes)); ?>'>
              <div class="ccsf2-sizeval"><?php echo !empty($sizes) ? esc_html($sizes[0]) : 'Size'; ?></div>
              <div class="ccsf2-arrows">
                <button type="button" class="ccsf2-arrow up" aria-label="Size up">▲</button>
                <button type="button" class="ccsf2-arrow down" aria-label="Size down">▼</button>
              </div>
            </div>
          </div>

          <a class="ccsf2-atc" href="<?php echo esc_url($add_href); ?>" data-go="<?php echo esc_url(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/')); ?>">
            <?php echo esc_html($cta_label); ?>
          </a>

        </div>
      </article>
    <?php return ob_get_clean();
  }

  // ---------- Vendor rating (best-effort, light) ----------
  private static function vendor_rating_avg(int $vendor_id): string {
    if (!function_exists('wc_get_products')) return '';
    // Best-effort: compute avg of top 20 products' avg ratings (not perfect, but stable for v1)
    $prods = wc_get_products([
      'status' => 'publish',
      'limit'  => 20,
      'orderby'=> 'date',
      'order'  => 'DESC',
      'return' => 'objects',
      'author' => $vendor_id,
    ]);
    if (empty($prods)) return '';
    $sum = 0.0; $n = 0;
    foreach ($prods as $p) {
      $r = (float) $p->get_average_rating();
      if ($r > 0) { $sum += $r; $n++; }
    }
    if ($n === 0) return '';
    return number_format($sum / $n, 1);
  }

  private static function vendor_rating_count(int $vendor_id): string {
    if (!function_exists('wc_get_products')) return '';
    $prods = wc_get_products([
      'status' => 'publish',
      'limit'  => 50,
      'return' => 'objects',
      'author' => $vendor_id,
    ]);
    if (empty($prods)) return '';
    $cnt = 0;
    foreach ($prods as $p) { $cnt += (int) $p->get_rating_count(); }
    return $cnt > 0 ? (string)$cnt : '';
  }

  // ---------- Policy copy (mirror customer message) ----------
  private static function policy_copy(): string {
    // Keep neutral and consistent with customer messaging.
    $html = '<ul class="ccsf2-policy-list">';
    $html .= '<li><strong>Returns:</strong> Returns follow Caricove’s customer policy and eligibility rules.</li>';
    $html .= '<li><strong>Cancellations:</strong> Cancellations are allowed before dispatch where applicable.</li>';
    $html .= '<li><strong>Support:</strong> Use the contact options below for help with orders and issues.</li>';
    $html .= '</ul>';
    return $html;
  }

  // ---------- CSS/JS ----------
  private static function css(): string {
    return <<<CSS
:root{
  --cc-ink:#eaf2ff;
  --cc-sub:rgba(234,242,255,.78);
  --cc-dim:rgba(234,242,255,.62);
  --cc-bor:rgba(120,170,255,.22);
  --cc-surface:rgba(5,20,45,.58);
  --cc-surface2:rgba(8,24,55,.62);
  --cc-blue:#2aa3ff;
  --cc-blue2:#1d6fff;
  --cc-gold:#f5d27a;
  --cc-gold2:#e6b84f;
  --cc-bg:#070b18;
}

.ccsf2-wrap{max-width:1280px;margin:0 auto;padding:18px 16px;color:var(--cc-ink)}
.ccsf2-card{background:var(--cc-surface);border:1px solid var(--cc-bor);border-radius:20px;padding:18px;backdrop-filter: blur(12px)}
.ccsf2-center{text-align:center}
.ccsf2-h{font-size:20px;font-weight:900}
.ccsf2-p{color:var(--cc-sub);margin-top:6px}
.ccsf2-btn{display:inline-block;margin-top:12px;padding:12px 16px;border-radius:16px;background:rgba(20,80,255,.22);border:1px solid rgba(120,170,255,.35);color:var(--cc-ink);text-decoration:none;font-weight:900}

/* HERO */
.ccsf2-hero{
  position:relative;
  border-radius:22px;
  overflow:hidden;
  min-height:240px;
  background:
    radial-gradient(1200px 280px at 20% 20%, rgba(42,163,255,.35), transparent 60%),
    radial-gradient(900px 320px at 70% 30%, rgba(245,210,122,.18), transparent 60%),
    linear-gradient(135deg, rgba(12,24,60,.9), rgba(5,20,45,.9));
  background-size:cover;
  background-position:center;
  border:1px solid rgba(120,170,255,.18);
}
.ccsf2-hero-overlay{
  position:absolute;inset:0;
  background:linear-gradient(180deg, rgba(0,0,0,.10), rgba(0,0,0,.62));
}
.ccsf2-hero-inner{position:relative;z-index:2;height:100%;display:flex;align-items:flex-end;padding:18px}
.ccsf2-hero-box{
  width:min(760px, 100%);
  margin:0 auto;
  background:rgba(5,20,45,.58);
  border:1px solid rgba(120,170,255,.22);
  border-radius:20px;
  backdrop-filter: blur(14px);
  padding:16px 16px 14px;
  box-shadow: 0 18px 42px rgba(0,0,0,.35);
}
.ccsf2-brand{display:flex;gap:12px;align-items:center}
.ccsf2-logo{width:64px;height:64px;border-radius:18px;overflow:hidden;border:1px solid rgba(255,255,255,.10);background:rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center}
.ccsf2-logo img{width:100%;height:100%;object-fit:cover}
.ccsf2-logo-fallback{font-weight:900;font-size:28px}
.ccsf2-store-name{font-weight:900;font-size:22px;line-height:1.1}
.ccsf2-tagline{color:var(--cc-sub);margin-top:4px;font-size:13px}

.ccsf2-hero-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.ccsf2-pill{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 12px;border-radius:999px;background:rgba(10,30,70,.55);border:1px solid rgba(120,170,255,.25);color:var(--cc-ink);text-decoration:none;font-weight:900;font-size:13px}
.ccsf2-primary{background:rgba(20,80,255,.25);border-color:rgba(120,170,255,.35)}

.ccsf2-status{margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.ccsf2-status-pill{padding:8px 10px;border-radius:999px;background:rgba(42,163,255,.18);border:1px solid rgba(42,163,255,.35);font-weight:900}
.ccsf2-status-pill.is-vac{background:rgba(245,210,122,.16);border-color:rgba(245,210,122,.35)}
.ccsf2-status-sub{color:var(--cc-sub);font-size:13px}

/* TRUST STRIP */
.ccsf2-trust{
  margin-top:14px;
  background:var(--cc-surface);
  border:1px solid rgba(120,170,255,.18);
  border-radius:16px;
  padding:12px 14px;
  display:flex;
  gap:18px;
  align-items:center;
  justify-content:space-between;
  flex-wrap:wrap;
}
.ccsf2-trust-item{display:flex;gap:10px;align-items:center;min-width:220px}
.ccsf2-trust-text{line-height:1.05}
.ccsf2-trust-top{font-weight:900}
.ccsf2-trust-sub{color:var(--cc-dim);font-size:12px;margin-top:4px}

/* Icons (medal w/ blue ribbon + white mid) */
.ccsf2-ico{width:34px;height:34px;display:inline-block;position:relative;flex:0 0 34px}
.ccsf2-ico.medal::before{
  content:"";position:absolute;left:9px;top:6px;width:16px;height:16px;border-radius:999px;
  background:linear-gradient(135deg,var(--cc-gold),var(--cc-gold2));
  box-shadow:0 0 16px rgba(230,184,79,.35);
  border:1px solid rgba(255,215,130,.7);
}
.ccsf2-ico.medal::after{
  content:"";position:absolute;left:10px;top:11px;width:14px;height:8px;border-left:3px solid rgba(255,255,255,.95);border-bottom:3px solid rgba(255,255,255,.95);
  transform:rotate(-45deg);
}
.ccsf2-ico.medal{background:
  linear-gradient(180deg, rgba(42,163,255,.95), rgba(29,111,255,.95)) 6px 0 / 10px 10px no-repeat,
  linear-gradient(180deg, rgba(255,255,255,.95), rgba(255,255,255,.95)) 16px 0 / 6px 10px no-repeat,
  linear-gradient(180deg, rgba(42,163,255,.95), rgba(29,111,255,.95)) 22px 0 / 10px 10px no-repeat;
  border-radius:10px;
}

.ccsf2-ico.star::before{content:"★";position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--cc-gold);font-size:22px;text-shadow:0 0 14px rgba(230,184,79,.35)}
.ccsf2-ico.recycle::before{content:"♻";position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--cc-gold);font-size:20px}
.ccsf2-ico.bolt::before{content:"⚡";position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--cc-gold);font-size:20px}

/* MEDIA */
.ccsf2-media{margin-top:18px}
.ccsf2-media-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;margin-bottom:10px}
.ccsf2-h3{font-size:18px;font-weight:900}
.ccsf2-muted{color:var(--cc-dim);font-size:13px}
.ccsf2-carousel{
  display:flex;gap:12px;overflow:auto;padding-bottom:8px;
  scroll-snap-type:x mandatory;
}
.ccsf2-slide{flex:0 0 320px;max-width:320px;aspect-ratio:16/9;border-radius:18px;overflow:hidden;border:1px solid rgba(120,170,255,.18);background:rgba(0,0,0,.18);scroll-snap-align:start}
.ccsf2-slide img{width:100%;height:100%;object-fit:cover}
.ccsf2-slide-empty{display:flex;align-items:center;justify-content:center}
.ccsf2-empty{color:var(--cc-dim);font-weight:900}

/* PRODUCTS */
.ccsf2-products{margin-top:18px}
.ccsf2-products-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.ccsf2-filterbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.ccsf2-search{width:280px;max-width:90vw;padding:10px 12px;border-radius:14px;border:1px solid rgba(120,170,255,.18);background:rgba(255,255,255,.90);color:#000;font-weight:800}
.ccsf2-sort{padding:10px 12px;border-radius:14px;border:1px solid rgba(120,170,255,.18);background:rgba(10,30,70,.55);color:var(--cc-ink);font-weight:900}

.ccsf2-grid{margin-top:14px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
@media(max-width:1100px){.ccsf2-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:820px){.ccsf2-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.ccsf2-grid{grid-template-columns:1fr}}

.ccsf2-pcard{
  border-radius:20px;
  background:var(--cc-surface);
  border:1px solid rgba(120,170,255,.18);
  overflow:hidden;
  box-shadow:0 10px 26px rgba(0,0,0,.28);
}
.ccsf2-pimg{display:block;background:#fff;aspect-ratio:1/1}
.ccsf2-pimg img{width:100%;height:100%;object-fit:contain}
.ccsf2-pmeta{padding:12px 12px 14px}
.ccsf2-ptitle{font-weight:900;color:var(--cc-ink);font-size:14px;line-height:1.2;min-height:34px}
.ccsf2-prating{margin-top:6px;display:flex;gap:6px;align-items:center;color:var(--cc-sub);font-weight:900}
.ccsf2-star{color:var(--cc-gold);text-shadow:0 0 14px rgba(230,184,79,.25)}
.ccsf2-price{margin-top:6px;color:#fff;font-weight:900}

/* Variation strip: connected boxes */
.ccsf2-varrow{margin-top:10px;display:flex;gap:8px;align-items:stretch}
.ccsf2-vars{display:flex;gap:6px;flex:1}
.ccsf2-var{flex:1;min-width:0;height:28px;border-radius:10px;border:1px solid rgba(120,170,255,.20);background:rgba(255,255,255,.10)}
.ccsf2-var.is-empty{opacity:.55}

.ccsf2-sizebox{
  width:74px;flex:0 0 74px;
  display:flex;align-items:center;justify-content:space-between;gap:6px;
  padding:6px 8px;border-radius:12px;border:1px solid rgba(120,170,255,.20);
  background:rgba(10,30,70,.55);
}
.ccsf2-sizeval{font-size:11px;font-weight:900;color:var(--cc-ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ccsf2-arrows{display:flex;flex-direction:column;gap:4px}
.ccsf2-arrow{width:22px;height:12px;border-radius:6px;border:1px solid rgba(120,170,255,.22);background:rgba(255,255,255,.06);color:var(--cc-ink);font-size:10px;line-height:10px;padding:0}

/* CTA: connected button */
.ccsf2-atc{
  display:block;margin-top:10px;
  padding:12px 12px;border-radius:14px;
  background:linear-gradient(90deg, rgba(42,163,255,.85), rgba(29,111,255,.85));
  color:#fff;text-align:center;text-decoration:none;font-weight:900;
  box-shadow:0 0 18px rgba(30,144,255,.35);
}
.ccsf2-atc:hover{filter:brightness(1.08)}

/* ABOUT */
.ccsf2-about{margin-top:22px}
.ccsf2-about-box{
  margin-top:10px;
  background:var(--cc-surface);
  border:1px solid rgba(120,170,255,.18);
  border-radius:18px;
  padding:14px;
  position:relative;
  max-height:140px;
  overflow:hidden;
}
.ccsf2-about-box.is-open{max-height:none}
.ccsf2-about-text{color:var(--cc-sub);font-size:14px;line-height:1.45}
.ccsf2-about-more{
  margin-top:10px;
  background:transparent;
  border:0;
  color:#7fb3ff;
  font-weight:900;
  cursor:pointer;
}
.ccsf2-about-box:not(.is-open)::after{
  content:"";
  position:absolute;left:0;right:0;bottom:0;height:64px;
  background:linear-gradient(180deg, transparent, rgba(5,20,45,.92));
}

/* POLICY */
.ccsf2-policy{margin-top:22px}
.ccsf2-policy-grid{margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:820px){.ccsf2-policy-grid{grid-template-columns:1fr}}
.ccsf2-panel{background:var(--cc-surface);border:1px solid rgba(120,170,255,.18);border-radius:18px;padding:14px}
.ccsf2-panel-title{font-weight:900;margin-bottom:8px}
.ccsf2-panel-body{color:var(--cc-sub);font-size:14px;line-height:1.45}
.ccsf2-policy-list{margin:0;padding-left:18px}
.ccsf2-policy-list li{margin:6px 0}
.ccsf2-panel-note{margin-top:10px;color:var(--cc-sub);font-size:13px}
.ccsf2-row{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid rgba(120,170,255,.10)}
.ccsf2-row:last-child{border-bottom:0}

/* SUPPORT */
.ccsf2-support{margin-top:22px}
.ccsf2-support-card{background:var(--cc-surface2);border:1px solid rgba(120,170,255,.18);border-radius:20px;padding:16px;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
.ccsf2-support-actions{display:flex;gap:10px;flex-wrap:wrap}
CSS;
  }

  private static function js(): string {
    $cart = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
    return <<<JS
(function(){
  // About toggle
  document.querySelectorAll('[data-ccsf2-about]').forEach(function(box){
    var btn = box.querySelector('[data-ccsf2-about-toggle]');
    if(!btn) return;
    btn.addEventListener('click', function(){
      box.classList.toggle('is-open');
      btn.textContent = box.classList.contains('is-open') ? 'Show less' : 'Read more';
    });
  });

  // Size up/down (visual only)
  document.querySelectorAll('.ccsf2-sizebox').forEach(function(box){
    var sizes = [];
    try{ sizes = JSON.parse(box.getAttribute('data-sizes')||'[]')||[]; }catch(e){ sizes=[]; }
    if(!Array.isArray(sizes) || !sizes.length) return;

    var idx = 0;
    var val = box.querySelector('.ccsf2-sizeval');
    var up = box.querySelector('.ccsf2-arrow.up');
    var dn = box.querySelector('.ccsf2-arrow.down');
    function render(){ if(val) val.textContent = sizes[idx] || 'Size'; }
    render();
    if(up) up.addEventListener('click', function(e){ e.preventDefault(); idx = (idx+1) % sizes.length; render(); });
    if(dn) dn.addEventListener('click', function(e){ e.preventDefault(); idx = (idx-1+sizes.length) % sizes.length; render(); });
  });

  // Filters: search + sort (client-side)
  var grid = document.querySelector('[data-ccsf2-grid]');
  if(grid){
    var cards = Array.prototype.slice.call(grid.querySelectorAll('.ccsf2-pcard'));
    var search = document.querySelector('[data-ccsf2-search]');
    var sort = document.querySelector('[data-ccsf2-sort]');

    function apply(){
      var q = (search && search.value ? search.value.toLowerCase().trim() : '');
      cards.forEach(function(c){
        var t = c.getAttribute('data-title') || '';
        c.style.display = (!q || t.indexOf(q) !== -1) ? '' : 'none';
      });
      applySort();
    }

    function numAttr(el, name){
      var v = el.getAttribute(name);
      if(!v) return NaN;
      var n = parseFloat(v);
      return isNaN(n) ? NaN : n;
    }

    function applySort(){
      if(!sort) return;
      var mode = sort.value || 'new';
      var visible = cards.filter(function(c){ return c.style.display !== 'none'; });

      visible.sort(function(a,b){
        if(mode === 'price_asc'){
          return (numAttr(a,'data-price')||0) - (numAttr(b,'data-price')||0);
        }
        if(mode === 'price_desc'){
          return (numAttr(b,'data-price')||0) - (numAttr(a,'data-price')||0);
        }
        if(mode === 'rating'){
          return (numAttr(b,'data-rating')||0) - (numAttr(a,'data-rating')||0);
        }
        return 0; // 'new' keeps DOM order
      });

      visible.forEach(function(c){ grid.appendChild(c); });
    }

    if(search) search.addEventListener('input', apply);
    if(sort) sort.addEventListener('change', applySort);
  }

  // Add-to-cart behavior: redirect to cart after add (simple products)
  document.addEventListener('click', async function(e){
    var a = e.target.closest('.ccsf2-atc');
    if(!a) return;

    // If seller away link, allow navigation
    var wrap = document.querySelector('.ccsf2-wrap');
    if(wrap && wrap.getAttribute('data-vac') === '1'){
      return;
    }

    // If href is a normal product page, allow navigation
    var href = a.getAttribute('href') || '';
    if(href.indexOf('add-to-cart=') === -1) return;

    e.preventDefault();
    try{
      await fetch(href, { credentials:'same-origin', redirect:'follow' });
    }catch(err){}
    window.location.href = '{$cart}' + (window.location.href.indexOf('?')!==-1 ? '&' : '?') + 'ccv=' + Date.now();
  }, true);

})();
JS;
  }
}

Caricove_Vendor_Storefront_V2::boot();
