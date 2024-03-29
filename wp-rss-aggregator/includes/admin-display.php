<?php

// Adds the "active" class to the feed source list table rows, for active feed sources
add_filter('post_class', function ($classes, $class, $postId) {
    $post = get_post($postId);

    if ($post->post_type !== 'wprss_feed') {
        return $classes;
    }

    if (wprss_is_feed_source_active($postId)) {
        $classes[] = 'active';
    }

    return $classes;
}, 10, 3);

add_filter('manage_wprss_feed_posts_columns', 'wprss_set_feed_custom_columns', 20, 1);
/**
 * Set up the custom columns for the wprss_feed list
 *
 * @since 2.0
 */
function wprss_set_feed_custom_columns($columns)
{
    $isTrashPage = filter_input(INPUT_GET, 'post_status') === 'trash';

    $columns = [
        'cb' => '<input type="checkbox" />',
    ];

    if (!$isTrashPage) {
        $columns['state'] = __('State', 'wprss');
    }

    $columns['title'] = __('Name', 'wprss');

    $columns = apply_filters('wprss_set_feed_custom_columns', $columns);

    if (!$isTrashPage) {
        $columns['updates'] = __('Updates', 'wprss');
        $columns['feed-count'] = __(apply_filters('wprss_feed_items_count_column', 'Imported items'), 'wprss');
    }

    return apply_filters('wprss_feed_columns', $columns);
}

add_action("manage_wprss_feed_posts_custom_column", "wprss_show_custom_columns", 10, 2);
/**
 * Show up the custom columns for the wprss_feed list
 *
 * @since 2.0
 */
