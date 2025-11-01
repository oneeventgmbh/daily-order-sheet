<?php
/**
 * Plugin Name: Daily Order Sheet
 * Plugin URI: https://github.com/oneeventgmbh/daily-order-sheet
 * Description: Display daily WooCommerce orders for Events Calendar events in a printable format
 * Version: 1.0.0
 * Author: Harry Fesenmayr
 * Author URI: https://www.fesenmayr.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: daily-order-sheet
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class
 */
class Daily_Order_Sheet {

    /**
     * Custom capability name
     */
    const CAPABILITY = 'view_daily_order_sheet';

    /**
     * Available table columns
     */
    const AVAILABLE_COLUMNS = [
        'event' => 'Event',
        'event_date' => 'Event Date/Time',
        'order_id' => 'Order ID',
        'purchaser_name' => 'Purchaser Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'status' => 'Status',
        'tickets' => 'Tickets',
    ];

    /**
     * Initialize the plugin
     */
    public static function init() {
        // Register activation hook
        register_activation_hook( __FILE__, [ __CLASS__, 'activate' ] );

        // Add admin menu
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );

        // Enqueue styles
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ] );

        // Enqueue scripts
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );

        // AJAX handler for loading orders
        add_action( 'wp_ajax_dos_load_orders', [ __CLASS__, 'ajax_load_orders' ] );
    }

    /**
     * Plugin activation - add capability to administrator role
     */
    public static function activate() {
        $role = get_role( 'administrator' );
        if ( $role ) {
            $role->add_cap( self::CAPABILITY );
        }
    }

    /**
     * Get visible columns for current user
     *
     * @return array Array of visible column keys
     */
    public static function get_visible_columns() {
        $user_id = get_current_user_id();
        $saved_columns = get_user_meta( $user_id, 'dos_visible_columns', true );

        // If no saved preference, return all columns as default
        if ( empty( $saved_columns ) || ! is_array( $saved_columns ) ) {
            return array_keys( self::AVAILABLE_COLUMNS );
        }

        return $saved_columns;
    }

    /**
     * Save visible columns for current user
     *
     * @param array $columns Array of visible column keys
     */
    public static function save_visible_columns( $columns ) {
        $user_id = get_current_user_id();

        // Validate that all columns exist
        $valid_columns = array_intersect( $columns, array_keys( self::AVAILABLE_COLUMNS ) );

        update_user_meta( $user_id, 'dos_visible_columns', $valid_columns );
    }

    /**
     * Check if a column is visible
     *
     * @param string $column Column key
     * @param array $visible_columns Array of visible column keys
     * @return bool
     */
    public static function is_column_visible( $column, $visible_columns ) {
        return in_array( $column, $visible_columns, true );
    }

    /**
     * AJAX handler for loading orders
     */
    public static function ajax_load_orders() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'dos_load_orders' ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'daily-order-sheet' ) ] );
        }

        // Check capability
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'daily-order-sheet' ) ] );
        }

        // Get and validate date
        $date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : date( 'Y-m-d' );

        // Enhanced date validation with format check and reasonable range
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid date format.', 'daily-order-sheet' ) ] );
        }

        $date_parts = explode( '-', $date );
        $year = (int) $date_parts[0];
        $month = (int) $date_parts[1];
        $day = (int) $date_parts[2];

        if ( ! checkdate( $month, $day, $year ) || $year < 2000 || $year > 2050 ) {
            wp_send_json_error( [ 'message' => __( 'Invalid date.', 'daily-order-sheet' ) ] );
        }

        // Get visible columns
        $visible_columns = self::get_visible_columns();

        // Check for refresh cache flag
        $refresh_cache = isset( $_POST['refresh_cache'] ) && $_POST['refresh_cache'] === '1';

        // Start output buffering
        ob_start();

        // Render the orders table
        self::render_orders_table( $date, $visible_columns );

        // Get the rendered HTML
        $html = ob_get_clean();

        // Send success response
        wp_send_json_success( [
            'html' => $html,
            'date' => $date,
            'formatted_date' => date( 'l, F j, Y', strtotime( $date ) )
        ] );
    }

    /**
     * Clear the orders cache for a specific date or all dates
     *
     * @param string|null $date Optional date in Y-m-d format. If null, clears all caches.
     */
    public static function clear_orders_cache( $date = null ) {
        if ( $date ) {
            // Clear specific date cache
            $cache_key = 'dos_orders_' . md5( $date );
            delete_transient( $cache_key );
        } else {
            // Clear all order caches by pattern using prepared statement
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $wpdb->esc_like('_transient_dos_orders_') . '%',
                    $wpdb->esc_like('_transient_timeout_dos_orders_') . '%'
                )
            );
        }
    }

    /**
     * Add admin menu item
     */
    public static function add_admin_menu() {
        add_menu_page(
            __( 'Daily Order Sheet', 'daily-order-sheet' ),
            __( 'Daily Order Sheet', 'daily-order-sheet' ),
            self::CAPABILITY,
            'daily-order-sheet',
            [ __CLASS__, 'render_admin_page' ],
            'dashicons-list-view',
            30
        );
    }

    /**
     * Enqueue admin styles
     */
    public static function enqueue_styles( $hook ) {
        if ( 'toplevel_page_daily-order-sheet' !== $hook ) {
            return;
        }

        // Register and enqueue a dummy style handle
        wp_register_style( 'daily-order-sheet-admin', false );
        wp_enqueue_style( 'daily-order-sheet-admin' );

        // Add inline styles using WordPress API
        $css = "
            .daily-order-sheet-wrap {
                margin: 20px 20px 20px 0;
            }
            .daily-order-sheet-header {
                background: #fff;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .daily-order-sheet-header h1 {
                margin: 0 0 15px 0;
            }
            .date-picker-form {
                display: flex;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
            }
            .date-picker-form input[type='date'] {
                padding: 8px 12px;
                font-size: 14px;
            }
            .column-visibility-toggle {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
            }
            .column-visibility-toggle summary {
                cursor: pointer;
                font-weight: 600;
                padding: 8px 0;
                user-select: none;
            }
            .column-visibility-toggle summary:hover {
                color: #2271b1;
            }
            .column-checkboxes {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 10px;
                margin-top: 10px;
                padding: 10px;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .column-checkboxes label {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 4px;
            }
            .column-checkboxes input[type='checkbox'] {
                margin: 0;
            }
            .orders-table-container {
                background: #fff;
                padding: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .orders-summary {
                margin-bottom: 20px;
                padding: 15px;
                background: #f0f6fc;
                border-left: 4px solid #2271b1;
            }
            .orders-summary h2 {
                margin: 0 0 10px 0;
                font-size: 18px;
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .cache-indicator {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                font-size: 12px;
                padding: 4px 8px;
                background: #fff3cd;
                color: #856404;
                border-radius: 3px;
                border: 1px solid #ffeaa7;
                font-weight: normal;
                cursor: help;
            }
            .cache-indicator.fresh {
                background: #d4edda;
                color: #155724;
                border-color: #c3e6cb;
            }
            .cache-indicator .dashicons {
                font-size: 14px;
                width: 14px;
                height: 14px;
            }
            .orders-summary .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-top: 10px;
            }
            .orders-summary .stat {
                padding: 10px;
                background: #fff;
                border-radius: 4px;
            }
            .orders-summary .stat strong {
                display: block;
                font-size: 24px;
                color: #2271b1;
            }
            .orders-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                table-layout: fixed;
            }
            .orders-table th {
                background: #f0f0f1;
                padding: 12px;
                text-align: left;
                font-weight: 600;
                border-bottom: 2px solid #ddd;
                white-space: nowrap;
                word-wrap: break-word;
                cursor: pointer;
                user-select: none;
                position: relative;
                padding-right: 25px;
            }
            .orders-table th:hover {
                background: #e8e8e9;
            }
            .orders-table th::after {
                content: '⇅';
                position: absolute;
                right: 8px;
                opacity: 0.3;
                font-size: 14px;
            }
            .orders-table th.sort-asc::after {
                content: '↑';
                opacity: 1;
                color: #2271b1;
            }
            .orders-table th.sort-desc::after {
                content: '↓';
                opacity: 1;
                color: #2271b1;
            }
            .orders-table td {
                padding: 12px;
                border-bottom: 1px solid #ddd;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            .orders-table tr:hover {
                background: #f9f9f9;
            }
            .order-link {
                color: #2271b1;
                text-decoration: none;
                font-weight: 600;
                transition: color 0.2s;
            }
            .order-link:hover {
                color: #135e96;
                text-decoration: underline;
            }
            .order-status {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }
            .order-status.completed,
            .order-status.wc-completed {
                background: #c6efce;
                color: #006100;
            }
            .order-status.processing,
            .order-status.wc-processing {
                background: #fff4ce;
                color: #9f6000;
            }
            .order-status.pending,
            .order-status.wc-pending {
                background: #e5e5e5;
                color: #333;
            }
            .order-status.on-hold,
            .order-status.wc-on-hold {
                background: #fef5e7;
                color: #f39c12;
            }
            .order-status.cancelled,
            .order-status.wc-cancelled,
            .order-status.failed,
            .order-status.wc-failed {
                background: #ffcccc;
                color: #a00;
            }
            .order-status.refunded,
            .order-status.wc-refunded {
                background: #eee;
                color: #666;
            }
            .no-orders {
                padding: 40px;
                text-align: center;
                color: #666;
            }
            .dos-loading {
                padding: 20px;
                text-align: center;
                font-size: 14px;
                color: #666;
                background: #f0f6fc;
                border-radius: 4px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }

            /* Print styles */
            @media print {
                /* Hide admin elements */
                .daily-order-sheet-header,
                #wpadminbar,
                #adminmenumain,
                #adminmenuback,
                #adminmenuwrap,
                .date-picker-form,
                .column-visibility-toggle,
                .cache-indicator,
                .dos-loading,
                .button,
                .notice,
                #wpfooter,
                #footer-thankyou,
                .update-nag {
                    display: none !important;
                }

                /* Reset page layout */
                * {
                    box-shadow: none !important;
                    text-shadow: none !important;
                }

                html, body {
                    margin: 0 !important;
                    padding: 0 !important;
                    width: 100% !important;
                }

                body {
                    margin: 0 !important;
                    padding: 0 !important;
                }

                #wpwrap,
                #wpcontent,
                #wpbody,
                #wpbody-content {
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .wrap {
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .daily-order-sheet-wrap {
                    margin: 0 !important;
                    padding: 0 !important;
                    width: 100% !important;
                }

                .orders-table-container {
                    margin: 0 !important;
                    padding: 10px !important;
                    background: white !important;
                }

                /* Summary styling */
                .orders-summary {
                    background: white !important;
                    border: 2px solid #000 !important;
                    padding: 10px !important;
                    margin-bottom: 15px !important;
                    page-break-after: avoid;
                    page-break-inside: avoid;
                }

                .orders-summary h2 {
                    font-size: 16px !important;
                    margin: 0 0 10px 0 !important;
                    color: #000 !important;
                }

                .orders-summary .stats {
                    display: flex !important;
                    gap: 20px !important;
                }

                .orders-summary .stat {
                    background: white !important;
                    border: 1px solid #000 !important;
                    padding: 5px 10px !important;
                }

                .orders-summary .stat strong {
                    font-size: 18px !important;
                    color: #000 !important;
                }

                /* Table styling */
                .orders-table {
                    width: 100% !important;
                    border-collapse: collapse !important;
                    font-size: 9px !important;
                    margin: 0 !important;
                    table-layout: fixed !important;
                }

                .orders-table th {
                    background: #f0f0f0 !important;
                    border: 1px solid #000 !important;
                    padding: 4px 3px !important;
                    text-align: left !important;
                    font-weight: bold !important;
                    font-size: 9px !important;
                    color: #000 !important;
                    white-space: nowrap !important;
                    cursor: default !important;
                }

                .orders-table th::after {
                    display: none !important;
                }

                .orders-table td {
                    border: 1px solid #999 !important;
                    padding: 4px 3px !important;
                    font-size: 8px !important;
                    line-height: 1.3 !important;
                    color: #000 !important;
                    vertical-align: top !important;
                }

                .orders-table tbody tr {
                    page-break-inside: avoid !important;
                    page-break-after: auto !important;
                }

                .orders-table tbody tr:nth-child(even) {
                    background: #f9f9f9 !important;
                }

                /* Status badges */
                .order-status {
                    display: inline-block !important;
                    padding: 2px 4px !important;
                    border: 1px solid #000 !important;
                    border-radius: 3px !important;
                    font-size: 7px !important;
                    font-weight: bold !important;
                    background: white !important;
                    color: #000 !important;
                }

                /* Ticket details */
                .orders-table td strong {
                    font-size: 9px !important;
                }

                .orders-table td small {
                    font-size: 7px !important;
                    line-height: 1.2 !important;
                    display: block !important;
                    margin-top: 2px !important;
                }

                /* Order links */
                .order-link {
                    color: #000 !important;
                    text-decoration: none !important;
                    font-weight: bold !important;
                }

                /* Page breaks */
                thead {
                    display: table-header-group !important;
                }

                tfoot {
                    display: table-footer-group !important;
                }

                /* Ensure first row stays with header */
                .orders-table thead + tbody tr:first-child {
                    page-break-before: avoid !important;
                }
            }
        ";

        wp_add_inline_style( 'daily-order-sheet-admin', $css );
    }

    /**
     * Enqueue admin scripts
     */
    public static function enqueue_scripts( $hook ) {
        if ( 'toplevel_page_daily-order-sheet' !== $hook ) {
            return;
        }

        // Enqueue jQuery as dependency
        wp_enqueue_script( 'jquery' );

        // Register our custom script (dummy handle for localization)
        wp_register_script( 'daily-order-sheet-ajax', false, [ 'jquery' ], '1.0.0', true );
        wp_enqueue_script( 'daily-order-sheet-ajax' );

        // Localize script for AJAX
        wp_localize_script( 'daily-order-sheet-ajax', 'dosAjax', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'dos_load_orders' ),
        ] );

        $js = "
        document.addEventListener('DOMContentLoaded', function() {
            // AJAX date loading
            const dateInput = document.getElementById('order_date');
            const tableContainer = document.querySelector('.orders-table-container');
            const refreshBtn = document.querySelector('button[name=\"refresh_cache\"]');

            if (dateInput && tableContainer) {
                dateInput.addEventListener('change', function() {
                    loadOrders(this.value, false);
                });

                if (refreshBtn) {
                    refreshBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const date = dateInput.value || new Date().toISOString().split('T')[0];
                        loadOrders(date, true);
                    });
                }
            }

            function loadOrders(date, refreshCache) {
                // Show loading state
                tableContainer.style.opacity = '0.5';
                tableContainer.style.pointerEvents = 'none';

                // Add loading spinner
                let loader = document.createElement('div');
                loader.className = 'dos-loading';
                loader.innerHTML = '<span class=\"spinner is-active\" style=\"float: none; margin: 0;\"></span> Loading orders...';
                tableContainer.insertBefore(loader, tableContainer.firstChild);

                // Make AJAX request
                jQuery.ajax({
                    url: dosAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dos_load_orders',
                        date: date,
                        refresh_cache: refreshCache ? '1' : '0',
                        nonce: dosAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update table content
                            tableContainer.innerHTML = response.data.html;

                            // Update URL without reload
                            const newUrl = new URL(window.location);
                            newUrl.searchParams.set('order_date', date);
                            if (refreshCache) {
                                newUrl.searchParams.set('refresh_cache', '1');
                            } else {
                                newUrl.searchParams.delete('refresh_cache');
                            }
                            window.history.pushState({date: date}, '', newUrl);

                            // Re-initialize table sorting
                            initTableSort();
                        } else {
                            alert('Error: ' + (response.data.message || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Failed to load orders. Please try again.');
                    },
                    complete: function() {
                        // Remove loading state
                        tableContainer.style.opacity = '1';
                        tableContainer.style.pointerEvents = 'auto';
                    }
                });
            }

            // Table sorting functionality (wrapped in function for re-initialization)
            function initTableSort() {
                const table = document.querySelector('.orders-table');
                if (!table) return;

                const headers = table.querySelectorAll('th');
                let currentSort = { column: -1, direction: 'asc' };

                headers.forEach((header, index) => {
                    header.addEventListener('click', function() {
                        sortTable(index);
                    });
                });

                function sortTable(columnIndex) {
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));

                // Determine sort direction
                let direction = 'asc';
                if (currentSort.column === columnIndex) {
                    direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                }

                // Remove sort classes from all headers
                headers.forEach(h => {
                    h.classList.remove('sort-asc', 'sort-desc');
                });

                // Add sort class to current header
                headers[columnIndex].classList.add('sort-' + direction);

                // Sort rows
                rows.sort((a, b) => {
                    const cellA = a.children[columnIndex];
                    const cellB = b.children[columnIndex];

                    if (!cellA || !cellB) return 0;

                    let valA = cellA.textContent.trim();
                    let valB = cellB.textContent.trim();

                    // Try to parse as number
                    const numA = parseFloat(valA.replace(/[^0-9.-]/g, ''));
                    const numB = parseFloat(valB.replace(/[^0-9.-]/g, ''));

                    if (!isNaN(numA) && !isNaN(numB)) {
                        return direction === 'asc' ? numA - numB : numB - numA;
                    }

                    // Try to parse as date
                    const dateA = Date.parse(valA);
                    const dateB = Date.parse(valB);

                    if (!isNaN(dateA) && !isNaN(dateB)) {
                        return direction === 'asc' ? dateA - dateB : dateB - dateA;
                    }

                    // String comparison
                    if (direction === 'asc') {
                        return valA.localeCompare(valB);
                    } else {
                        return valB.localeCompare(valA);
                    }
                });

                // Re-append rows in sorted order
                rows.forEach(row => tbody.appendChild(row));

                    // Update current sort
                    currentSort = { column: columnIndex, direction: direction };
                }
            }

            // Initialize table sorting on page load
            initTableSort();

            // Handle browser back/forward buttons
            window.addEventListener('popstate', function(e) {
                if (e.state && e.state.date) {
                    dateInput.value = e.state.date;
                    loadOrders(e.state.date, false);
                }
            });
        });
        ";

        wp_add_inline_script( 'daily-order-sheet-ajax', $js );
    }

    /**
     * Render the admin page
     */
    public static function render_admin_page() {
        // Check capability
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'daily-order-sheet' ) );
        }

        // Handle column visibility save
        if ( isset( $_POST['save_columns'] ) ) {
            // Verify nonce for CSRF protection
            if ( ! isset( $_POST['dos_column_nonce'] ) || ! wp_verify_nonce( $_POST['dos_column_nonce'], 'save_column_visibility' ) ) {
                wp_die( __( 'Security check failed. Please refresh the page and try again.', 'daily-order-sheet' ) );
            }

            if ( isset( $_POST['visible_columns'] ) && is_array( $_POST['visible_columns'] ) ) {
                // Sanitize each column key
                $columns = array_map( 'sanitize_key', $_POST['visible_columns'] );
                self::save_visible_columns( $columns );
                echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Column preferences saved.', 'daily-order-sheet' ) . '</p></div>';
            }
        }

        // Get selected date (default to today)
        $selected_date = isset( $_GET['order_date'] ) ? sanitize_text_field( $_GET['order_date'] ) : date( 'Y-m-d' );

        // Enhanced date validation with format check and reasonable range
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $selected_date ) ) {
            // Invalid format
            $selected_date = date( 'Y-m-d' );
        } else {
            $date_parts = explode( '-', $selected_date );
            $year = (int) $date_parts[0];
            $month = (int) $date_parts[1];
            $day = (int) $date_parts[2];

            // Validate date and ensure reasonable range (2000-2050)
            if ( ! checkdate( $month, $day, $year ) || $year < 2000 || $year > 2050 ) {
                $selected_date = date( 'Y-m-d' );
            }
        }

        // Get visible columns
        $visible_columns = self::get_visible_columns();

        ?>
        <div class="wrap daily-order-sheet-wrap">
            <div class="daily-order-sheet-header">
                <h1><?php _e( 'Daily Order Sheet', 'daily-order-sheet' ); ?></h1>

                <div class="date-picker-form">
                    <label for="order_date"><?php _e( 'Select Date:', 'daily-order-sheet' ); ?></label>
                    <input type="date" id="order_date" name="order_date" value="<?php echo esc_attr( $selected_date ); ?>" required>
                    <button type="button" name="refresh_cache" class="button" title="<?php esc_attr_e( 'Refresh data from database (bypass cache)', 'daily-order-sheet' ); ?>">
                        <span class="dashicons dashicons-update"></span> <?php _e( 'Refresh', 'daily-order-sheet' ); ?>
                    </button>
                    <button type="button" class="button" onclick="window.print();"><?php _e( 'Print', 'daily-order-sheet' ); ?></button>
                </div>

                <details class="column-visibility-toggle">
                    <summary><?php _e( 'Column Visibility', 'daily-order-sheet' ); ?></summary>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'save_column_visibility', 'dos_column_nonce' ); ?>
                        <div class="column-checkboxes">
                            <?php foreach ( self::AVAILABLE_COLUMNS as $key => $label ) : ?>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="visible_columns[]"
                                        value="<?php echo esc_attr( $key ); ?>"
                                        <?php checked( in_array( $key, $visible_columns ) ); ?>
                                    >
                                    <?php echo esc_html( __( $label, 'daily-order-sheet' ) ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p style="margin-top: 10px;">
                            <button type="submit" name="save_columns" class="button button-secondary">
                                <?php _e( 'Save Preferences', 'daily-order-sheet' ); ?>
                            </button>
                        </p>
                    </form>
                </details>
            </div>

            <div class="orders-table-container">
                <?php self::render_orders_table( $selected_date, $visible_columns ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the orders table for a specific date
     *
     * @param string $date Date in Y-m-d format
     * @param array $visible_columns Array of visible column keys
     */
    public static function render_orders_table( $date, $visible_columns = null ) {
        // If no visible columns specified, show all
        if ( $visible_columns === null ) {
            $visible_columns = array_keys( self::AVAILABLE_COLUMNS );
        }
        // Check if required plugins are active
        if ( ! class_exists( 'Tribe__Tickets_Plus__Commerce__WooCommerce__Orders__Table' ) ) {
            echo '<div class="notice notice-error"><p>' . __( 'Event Tickets Plus with WooCommerce integration is required.', 'daily-order-sheet' ) . '</p></div>';
            return;
        }

        if ( ! function_exists( 'wc_get_order' ) ) {
            echo '<div class="notice notice-error"><p>' . __( 'WooCommerce is required.', 'daily-order-sheet' ) . '</p></div>';
            return;
        }

        // Check if data is cached
        $cache_key = 'dos_orders_' . md5( $date );
        $is_cached = ( false !== get_transient( $cache_key ) && ! isset( $_GET['refresh_cache'] ) );

        // Get orders data
        $orders_data = self::get_orders_for_date( $date );

        if ( empty( $orders_data ) ) {
            echo '<div class="no-orders"><p>' . sprintf( __( 'No orders found for %s', 'daily-order-sheet' ), date( 'F j, Y', strtotime( $date ) ) ) . '</p></div>';
            return;
        }

        // Calculate summary statistics
        $total_orders = count( $orders_data );
        $total_tickets = array_sum( array_column( $orders_data, 'ticket_count' ) );
        $unique_events = count( array_unique( array_column( $orders_data, 'event_id' ) ) );

        // Display summary
        ?>
        <div class="orders-summary">
            <h2>
                <?php echo sprintf( __( 'Orders for %s', 'daily-order-sheet' ), date( 'l, F j, Y', strtotime( $date ) ) ); ?>
                <?php if ( $is_cached ) : ?>
                    <span class="cache-indicator" title="<?php esc_attr_e( 'Data loaded from cache for better performance. Click Refresh to get latest data.', 'daily-order-sheet' ); ?>">
                        <span class="dashicons dashicons-clock"></span> <?php _e( 'Cached', 'daily-order-sheet' ); ?>
                    </span>
                <?php else : ?>
                    <span class="cache-indicator fresh" title="<?php esc_attr_e( 'Fresh data from database', 'daily-order-sheet' ); ?>">
                        <span class="dashicons dashicons-yes-alt"></span> <?php _e( 'Fresh', 'daily-order-sheet' ); ?>
                    </span>
                <?php endif; ?>
            </h2>
            <div class="stats">
                <div class="stat">
                    <span><?php _e( 'Total Orders', 'daily-order-sheet' ); ?></span>
                    <strong><?php echo esc_html( $total_orders ); ?></strong>
                </div>
                <div class="stat">
                    <span><?php _e( 'Total Tickets', 'daily-order-sheet' ); ?></span>
                    <strong><?php echo esc_html( $total_tickets ); ?></strong>
                </div>
                <div class="stat">
                    <span><?php _e( 'Events', 'daily-order-sheet' ); ?></span>
                    <strong><?php echo esc_html( $unique_events ); ?></strong>
                </div>
            </div>
        </div>

        <table class="orders-table">
            <thead>
                <tr>
                    <?php if ( self::is_column_visible( 'event', $visible_columns ) ) : ?>
                        <th><?php _e( 'Event', 'daily-order-sheet' ); ?></th>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'event_date', $visible_columns ) ) : ?>
                        <th><?php _e( 'Event Date/Time', 'daily-order-sheet' ); ?></th>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'order_id', $visible_columns ) ) : ?>
                        <th><?php _e( 'Order ID', 'daily-order-sheet' ); ?></th>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'purchaser_name', $visible_columns ) ) : ?>
                        <th><?php _e( 'Purchaser Name', 'daily-order-sheet' ); ?></th>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'email', $visible_columns ) ) : ?>
                        <th><?php _e( 'Email', 'daily-order-sheet' ); ?></th>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'phone', $visible_columns ) ) : ?>
                        <th><?php _e( 'Phone', 'daily-order-sheet' ); ?></th>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'status', $visible_columns ) ) : ?>
                        <th><?php _e( 'Status', 'daily-order-sheet' ); ?></th>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'tickets', $visible_columns ) ) : ?>
                        <th><?php _e( 'Tickets', 'daily-order-sheet' ); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $orders_data as $order ) : ?>
                <tr>
                    <?php if ( self::is_column_visible( 'event', $visible_columns ) ) : ?>
                        <td><strong><?php echo esc_html( $order['event_title'] ); ?></strong></td>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'event_date', $visible_columns ) ) : ?>
                        <td><?php echo esc_html( date( 'M j, Y g:i A', strtotime( $order['event_date'] ) ) ); ?></td>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'order_id', $visible_columns ) ) : ?>
                        <td>
                            <a href="<?php echo esc_url( $order['order_edit_url'] ); ?>" target="_blank" class="order-link">
                                #<?php echo esc_html( $order['order_number'] ); ?>
                            </a>
                        </td>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'purchaser_name', $visible_columns ) ) : ?>
                        <td><?php echo esc_html( $order['purchaser_name'] ); ?></td>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'email', $visible_columns ) ) : ?>
                        <td><?php echo esc_html( $order['purchaser_email'] ); ?></td>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'phone', $visible_columns ) ) : ?>
                        <td><?php echo esc_html( $order['purchaser_phone'] ?: '-' ); ?></td>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'status', $visible_columns ) ) : ?>
                        <td>
                            <span class="order-status <?php echo esc_attr( $order['order_status'] ); ?>">
                                <?php echo esc_html( $order['order_status_label'] ); ?>
                            </span>
                        </td>
                    <?php endif; ?>
                    <?php if ( self::is_column_visible( 'tickets', $visible_columns ) ) : ?>
                        <td>
                            <strong><?php echo esc_html( $order['ticket_count'] ); ?></strong><br>
                            <small><?php echo esc_html( $order['tickets'] ); ?></small>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Get WooCommerce orders for events on a specific date
     *
     * @param string $date Date in Y-m-d format
     * @param bool $force_refresh Optional. Force refresh cache. Default false.
     * @return array Array of order data
     */
    public static function get_orders_for_date( $date, $force_refresh = false ) {
        // Generate cache key
        $cache_key = 'dos_orders_' . md5( $date );

        // Check if we should bypass cache
        $bypass_cache = $force_refresh || ( isset( $_GET['refresh_cache'] ) && current_user_can( self::CAPABILITY ) );

        // Try to get cached data unless force refresh
        if ( ! $bypass_cache ) {
            $cached_results = get_transient( $cache_key );
            if ( false !== $cached_results && is_array( $cached_results ) ) {
                // Log cache hit
                error_log( sprintf( '[Daily Order Sheet] Cache HIT for date: %s', $date ) );

                // Still log PII access even when using cache
                $current_user = wp_get_current_user();
                $log_message = sprintf(
                    '[Daily Order Sheet] PII Access (CACHED) - User: %s (ID: %d, Email: %s) | Date: %s | Time: %s | IP: %s',
                    $current_user->user_login,
                    $current_user->ID,
                    $current_user->user_email,
                    $date,
                    current_time( 'mysql' ),
                    isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'
                );
                error_log( $log_message );

                return $cached_results;
            }
        }

        // Cache miss or forced refresh - log it
        error_log( sprintf( '[Daily Order Sheet] Cache MISS for date: %s (Forced: %s)', $date, $bypass_cache ? 'Yes' : 'No' ) );

        // Log PII access for GDPR/CCPA compliance
        $current_user = wp_get_current_user();
        $log_message = sprintf(
            '[Daily Order Sheet] PII Access - User: %s (ID: %d, Email: %s) | Date: %s | Time: %s | IP: %s',
            $current_user->user_login,
            $current_user->ID,
            $current_user->user_email,
            $date,
            current_time( 'mysql' ),
            isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'
        );
        error_log( $log_message );

        // Query events occurring on this date
        $args = [
            'post_type'      => 'tribe_events',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => '_EventStartDate',
                    'value'   => [ $date . ' 00:00:00', $date . ' 23:59:59' ],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATETIME',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => '_EventStartDate',
            'order'          => 'ASC',
        ];

        $events = get_posts( $args );

        if ( empty( $events ) ) {
            return [];
        }

        $results = [];

        foreach ( $events as $event ) {
            // Get all orders for this event with error handling
            try {
                $orders = Tribe__Tickets_Plus__Commerce__WooCommerce__Orders__Table::get_orders( $event->ID );
            } catch ( Exception $e ) {
                error_log( sprintf( '[Daily Order Sheet] Error getting orders for event %d: %s', $event->ID, $e->getMessage() ) );
                continue;
            }

            if ( empty( $orders ) ) {
                continue;
            }

            // Get valid order items for this event with error handling
            try {
                $valid_items = Tribe__Tickets_Plus__Commerce__WooCommerce__Orders__Table::get_valid_order_items_for_event( $event->ID, $orders );
            } catch ( Exception $e ) {
                error_log( sprintf( '[Daily Order Sheet] Error getting order items for event %d: %s', $event->ID, $e->getMessage() ) );
                continue;
            }

            foreach ( $orders as $order_id => $order_data ) {
                // Skip if order has no valid items for this event
                if ( empty( $valid_items[ $order_id ] ) ) {
                    continue;
                }

                // Get WC_Order object for additional data with error handling
                try {
                    $order = wc_get_order( $order_id );
                } catch ( Exception $e ) {
                    error_log( sprintf( '[Daily Order Sheet] Error getting WooCommerce order %d: %s', $order_id, $e->getMessage() ) );
                    continue;
                }

                if ( ! $order ) {
                    continue;
                }

                // Count tickets for this event in this order
                $ticket_count = 0;
                $ticket_names = [];

                foreach ( $valid_items[ $order_id ] as $ticket_id => $line_item ) {
                    $ticket_count += absint( $line_item['quantity'] );
                    $ticket_names[] = $line_item['name'] . ' (x' . $line_item['quantity'] . ')';
                }

                // Build order data array
                $results[] = [
                    'event_id'           => $event->ID,
                    'event_title'        => $event->post_title,
                    'event_date'         => get_post_meta( $event->ID, '_EventStartDate', true ),
                    'order_id'           => $order_id,
                    'order_number'       => $order->get_order_number(),
                    'order_edit_url'     => admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' ),
                    'purchaser_name'     => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                    'purchaser_email'    => $order->get_billing_email(),
                    'purchaser_phone'    => $order->get_billing_phone(),
                    'order_status'       => $order->get_status(),
                    'order_status_label' => wc_get_order_status_name( $order->get_status() ),
                    'ticket_count'       => $ticket_count,
                    'tickets'            => implode( ', ', $ticket_names ),
                    'order_date'         => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
                ];
            }
        }

        // Sort by event date, then by order date
        usort( $results, function( $a, $b ) {
            $event_compare = strcmp( $a['event_date'], $b['event_date'] );
            if ( $event_compare !== 0 ) {
                return $event_compare;
            }
            return strcmp( $a['order_date'], $b['order_date'] );
        });

        // Store results in cache for 1 hour
        set_transient( $cache_key, $results, HOUR_IN_SECONDS );
        error_log( sprintf( '[Daily Order Sheet] Cached %d orders for date: %s', count( $results ), $date ) );

        return $results;
    }
}

// Hook to clear cache when orders are created or updated
add_action( 'woocommerce_new_order', 'daily_order_sheet_clear_cache' );
add_action( 'woocommerce_update_order', 'daily_order_sheet_clear_cache' );
add_action( 'save_post_tribe_events', 'daily_order_sheet_clear_cache' );

/**
 * Clear Daily Order Sheet cache when orders or events are modified
 */
function daily_order_sheet_clear_cache() {
    Daily_Order_Sheet::clear_orders_cache();
}

// Initialize the plugin
Daily_Order_Sheet::init();
