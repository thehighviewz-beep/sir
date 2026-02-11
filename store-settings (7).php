<?php
/**
 * Caricove — Store Settings (Caribbean) — WORKING FINAL (MU)
 * Shortcode: [caricove_store_settings]
 *
 * Drop this file at:
 * /wp-content/mu-plugins/caricove-store-settings.php
 *
 * IMPORTANT: remove/disable older duplicates.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('ccss_country_data')) {
    function ccss_country_data(): array {
        return [
            'GY' => ['name'=>'Guyana','sub_label'=>'Region','sub_options'=>['Region 1','Region 2','Region 3','Region 4','Region 5','Region 6','Region 7','Region 8','Region 9','Region 10']],
            'HT' => ['name'=>'Haiti','sub_label'=>'Department','sub_options'=>['Artibonite','Centre','Grand Anse','Nippes','Nord','Nord-Est','Nord-Ouest','Ouest','Sud','Sud-Est']],
            'TT' => ['name'=>'Trinidad & Tobago','sub_label'=>'Region','sub_options'=>['Port of Spain','San Fernando','Arima','Chaguanas','Couva-Tabaquite-Talparo','Diego Martin','Mayaro-Rio Claro','Penal-Debe','Point Fortin','Princes Town','Sangre Grande','San Juan-Laventille','Siparia','Tunapuna-Piarco','Tobago']],
            'JM' => ['name'=>'Jamaica','sub_label'=>'Parish','sub_options'=>['Kingston','St Andrew','St Thomas','Portland','St Mary','St Ann','Trelawny','St James','Hanover','Westmoreland','St Elizabeth','Manchester','Clarendon','St Catherine']],
            'BB' => ['name'=>'Barbados','sub_label'=>'Parish','sub_options'=>['Christ Church','Saint Andrew','Saint George','Saint James','Saint John','Saint Joseph','Saint Lucy','Saint Michael','Saint Peter','Saint Philip','Saint Thomas']],
            'AG' => ['name'=>'Antigua & Barbuda','sub_label'=>'Parish','sub_options'=>['Saint George','Saint John','Saint Mary','Saint Paul','Saint Peter','Saint Philip','Barbuda','Redonda']],
            'LC' => ['name'=>'Saint Lucia','sub_label'=>'District','sub_options'=>['Anse la Raye','Canaries','Castries','Choiseul','Dennery','Gros Islet','Laborie','Micoud','Soufriere','Vieux Fort']],
            'GD' => ['name'=>'Grenada','sub_label'=>'Parish','sub_options'=>['Saint Andrew','Saint David','Saint George','Saint John','Saint Mark','Saint Patrick','Carriacou & Petite Martinique']],
            'VC' => ['name'=>'St Vincent & the Grenadines','sub_label'=>'Parish','sub_options'=>['Charlotte','Grenadines','Saint Andrew','Saint David','Saint George','Saint Patrick']],
            'DM' => ['name'=>'Dominica','sub_label'=>'Parish','sub_options'=>['Saint Andrew','Saint David','Saint George','Saint John','Saint Joseph','Saint Luke','Saint Mark','Saint Patrick','Saint Paul','Saint Peter']],
            'KN' => ['name'=>'St Kitts & Nevis','sub_label'=>'Parish','sub_options'=>['Christ Church Nichola Town','Saint Anne Sandy Point','Saint George Basseterre','Saint George Gingerland','Saint James Windward','Saint John Capisterre','Saint John Figtree','Saint Mary Cayon','Saint Paul Capisterre','Saint Paul Charlestown','Saint Peter Basseterre','Saint Thomas Lowland','Saint Thomas Middle Island','Trinity Palmetto Point']],
            'BS' => ['name'=>'The Bahamas','sub_label'=>'Island / District','sub_options'=>['New Providence','Grand Bahama','Abaco','Andros','Eleuthera','Exuma','Long Island','Cat Island','Bimini','Berry Islands','Inagua','Mayaguana','Ragged Island','San Salvador','Acklins & Crooked Island']],
            'BZ' => ['name'=>'Belize','sub_label'=>'District','sub_options'=>['Belize','Cayo','Corozal','Orange Walk','Stann Creek','Toledo']],
            'SR' => ['name'=>'Suriname','sub_label'=>'District','sub_options'=>['Brokopondo','Commewijne','Coronie','Marowijne','Nickerie','Para','Paramaribo','Saramacca','Sipaliwini','Wanica']],
            'KY' => ['name'=>'Cayman Islands','sub_label'=>'District','sub_options'=>['George Town','West Bay','Bodden Town','North Side','East End','Sister Islands']],
        ];
    }
}

if (!shortcode_exists('caricove_store_settings')) {
    add_shortcode('caricove_store_settings', function () {

        if (!is_user_logged_in()) return '';

        $uid  = get_current_user_id();
        $DATA = ccss_country_data();

        // === Load metas ===
        $store_name   = (string)get_user_meta($uid,'_cc_store_name',true);
        $tagline      = (string)get_user_meta($uid,'_cc_store_tagline',true);
        $public_email = (string)get_user_meta($uid,'_cc_store_public_email',true);
        $public_phone = (string)get_user_meta($uid,'_cc_store_public_phone',true);
        $about        = (string)get_user_meta($uid,'_cc_store_about',true);
        $instagram    = (string)get_user_meta($uid,'_cc_store_instagram',true);
        $website      = (string)get_user_meta($uid,'_cc_store_website',true);

        $vac_enabled   = (string)get_user_meta($uid,'_cc_store_vacation_enabled',true);
        $vac_message   = (string)get_user_meta($uid,'_cc_store_vacation_message',true);
        $support_email = (string)get_user_meta($uid,'_cc_store_support_email',true);
        $avail_notice  = (string)get_user_meta($uid,'_cc_store_availability_notice',true);
        $updated_at    = (string)get_user_meta($uid,'_cc_store_settings_updated_at',true);

        $street1       = (string)get_user_meta($uid,'_cc_store_street_1',true);
        $street2       = (string)get_user_meta($uid,'_cc_store_street_2',true);
        $city          = (string)get_user_meta($uid,'_cc_store_city',true);
        $postcode      = (string)get_user_meta($uid,'_cc_store_postcode',true);
        $country_code  = (string)get_user_meta($uid,'_cc_store_country_code',true);
        if ($country_code==='' || !isset($DATA[$country_code])) $country_code='GY';

        // Store KEY and LABEL separately
        $subdivision_key   = (string)get_user_meta($uid,'_cc_store_subdivision',true);
        $subdivision_label = (string)get_user_meta($uid,'_cc_store_subdivision_label',true);

        $logo_id   = (int)get_user_meta($uid,'_cc_store_logo_id',true);
        $banner_id = (int)get_user_meta($uid,'_cc_store_banner_id',true);

        $notice = '';
        $notice_class = 'ok';

        // === Handle POST ===
        $did_submit = ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ccss_save']));
        if ($did_submit) {

            $nonce = (string)($_POST['ccss_nonce'] ?? '');
            if ($nonce==='' || !wp_verify_nonce($nonce,'ccss_save')) {
                $notice='Security token expired. Please reload and try again.';
                $notice_class='warn';
            } else {

                $n_store = sanitize_text_field((string)($_POST['store_name'] ?? ''));
                $n_tag   = sanitize_text_field((string)($_POST['tagline'] ?? ''));
                $n_eml   = sanitize_email((string)($_POST['public_email'] ?? ''));
                $n_ph    = sanitize_text_field((string)($_POST['public_phone'] ?? ''));
                $n_abt   = sanitize_textarea_field((string)($_POST['about'] ?? ''));
                $n_ig    = esc_url_raw((string)($_POST['instagram'] ?? ''));
                $n_web   = esc_url_raw((string)($_POST['website'] ?? ''));

                $n_vac     = !empty($_POST['vac_enabled']) ? 'yes' : 'no';
                $n_vac_msg = sanitize_text_field((string)($_POST['vac_message'] ?? ''));
                $n_support = sanitize_email((string)($_POST['support_email'] ?? ''));
                $n_avail   = sanitize_text_field((string)($_POST['availability_notice'] ?? ''));

                $n_st1  = sanitize_text_field((string)($_POST['street1'] ?? ''));
                $n_st2  = sanitize_text_field((string)($_POST['street2'] ?? ''));
                $n_city = sanitize_text_field((string)($_POST['city'] ?? ''));
                $n_post = sanitize_text_field((string)($_POST['postcode'] ?? ''));
                $n_cc   = sanitize_text_field((string)($_POST['country_code'] ?? 'GY'));
                if ($n_cc==='' || !isset($DATA[$n_cc])) $n_cc='GY';

                $cfg = $DATA[$n_cc] ?? $DATA['GY'];
                $sub_opts = (array)($cfg['sub_options'] ?? []);
                $needs_sub = !empty($sub_opts);

                // Posted KEY/index
                $n_sub_key = sanitize_text_field((string)($_POST['subdivision'] ?? ''));
                $n_sub_key = trim($n_sub_key);
                $low = strtolower($n_sub_key);
                if ($low==='select' || $low==='select...' || $low==='select…') $n_sub_key='';

                // Compute LABEL from the posted key/index (never trust hidden field)
                $n_sub_label = '';
                if ($needs_sub && $n_sub_key!=='') {
                    if (ctype_digit($n_sub_key)) {
                        $idx = (int)$n_sub_key;
                        if (isset($sub_opts[$idx])) $n_sub_label = (string)$sub_opts[$idx];
                    } else {
                        // If key posted as exact label, accept
                        if (in_array($n_sub_key, $sub_opts, true)) $n_sub_label = (string)$n_sub_key;
                    }
                }

                // Required fields (street2 optional)
                $missing = [];
                if (trim($n_st1)==='') $missing[]='Street address';
                if (trim($n_city)==='') $missing[]='City';
                if (trim($n_post)==='') $missing[]='Postcode';
                if ($n_cc==='' || !isset($DATA[$n_cc])) $missing[]='Country';
                if ($needs_sub && $n_sub_key==='') $missing[]=$cfg['sub_label'];

                if (empty($missing) && $n_support!=='' && !is_email($n_support)) {
                    $missing[]='Support email (invalid)';
                }

                if (!empty($missing)) {
                    $notice = 'Please fill: '.implode(', ', $missing).'.';
                    $notice_class='warn';

                    // Persist typed values on render (no empty refresh)
                    $store_name=$n_store; $tagline=$n_tag; $public_email=$n_eml; $public_phone=$n_ph;
                    $about=$n_abt; $instagram=$n_ig; $website=$n_web;
                    $vac_enabled=$n_vac; $vac_message=$n_vac_msg; $support_email=$n_support; $avail_notice=$n_avail;
                    $street1=$n_st1; $street2=$n_st2; $city=$n_city; $postcode=$n_post; $country_code=$n_cc;
                    $subdivision_key=$n_sub_key; $subdivision_label=$n_sub_label;

                } else {

                    // Save metas
                    update_user_meta($uid,'_cc_store_name',$n_store);
                    update_user_meta($uid,'_cc_store_tagline',$n_tag);
                    update_user_meta($uid,'_cc_store_public_email',$n_eml);
                    update_user_meta($uid,'_cc_store_public_phone',$n_ph);
                    update_user_meta($uid,'_cc_store_about',$n_abt);
                    update_user_meta($uid,'_cc_store_instagram',$n_ig);
                    update_user_meta($uid,'_cc_store_website',$n_web);

                    update_user_meta($uid,'_cc_store_vacation_enabled',$n_vac);
                    update_user_meta($uid,'_cc_store_vacation_message',$n_vac_msg);
                    update_user_meta($uid,'_cc_store_support_email',$n_support);
                    update_user_meta($uid,'_cc_store_availability_notice',$n_avail);

                    update_user_meta($uid,'_cc_store_street_1',$n_st1);
                    update_user_meta($uid,'_cc_store_street_2',$n_st2);
                    update_user_meta($uid,'_cc_store_city',$n_city);
                    update_user_meta($uid,'_cc_store_postcode',$n_post);
                    update_user_meta($uid,'_cc_store_country_code',$n_cc);

                    update_user_meta($uid,'_cc_store_subdivision',$n_sub_key);
                    update_user_meta($uid,'_cc_store_subdivision_label',$n_sub_label);

                    update_user_meta($uid,'_cc_store_logo_id', (int)($_POST['logo_id'] ?? $logo_id));
                    update_user_meta($uid,'_cc_store_banner_id', (int)($_POST['banner_id'] ?? $banner_id));

                    update_user_meta($uid,'_cc_store_settings_updated_at', current_time('mysql'));

                    // Refresh render vars from posted values
                    $store_name=$n_store; $tagline=$n_tag; $public_email=$n_eml; $public_phone=$n_ph;
                    $about=$n_abt; $instagram=$n_ig; $website=$n_web;
                    $vac_enabled=$n_vac; $vac_message=$n_vac_msg; $support_email=$n_support; $avail_notice=$n_avail;
                    $street1=$n_st1; $street2=$n_st2; $city=$n_city; $postcode=$n_post; $country_code=$n_cc;
                    $subdivision_key=$n_sub_key; $subdivision_label=$n_sub_label;
                    $logo_id = (int)($_POST['logo_id'] ?? $logo_id);
                    $banner_id = (int)($_POST['banner_id'] ?? $banner_id);

                    $notice='Saved.';
                    $notice_class='ok';
                    $updated_at = current_time('mysql');
                }
            }
        }

        // Render config
        $cfg = $DATA[$country_code] ?? $DATA['GY'];
        $sub_label = (string)$cfg['sub_label'];
        $sub_options = (array)$cfg['sub_options'];

if (function_exists('wp_enqueue_media') && current_user_can('upload_files')) {
    wp_enqueue_media();
}
        // Build preview lines (server-side persisted)
        $country_name = $DATA[$country_code]['name'] ?? 'Guyana';
        $ra = [];
        if (trim($store_name)!=='') $ra[] = trim($store_name);
        if (trim($street1)!=='') $ra[] = trim($street1);
        if (trim($street2)!=='') $ra[] = trim($street2);
        $line = trim($city);
        if (trim($postcode)!=='') $line .= ($line ? ', ' : '') . trim($postcode);
        if ($line!=='') $ra[] = $line;
        if (trim($country_name)!=='') $ra[] = $country_name;
        if (trim($subdivision_label)!=='') $ra[] = trim($subdivision_label);

        $has_real = (trim($street1) !== '' || trim($city) !== '' || trim($postcode) !== '' || trim($subdivision_label) !== '');
        $preview_lines = $has_real ? implode("\n", array_filter($ra)) : ("123 Example Street\nGeorgetown, 00000\n".$country_name."\n".$sub_label." 1");

        ob_start(); ?>
<div class="ccss ccss-v1">
  <div class="ccss-shell">
    <header class="ccss-head">
      <div class="ccss-title">
        <h1>Store Settings</h1>
        <p>Your public storefront details, availability, and pickup location.</p>
      </div>
      <button class="ccss-save-top" type="submit" form="ccssForm">Save changes</button>
    </header>

    <?php if ($notice!=='') : ?>
      <div class="notice <?php echo esc_attr($notice_class); ?>"><?php echo esc_html($notice); ?></div>
    <?php endif; ?>

    <form id="ccssForm" method="post" class="ccss-form">
      <?php wp_nonce_field('ccss_save','ccss_nonce'); ?>
      <input type="hidden" name="ccss_save" value="1" />

      <section class="ccss-card">
        <div class="ccss-card-hd"><h2>Store Status</h2><div class="ccss-card-sub">Control whether customers can place new orders.</div></div>
        <div class="ccss-grid2 ccss-status-grid">
          <div class="ccss-field ccss-toggleline">
            <label class="ccss-toggle">
              <input type="checkbox" name="vac_enabled" value="1" <?php checked($vac_enabled==='yes'); ?> />
              <span class="ccss-switch" aria-hidden="true"></span>
              <span class="ccss-toggle-txt">Vacation mode</span>
            </label>
            <div class="ccss-hint">When enabled, your products are hidden from catalog and cannot be purchased.</div>
          </div>
          <div class="ccss-field ccss-status-msg">
            <label>Customer message (optional)</label>
            <input type="text" name="vac_message" value="<?php echo esc_attr($vac_message); ?>" placeholder="We’re temporarily unavailable. Please check back soon." />
            <div class="ccss-hint">Shown on your product pages, cart, and checkout.</div>
          </div>
        </div>
      </section>

      <section class="ccss-card">
        <div class="ccss-card-hd"><h2>Branding</h2><div class="ccss-card-sub">These appear on your store page and listings.</div></div>
        <div class="ccss-grid2">
          <div class="ccss-field"><label>Store name</label><input type="text" name="store_name" value="<?php echo esc_attr($store_name); ?>"></div>
          <div class="ccss-field"><label>Tagline</label><input type="text" name="tagline" value="<?php echo esc_attr($tagline); ?>"></div>
        </div>
        <div class="ccss-grid2 ccss-media">
          <div class="ccss-field">
            <label>Store logo</label>
            <div class="ccss-media-row"><button type="button" class="ccss-btn-lite" data-media="logo">Choose</button><span class="ccss-hint">Square works best.</span></div>
            <div class="ccss-preview" data-preview="logo"><?php echo $logo_id ? wp_get_attachment_image($logo_id,'medium') : '<span>Logo preview</span>'; ?></div>
            <input type="hidden" name="logo_id" value="<?php echo (int)$logo_id; ?>">
          </div>
          <div class="ccss-field">
            <label>Store banner</label>
            <div class="ccss-media-row"><button type="button" class="ccss-btn-lite" data-media="banner">Choose</button><span class="ccss-hint">Wide image recommended.</span></div>
            <div class="ccss-preview ccss-preview-banner" data-preview="banner"><?php echo $banner_id ? wp_get_attachment_image($banner_id,'medium') : '<span>Banner preview</span>'; ?></div>
            <input type="hidden" name="banner_id" value="<?php echo (int)$banner_id; ?>">
          </div>
        </div>
      </section>

      <section class="ccss-card">
        <div class="ccss-card-hd"><h2>Contact</h2><div class="ccss-card-sub">Public contact appears on your store. Support email helps with returns and inquiries.</div></div>
        <div class="ccss-grid2">
          <div class="ccss-field"><label>Public email</label><input type="email" name="public_email" value="<?php echo esc_attr($public_email); ?>"></div>
          <div class="ccss-field"><label>Public phone</label><input type="text" name="public_phone" value="<?php echo esc_attr($public_phone); ?>"></div>
          <div class="ccss-field"><label>Support email (customers)</label><input type="email" name="support_email" value="<?php echo esc_attr($support_email); ?>"></div>
          <div class="ccss-field"><label>Availability notice (optional)</label><input type="text" name="availability_notice" value="<?php echo esc_attr($avail_notice); ?>"></div>
        </div>
      </section>

      <section class="ccss-card">
        <div class="ccss-card-hd"><h2>Storefront</h2><div class="ccss-card-sub">A short description helps customers trust your store.</div></div>
        <div class="ccss-field"><label>About</label><textarea name="about" rows="5"><?php echo esc_textarea($about); ?></textarea></div>
        <div class="ccss-grid2">
          <div class="ccss-field"><label>Instagram</label><input type="url" name="instagram" value="<?php echo esc_attr($instagram); ?>"></div>
          <div class="ccss-field"><label>Website</label><input type="url" name="website" value="<?php echo esc_attr($website); ?>"></div>
        </div>
      </section>

      <section class="ccss-card">
        <div class="ccss-card-hd"><h2>Pickup location</h2><div class="ccss-card-sub">Used for courier pickup and return routing.</div></div>
        <div class="ccss-grid2">
          <div class="ccss-field"><label>Street address</label><input type="text" name="street1" value="<?php echo esc_attr($street1); ?>"></div>
          <div class="ccss-field"><label>Street address 2</label><input type="text" name="street2" value="<?php echo esc_attr($street2); ?>"></div>
        </div>
        <div class="ccss-grid2">
          <div class="ccss-field"><label>City</label><input type="text" name="city" value="<?php echo esc_attr($city); ?>"></div>
          <div class="ccss-field"><label>Postcode</label><input type="text" name="postcode" value="<?php echo esc_attr($postcode); ?>"></div>
        </div>
        <div class="ccss-grid2">
          <div class="ccss-field">
            <label>Country</label>
            <select name="country_code" id="ccssCountry">
              <?php foreach ($DATA as $cc => $cdata): ?>
                <option value="<?php echo esc_attr($cc); ?>" <?php selected($country_code,$cc); ?>><?php echo esc_html($cdata['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ccss-field">
            <label><?php echo esc_html($sub_label); ?></label>
            <select name="subdivision" id="ccssRegion">
              <option value="">Select…</option>
              <?php foreach ($sub_options as $idx=>$lbl): ?>
                <option value="<?php echo esc_attr((string)$idx); ?>" <?php selected((string)$subdivision_key,(string)$idx); ?>><?php echo esc_html($lbl); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="ccss-split">
          <div class="ccss-previewbox">
            <div class="ccss-previewbox-hd">Return address (preview)</div>
            <div class="ccss-hint">This is the address customers will return items to.</div>
            <div class="ccss-previewtext <?php echo $has_real ? '' : 'ccss-is-placeholder'; ?>" id="ccssReturnPreview"><?php echo nl2br(esc_html($preview_lines)); ?></div>
            <div class="ccss-previewnote <?php echo $has_real ? 'ccss-hide' : ''; ?>" id="ccssReturnPreviewNote">Save your pickup address above to populate this preview.</div>
          </div>
          <div class="ccss-actions">
            <button class="ccss-btn-primary" type="submit">Save changes</button>
            <?php if (trim($updated_at)!=='') : ?>
              <div class="ccss-hint" style="margin-top:10px;">Last updated: <strong><?php echo esc_html($updated_at); ?></strong></div>
            <?php endif; ?>
          </div>
        </div>

      </section>
    </form>
  </div>
</div>

<style>
.ccss.ccss-v1{--ink:#0b1220;--muted:#66758d;--blue:#18a5ff;--blue2:#0b7bff;background:linear-gradient(180deg,#f5f2ec 0%,#eef4ff 35%,#f7f5f0 100%);border-radius:24px;padding:34px 18px}
.ccss-shell{max-width:1100px;margin:0 auto;font-family:Inter,system-ui}
.ccss-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:18px}
.ccss-title h1{margin:0;font-size:34px;line-height:1.1;font-weight:900;color:var(--ink)}








/* =========================================================
   WP MEDIA MODAL — Caricove polish (scoped)
   ========================================================= */