function wprss_show_custom_columns($column, $post_id)
{
    switch ($column) {
        case 'state':
            $switch_title = __('Activate or pause auto importing for this feed', 'wprss');
            ?>
            <div class="wprss-feed-state-container" title="<?= esc_attr($switch_title); ?>">
                <label class="wprss-switch">
                    <input
                        type="checkbox"
                        class="wprss-toggle-feed-state"
                        autocomplete="off"
                        value="<?= esc_attr($post_id); ?>"
                        <?php checked(true, wprss_is_feed_source_active($post_id)) ?>
                    />
                    <span class="wprss-switch-slider"></span>
                </label>
            </div>

            <?php
            $feed_type = 'rss';
            $feed_icon = 'rss';
            $icon_title = __('Normal RSS Feed', 'wprss');

            if (wprss_is_feed_youtube($post_id)) {
                $feed_type = 'yt';
                $feed_icon = 'video-alt3';
                $icon_title = __('YouTube Feed', 'wprss');
            }
            ?>

            <div
                class="wprss-feed-source-type wprss-feed-source-type-<?= esc_attr($feed_type) ?>"
                title="<?= esc_attr($icon_title) ?>"
            >
                <span class="dashicons dashicons-<?= esc_attr($feed_icon) ?>"></span>
            </div>
            <?php

            break;

        case 'updates':
            // Get the update interval
            $update_interval = get_post_meta($post_id, 'wprss_update_interval', true);
            // Get the last updated and next update data
            $last_update = get_post_meta($post_id, 'wprss_last_update', true);
            $last_update_items = get_post_meta($post_id, 'wprss_last_update_items', true);
            $next_update = wprss_get_next_feed_source_update($post_id);

            // If using the global interval, get the timestamp of the next global update
            if ($update_interval === wprss_get_default_feed_source_update_interval() || $update_interval === '') {
                $next_update = wp_next_scheduled('wprss_fetch_all_feeds_hook', []);
            }

            // Update the meta field
            if (wprss_is_feed_source_active($post_id)) {
                $next_update_text = $next_update === false
                    ? __('None', 'wprss')
                    : human_time_diff($next_update, time());
            } else {
                $next_update_text = __('...', 'wprss');
            }
            update_post_meta($post_id, 'wprss_next_update', $next_update_text);

            $timeAgo = empty($last_update)
                ? ''
                : human_time_diff($last_update, time());
            ?>

            <p class="next-update-container">
                <?= __('Next update in', 'wprss') ?>
                <code class="next-update">
                    <?= esc_html($next_update_text); ?>
                </code>
            </p>

            <p
                class="last-update-container"
                style="display: <?php echo empty($timeAgo) ? 'none' : 'inline-block'; ?>">
                <span class="last-update-num-items-container">
                    <?= _x('Updated', 'Example: "Updated 2 days ago"', 'wprss'); ?>
                    <span class="last-update-time-container">
                        <code class="last-update-time">
                            <?php printf(__('%1$s ago', 'wprss'), $timeAgo) ?>
                        </code>
                    </span>
                    (
                        <span class="last-update-num-items">
                            <?= esc_html($last_update_items) ?>
                        </span>

                        <?= _x('items', 'Example: "15 new"', 'wprss'); ?>
                    )
                </span>
            </p>

            <?php
            break;

        case 'feed-count':
            $items = wprss_get_feed_items_for_source($post_id);
            $has_items_class = ($items->post_count > 0) ? 'has-imported-items' : '';

            $errors = get_post_meta($post_id, 'wprss_error_last_import', true);
            $errorShowClass = ($errors !== '') ? 'wprss-show' : '';
            $default_msg = __(
                "This feed source experienced an error during the last feed fetch or validation check. Re-check the feed source URL or check the Error Log in the Debugging page for more details.",
                'wprss'
            );
            $msg = strlen($errors) > 0
                ? $errors
                : $default_msg;

            $errorIcon = sprintf(
                '<i title="%1$s" class="fa fa-warning fa-fw wprss-feed-error-symbol %2$s"></i>',
                esc_attr($msg),
                esc_attr($errorShowClass)
            );

            $view_items_url = admin_url('edit.php?post_type=wprss_feed_item&wprss_feed=' . $post_id);
            $view_items_url = apply_filters('wprss_view_feed_items_row_action_link', $view_items_url, $post_id);
            ?>
            <a
                href="<?= esc_attr($view_items_url); ?>"
                class="items-imported-link <?= esc_attr($has_items_class); ?>"
                title="<?= esc_attr(__('View the imported items for this feed source', 'wprss')); ?>"
            >
                <span class="items-imported">
                    <?= esc_attr($items->post_count) ?>
                </span>
                <?= __('items', 'wprss') ?>
            </a>
            <div class="spinner"></div>

            <?= $errorIcon ?>

            <div class="row-actions">
                <span class="fetch">
                    <a
                        href="javascript:void(0);"
                        class="wprss_fetch_items_ajax_action"
                        pid="<?= esc_attr($post_id); ?>"
                        purl="<?= esc_attr(admin_url('admin-ajax.php')); ?>">
                        <?= esc_html(__('Fetch', 'wprss')); ?>
                    </a>
                </span>
                <span class="purge-posts trash <?= esc_attr($has_items_class) ?>">
                        |
                        <a
                            href="javascript:void(0);"
                            class="wprss_delete_items_ajax_action"
                            pid="<?= esc_attr($post_id); ?>"
                            purl="<?= esc_attr(admin_url('admin-ajax.php')); ?>">
                            <?= esc_html(__('Delete items', 'wprss')) ?>
                        </a>
                    </span>
            </div>
            <?php

            // Set meta field for items imported
            update_post_meta($post_id, 'wprss_items_imported', $items->post_count);

            break;
    }
}

add_filter("manage_edit-wprss_feed_sortable_columns", "wprss_feed_sortable_columns");
/**
 * Make the custom columns sortable for wprss_feed post type
 *
 * @since 2.0
 */
function wprss_feed_sortable_columns()
{
    $sortable_columns = [
        // meta column id => sort by value used in query
        'state' => 'state',
        'title' => 'title',
        'updates' => 'updates',
        'feed-count' => 'feed-count',
    ];

    return apply_filters('wprss_feed_sortable_columns', $sortable_columns);
}

