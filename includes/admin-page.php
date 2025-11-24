<?php
// UPDATED FILE WITH REQUESTED FUNCTIONALITIES
if ( ! defined( 'ABSPATH' ) ) exit;

/* =====================================================================
   ADMIN PAGE
   ===================================================================== */
function garbell_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes permisos suficientes.'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('garbell_save_settings')) {
        $settings = get_option('garbell_settings', array());
        $settings['maxDepth'] = intval($_POST['maxDepth']);
        $settings['maxPages'] = intval($_POST['maxPages']);
        $settings['maxExecutionSeconds'] = intval($_POST['maxExecutionSeconds']);
        update_option('garbell_settings', $settings);
        echo '<div class="updated"><p>Opcions desades.</p></div>';
    }

    $settings = get_option('garbell_settings', array('maxDepth'=>2,'maxPages'=>50,'maxExecutionSeconds'=>5));
    ?>
    <div class="wrap">
        <h1>garbell - Configuració</h1>
        <form method="post">
            <?php wp_nonce_field('garbell_save_settings'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="maxDepth">Límit maxDepth</label></th>
                    <td><input type="number" name="maxDepth" id="maxDepth" value="<?php echo esc_attr($settings['maxDepth']); ?>" min="0"></td>
                </tr>
                <tr>
                    <th><label for="maxPages">Límit Nº págines</label></th>
                    <td><input type="number" name="maxPages" id="maxPages" value="<?php echo esc_attr($settings['maxPages']); ?>" min="1"></td>
                </tr>
                <tr>
                    <th><label for="maxExecutionSeconds">Temps màxim d'execució (segons)</label></th>
                    <td><input type="number" name="maxExecutionSeconds" id="maxExecutionSeconds" value="<?php echo esc_attr($settings['maxExecutionSeconds']); ?>" min="1"></td>
                </tr>
            </table>
            <?php submit_button('Desar configuració'); ?>
        </form>

        <h2>Acceso a DB (opcional)</h2>
        <p>El plugin usa <code>$wpdb</code> internamente.</p>
    </div>

    <div>
        <?php combobox_guids_post_ajax(); ?>
    </div>
    <?php
}

/* =====================================================================
   AJAX + UI TABLE
   ===================================================================== */
