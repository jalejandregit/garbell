<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * AJAX: Cargar configuración asociada a un startUrl
 * ==========================================================
 */
function garbell_load_config() {
    check_ajax_referer('garbell_ajax_nonce');

    $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
    if (empty($url)) {
        wp_send_json_success([
            'tagsExclusions' => '?showcomment | archive',
            'selectorEntries' => '//a[@id="Blog1_blog-pager-older-link"]',
            'selectorImages' => '//*[@itemprop="blogPost"]//a[img]',
            'selectorPages' => '//*[@id="Blog1"]/div[1]/div[7]/div/div'
        ]);
    }

    $option = 'garbell_' . sanitize_title($url);
    $config = get_option($option);

    if (!$config || !is_array($config)) {
        wp_send_json_success([
            'tagsExclusions' => '?showcomment | archive',
            'selectorEntries' => '//a[@id="Blog1_blog-pager-older-link"]',
            'selectorImages' => '//*[@itemprop="blogPost"]//a[img]',
            'selectorPages' => '//*[@id="Blog1"]/div[1]/div[7]/div/div'
        ]);
    }

    wp_send_json_success($config);
}
add_action('wp_ajax_garbell_load_config', 'garbell_load_config');



/**
 * ==========================================================
 * Página principal del colaborador
 * ==========================================================
 */
