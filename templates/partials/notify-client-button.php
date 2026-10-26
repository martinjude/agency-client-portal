<?php
/**
 * Partial : bouton « Notifier le client » pour l'aire .agency-actions des blocs.
 *
 * Attendu en scope : $block, $client, $project (contexte manager uniquement).
 * Inclus dans chaque template block-view-*.php afin de centraliser le markup.
 *
 * @package Agency
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($block) || empty($client) || empty($project)) {
    return;
}

?>
    <button type="button"
            class="agency-btn agency-btn-outline notify-client-btn"
            data-block-id="<?php echo esc_attr($block->id); ?>"
            data-client-id="<?php echo esc_attr($client->id); ?>"
            data-client-slug="<?php echo esc_attr($client->slug); ?>"
            data-project-id="<?php echo esc_attr($project->id); ?>"
            data-project-slug="<?php echo esc_attr($project->slug); ?>"
            data-project-email="<?php echo esc_attr($project->email ?? ''); ?>"
            data-project-name="<?php echo esc_attr($project->name); ?>">
        <?php Agency_Icons::render('mail', 16); ?> <?php esc_html_e('Notify client', 'agency-client-portal'); ?>
    </button>

<?php
// Popin de notification — rendue une seule fois par page de bloc (un bloc par page).
// position:fixed => l'emplacement DOM dans .agency-actions n'a pas d'incidence visuelle.
if (empty($GLOBALS['agency_notify_modal_rendered'])) :
    $GLOBALS['agency_notify_modal_rendered'] = true;
    ?>
    <div class="agency-modal" id="notify-client-modal">
        <div class="agency-modal-content">
            <div class="agency-modal-header">
                <h3 class="agency-modal-title"><?php esc_html_e('Notify client', 'agency-client-portal'); ?></h3>
                <button class="agency-modal-close" type="button">&times;</button>
            </div>
            <div class="agency-modal-body">
                <input type="hidden" id="notify-client-block-id" value="<?php echo esc_attr($block->id); ?>">
                <div class="agency-form-group">
                    <label class="agency-form-label" for="notify-client-email"><?php esc_html_e('Client email', 'agency-client-portal'); ?></label>
                    <input type="email" id="notify-client-email" class="agency-form-input"
                           value="<?php echo esc_attr($project->email ?? ''); ?>"
                           placeholder="<?php esc_attr_e('client@example.com', 'agency-client-portal'); ?>" required>
                </div>
                <div class="agency-form-group">
                    <label class="agency-form-label" for="notify-client-name"><?php esc_html_e('Name', 'agency-client-portal'); ?></label>
                    <input type="text" id="notify-client-name" class="agency-form-input"
                           placeholder="<?php esc_attr_e('Recipient name', 'agency-client-portal'); ?>">
                </div>
                <div class="agency-form-group">
                    <label class="agency-form-label" for="notify-client-subject"><?php esc_html_e('Subject', 'agency-client-portal'); ?></label>
                    <input type="text" id="notify-client-subject" class="agency-form-input"
                           placeholder="<?php esc_attr_e('Email subject', 'agency-client-portal'); ?>">
                </div>
                <div class="agency-form-group">
                    <label class="agency-form-label" for="notify-client-message"><?php esc_html_e('Message', 'agency-client-portal'); ?></label>
                    <textarea id="notify-client-message" class="agency-form-input" rows="5"
                              placeholder="<?php esc_attr_e('Your message…', 'agency-client-portal'); ?>"></textarea>
                </div>
                <p class="agency-form-hint"><?php esc_html_e('A secure link to this block will be added automatically.', 'agency-client-portal'); ?></p>
                <div class="notify-client-feedback" style="display:none;"></div>
            </div>
            <div class="agency-modal-footer">
                <button class="agency-btn agency-btn-secondary modal-cancel" type="button"><?php esc_html_e('Cancel', 'agency-client-portal'); ?></button>
                <button class="agency-btn agency-btn-primary" id="notify-client-send" type="button"><?php esc_html_e('Send', 'agency-client-portal'); ?></button>
            </div>
        </div>
    </div>
<?php endif; ?>