add_action('pre_get_posts', 'wprss_feed_source_order');
/**
 * Change order of feed sources to alphabetical ascending according to feed name
 *
 * @since 2.2
 */
function wprss_feed_source_order($query)
{
    // Check if the query is being processed in WP Admin, is the main query, and is targeted
    // for the wprss_feed CPT. If not, stop
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'wprss_feed') {
        return;
    }

    // Get the sorting query args
    $order = strtoupper($query->get('order'));
    $orderby = $query->get('orderby');

    // If order is not specified, default to ascending
    if ($order !== 'ASC' && $order !== 'DESC') {
        $order = 'ASC';
    }

    $query->set('order', $order);

    // If not explicitly sorting or sorting by title, sort by title
    if (!$orderby || $orderby === 'title') {
        $query->set('orderby', 'title');
    }

    // Check what we are sorting by
    switch ($orderby) {
        case 'state':
            $query->set('meta_key', 'wprss_state');
            $query->set('orderby', 'meta_value');
            break;

        case 'updates':
            $query->set('meta_key', 'wprss_next_update');
            $query->set('orderby', 'meta_value');

            break;
        case 'feed-count':
            $query->set('meta_key', 'wprss_items_imported');
            $query->set('orderby', 'meta_value_num');

            break;
    }
}

add_filter('manage_wprss_feed_item_posts_columns', 'wprss_set_feed_item_custom_columns', 20, 1);
/**
 * Set up the custom columns for the wprss_feed source list
 *
 * @since 2.0
 */
function wprss_set_feed_item_custom_columns($columns)
{
    return apply_filters('wprss_set_feed_item_custom_columns', [
        'cb' => '<input type="checkbox" />',
        'title' => __('Name', 'wprss'),
        'permalink' => __('Permalink', 'wprss'),
        'publishdate' => __('Date published', 'wprss'),
        'source' => __('Source', 'wprss'),
    ]);
}

add_action("manage_wprss_feed_item_posts_custom_column", "wprss_show_feed_item_custom_columns", 10, 2);
/**
 * Show up the custom columns for the wprss_feed list
 *
 * @since 2.0
 */
function wprss_show_feed_item_custom_columns($column, $post_id)
{
    switch ($column) {
        case "permalink":
            $url = get_post_meta($post_id, 'wprss_item_permalink', true);
            printf(
                '<a href="%s">%s</a>',
                esc_attr(esc_url($url)),
                esc_html($url)
            );
            break;

        case "publishdate":
            $item_date = get_the_time('U', get_the_ID());
            $item_date = ($item_date === '') ? date('U') : $item_date;
            $publishdate = date('Y-m-d H:i:s', $item_date);
            echo $publishdate;
            break;

        case "source":
            $query = new WP_Query();

            $feedId = get_post_meta($post_id, 'wprss_feed_id', true);
            $feedName = get_the_title($feedId);
            $feedEditLink = get_edit_post_link($feedId);

            printf(
                '<a href="%s">%s</a>',
                $feedEditLink,
                $feedName
            );

            break;
    }
}

add_filter("manage_edit-wprss_feed_item_sortable_columns", "wprss_feed_item_sortable_columns");
/**
 * Make the custom columns sortable
 *
 * @since 2.0
 */
function wprss_feed_item_sortable_columns()
{
    return apply_filters('wprss_feed_item_sortable_columns', [
        'publishdate' => 'publishdate',
        'source' => 'source',
    ]);
}

add_action('pre_get_posts', 'wprss_feed_item_orderby');
/**
 * Change ordering of posts on wprss_feed_item screen
 *
 * @since 2.0
 */