function garbell_contributor_page() {

    if (!current_user_can('edit_posts')) {
        wp_die(__('No tienes permisos para usar esta página.'));
    }

    /* -------------------------------------------------------
     * 1) PROCESO DE GUARDADO
     * ------------------------------------------------------- */

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('garbell_plantilles')) {
        $data =$_POST['startUrl'];
        //echo '<div class="updated"><p>Opcions desades a <strong>' . esc_html($data) . '</strong></p></div>';
         $error='';
        $url_test=$_POST['startUrl'] ?? '';
        if (isset($_POST['startUrl']) && !empty($_POST['startUrl'])) {
            //
          
            if (!filter_var($_POST['startUrl'], FILTER_VALIDATE_URL)) {
                $error='INVALID_URL + FILTER_VALIDATE_URL';
                //echo '<div class="notice notice-error is-dismissible inline notice-alt"><p><strong> FILTER_VALIDATE_URL La URL introduïda no és vàlida.</strong>.</p></div>';
            }

            //$regex = "/^(http|https|ftp):\/\/[^\s\/$.?#].[^\s]*$/i";
            //$regex = "/^(http|https):\/\/([A-Za-z0-9\-]+\.)+[A-Za-z]{2,}(:[0-9]{1,5})?(\/.*)?$/i"; //-> https://urtohijodelia
            $regex = "/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i";
            //$regex = "/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i"; 
            //$regex= "%^(?:(?:(?:https?|ftp):)?\/\/)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z0-9\x{00a1}-\x{ffff}][a-z0-9\x{00a1}-\x{ffff}_-]{0,62})?[a-z0-9\x{00a1}-\x{ffff}]\.)+(?:[a-z\x{00a1}-\x{ffff}]{2,}\.?))(?::\d{2,5})?(?:[/?#]\S*)?$%iuS";
            if (!preg_match($regex, $_POST['startUrl'])) {
                $error=$error.' --> REGEX';
            }

            //Si fins aqui no hi ha errors, fem una prova de connexió
            if($error==''){
                $ch = curl_init($_POST['startUrl']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                // Verifica si ocurre un error
                if(!curl_errno($ch)) {
                    //echo 'Error Curl : ' . curl_error($ch). "- CURL_TEST".curl_errno($ch);
                }else{
                    $error=$error.' CURL_ERROR';
                }
                curl_close($ch);
            }

        } else {
            $error=$error.'EMPTY_URL';
        }

        if ($error != '') {
            echo '<div class="notice notice-error is-dismissible inline notice-alt"><p><strong>No es poden desar les opcions; La url proporcionada es ('.$url_test.' ) es '.$error.'</strong>.</p></div>';
        } else{
            /* -------------------------------------------------------
             * GUARDAR CONFIGURACIÓN
             * ------------------------------------------------------- */
            $startUrl        = sanitize_text_field($_POST['startUrl']        ?? '');
            $tagsExclusions  = sanitize_textarea_field(wp_unslash($_POST['tagsExclusions']  ?? ''));
            $selectorEntries = sanitize_textarea_field(wp_unslash($_POST['selectorEntries'] ?? ''));
            $selectorImages  = sanitize_textarea_field(wp_unslash($_POST['selectorImages']  ?? ''));
            $selectorPages   = sanitize_textarea_field(wp_unslash($_POST['selectorPages']   ?? ''));

            $option_name = 'garbell_' . sanitize_title($startUrl);

            update_option($option_name, [
                'startUrl'        => $startUrl,
                'tagsExclusions'  => $tagsExclusions,
                'selectorEntries' => $selectorEntries,
                'selectorImages'  => $selectorImages,
                'selectorPages'   => $selectorPages
            ]);
            echo '<div class="updated"><p>Opcions desades a <strong>' . esc_html($data) . '</strong></p></div>';
        }


    }

    /* -------------------------------------------------------
     * 2) CARGAR CONFIGURACIÓN GUARDADA (según startUrl)
     * ------------------------------------------------------- */

    $saved_url = '';
    $saved_conf = [];

    foreach (wp_load_alloptions() as $key => $val) {
        if (strpos($key, 'garbell_') === 0) {

            $conf = get_option($key);
            if (is_array($conf) && !empty($conf['startUrl'])) {
                $saved_url = $conf['startUrl'];
                $saved_conf = $conf;
                break;
            }
        }
    }

    // Valores por defecto
    $saved_conf = wp_parse_args($saved_conf, [
        'startUrl'        => $saved_url,
        'tagsExclusions'  => '?showcomment | archive',
        'selectorEntries' => '//*[@id="Blog1"]/div[1]/div[7]/div/div',
        'selectorImages'  => '//*[@itemprop="blogPost"]//a[img]',
        'selectorPages'   => '//a[@id="Blog1_blog-pager-older-link"]'
    ]);

    // Ajustes generales (NO sobreescribimos saved_conf)
    $general_settings = get_option('garbell_settings', [
        'maxDepth' => 2,
        'maxPages' => 50,
        'maxExecutionSeconds' => 5
    ]);

    $username = wp_get_current_user()->user_login;
    $hora     = current_time('mysql');

    ?>

    <div class="wrap">
        <h1>Garbell - Col·laborador: <?php echo esc_html($username . ' ' . $hora); ?></h1>

        <form method="post">
            <?php wp_nonce_field('garbell_plantilles'); ?>

            <table class="garbell fixed">
                <thead>
                    <tr>
                        <th style="width: 10%;"></th>
                        <th style="width: 50%;">URL Scraper</th>
                        <th style="width: 20%;">Profunditat</th>
                        <th style="width: 20%;">Nº pàgines a extreure</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php submit_button('Desar configuració'); ?></td>
                        <td><?php combobox_urls_ajax($saved_conf['startUrl']); ?></td>
                        <td><input type="number" id="maxDepth" name="maxDepth" value="<?php echo esc_attr($general_settings['maxDepth']); ?>"></td>
                        <td><input type="number" id="maxPages" name="maxPages" value="<?php echo esc_attr($general_settings['maxPages']); ?>"></td>
                    </tr>
                </tbody>
            </table>

            <table class="garbell nested">
                <tr>
                    <td style="width:10%;font-weight:bold;">Tags exclusions</td>
                    <td style="width:90%;">
                        <input type="text"
                               id="tagsExclusions"
                               name="tagsExclusions"
                               style="width:100%;"
                               value="<?php echo esc_attr($saved_conf['tagsExclusions']); ?>">
                    </td>
                </tr>
                <tr>
                    <td style="font-weight:bold;">Selector entrades</td>
                    <td>
                        <input type="text"
                               id="selectorEntries"
                               name="selectorEntries"
                               style="width:100%;"
                               value="<?php echo esc_attr($saved_conf['selectorEntries']); ?>">
                    </td>
                </tr>
                <tr>
                    <td style="font-weight:bold;">Selector imatges</td>
                    <td>
                        <input type="text"
                               id="selectorImages"
                               name="selectorImages"
                               style="width:100%;"
                               value="<?php echo esc_attr($saved_conf['selectorImages']); ?>">
                    </td>
                </tr>
                <tr>
                    <td style="font-weight:bold;">Selector pàgines</td>
                    <td>
                        <input type="text"
                               id="selectorPages"
                               name="selectorPages"
                               style="width:100%;"
                               value="<?php echo esc_attr($saved_conf['selectorPages']); ?>">
                    </td>
                </tr>
            </table>
            <br>
            

            <div id="div-scraping"   style="display: flex; gap: 10px;">
                <button id="garbell-run" class="button button-primary" type="button">
                    Scraping
                </button>
                <img id="myLoaderGif" src="http://localhost/triapedres/wp-content/uploads/2025/11/lBsSZ.gif" alt="GIF de carga AJAX">
            </div>
        </form>

        <div id="garbell-results"></div>
    </div>

    <?php
}



