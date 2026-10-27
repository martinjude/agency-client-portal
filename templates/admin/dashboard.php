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
$agency_total_clients = intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}agency_clients WHERE status = 'active'"));

// Pagination
$agency_per_page = 12;
$agency_current_page = isset($_GET['client_page']) ? max(1, intval($_GET['client_page'])) : 1;
$agency_offset = ($agency_current_page - 1) * $agency_per_page;
$agency_total_pages = ceil($agency_total_clients / $agency_per_page);

// Tous les clients actifs avec pagination
$agency_all_clients = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}agency_clients WHERE status = 'active' ORDER BY created_at DESC LIMIT %d OFFSET %d",
    $agency_per_page,
    $agency_offset
));

// Pre-charger le nombre de projets par client
$agency_client_ids = wp_list_pluck($agency_all_clients, 'id');
$agency_project_counts = array();
if (!empty($agency_client_ids)) {
    $agency_ids_placeholder = implode(',', array_map('intval', $agency_client_ids));
    $agency_counts = $wpdb->get_results(
        "SELECT client_id, COUNT(*) as cnt FROM {$wpdb->prefix}agency_projects WHERE client_id IN ($agency_ids_placeholder) AND status = 'active' GROUP BY client_id"
    );
    foreach ($agency_counts as $agency_row) {
        $agency_project_counts[$agency_row->client_id] = intval($agency_row->cnt);
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

            <?php if (empty($agency_all_clients)): ?>
                <p><?php esc_html_e('No clients yet.', 'agency-client-portal'); ?></p>
            <?php else: ?>
                <div class="agency-clients-grid">
                    <?php foreach ($agency_all_clients as $agency_client): ?>
                        <?php
                        $agency_edit_url = home_url('/agency/edit-client/' . intval($agency_client->id) . '/');
                        $agency_nb_projects = isset($agency_project_counts[$agency_client->id]) ? $agency_project_counts[$agency_client->id] : 0;
                        $agency_initials = mb_strtoupper(mb_substr(trim($agency_client->name), 0, 2));
                        $agency_has_token = !empty($agency_client->token_hash) && !empty($agency_client->token_active);

                        $agency_logo_url = !empty($agency_client->logo_url) ? $agency_client->logo_url : '';
                        ?>
                        <div class="agency-client-card">
                            <div class="agency-client-card-header">
                                <div class="agency-client-avatar" <?php if ($agency_logo_url): ?>style="background-image:url('<?php echo esc_url($agency_logo_url); ?>'); background-size:cover; background-position:center;"<?php endif; ?>>
                                    <?php if (!$agency_logo_url): ?><?php echo esc_html($agency_initials); ?><?php endif; ?>
                                </div>
                                <div class="agency-client-info">
                                    <div class="agency-client-name"><?php echo esc_html($agency_client->name); ?></div>
                                    <div class="agency-client-stats">
                                        <?php echo esc_html(sprintf(
                                            /* translators: %d: number of projects */
                                            _n('%d project', '%d projects', $agency_nb_projects, 'agency-client-portal'),
                                            $agency_nb_projects
                                        )); ?>
                                        <?php if ($agency_has_token): ?>
                                            <span class="agency-token-badge active" title="<?php esc_attr_e('Direct access active', 'agency-client-portal'); ?>">&#x1f511;</span>
                                        <?php else: ?>
                                            <span class="agency-token-badge inactive" title="<?php esc_attr_e('No access link', 'agency-client-portal'); ?>">&#x1f512;</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="agency-client-card-actions">
                                <a href="<?php echo esc_url($agency_edit_url); ?>" class="agency-btn agency-btn-sm agency-btn-secondary">
                                    <?php esc_html_e('Edit', 'agency-client-portal'); ?>
                                </a>
                                <a href="<?php echo esc_url(home_url('/client/' . $agency_client->slug)); ?>" class="agency-btn agency-btn-sm agency-btn-secondary">
                                    <?php esc_html_e('Projects', 'agency-client-portal'); ?>
                                </a>
                                <?php if ($agency_has_token): ?>
                                    <button type="button" class="agency-btn agency-btn-sm agency-btn-primary agency-copy-access-url"
                                            data-client-id="<?php echo esc_attr($agency_client->id); ?>">
                                        <?php esc_html_e('Private link', 'agency-client-portal'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($agency_total_pages > 1): ?>
                <div class="agency-pagination">
                    <?php for ($agency_i = 1; $agency_i <= $agency_total_pages; $agency_i++): ?>
                        <?php if ($agency_i === $agency_current_page): ?>
                            <span class="agency-page-current"><?php echo esc_html($agency_i); ?></span>
                        <?php else: ?>
                            <a href="<?php echo esc_url(add_query_arg('client_page', $agency_i)); ?>" class="agency-page-link"><?php echo esc_html($agency_i); ?></a>
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