function wprss_feed_item_orderby($query)
{
    // Check if we're on the feed listing admin page
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'wprss_feed_item') {
        return;
    }

    // Set general orderby to date the feed item was published
    $query->set('orderby', 'date');

    // If user clicks on the reorder link, implement reordering
    $orderby = $query->get('orderby');
    if ('publishdate' == $orderby) {
        $query->set('order', 'DESC');
        $query->set('orderby', 'date');
    }
}

add_filter('post_updated_messages', 'wprss_feed_updated_messages');
/**
 * Change default notification message when new feed is added or updated
 *
 * @since 2.0
 */
function wprss_feed_updated_messages($messages)
{
    global $post, $post_ID;

    $messages['wprss_feed'] = [
        0 => '', // Unused. Messages start at index 1.
        1 => __('Feed source updated. ', 'wprss'),
        2 => __('Custom field updated.', 'wprss'),
        3 => __('Custom field deleted.', 'wprss'),
        4 => __('Feed source updated.', 'wprss'),
        5 => '',
        6 => __('Feed source saved.', 'wprss'),
        7 => __('Feed source saved.', 'wprss'),
        8 => __('Feed source submitted.', 'wprss'),
        9 => '',
        10 => __('Feed source updated.', 'wprss'),
    ];

    return apply_filters('wprss_feed_updated_messages', $messages);
}

add_filter('post_row_actions', 'wprss_remove_row_actions', 10, 2);
/**
 * Remove actions row for imported feed items, we don't want them to be editable or viewable
 *
 * @since 2.0
 */
function wprss_remove_row_actions($actions, $post)
{
    if (get_post_type($post) === 'wprss_feed_item') {
        if (!wpra_is_dev_mode()) {
            unset($actions['edit']);
        }
        unset($actions['view']);
        unset($actions['inline hide-if-no-js']);
    } elseif (get_post_type($post) === 'wprss_feed') {
        $actions = array_reverse($actions);
        $actions['id'] = sprintf(
            '<span class="wprss-row-id">%s</span>',
            sprintf(__('ID: %1$s', 'wprss'), $post->ID)
        );
        $actions = array_reverse($actions);

        unset($actions['view']);
        unset($actions['inline hide-if-no-js']);
    }

    return apply_filters('wprss_remove_row_actions', $actions);
}

add_action('wprss_delete_feed_items_from_source_hook', 'wprss_delete_feed_items_of_feed_source', 10, 1);
/**
 * Deletes the feed items of the feed source identified by the given ID.
 *
 * @since 3.5
 *
 * @param int $source_id The ID of the feed source
 */
function wprss_delete_feed_items_of_feed_source($source_id)
{
    wprss_delete_feed_items($source_id);

    update_post_meta($source_id, 'wprss_feed_is_deleting_items', '');
}

/**
 * Shows a notification that tells the user that feed items for a particular source are being deleted
 *
 * @since 3.5
 */
function wprss_notify_about_deleting_source_feed_items()
{
    $message = apply_filters(
        'wprss_notify_about_deleting_source_feed_items_message',
        __('The feed items for this feed source are being deleted in the background.', 'wprss')
    );

    printf('<div class="updated"><p>%s</p></div>', $message);
}

add_action('wp_ajax_wprss_fetch_items_row_action', 'wprss_fetch_feeds_action_hook');
/**
 * The AJAX function for the 'Fetch Feed Items' row action on the
 * 'All Feed Sources' page.
 *
 * @since 3.3
 */