/**
 * ==========================================================
 * AJAX: Ejecutar scraping
 * ==========================================================
 */
function garbell_ajax_run_scrape() {
    check_ajax_referer('garbell_nonce', 'nonce');

    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error('Permisos insuficientes', 403);
    }

    $startUrl        = esc_url_raw($_POST['startUrl'] ?? '');
    $maxDepth        = intval($_POST['maxDepth'] ?? 2);
    $maxPages        = intval($_POST['maxPages'] ?? 50);
    $tagsExclusions   = sanitize_text_field($_POST['tagsExclusions'] ?? '');
    $selectorEntries = sanitize_text_field($_POST['selectorEntries'] ?? '');
    $selectorPages   = sanitize_text_field($_POST['selectorPages'] ?? '');
    $selectorImages  = sanitize_text_field($_POST['selectorImages'] ?? '');

    if (empty($startUrl)) {
        wp_send_json_error('startUrl vacío', 400);
    }

    require_once garbell_PLUGIN_DIR . 'includes/scraper.php';

    $scraper = new garbell_Scraper([
        'startUrl'        => $startUrl,
        'maxDepth'        => $maxDepth,
        'maxPages'        => $maxPages,
        'selectorImages'  => $selectorImages,
        'selectorPages'   => $selectorPages,
        'tagsExclusions'   => $tagsExclusions,
        'selectorEntries' => $selectorEntries,
        
    ]);

    $result = $scraper->run();

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message(), 500);
    }

    wp_send_json_success($result);
}
add_action('wp_ajax_garbell_run_scrape', 'garbell_ajax_run_scrape');



/**
 * ==========================================================
 * AJAX: Importar posts
 * ==========================================================
 */
function garbell_ajax_import_selected() {
    check_ajax_referer('garbell_nonce', 'nonce');

    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_send_json_error('Permisos insuficientes', 403);
    }

    $payload = json_decode(wp_unslash($_POST['payload'] ?? ''), true);

    if (!$payload || !is_array($payload)) {
        wp_send_json_error('Payload inválido', 400);
    }

    require_once garbell_PLUGIN_DIR . 'includes/importer.php';

    $importer = new garbell_Importer();
    $res = $importer->import_batch($payload);

    if (is_wp_error($res)) {
        wp_send_json_error($res->get_error_message(), 500);
    }

    // Si $res es array('inserted_post_ids' => [...]) devolvemos sólo el array de ids
    $ids = is_array($res) && isset($res['inserted_post_ids']) ? $res['inserted_post_ids'] : $res;

    //error_log('AJAX import: ' . print_r($ids, true)); // sólo para debug en log
    wp_send_json_success(array_values($ids)); // devuelve directamente la lista en resp.data

    /*
    $res = $importer->import_batch($payload);
    if (is_wp_error($res)) {
        wp_send_json_error($res->get_error_message(), 500);
    }
    wp_send_json_success($res);
    */
}
add_action('wp_ajax_garbell_import_selected', 'garbell_ajax_import_selected');



/**
 * ==========================================================
 * Combo URL dinámica + AJAX
 * ==========================================================
 */
function combobox_urls_ajax($selected_startUrl) {
    global $wpdb;

    $nonce = wp_create_nonce('garbell_ajax_nonce');

    $guids = $wpdb->get_col("
        SELECT DISTINCT guid 
        FROM {$wpdb->posts}
        WHERE post_type = 'post'
        AND (post_status = 'publish' OR post_status = 'draft')
        AND guid <> ''
        ORDER BY guid ASC

    ");

    echo '<input type="text" id="startUrl" name="startUrl" value="' . esc_attr($selected_startUrl) . '" style="width:70%">';
    echo '<datalist id="startUrl_list">';

    foreach ($guids as $g) {
        echo "<option value='" . esc_attr($g) . "'>";
    }

    echo '</datalist>';
    echo '<script>document.getElementById("startUrl").setAttribute("list", "startUrl_list");</script>';

    ?>

    <script>
    jQuery(function ($) {

        $("#startUrl").on("input change", function () {
            let url = $(this).val();

            $.post(ajaxurl, {
                action: "garbell_load_config",
                url: url,
                _ajax_nonce: "<?php echo $nonce; ?>"
            }, function (response) {
                if (!response.success) return;

                $("#tagsExclusions").val(response.data.tagsExclusions);
                $("#selectorPages").val(response.data.selectorPages);
                $("#selectorImages").val(response.data.selectorImages);
                $("#selectorEntries").val(response.data.selectorEntries);
            });
        });
     });
    </script>

    <?php
}
