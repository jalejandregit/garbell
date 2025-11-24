<?php
// UPDATED FILE WITH REQUESTED FUNCTIONALITIES
if ( ! defined( 'ABSPATH' ) ) exit;









function admin_plantilles_page() {
    //$saved_gt = "";
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes permisos suficientes.'));
    }
    




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
            echo '<div class="updated"><p>Opcions desades a <strong>' . esc_html($data) . '</strong></p></div>';
        }


    }
    function reg() {
       $reg_exp= "^(?:(?:(?:https?|ftp):)?\\/\\/)(?:\\S+(?::\\S*)?@)?(?:[a-z0-9\\u00a1-\\uffff][a-z0-9\\u00a1-\\uffff_-]{0,62})?[a-z0-9\\u00a1-\\uffff]\\.)(?:[a-z\\u00a1-\\uffff]{2,}\\.?)"  ;
    
    
    }

    function show_Url($url) {
       $ch = curl_init($_POST['startUrl']);

                //porta la pagina sencera
                curl_exec($ch);
                //-----------*/

                if (!curl_errno($ch)) {
                    switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
                        case 200:  # OK
                        break;
                        default:
                        $error=$error. 'Unexpected HTTP code: '. $http_code. "\n";
                    }
                    //$error=$error.' --> CURL_OK';
                } 
    }
 
    ?>

    <div class="wrap">
        <h1>Configuració Plantilles</h1>
        <form method="post">
            <?php wp_nonce_field('garbell_plantilles'); ?>

            <table class="form-table">
                <tr>
                    <th><label for="startUrl">URL</label></th>

                                        <td>
                        <input type="text"
                               id="startUrl"
                               name="startUrl"
                               style="width:100%;"
                               value="https://logalldeponent.blogspot.com/">
                    </td>
                </tr>

            </table>

            <?php submit_button('Desar configuració'); ?>
        </form>

    </div>
<?php
}

function combobox_ajax($selected_startUrl) {
    
    echo 'startUrl: '.$selected_startUrl;
?>
<script>
jQuery(function($) {

    
    
 

});
</script>
<?php
}