function wprss_fetch_feeds_action_hook()
{
    $response = wprss()->createAjaxResponse();
    $wprss = wprss();
    $feedIdKey = 'feed_source_id';

    try {
        if (!current_user_can('edit_feed_sources')) {
            throw new Exception(__('Could not schedule fetch for feed source: user must have sufficient privileges.'));
        }

        // Verify admin referer
        if (!wprss_verify_nonce('wprss_feed_source_action', 'wprss_admin_ajax_nonce')) {
            throw new Exception(__('Could not schedule fetch for feed source: nonce is invalid.', 'wprss'));
        }

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception($wprss->__('Could not schedule fetch: feed source ID is invalid or was not specified'));
        }
        $response->setAjaxData($feedIdKey, $id);


        update_post_meta($id, 'wprss_force_next_fetch', '1');

        // Prepare the schedule args
        $schedule_args = [strval($id)];

        // Get the current schedule - do nothing if not scheduled
        $next_scheduled = wp_next_scheduled('wprss_fetch_single_feed_hook', $schedule_args);
        if ($next_scheduled !== false) {
            // If scheduled, unschedule it
            wp_unschedule_event($next_scheduled, 'wprss_fetch_single_feed_hook', $schedule_args);

            // Get the interval option for the feed source
            $interval = get_post_meta($id, 'wprss_update_interval', true);
            // if the feed source uses its own interval
            if ($interval !== '' && $interval !== wprss_get_default_feed_source_update_interval()) {
                // Add meta in feed source. This is used to notify the source that it needs to reschedule it
                update_post_meta($id, 'wprss_reschedule_event', $next_scheduled);
            }
        }

        // Schedule the event for 5 seconds from now
        $offset = floor(count(wpra_get_ready_cron_jobs()) / 2);
        $success = wp_schedule_single_event(time() + $offset, 'wprss_fetch_single_feed_hook', $schedule_args);
        if (!$success) {
            throw new Exception(__('Failed to schedule cron', 'wprss'));
        }
        wprss_flag_feed_as_updating($id);
    } catch (Exception $e) {
        $response = wprss()->createAjaxErrorResponse($e);
        if (isset($id)) {
            $response->setAjaxData($feedIdKey, $id);
        }
        echo $response->getBody();
        exit();
    }

    $response->setAjaxData('message', $wprss->__(['Fetch for feed source #%1$s successfully scheduled', $id]));
    $response->setAjaxData('success', $success);
    echo $response->getBody();
    exit();
}

add_action('wp_ajax_wprss_delete_items_row_action', 'wprss_delete_items_ajax_action_hook');
/**
 * The AJAX function for the 'Delete Items' row action on the 'All Feed Sources' page.
 *
 * @since 4.14
 */
function wprss_delete_items_ajax_action_hook()
{
    $kFeedSourceId = 'feed_source_id';
    $response = wprss()->createAjaxResponse();
    $wprss = wprss();
    try {
        $id = filter_input(INPUT_POST, 'id');
        if (empty($id)) {
            throw new Exception($wprss->__('Source ID was not specified'));
        }

        $response->setAjaxData($kFeedSourceId, $id);

        if (!current_user_can('edit_feed_sources')) {
            throw new Exception($wprss->__(['User must have sufficient privileges', $id]));
        }

        // Verify admin referer
        if (!wprss_verify_nonce('wprss_feed_source_action', 'wprss_admin_ajax_nonce')) {
            throw new Exception($wprss->__(['Nonce has expired - Please refresh the page.', $id]));
        }

        // Schedule a job that runs this function with the source id parameter
        $offset = floor(count(wpra_get_ready_cron_jobs()) / 2);
        $success = wp_schedule_single_event(time() + $offset, 'wprss_delete_feed_items_from_source_hook', [$id]);
        if (!$success) {
            throw new Exception(__('Failed to schedule cron', 'wprss'));
        }
        // Mark feed as deleting its items
        update_post_meta($id, 'wprss_feed_is_deleting_items', time());
    } catch (Exception $e) {
        $response = wprss()->createAjaxErrorResponse($e);
        if (isset($id)) {
            $response->setAjaxData($kFeedSourceId, $id);
        }
        echo $response->getBody();
        exit();
    }

    $response->setAjaxData('message', $wprss->__(['Items are being deleted', $id]));
    $response->setAjaxData('success', $success);
    echo $response->getBody();
    exit();
}

