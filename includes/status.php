
<?php
function custom_status_inline(){
    echo "<script>
    jQuery(document).ready( function(){
        jQuery( 'select[name=\"_status\"]' ).append( '<option value=\"scraped\">Scraped</option>' );
    });
    </script>";
}
add_action('admin_footer-edit.php','custom_status_inline');