function combobox_guids_post_ajax() {
    global $wpdb;

    $nonce = wp_create_nonce('cmbg_nonce');
    $guids = $wpdb->get_col("
        SELECT DISTINCT guid 
        FROM {$wpdb->posts}
        WHERE post_type = 'post'
        AND (post_status = 'publish' OR post_status = 'draft')
        AND guid <> ''
        ORDER BY guid ASC

    ");

    echo '<label for="guid_select">Selecciona GUID: </label>';
    echo '<select id="guid_select" name="guid_select">';
    echo '<option value="">-- Todos --</option>';
    foreach ($guids as $g) echo '<option value="'.esc_attr($g).'">'.esc_html($g).'</option>';
    echo '</select>';

    echo '<div id="cmbg_table_container" style="margin-top:12px;"><p>Selecciona un GUID…</p></div>';

    $ajax_url = admin_url('admin-ajax.php');

    /* ================= JS ================= */
    ?>
<script>
(function($){
    var ajaxUrl = '<?php echo $ajax_url; ?>';
    var nonce = '<?php echo $nonce; ?>';

    function buildTableHtml(posts, users){
        if(!posts.length) return '<p>No hay registros.</p>';

        var html="";

        /* ================= BULK BAR ================= */
        html += '<div class="tablenav top">';
        html += '<div class="alignleft actions bulkactions">';
        html += '<select id="bulk-action-selector-top">';
        html += '<option value="-1">Accions en massa</option>';
        html += '<option value="draft">Moure a esborrany</option>';
        html += '<option value="trash">Moure a paperera</option>';
        html += '<option value="publish">Moure a publicat</option>';
        html += '<option value="change_author">Cambiar autor</option>';
        html += '</select>';
        html += '</div>';

        html += '<div class="alignleft actions">';
        html += '<select id="select_user">';
        html += '<option value="">-- Seleccionar author --</option>';
        users.forEach(function(u){
            html += '<option value="'+u.ID+'">'+u.display+'</option>';
        });
        html += '</select>';
        html += '</div>';

        html += '<button id="doaction" class="button action">Aplicar</button>';
        html += '<div class="clear"></div><br>';
        html += '</div>';

        /* ================= TABLE ================= */
        html += '<table id="tabla-posts" border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%;">';
          html += '<thead><tr>';
            html +='<th id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox"></th>';
			html +='<label for="cb-select-all-1"><span class="screen-reader-text">Selecciona-ho tot</span></label></th>';
            html += '<th onclick="cmbg_sortTable(1)" style="cursor:pointer;">ID</th>';
            html += '<th onclick="cmbg_sortTable(2)" style="cursor:pointer;">User</th>';
            html += '<th onclick="cmbg_sortTable(3)" style="cursor:pointer;">Status</th>';
            html += '<th onclick="cmbg_sortTable(4)" style="cursor:pointer;">Title</th>';
            html += '<th onclick="cmbg_sortTable(5)" style="cursor:pointer;">Pinged</th>';
            html += '</tr></thead>';
            html += '<tbody>';

        posts.forEach(function(p){
            html += '<tr>';
            //html += '<td><input type="checkbox" class="cb-post" value="'+p.ID+'"></td>';
            html += '<td><input type="checkbox" class="cb-post" name="post[]" value="'+escapeAttr(p.ID)+'"></td>';
            html += '<td>'+p.ID+'</td>';
            html += '<td>'+p.post_author+'</td>';
            html += '<td>'+p.post_status+'</td>';
            html += '<td>'+p.post_title+'</td>';
            //html += '<td>'+(p.pinged ? p.pinged : '-')+'</td>';
             if (p.pinged && p.pinged.length) {
                    // Si pinged contiene varias urls, se muestra tal cual; se podría split si se desea
                    var safeUrl = escapeAttr(p.pinged);
                    var safeText = escapeHtml(p.pinged);
                    html += '<td><a href="'+safeUrl+'" target="_blank" rel="noopener noreferrer">'+safeText+'</a></td>';
                } else {
                    html += '<td>-</td>';
                }
            html += '</tr>';
        });
        html += '</tbody></table>';

        return html;
    }

            // Funciones de escaping simples para JS (no reemplazan a esc_* de PHP, pero añaden seguridad)
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>"'`=\/]/g, function(s) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                    '/': '&#x2F;',
                    '`': '&#x60;',
                    '=': '&#x3D;'
                })[s];
            });
        }
        function escapeAttr(str) { return escapeHtml(str); }

    function fetchPosts(guid){
        $('#cmbg_table_container').html('<p>Cargando…</p>');
        $.post(ajaxUrl,{action:'cmbg_get_posts',nonce:nonce,guid:guid})
        .done(function(r){
            if(!r.success) return $('#cmbg_table_container').html('<p>Error.</p>');
            $('#cmbg_table_container').html(buildTableHtml(r.data.posts,r.data.users));
        });
    }

    $('#guid_select').on('change',function(){ fetchPosts($(this).val()); });

    /* =================== BULK ACTION =================== */
    $(document).on('click','#doaction',function(){
        //bulk-action-selector-top
        var action = $('#bulk-action-selector-top').val();
        //alert(action);
        var ids = [];
        $('.cb-post:checked').each(function(){ ids.push($(this).val()); });

        if(action==='-1') return alert('Selecciona una acción.');
        if(!ids.length) return alert('Selecciona registros.');

        var userID = $('#select_user').val();
        if(action==='change_author' && !userID) return alert('Selecciona un usuario.');

        $.post(ajaxUrl,{
            action:'cmbg_bulk_ops',
            nonce:nonce,
            bulk_action:action,
            posts:ids,
            user_id:userID
        }).done(function(r){
            alert(r.data.message);
            fetchPosts($('#guid_select').val());
        });
    });


            // Función de ordenado reutilizable global (llamada desde los th)
        window.cmbg_sortTable = function(n) {
            var table = document.getElementById("tabla-posts");
            if(!table) return;
            var switching = true;
            var dir = "asc";
            var switchcount = 0;
            while (switching) {
                switching = false;
                var rows = table.rows;
                for (var i = 1; i < (rows.length - 1); i++) {
                    var shouldSwitch = false;
                    var x = rows[i].getElementsByTagName("TD")[n];
                    var y = rows[i + 1].getElementsByTagName("TD")[n];
                    if (!x || !y) continue;
                    var a = x.innerText.toLowerCase();
                    var b = y.innerText.toLowerCase();
                    if (dir === "asc") {
                        if (a > b) { shouldSwitch = true; break; }
                    } else {
                        if (a < b) { shouldSwitch = true; break; }
                    }
                }
                if (shouldSwitch) {
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    switchcount++;
                } else {
                    if (switchcount === 0 && dir === "asc") {
                        dir = "desc";
                        switching = true;
                    }
                }
            }
        };

        // Cuando se cambie el checkbox de cabecera: marcar/desmarcar todos
    $(document).on('change', '#cb-select-all-1', function(){
        var checked = $(this).prop('checked');
        // buscar dentro del contenedor para evitar interferir con otras tablas
        $('#cmbg_table_container').find('.cb-post').prop('checked', checked);
    });

    // Cuando cambie cualquier checkbox individual, actualizar el "select all"
    $(document).on('change', '#cmbg_table_container .cb-post', function(){
        var total = $('#cmbg_table_container .cb-post').length;
        var checked = $('#cmbg_table_container .cb-post:checked').length;
        $('#cb-select-all-1').prop('checked', total === checked);
        // Si quieres un estado 'indeterminate' cuando hay mezcla:
        var header = document.getElementById('cb-select-all-1');
        if(header){
            header.indeterminate = (checked > 0 && checked < total);
        }
    });

    // Opcional: si la tabla se vuelve a renderizar, forzar actualizar el estado del header
    $(document).on('DOMNodeInserted', '#cmbg_table_container', function(){
        // pequeña espera para que el DOM esté listo
        setTimeout(function(){
            var total = $('#cmbg_table_container .cb-post').length;
            var checked = $('#cmbg_table_container .cb-post:checked').length;
            $('#cb-select-all-1').prop('checked', total && (total === checked) );
            var header = document.getElementById('cb-select-all-1');
            if(header) header.indeterminate = (checked > 0 && checked < total);
        }, 50);
    });



})(jQuery);
</script>
<?php
}