add_action('wp_ajax_wprss_toggle_feed_state', 'wprss_ajax_toggle_feed_state');
/**
 * The AJAX function for toggling a feed's state from the 'All Feed Sources' page.
 *
 * @since 4.14
 */
function wprss_ajax_toggle_feed_state()
{
    $kFeedSourceId = 'feed_source_id';
    $response = wprss()->createAjaxResponse();
    $wprss = wprss();
    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_DEFAULT);
        if (empty($id)) {
            throw new Exception($wprss->__('Source ID was not specified'));
        }

        $response->setAjaxData($kFeedSourceId, $id);

        if (!current_user_can('edit_feed_sources')) {
            throw new Exception($wprss->__(['User must have sufficient privileges', $id]));
        }

        // Verify admin referer
        if (!wprss_verify_nonce('wprss_feed_source_action', 'wprss_admin_ajax_nonce')) {
            throw new Exception($wprss->__(['Nonce has expired - Please refresh the page.', $id]));
        }

        $active = wprss_is_feed_source_active($id);

        if ($active) {
            wprss_pause_feed_source($id);
        } else {
            wprss_activate_feed_source($id);
        }

        $response->setAjaxData('active', !$active);
    } catch (Exception $e) {
        $response = wprss()->createAjaxErrorResponse($e);
        if (isset($id)) {
            $response->setAjaxData($kFeedSourceId, $id);
        }
        echo $response->getBody();
        exit();
    }

    $response->setAjaxData('message', $wprss->__(['Feed state changed successfully', $id]));
    echo $response->getBody();
    exit();
}

add_action('manage_posts_extra_tablenav', function ($which) {
    $screen = get_current_screen();
    $postType = $screen->post_type;
    // Only add on feed source list
    if ($postType !== 'wprss_feed') {
        return;
    }

    $nonceEl = new \Aventura\Wprss\Core\Block\Html\Span([
        'data-value' => wp_create_nonce('wprss_feed_source_action'),
        'id' => 'wprss_feed_source_action_nonce',
        'class' => 'hidden',
    ]);

    echo (string) $nonceEl;
});

add_filter('bulk_actions-edit-wprss_feed_item', 'wprss_custom_feed_item_bulk_actions');
/**
 * Allow filtering bulk actions for feed items
 *
 * @since 2.0
 */
function wprss_custom_feed_item_bulk_actions($actions)
{
    if (!wpra_is_dev_mode()) {
        unset($actions['edit']);
    }

    return apply_filters('wprss_custom_feed_item_bulk_actions', $actions);
}

add_action('admin_footer-edit.php', 'wprss_remove_a_from_feed_title');
/**
 * Remove hyperlink from imported feed titles in list posts screen
 *
 * @since 2.0
 */
function wprss_remove_a_from_feed_title()
{
    if ('edit-wprss_feed_item' !== get_current_screen()->id) {
        return;
    }

    ?>
    <script type="text/javascript">
        jQuery('table.wp-list-table a.row-title').contents().unwrap();
    </script>
    <?php
}

add_action('wp_before_admin_bar_render', 'wprss_modify_admin_bar');
/**
 * Removes the old "View Source" menu item from the admin bar and adds a new
 * "View items" menu bar item, that opens a new tab, showing the items imported
 * from that feed source.
 *
 * Only shown on the wprss_feed edit page.
 *
 * @since 4.2
 */
