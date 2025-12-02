<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class garbell_Importer {

    public function __construct() {}

public function import_batch($batch = array()) {
    global $wpdb;
    if (empty($batch) || !is_array($batch)) {
        return new WP_Error('invalid_payload', 'Payload inválido');
    }

    global $wpdb;
    $inserted = array();


    $max_contador = $wpdb->get_var(
        "SELECT MAX(CAST(meta_value AS UNSIGNED)) 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = 'link_click_counter'"
    );
    //echo $max_contador;





    foreach ($batch as $item) {

        // Campos requeridos
        $url        = $item['linkPost']       ?? '';
        $title      = $item['linkTitlePost']  ?? '';
        $images     = $item['linksImg']       ?? array();
        $thumbs     = $item['linksImgThumb']  ?? array();
        $lat        = $item['lat']            ?? '';
        $lon        = $item['lon']            ?? '';
        $autor      = $item['autor']          ?? '';
        $autorUrl   = $item['autorUrl']       ?? '';
        $orientacio = $item['orientacio']     ?? '';
        $location   = $item['location']       ?? '';
        $import     = $item['import_post']    ?? '';


        //if (defined('WP_DEBUG') && WP_DEBUG) {
            $rows = count($images);  

            for ($i = 0; $i < $rows; $i++) {
                //error_log($title.' -->Imagen_'.$i.'='. $images[$i] );
                
                // Reverse geocode
                $geo = array();
                if (!empty($lat) && !empty($lon)) {
                    $geo = $this->reverse_geocode($lat, $lon);
                    if (!empty($geo['location'])) {
                        $location = $geo['location'];
                    }
                }

                $dominio  = parse_url($url, PHP_URL_HOST);
                $esquema  = parse_url($url, PHP_URL_SCHEME);
                $dominio_limpio = $esquema . "://" . $dominio . "\n";

                // ---------------------------------------------
                // INSERTAR POST
                // ---------------------------------------------
                
                $post_arr = array(
                    'post_author'       => get_current_user_id() ?: 1,  // evitar pending
                    'post_date'         => current_time('mysql'),
                    'post_date_gmt'     => current_time('mysql', 1),
                    'post_title'        => $title,
                    'post_excerpt'      => '',
                    'post_status'       => 'publish',
                    'comment_status'    => 'closed',
                    'ping_status'       => 'closed',
                    'post_name'         => sanitize_title($title),
                    'pinged'            => $url,
                    'guid'              => $dominio_limpio,
                    'post_type'         => 'post',
                );

                $post_id = wp_insert_post($post_arr, true);

                if (is_wp_error($post_id)) {
                    continue;
                }

                // ---------------------- METADATOS ---------------------- //

                add_post_meta($post_id, 'autor', $autor);
                add_post_meta($post_id, '_autor', 'field_6332e9bef19fb');

                add_post_meta($post_id, 'autor_url', $autorUrl);
                add_post_meta($post_id, '_autor_url', 'field_637b9f18752d4');

                add_post_meta($post_id, 'link_post', $url);
                add_post_meta($post_id, '_link_post', 'field_630485e99c971');

                $image_for_meta = !empty($images) ? $images[$i] : '';
                add_post_meta($post_id, 'urlImagen', $image_for_meta);

                $thumb_for_meta = !empty($thumbs) ? $thumbs[$i] : '';
                add_post_meta($post_id, '_thumbnail_id', $thumb_for_meta);

                add_post_meta($post_id, 'orientacio', $orientacio);
                add_post_meta($post_id, '_orientacio','field_6331d0e326d66');

                add_post_meta($post_id, 'latitud', $lat);
                add_post_meta($post_id, '_latitud', 'field_6332ddd40bc69');
                add_post_meta($post_id, 'longitud', $lon);
                add_post_meta($post_id, '_longitud', 'field_6332de0d0bc6a');

                add_post_meta($post_id, 'location', $location);
                add_post_meta($post_id, '_location', 'field_6332fc162b233');

                add_post_meta($post_id, 'link_click_counter', $max_contador + 1);
                add_post_meta($post_id, '_link_click_counter', 'field_6358d311c8d40');
                
        

                if (!empty($images)) add_post_meta($post_id, 'images_all', maybe_serialize($images));
                if (!empty($thumbs)) add_post_meta($post_id, 'thumbs_all', maybe_serialize($thumbs));

                // ---------------------- CATEGORÍAS GEO ---------------------- //
                $state   = $geo['state']   ?? '';
                $county  = $geo['county']  ?? '';
                $city = $geo['city'] ?? '';

                $state_1 = explode('/', $state)[0];
                $county_1 = explode('/', $county)[0];
                $city_1 = explode('/', $city)[0];

                $cat_top = $this->ensure_category("Roca", 0);
                $cat_state_id   = $this->ensure_category($state_1, $cat_top);
                $cat_county_id  = $this->ensure_category($county_1, $cat_state_id);
                $cat_village_id = $this->ensure_category($city_1, $cat_county_id);

                $categories = array_filter([$cat_state_id, $cat_county_id, $cat_village_id]);
                if (!empty($categories)) {
                    wp_set_post_terms($post_id, $categories, 'category', true);
                }

                // ---------------------------------------------------------
                //   AÑADIR TÉRMINO "orientacio" → wp_term_relationships
                // ---------------------------------------------------------
                if (!empty($orientacio)) {

                    $term_taxonomy_id = $wpdb->get_var(
                        $wpdb->prepare("
                            SELECT tt.term_taxonomy_id
                            FROM {$wpdb->terms} t
                            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                            WHERE t.name = %s
                            LIMIT 1
                        ", $orientacio)
                    );

                    if ($term_taxonomy_id) {
                        $wpdb->insert(
                            $wpdb->term_relationships,
                            [
                                'object_id'        => $post_id,
                                'term_taxonomy_id' => $term_taxonomy_id,
                                'term_order'       => 0,
                            ],
                            ['%d','%d','%d']
                        );
                    }

                }

                // ---------------------- EXCERPT + PUBLICACIÓN ---------------------- //
                $this->topo_Custom_Excerpt($post_id);

                //error_log("importado => ".$post_id );
                $inserted[] = $post_id;
            }
            
        //} //END if (defined('WP_DEBUG') && WP_DEBUG)

        
    }

    foreach ($inserted as $val) {
        error_log("Insertado: ".$val . '<br />');
    }
    return array('inserted_post_ids' => $inserted);
}


    private function ensure_category($name, $parent_id = 0) {
        if (empty($name)) return 0;
        $term = term_exists($name, 'category');
        if ($term) return $term['term_id'];
        $new = wp_insert_term($name, 'category', ['parent' => $parent_id]);
        return is_wp_error($new) ? 0 : $new['term_id'];
    }

    private function reverse_geocode($lat, $lon) {
        $url = esc_url_raw("https://nominatim.openstreetmap.org/reverse?format=geojson&lat=". rawurlencode($lat) . "&lon=" . rawurlencode($lon)."&layer=address");

        $resp = wp_remote_get($url, [
            'headers' => ['User-Agent' => 'garbellPlugin/1.0'],
            'timeout' => 10
        ]);

        if (is_wp_error($resp)) return [];

        $body = wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);

        print_r($json);

        $properties = $json['features'][0]['properties'] ?? [];
        $address    = $properties['address'] ?? [];
        return [
            'location' => $properties['display_name'] ?? '',
            'state'    => $address['country']
                        ?? $address['state'] 
                        ?? '',
            'county'   => $address['county']
                        ?? $address['state'] 
                        ?? '',
            'city'     => $address['city'] 
                        ?? $address['town'] 
                        ?? $address['village'] 
                        ?? '',
        ];
    }

    // ----------------------------------------------------------
    //               EXCERPT + PUBLICACIÓN
    // ----------------------------------------------------------
    private function topo_Custom_Excerpt($post_id) {

        $autor      = get_field('autor', $post_id);
        $autorurl   = get_field('autor_url', $post_id);
        $post_title = get_the_title($post_id);

        $post_link  = get_field('link_post', $post_id);
        $post_orient= get_field('orientacio', $post_id);
        $post_lat   = get_field('latitud', $post_id);
        $post_lon   = get_field('longitud', $post_id);

        $link_location = $this->getLinkopenMaps($post_lat,$post_lon)
                        . ' - '
                        . $this->getLinkopenOSMaps($post_lat,$post_lon);

        $rosavents = $this->getDivOrientacio($post_orient);

        $href  = get_field('urlImagen', $post_id);
        $thumb = get_field('_thumbnail_id', $post_id);

        $post_link_title = '<p class="list-post-meta"><span class="published"><a class="count" id="countable_link_'.$post_id.'"  href="'.$post_link.'" target="_blank">'.$post_title.'</a></span></p>';
        $taula_dades = $this->getTableDades($autor, $autorurl, $rosavents, $link_location);

        $post_excerpt = '<strong>'.$post_link_title.'</strong>'.$taula_dades.'<div class="thumb"><a class="count" id="countable_link_'.$post_id.'-2"  href="'.$href.'" target="_blank"><img src="'.$thumb.'" ></a></div>';

        add_post_meta($post_id, 'orientacio', $post_orient);

        wp_update_post([
            'ID'           => $post_id,
            'post_excerpt' => $post_excerpt,
            'post_status'  => 'publish'
        ]);
        
    }

    private function getTableDades($autor, $autorurl, $orientacio_html, $location_html) {
        $table1 = '<table style="width:100%" class="table_dades"><tbody><tr><td style="width:5%"><i class="fas fa-user-pen"></i></td><td><a href="'.$autorurl.'" target="_blank">'.$autor.'</a></td><td style="width:15%" rowspan="2">'.$orientacio_html.'</td></tr><tr>';
        $table2 = '<td style="width:5%"><i class="fas fa-location-dot"></i></td><td>'.$location_html.'</td></tr></tbody></table>';
        return $table1.$table2;
    }

    /******** Orientacio ****/
    function getDivOrientacio($arg_o){

    $o_11 = '<div dades="" datao="" title="E" class="control">';
    $o_22 = '<svg dades="" xmlns="http://www.w3.org/2000/svg" width="40" height="40" version="1.1" viewBox="0 0 454.00715 454.00714" class="orientacio is-unselectable is-read-only">';
    $o_nn = '<g dades="" class=""><path dades="" d="m285.19 83.727-58.18 142.14-58.19-142.14v-0.005l58.19-83.725z"></path> <text dades="" y="105.007141" x="205.0424" font-size="150">.</text></g>';
    $o_ne = '<g dades="" class=""><path dades="" d="m369.46 166.83-141.65 59.371 59.368-141.65 0.002-0.002 100.34-18.058z"></path><text dades="" y="135.007141" x="306.6721" font-size="150">.</text></g>';
    $o_ee = '<g dades="" class=""><path dades="" d="m370.28 168.82-142.14 58.18 142.14 58.185h0.005l83.722-58.185z"></path> <text dades="" y="247.58344" x="350" font-size="150">.</text></g>';
    $o_se = '<g dades="" class=""><path dades="" d="m369.46 287.17-141.65-59.371 59.368 141.65 0.002 0.002 100.34 18.058z"></path><text dades="" y="345.58344" x="300.10295" font-size="150">.</text></g>';
    $o_ss = '<g dades="" class=""><path dades="" d="m285.19 370.28-58.18-142.14-58.185 142.14v0.005l58.185 83.722z"></path> <text dades="" y="390.00714" x="206.10295" font-size="150">.</text></g>';
    $o_sw = '<g dades="" class=""><path dades="" d="m166.83 369.46 59.371-141.65-141.65 59.368-0.0032 0.002-18.058 100.34z"></path><text dades="" y="345.58344" x="106.6721" font-size="150">.</text></g>';
    $o_ww = '<g dades="" class=""><path dades="" d="m83.727 168.82 142.14 58.18-142.14 58.185h-0.0046l-83.725-58.18z"></path> <text dades="" y="247.58344" x="56" font-size="150">.</text></g>';
    $o_nw = '<g dades="" class=""><path dades="" d="m166.83 84.55 59.371 141.65-141.65-59.368-0.0032-0.002l-18.058-100.34z"></path><text dades="" y="135.007141" x="106.6721" font-size="150">.</text></g> </svg>';
    $o_33 = '</div>';

    switch ($arg_o) { 
        case 'N':
            $o_11 = '<div dades="" datao="" title="N" class="control">';	
            $o_nn = '<g dades="" class="orientacio-selected"><path dades="" d="m285.19 83.727-58.18 142.14-58.19-142.14v-0.005l58.19-83.725z"></path> <text dades="" y="105.007141" x="205.0424" font-size="150">.</text></g>';
            break;
        case 'NE':
            $o_11 = '<div dades="" datao="" title="NE" class="control">';
            $o_ne = '<g dades="" class="orientacio-selected"><path dades="" d="m369.46 166.83-141.65 59.371 59.368-141.65 0.002-0.002 100.34-18.058z"></path><text dades="" y="135.007141" x="306.6721" font-size="150">.</text></g>';
            break;
        case 'E':
            $o_11 = '<div dades="" datao="" title="E" class="control">';
            $o_ee = '<g dades="" class="orientacio-selected"><path dades="" d="m370.28 168.82-142.14 58.18 142.14 58.185h0.005l83.722-58.185z"></path> <text dades="" y="247.58344" x="350" font-size="150">.</text></g>';
            break;
        case 'SE':
            $o_11 = '<div dades="" datao="" title="SE" class="control">';
            $o_se = '<g dades="" class="orientacio-selected"><path dades="" d="m369.46 287.17-141.65-59.371 59.368 141.65 0.002 0.002 100.34 18.058z"></path><text dades="" y="345.58344" x="300.10295" font-size="150">.</text></g>';
            break;
        case 'S': 
            $o_11 = '<div dades="" datao="" title="S" class="control">';
            $o_ss = '<g dades="" class="orientacio-selected"><path dades="" d="m285.19 370.28-58.18-142.14-58.185 142.14v0.005l58.185 83.722z"></path> <text dades="" y="390.00714" x="206.10295" font-size="150">.</text></g>';
            break;
        case 'SW':
            $o_11 = '<div dades="" datao="" title="SW" class="control">';
            $o_sw = '<g dades="" class="orientacio-selected"><path dades="" d="m166.83 369.46 59.371-141.65-141.65 59.368-0.0032 0.002-18.058 100.34z"></path><text dades="" y="345.58344" x="106.6721" font-size="150">.</text></g>';
            break;
        case 'W':
            $o_11 = '<div dades="" datao="" title="W" class="control">';
            $o_ww = '<g dades="" class="orientacio-selected"><path dades="" d="m83.727 168.82 142.14 58.18-142.14 58.185h-0.0046l-83.725-58.18z"></path> <text dades="" y="247.58344" x="56" font-size="150">.</text></g>';
            break;
        case 'NW': 
            $o_11 = '<div dades="" datao="" title="NW" class="control">';
            $o_nw = '<g dades="" class="orientacio-selected"><path dades="" d="m166.83 84.55 59.371 141.65-141.65-59.368-0.0032-0.002l-18.058-100.34z"></path><text dades="" y="135.007141" x="106.6721" font-size="150">.</text></g> </svg>';
            break;		
    } 
        $return_orientacio = $o_11.$o_22.$o_nn.$o_ne.$o_ee.$o_se.$o_ss.$o_sw.$o_ww.$o_nw.$o_33;
        return $return_orientacio;
    }    /** End Orientacio */

    function getLinkopenMaps($post_lat,$post_lon){
        return '<a href="https://www.google.com/maps/search/?api=1&query='.$post_lat.','.$post_lon.'" target="_blank" title="Obrir amb Google Maps">GMaps</a>';
    }

    function getLinkopenOSMaps($post_lat,$post_lon){
        return '<a href="http://www.openstreetmap.org/?lat='.$post_lat.'&lon='.$post_lon.'&zoom=17&layers=Y" target="_blank" title="Obrir amb Open Street Map">OSMaps</a>';
    }
}
?>
