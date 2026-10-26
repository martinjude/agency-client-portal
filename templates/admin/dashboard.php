<?php
/**
 * Template Dashboard Admin
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$total_clients = intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}agency_clients WHERE status = 'active'"));

// Pagination
$per_page = 12;
$current_page = isset($_GET['client_page']) ? max(1, intval($_GET['client_page'])) : 1;
$offset = ($current_page - 1) * $per_page;
$total_pages = ceil($total_clients / $per_page);

// Tous les clients actifs avec pagination
$all_clients = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}agency_clients WHERE status = 'active' ORDER BY created_at DESC LIMIT %d OFFSET %d",
    $per_page,
    $offset
));

// Pre-charger le nombre de projets par client
$client_ids = wp_list_pluck($all_clients, 'id');
$project_counts = array();
if (!empty($client_ids)) {
    $ids_placeholder = implode(',', array_map('intval', $client_ids));
    $counts = $wpdb->get_results(
        "SELECT client_id, COUNT(*) as cnt FROM {$wpdb->prefix}agency_projects WHERE client_id IN ($ids_placeholder) AND status = 'active' GROUP BY client_id"
    );
    foreach ($counts as $row) {
        $project_counts[$row->client_id] = intval($row->cnt);
    }
}
?>

<div class="wrap">
    <div class="agency-admin-wrap">
        <div class="agency-admin-header">
            <h1 class="agency-admin-title"><?php esc_html_e('Agency Portal', 'agency-client-portal'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=agency-new-client')); ?>" class="agency-btn agency-btn-sm agency-btn-primary">
                <?php esc_html_e('+ New Client', 'agency-client-portal'); ?>
            </a>
        </div>

        <!-- Grille Clients -->
        <div class="agency-section">
            <h2><?php esc_html_e('Clients', 'agency-client-portal'); ?></h2>

            <?php if (empty($all_clients)): ?>
                <p><?php esc_html_e('No clients yet.', 'agency-client-portal'); ?></p>
            <?php else: ?>
                <div class="agency-clients-grid">
                    <?php foreach ($all_clients as $client): ?>
                        <?php
                        $edit_url = home_url('/agency/edit-client/' . intval($client->id) . '/');
                        $nb_projects = isset($project_counts[$client->id]) ? $project_counts[$client->id] : 0;
                        $initials = mb_strtoupper(mb_substr(trim($client->name), 0, 2));
                        $has_token = !empty($client->token_hash) && !empty($client->token_active);

                        $logo_url = !empty($client->logo_url) ? $client->logo_url : '';
                        ?>
                        <div class="agency-client-card">
                            <div class="agency-client-card-header">
                                <div class="agency-client-avatar" <?php if ($logo_url): ?>style="background-image:url('<?php echo esc_url($logo_url); ?>'); background-size:cover; background-position:center;"<?php endif; ?>>
                                    <?php if (!$logo_url): ?><?php echo esc_html($initials); ?><?php endif; ?>
                                </div>
                                <div class="agency-client-info">
                                    <div class="agency-client-name"><?php echo esc_html($client->name); ?></div>
                                    <div class="agency-client-stats">
                                        <?php echo esc_html(sprintf(
                                            /* translators: %d: number of projects */
                                            _n('%d project', '%d projects', $nb_projects, 'agency-client-portal'),
                                            $nb_projects
                                        )); ?>
                                        <?php if ($has_token): ?>
                                            <span class="agency-token-badge active" title="<?php esc_attr_e('Direct access active', 'agency-client-portal'); ?>">&#x1f511;</span>
                                        <?php else: ?>
                                            <span class="agency-token-badge inactive" title="<?php esc_attr_e('No access link', 'agency-client-portal'); ?>">&#x1f512;</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="agency-client-card-actions">
                                <a href="<?php echo esc_url($edit_url); ?>" class="agency-btn agency-btn-sm agency-btn-secondary">
                                    <?php esc_html_e('Edit', 'agency-client-portal'); ?>
                                </a>
                                <a href="<?php echo esc_url(home_url('/client/' . $client->slug)); ?>" class="agency-btn agency-btn-sm agency-btn-secondary">
                                    <?php esc_html_e('Projects', 'agency-client-portal'); ?>
                                </a>
                                <?php if ($has_token): ?>
                                    <button type="button" class="agency-btn agency-btn-sm agency-btn-primary agency-copy-access-url"
                                            data-client-id="<?php echo esc_attr($client->id); ?>">
                                        <?php esc_html_e('Private link', 'agency-client-portal'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="agency-pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i === $current_page): ?>
                            <span class="agency-page-current"><?php echo esc_html($i); ?></span>
                        <?php else: ?>
                            <a href="<?php echo esc_url(add_query_arg('client_page', $i)); ?>" class="agency-page-link"><?php echo esc_html($i); ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php ob_start(); ?>
(function() {
    document.querySelectorAll('.agency-copy-access-url').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var clientId = this.getAttribute('data-client-id');
            var button = this;
            var originalText = button.textContent;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success && response.data.access_url) {
                        var temp = document.createElement('input');
                        temp.value = response.data.access_url;
                        document.body.appendChild(temp);
                        temp.select();
                        document.execCommand('copy');
                        document.body.removeChild(temp);
                        button.textContent = '\u2713 Copie !';
                        setTimeout(function() {
                            button.textContent = originalText;
                        }, 2000);
                    }
                }
            };
            xhr.send('action=agency_get_access_url&client_id=' + clientId + '&nonce=' + (typeof agencyAdmin !== 'undefined' ? agencyAdmin.nonce : ''));
        });
    });
})();
<?php wp_print_inline_script_tag(ob_get_clean()); ?>