/* 1) Make the "Filter media" dropdown wide enough */
.media-frame select.attachment-filters,
.media-frame .media-toolbar select.attachment-filters,
.media-frame .media-toolbar .attachment-filters {
  min-width: 320px !important;
  width: 320px !important;
  max-width: 100% !important;
}

/* Also widen the taxonomy/search filter row so it doesn't squeeze */
.media-frame .media-toolbar-secondary {
  min-width: 360px !important;
}

/* 2) Upload files tab: black text, hover blue */
.media-frame .media-menu .media-menu-item,
.media-frame .media-router .media-menu-item {
  color: #0b1220 !important;          /* black-ish */
  font-weight: 800 !important;
  opacity: 0.92 !important;
}

.media-frame .media-menu .media-menu-item:hover,
.media-frame .media-router .media-menu-item:hover {
  color: #18a5ff !important;          /* Caricove blue */
  opacity: 1 !important;
}

.media-frame .media-menu .media-menu-item.active,
.media-frame .media-router .media-menu-item.active {
  color: #0b7bff !important;          /* deeper blue for active */
  opacity: 1 !important;
}

/* 3) Close X button: sleek small gradient-black box + glimmer blue X */
.media-modal .media-modal-close,
.media-modal .media-modal-close:hover,
.media-modal .media-modal-close:focus {
  background: linear-gradient(180deg, #0b1220 0%, #070b14 100%) !important;
  border: 1px solid rgba(24,165,255,0.35) !important;
  border-radius: 10px !important;
  width: 34px !important;
  height: 34px !important;
  top: 10px !important;
  right: 10px !important;
  box-shadow: 0 10px 28px rgba(0,0,0,0.35) !important;
  outline: none !important;
}

/* The X icon itself */
.media-modal .media-modal-close .media-modal-icon:before {
  color: #18a5ff !important;
  text-shadow:
    0 0 10px rgba(24,165,255,0.75),
    0 0 18px rgba(11,123,255,0.55) !important;
  font-weight: 900 !important;
}

/* Hover glow + subtle shimmer */
.media-modal .media-modal-close:hover {
  box-shadow:
    0 12px 34px rgba(0,0,0,0.45),
    0 0 0 4px rgba(24,165,255,0.14) !important;
  transform: translateY(-1px) !important;
}

.media-modal .media-modal-close:active {
  transform: translateY(0) !important;
}

/* Optional: make the tab row feel cleaner */
.media-frame .media-router {
  border-bottom: 1px solid rgba(0,0,0,0.08) !important;
}











/* === Pull Vacation mode section upward === */

/* Tighten the Store Status card spacing */
.ccss-card:has(.ccss-toggleline) {
  padding-top: 12px !important;
}

/* Pull the Vacation mode row upward */
.ccss-toggleline {
  margin-top: -14px !important;
}

/* Pull the description text closer to the toggle */
.ccss-toggleline .ccss-hint {
  margin-top: 4px !important;
  margin-left: 0 !important;
}

/* Reduce vertical gap under "Store Status" heading */
.ccss-card-hd + .ccss-status-grid {
  margin-top: -8px !important;
}



.ccss-field.ccss-status-msg{margin-top:-47px;}

/* === Move ONLY the vacation mode checkbox/switch === */



/* Move the actual checkbox/switch UP */
.ccss-toggleline .ccss-toggle input {
  position: relative;
  top: 16.5px;          /* 👈 adjust this (−6px to −10px) */
  left:-120px;
}

/* If you're using the custom switch pill, move that instead/as well */
.ccss-toggleline .ccss-switch {
  position: relative;
  top: -8px;          /* 👈 keep same value as above */
}



.ccss-title p{margin:6px 0 0;color:var(--muted);font-weight:650}
.ccss-save-top{background:linear-gradient(135deg,var(--blue),var(--blue2));color:#fff;border:0;border-radius:14px;padding:12px 18px;font-weight:900;box-shadow:0 14px 36px rgba(24,165,255,.22);cursor:pointer}
.ccss-form{display:flex;flex-direction:column;gap:16px}
.ccss-card{background:linear-gradient(180deg,rgba(255,255,255,.94),rgba(255,255,255,.80));border:1px solid rgba(16,40,90,.10);box-shadow:0 10px 26px rgba(10,20,40,.10);border-radius:18px;padding:18px}
.ccss-card-hd{margin-bottom:12px}
.ccss-card-hd h2{margin:0 0 4px;font-size:18px;font-weight:900;color:var(--ink)}
.ccss-card-sub{color:var(--muted);font-weight:650;font-size:13px}
.ccss-grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:flex-start}
.ccss-field label{display:block;font-weight:900;color:#101c33;margin-bottom:6px}
.ccss-field input,.ccss-field textarea,.ccss-field select{width:100%;padding:12px 14px;border-radius:14px;border:1px solid rgba(18,45,90,.18);background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(248,252,255,.92));color:#0b1220}
.ccss-hint{color:var(--muted);font-size:12.5px;margin-top:6px}
.notice{border-radius:16px;padding:12px 14px;margin:10px 0;font-weight:850}
.notice.ok{background:linear-gradient(180deg,rgba(16,185,129,.12),rgba(16,185,129,.06));border:1px solid rgba(16,185,129,.35)}
.notice.warn{background:linear-gradient(180deg,rgba(245,158,11,.14),rgba(245,158,11,.06));border:1px solid rgba(245,158,11,.35)}
.ccss-media .ccss-preview{height:180px;border-radius:16px;border:1px dashed rgba(18,45,90,.18);background:rgba(255,255,255,.65);display:flex;align-items:center;justify-content:center;overflow:hidden}
.ccss-preview img{width:100%;height:100%;object-fit:cover}
.ccss-preview-banner{height:240px}
.ccss-media-row{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.ccss-btn-lite{border:none;border-radius:14px;padding:10px 16px;font-weight:900;color:#fff;cursor:pointer;background:linear-gradient(135deg,#18a5ff,#0b7bff);box-shadow:0 12px 26px rgba(24,165,255,.25)}
.ccss-split{display:grid;grid-template-columns:1.2fr .8fr;gap:14px;margin-top:14px}
.ccss-previewbox{background:rgba(255,255,255,.72);border:1px solid rgba(18,45,90,.10);border-radius:16px;padding:14px}
.ccss-previewbox-hd{font-weight:950;color:#0b1220;margin-bottom:6px}
.ccss-previewtext{white-space:pre-line;margin-top:10px;font-weight:900;color:#0b1220;line-height:1.35}
.ccss-previewtext.ccss-is-placeholder{color:#b6c0d0;font-weight:700}
.ccss-previewnote{margin-top:10px;color:#94a3b8;font-weight:800}
.ccss-previewnote.ccss-hide{display:none}
.ccss-actions{background:rgba(255,255,255,.72);border:1px solid rgba(18,45,90,.10);border-radius:16px;padding:14px}
.ccss-btn-primary{width:100%;background:linear-gradient(135deg,var(--blue),var(--blue2));color:#fff;border:0;border-radius:14px;padding:12px 16px;font-weight:950;cursor:pointer;box-shadow:0 14px 36px rgba(24,165,255,.22)}
@media (max-width:900px){.ccss-head{flex-direction:column;align-items:stretch}.ccss-save-top{width:100%}.ccss-grid2{grid-template-columns:1fr}.ccss-split{grid-template-columns:1fr}}
</style>

<script>
(function(){
 // Media picker (logo / banner) — delegated + DOM-safe
document.addEventListener('DOMContentLoaded', function () {

  function openMediaPicker(type){
    if (!(window.wp && wp.media)) {
      console.warn('WP media not loaded');
      alert('Media Library is not available. Refresh the page and try again.');
      return;
    }

    const frame = wp.media({
      title: type === 'logo' ? 'Select store logo' : 'Select store banner',
      button: { text: 'Use this image' },
      multiple: false
    });

    frame.on('select', function(){
      const att = frame.state().get('selection').first();
      if(!att) return;

      const json = att.toJSON();
      const id = json.id || 0;

      // Prefer a crisp preview size when available
      let url = json.url || '';
      if (type === 'logo') {
        url = (json.sizes && json.sizes.medium && json.sizes.medium.url) ? json.sizes.medium.url : url;
        url = (json.sizes && json.sizes.thumbnail && json.sizes.thumbnail.url) ? json.sizes.thumbnail.url : url;
      } else {
        url = (json.sizes && json.sizes.large && json.sizes.large.url) ? json.sizes.large.url : url;
        url = (json.sizes && json.sizes.medium_large && json.sizes.medium_large.url) ? json.sizes.medium_large.url : url;
      }

      const input = document.querySelector('.ccss input[name="'+type+'_id"]');
      if (input) input.value = id;

      const prev = document.querySelector('.ccss [data-preview="'+type+'"]');
      if (prev) {
        prev.innerHTML = url
          ? '<img src="'+url+'" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:14px;">'
          : '<span>'+ (type==='logo'?'Logo':'Banner') +' preview</span>';
      }
    });

    frame.open();
  }

  // Delegated click (works even if DOM changes)
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.ccss .ccss-btn-lite[data-media]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();

    const type = btn.getAttribute('data-media'); // "logo" | "banner"
    if (type !== 'logo' && type !== 'banner') return;

    openMediaPicker(type);
  }, true);

});

  // Country -> region rebuild + live preview (single source of truth)
  var DATA = <?php echo wp_json_encode($DATA); ?>;
  var selCountry = document.getElementById('ccssCountry');
  var selRegion  = document.getElementById('ccssRegion');
  var prev = document.getElementById('ccssReturnPreview');
  var note = document.getElementById('ccssReturnPreviewNote');
  var fStreet1 = document.querySelector('.ccss input[name="street1"]');
  var fStreet2 = document.querySelector('.ccss input[name="street2"]');
  var fCity    = document.querySelector('.ccss input[name="city"]');
  var fPost    = document.querySelector('.ccss input[name="postcode"]');

  function optText(sel){
    if(!sel) return '';
    var o = (sel.options && sel.selectedIndex>=0) ? sel.options[sel.selectedIndex] : null;
    var t = o ? String(o.textContent||'').trim() : '';
    if(/^select\b/i.test(t)) return '';
    return t;
  }

  function rebuild(){
    if(!selCountry || !selRegion) return;
    var cc = selCountry.value || 'GY';
    var cfg = DATA[cc] || DATA['GY'];
    var labelEl = selRegion.closest('.ccss-field').querySelector('label');
    if(labelEl) labelEl.textContent = cfg.sub_label || 'Region';
    var keep = selRegion.value || '';
    selRegion.innerHTML = '';
    var opt0 = document.createElement('option'); opt0.value=''; opt0.textContent='Select…'; selRegion.appendChild(opt0);
    (cfg.sub_options || []).forEach(function(lbl, idx){
      var o = document.createElement('option'); o.value = String(idx); o.textContent = lbl; selRegion.appendChild(o);
    });
    if(keep) selRegion.value = keep;
  }

  function renderPreview(){
    if(!prev) return;
    var s1 = fStreet1 ? fStreet1.value.trim() : '';
    var s2 = fStreet2 ? fStreet2.value.trim() : '';
    var city = fCity ? fCity.value.trim() : '';
    var post = fPost ? fPost.value.trim() : '';
    var countryName = optText(selCountry) || 'Guyana';
    var reg = optText(selRegion);

    var has = !!(s1 || s2 || city || post || reg);
    if(!has){
      prev.classList.add('ccss-is-placeholder');
      if(note) note.classList.remove('ccss-hide');
      prev.textContent = '123 Example Street\nGeorgetown, 00000\n'+countryName+'\n'+(DATA[selCountry.value]?.sub_label || 'Region')+' 1';
      return;
    }
    prev.classList.remove('ccss-is-placeholder');
    if(note) note.classList.add('ccss-hide');

    var lines = [];
    var line1 = (s1 && s2) ? (s1 + ', ' + s2) : (s1 || s2);
    if(line1) lines.push(line1);
    var line2 = city;
    if(post) line2 += (line2 ? ', ' : '') + post;
    if(line2) lines.push(line2);
    lines.push(countryName);
    if(reg) lines.push(reg);
    prev.textContent = lines.join('\n');
  }

  if(selCountry) selCountry.addEventListener('change', function(){ rebuild(); renderPreview(); });
  if(selRegion) selRegion.addEventListener('change', renderPreview);
  ['input','keyup','change'].forEach(function(ev){
    [fStreet1,fStreet2,fCity,fPost].forEach(function(el){ if(el) el.addEventListener(ev, renderPreview); });
  });

  rebuild();
  renderPreview();

})();
</script>
<?php return (string)ob_get_clean();
    });
}

/* Vacation Mode + Availability Notice hooks omitted here for brevity; keep your existing file's hooks if needed. */