function wprss_modify_admin_bar()
{
    if (!is_admin()) {
        return;
    }

    $screen = get_current_screen();
    $action = filter_input(INPUT_GET, 'action');
    $action = strtolower($action);

    if (empty($screen) || $screen->base !== 'post' || $screen->post_type !== 'wprss_feed' || $action !== 'edit') {
        return;
    }

    global $wp_admin_bar;
    // Remove the old 'View Source' menu item
    $wp_admin_bar->remove_node('view');

    // Prepare the view items link and text
    $view_items_link = apply_filters(
        'wprss_view_feed_items_row_action_link',
        admin_url('edit.php?post_type=wprss_feed_item&wprss_feed=' . get_the_ID()),
        get_the_ID()
    );
    $view_items_text = apply_filters('wprss_view_feed_items_row_action_text', __('View Items', 'wprss'));

    // Prepare the link target
    $link_target = 'wprss-view-items-' . get_the_ID();

    // Add the new menu item
    $wp_admin_bar->add_node([
        'href' => $view_items_link,
        'id' => 'view',
        'title' => $view_items_text,
        'meta' => [
            'target' => $link_target,
        ],
    ]);
}

if (is_admin()) {
    /**
     * Alters the main query in the WordPress admin, when the wprss_feed GET parameter is set.
     * The queried items are then filtered down to the items imported by the feed source with
     * the ID given in the wprss_feed GET parameter.
     *
     * @since 4.2
     */
    add_filter('pre_get_posts', function ($query) {
        // Get the ID from the GET param
        $id = filter_input(INPUT_GET, 'wprss_feed', FILTER_VALIDATE_INT);

        // Make sure we are in the admin area, filtering the main query, and the GET param is present
        if (!is_admin() || !$query->is_main_query() || empty($id)) {
            return $query;
        }

        // Bail if the ID does not correspond to a WPRA feed source
        $feed = get_post($id);
        if ($feed instanceof WP_Post && $feed->post_type !== 'wprss_feed') {
            return $query;
        }

        // Get the existing meta query
        $mq = $query->get('meta_query');
        // Add a meta query if one is not yet set
        if (!is_array($mq)) {
            // initialize it
            $mq = ['relation' => 'AND'];
        }
        // Add the custom meta query
        $mq[] = apply_filters(
            'wprss_view_feed_items_meta_query',
            [
                'key' => 'wprss_feed_id',
                'value' => $id,
                'compare' => '=',
            ],
            $id
        );
        // Set the new meta query
        $query->set('meta_query', $mq);

        // Return the query
        return $query;
    });
}


add_action('admin_footer', function () {
    $screen = get_current_screen();
    if ($screen->id !== 'edit-wprss_feed') {
        return;
    }

    $footer = apply_filters('wprss_admin_footer_links', [
        'news_aggregator' => [
            'url' => 'https://www.wprssaggregator.com/news-aggregators',
            'icon' => WpraAdminFooter::newsIcon(),
            'heading' => __('News Aggregator', 'wprss'),
            'text' => __('All the features you need, directly on your site.', 'wprss'),
        ],
        'content_hub' => [
            'url' => 'https://www.wprssaggregator.com/content-hub',
            'icon' => WpraAdminFooter::graphIcon(),
            'heading' => __('Content Hub', 'wprss'),
            'text' => __('Build a comprehensive content hub with WordPress.', 'wprss'),
        ],
        'upgrade' => [
            'url' => 'https://www.wprssaggregator.com/upgrade',
            'icon' => WpraAdminFooter::starIcon(),
            'heading' => __('Upgrade', 'wprss'),
            'text' => __('Get started today.', 'wprss'),
        ],
    ]);

    echo WpraAdminFooter::footer($footer);
});

