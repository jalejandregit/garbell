<?php
if (!defined('ABSPATH')) exit;

function registres_contributor_page() {

    if (!is_user_logged_in() || !current_user_can('edit_posts')) {
        wp_die('No tens permissos per veure aquesta pàgina.');
    }

    global $wpdb;
    $nonce = wp_create_nonce('registre_nonce');

    // EL AJAX URL CORRECTO
    $ajax_url = admin_url('admin-ajax.php');
?>
<div class="wrap">
    <h1>Registres</h1>

    <div id="cmbg_table_container"></div>
</div>

<script>
(function($){

    var ajaxUrl = '<?php echo $ajax_url; ?>';
    var nonce   = '<?php echo $nonce; ?>';

    /* ==========================================================
       Construcción de tabla
       ========================================================== */
    function buildTableHtml(posts){
        if(!posts.length) return '<p>No hay registros.</p>';

        var html = "";

        html += '<div class="tablenav top">';
        html += '<div class="alignleft actions bulkactions">';
        html += '<select id="bulk-action-selector-top">';
        html += '<option value="-1">Accions en massa</option>';
        html += '<option value="publish">Moure a publicat</option>';
        html += '<option value="draft">Moure a esborrany</option>';
        html += '<option value="trash">Moure a paperera</option>';
        html += '</select>';
        html += '</div>';
        html += '<button id="doaction" class="button action">Aplicar</button>';
        html += '<div class="clear"></div><br>';
        html += '</div>';

        html += '<table id="tabla-posts" border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%;">';
        html += '<thead><tr>';
        html += '<th class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox"></th>';
        html += '<th onclick="cmbg_sortTable(1)" style="cursor:pointer;">ID</th>';
        html += '<th onclick="cmbg_sortTable(2)" style="cursor:pointer;">Status</th>';
        html += '<th onclick="cmbg_sortTable(3)" style="cursor:pointer;">Title</th>';
        html += '<th onclick="cmbg_sortTable(4)" style="cursor:pointer;">Pinged</th>';
        html += '</tr></thead>';

        html += '<tbody>';

        posts.forEach(function(p){
            html += '<tr>';
            html += '<td><input type="checkbox" class="cb-post" value="'+p.ID+'"></td>';
            html += '<td>'+p.ID+'</td>';
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

    /* ==========================================================
       Cargar POST del usuario actual
       ========================================================== */
    function fetchPosts(){
        $('#cmbg_table_container').html('<p>Cargando…</p>');

        $.post(ajaxUrl, {
            action : 'registres_get_posts',
            nonce  : nonce
        })
        .done(function(r){
            if(!r.success){
                $('#cmbg_table_container').html('<p>Error al cargar.</p>');
                return;
            }
            $('#cmbg_table_container').html(buildTableHtml(r.data.posts));
        });
    }

    fetchPosts();

    /* ==========================================================
       Bulk actions
       ========================================================== */
    $(document).on('click', '#doaction', function(){

        var action = $('#bulk-action-selector-top').val();
        var ids = [];

        $('.cb-post:checked').each(function(){
            ids.push($(this).val());
        });

        if(action === '-1') return alert('Selecciona una acció.');
        if(!ids.length)    return alert('Selecciona registres.');

        $.post(ajaxUrl, {
            action      : 'registres_bulk_ops',
            nonce       : nonce,
            bulk_action : action,
            posts       : ids
        })
        .done(function(r){
            alert(r.data.message);
            fetchPosts();
        });
    });
    
    // Cuando se cambie el checkbox de cabecera: marcar/desmarcar todos
    $(document).on('change', '#cb-select-all-1', function(){
        var checked = $(this).prop('checked');
        // buscar dentro del contenedor para evitar interferir con otras tablas
        $('#cmbg_table_container').find('.cb-post').prop('checked', checked);
    });

    /* ==========================================================
       Sort table function
       ========================================================== */
    window.cmbg_sortTable = function(n) {
        var table = document.getElementById("tabla-posts");
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

                if (dir === "asc" && a > b) { shouldSwitch = true; break; }
                if (dir === "desc" && a < b) { shouldSwitch = true; break; }
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

})(jQuery);
</script>
<?php
}

/* ==========================================================
   AJAX CALLBACKS
   ========================================================== */
add_action('wp_ajax_registres_get_posts', 'registres_get_posts_callback');
add_action('wp_ajax_registres_bulk_ops', 'registres_bulk_ops_callback');

function registres_get_posts_callback(){
    global $wpdb;

    if(!wp_verify_nonce($_POST['nonce'], 'registre_nonce')) wp_send_json_error();
    if(!current_user_can('edit_posts')) wp_send_json_error();

    $uid = get_current_user_id();

    $sql = $wpdb->prepare(
        "SELECT ID, post_status, post_title, pinged
         FROM {$wpdb->posts}
         WHERE post_type='post' AND post_author=%d
         ORDER BY ID DESC",
         $uid
    );

    $rows = $wpdb->get_results($sql);

    wp_send_json_success(['posts'=>$rows]);
}


function registres_bulk_ops_callback(){

    if(!wp_verify_nonce($_POST['nonce'],'registre_nonce')) wp_send_json_error();
    if(!current_user_can('edit_posts')) wp_send_json_error();

    $action = sanitize_text_field($_POST['bulk_action']);
    $ids    = array_map('intval', $_POST['posts']);

    foreach($ids as $id){
        if(in_array($action, ['draft','publish','trash'])){
            wp_update_post(['ID'=>$id,'post_status'=>$action]);
        }
    }

    wp_send_json_success(['message'=>'Acción realizada correctamente.']);
}
?>