/* =====================================================================
   AJAX HANDLERS
   ===================================================================== */
add_action('wp_ajax_cmbg_get_posts','cmbg_get_posts_callback');
add_action('wp_ajax_cmbg_bulk_ops','cmbg_bulk_ops_callback');

function cmbg_get_posts_callback(){
    global $wpdb;

    if(!wp_verify_nonce($_POST['nonce'],'cmbg_nonce')) wp_send_json_error();
    if(!current_user_can('edit_posts')) wp_send_json_error();

    $guid = sanitize_text_field($_POST['guid']);

    $sql = "SELECT ID, post_author, post_status, post_title, pinged FROM {$wpdb->posts} WHERE post_type='post'";
    if($guid!=='') $sql .= $wpdb->prepare(" AND guid=%s",$guid);
    $sql .= " ORDER BY ID DESC";

    $rows = $wpdb->get_results($sql);
    $posts = [];

    foreach($rows as $r){
        $a = get_userdata($r->post_author);
        $posts[] = [
            'ID'=>$r->ID,
            'post_author'=>$a?$a->display_name:'-',
            'post_status'=>$r->post_status,
            'post_title'=>$r->post_title,
            'pinged'=>$r->pinged
        ];
    }

    /* Usuarios para SELECT */
    $userList=[];
    $users=get_users(['fields'=>['ID','display_name']]);
    foreach($users as $u){
        $userList[]=['ID'=>$u->ID,'display'=>$u->display_name];
    }

    wp_send_json_success(['posts'=>$posts,'users'=>$userList]);
}

/* =====================================================================
   BULK OPERATIONS
   ===================================================================== */
function cmbg_bulk_ops_callback(){
    if(!wp_verify_nonce($_POST['nonce'],'cmbg_nonce')) wp_send_json_error();
    if(!current_user_can('edit_posts')) wp_send_json_error();

    $action = sanitize_text_field($_POST['bulk_action']);
    $ids = array_map('intval', $_POST['posts']);
    $userID = intval($_POST['user_id']);

    foreach($ids as $id){
        if($action==='draft' || $action==='trash' || $action==='publish'){
            wp_update_post(['ID'=>$id,'post_status'=>$action]);
        }
        elseif($action==='change_author'){
            wp_update_post(['ID'=>$id,'post_author'=>$userID]);
        }
    }

    wp_send_json_success(['message'=>'Acción realizada correctamente.']);
}
