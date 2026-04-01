<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
delete_option( 'aisc_settings' );
delete_option( 'aisc_chat_history' );