class WpraAdminFooter
{
    public static function footer(array $boxes)
    {
        ob_start();
        ?>
        <div class="wpra-footer">
            <div class="wpra-footer-grid">
                <?php foreach ($boxes as $box) : ?>
                    <?= WpraAdminFooter::box($box['url'], $box['icon'], $box['heading'], $box['text']) ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function box($url, $icon, $heading, $text)
    {
        ob_start();
        ?>
        <a href="<?= esc_attr($url) ?>" target="_blank" class="wpra-footer-link">
            <button class="wpra-footer-box">
                <div class="wpra-footer-col wpra-footer-box-icon">
                    <?= $icon ?>
                </div>
                <div class="wpra-footer-col wpra-footer-box-text">
                    <div><?= $heading ?></div>
                    <div><?= $text ?></div>
                </div>
                <div class="wpra-footer-col wpra-footer-box-external">
                    <?= WpraAdminFooter::externalIcon() ?>
                </div>
            </button>
        </a>
        <?php
        return ob_get_clean();
    }

    public static function externalIcon()
    {
        ob_start();
        ?>
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
            <line x1="1.56239" y1="4.0052" x2="1.56239" y2="13.0169" stroke="#333333" stroke-width="2.0026" stroke-linecap="round"/>
            <line x1="1.56239" y1="13.0182" x2="10.5598" y2="13.0182" stroke="#333333" stroke-width="2.0026" stroke-linecap="round"/>
            <line x1="10.5605" y1="9.01302" x2="10.5605" y2="13.0182" stroke="#333333" stroke-width="2.0026" stroke-linecap="round"/>
            <line x1="1.56239" y1="4.00651" x2="5.5598" y2="4.00651" stroke="#333333" stroke-width="2.0026" stroke-linecap="round"/>
            <rect width="5.81246" height="3.48747" rx="1.0013" transform="matrix(0.706648 -0.707565 0.706648 0.707565 5.2757 6.83203)" fill="#333333"/>
            <path d="M13.0924 0.988909C13.3565 0.996455 13.5692 1.20829 13.5782 1.47267L13.7696 7.11634C13.785 7.57064 13.2368 7.80902 12.9158 7.48759L7.1195 1.68376C6.79942 1.36327 7.03508 0.815821 7.48756 0.82875L13.0924 0.988909Z" fill="#333333"/>
            </svg>
        <?php
        return ob_get_clean();
    }

    public static function newsIcon()
    {
        ob_start();
        ?>
        <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M2.5 3.75V22.5C2.5 24.5562 4.19379 26.25 6.25 26.25H23.75C25.8062 26.25 27.5 24.5562 27.5 22.5V8.75H25V22.5C25 23.2063 24.4563 23.75 23.75 23.75C23.0437 23.75 22.5 23.2063 22.5 22.5V3.75H2.5ZM5 6.25H20V22.5C20 22.9854 20.3487 23.3225 20.52 23.75H6.25C5.54371 23.75 5 23.2063 5 22.5V6.25ZM7.5 8.75V12.5H17.5V8.75H7.5ZM7.5 15V17.5H17.5V15H7.5ZM7.5 20V22.5H17.5V20H7.5Z" fill="#333333"/>
        </svg>
        <?php
        return ob_get_clean();
    }

    public static function graphIcon()
    {
        ob_start();
        ?>
        <svg width="23" height="24" viewBox="0 0 23 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M17.8111 17L12.8111 12V8.025C14.2611 7.5 15.3111 6.125 15.3111 4.5C15.3111 2.425 13.6361 0.75 11.5611 0.75C9.4861 0.75 7.8111 2.425 7.8111 4.5C7.8111 6.125 8.8611 7.5 10.3111 8.025V12L5.3111 17H0.311096V23.25H6.5611V19.4375L11.5611 14.1875L16.5611 19.4375V23.25H22.8111V17H17.8111Z" fill="#333333"/>
        </svg>
        <?php
        return ob_get_clean();
    }

    public static function starIcon()
    {
        ob_start();
        ?>
        <svg width="31" height="30" viewBox="0 0 31 30" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="1.19362" y="1.07143" width="28.7349" height="27.8571" rx="3.21429" fill="white" stroke="#FF792B" stroke-width="2.14286"/>
            <path d="M15.5611 6L17.6408 12.2188H24.371L18.9262 16.0623L21.0059 22.2812L15.5611 18.4377L10.1162 22.2812L12.196 16.0623L6.75113 12.2188H13.4813L15.5611 6Z" fill="#FF792B"/>
        </svg>
        <?php
        return ob_get_clean();
    }
}
