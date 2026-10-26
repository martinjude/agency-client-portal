<?php
/**
 * Agency common footer
 * Expects $client to be defined in the including scope.
 */
if (!defined('ABSPATH')) { exit; }

// Resolve client from query vars if not provided
if (!isset($client) || !$client) {
    $client_slug = get_query_var('agency_client');
    if ($client_slug) {
        $client = Agency_Client::get_by_slug($client_slug);
    }
}

$recent_projects = array();
if ($client && isset($client->id)) {
    $projects = Agency_Project::get_by_client($client->id);
    // Take the last 3 projects by created_at desc
    if (!empty($projects)) {
        usort($projects, function($a, $b) {
            return strtotime($b->created_at) <=> strtotime($a->created_at);
        });
        $recent_projects = array_slice($projects, 0, 3);
    }
}
?>
<footer class="agency-footer">
    <div class="agency-container">
        <p><?php echo esc_html(isset($client->name) ? $client->name : get_bloginfo('name')); ?></p>
        <?php if (!empty($recent_projects) && isset($client->slug)) : ?>
            <div class="agency-recent-projects" style="margin-top:10px; margin-bottom:10px; font-size:14px;">
                <strong><?php esc_html_e('Latest projects', 'agency-client-portal'); ?>:</strong>
                <?php foreach ($recent_projects as $idx => $p): ?>
                    <?php
                        $pname = isset($p->name) && $p->name !== '' ? $p->name : sprintf(/* translators: %d: project ID */ __('Project #%d', 'agency-client-portal'), intval($p->id));
                        $plink = home_url("/client/{$client->slug}/project/{$p->slug}");
                    ?>
                    <a href="<?php echo esc_url($plink); ?>" style="color: inherit; text-decoration: underline;">
                        <?php echo esc_html($pname); ?>
                    </a><?php if ($idx < count($recent_projects) - 1): ?> · <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <img src="<?php echo esc_url($client->logo_url); ?>" alt="<?php echo esc_attr($client->name); ?>" class="agency-logo">
    </div>
</footer>
