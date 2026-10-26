<?php
/**
 * Panneau « Commentaires » des pages de bloc (planches 11–17).
 *
 * Attendu en scope : $block, $is_manager, $cm_comments (liste plate).
 * Optionnel : $cm_asset_id (commentaires d'un asset, sinon commentaires du bloc).
 *
 * Les identifiants historiques (#new-comment-form, .new-asset-comment-form,
 * .delete-comment…) sont conservés : les scripts des templates s'y accrochent.
 *
 * @package Agency
 */
if (!defined('ABSPATH')) { exit; }

$cm_asset_id = isset($cm_asset_id) ? (int) $cm_asset_id : 0;
$cm_total    = count($cm_comments);
$cm_threads  = Agency_Comment::nest($cm_comments);

/**
 * Affiche un commentaire (et ses réponses pour une racine).
 */
$cm_render = function ($comment, $is_reply = false) use (&$cm_render, $block, $is_manager, $cm_asset_id) {
    $name     = isset($comment->user->display_name) ? $comment->user->display_name : __('Client', 'agency-client-portal');
    $is_staff = !empty($comment->user_id) && user_can((int) $comment->user_id, 'manage_agency_projects');
    ?>
    <article class="agency-cm__item comment-item<?php echo $is_reply ? ' agency-cm__item--reply' : ''; ?>" data-comment-id="<?php echo esc_attr($comment->id); ?>">
        <span class="agency-avatar agency-avatar--28<?php echo $is_staff ? ' agency-avatar--staff' : ''; ?>"><?php echo esc_html(Agency_Template::initials($name)); ?></span>
        <div class="agency-cm__body">
            <div class="agency-cm__meta">
                <span class="agency-cm__author comment-author"><?php echo esc_html($name); ?></span>
                <span class="agency-cm__time comment-date"><?php echo esc_html(Agency_Template::time_ago($comment->created_at)); ?></span>
                <?php if ($is_manager): ?>
                <div class="agency-dropdown agency-cm__menu">
                    <button type="button" class="agency-btn-icon agency-btn-icon--sm" data-agency-dropdown aria-haspopup="true" aria-expanded="false" aria-label="<?php esc_attr_e('More actions', 'agency-client-portal'); ?>">
                        <?php Agency_Icons::render_ui('more', 16); ?>
                    </button>
                    <div class="agency-menu" role="menu">
                        <button type="button" class="agency-menu__item agency-menu__item--danger delete-comment" data-comment-id="<?php echo esc_attr($comment->id); ?>">
                            <?php Agency_Icons::render('trash', 16); ?> <?php esc_html_e('Delete', 'agency-client-portal'); ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="agency-cm__text comment-content"><?php echo wp_kses_post($comment->content); ?></div>
            <?php if (!$is_reply): ?>
            <div class="agency-cm__actions">
                <button type="button" class="agency-cm__reply-btn" data-cm-reply>
                    <?php Agency_Icons::render_ui('reply', 14); ?> <?php esc_html_e('Reply', 'agency-client-portal'); ?>
                </button>
            </div>
            <?php if (!empty($comment->replies)): ?>
            <div class="agency-cm__replies">
                <?php foreach ($comment->replies as $reply) { $cm_render($reply, true); } ?>
            </div>
            <?php endif; ?>
            <form class="agency-cm__reply-form" hidden
                  data-block-id="<?php echo esc_attr($block->id); ?>"
                  data-asset-id="<?php echo esc_attr($cm_asset_id); ?>"
                  data-parent-id="<?php echo esc_attr($comment->id); ?>">
                <textarea rows="2" required placeholder="<?php esc_attr_e('Your reply...', 'agency-client-portal'); ?>"></textarea>
                <div class="agency-cm__reply-actions">
                    <button type="button" class="agency-btn agency-btn-sm" data-cm-reply-cancel><?php esc_html_e('Cancel', 'agency-client-portal'); ?></button>
                    <button type="submit" class="agency-btn agency-btn-sm agency-btn-primary"><?php esc_html_e('Send', 'agency-client-portal'); ?></button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </article>
    <?php
};
?>
<section class="agency-cm block-comments<?php echo $cm_asset_id ? ' agency-cm--asset' : ''; ?>">
    <header class="agency-cm__head">
        <h3 class="agency-cm__title"><?php esc_html_e('Comments', 'agency-client-portal'); ?> <span class="agency-count"><?php echo intval($cm_total); ?></span></h3>
    </header>

    <div class="agency-cm__list <?php echo $cm_asset_id ? 'asset-comments-list' : 'comments-list'; ?>"<?php echo $cm_asset_id ? ' data-asset-id="' . esc_attr($cm_asset_id) . '"' : ' id="comments-list"'; ?>>
        <?php if (empty($cm_threads)): ?>
            <div class="agency-cm__empty no-comments">
                <?php Agency_Icons::render('comment', 20); ?>
                <span><?php esc_html_e('No comments yet.', 'agency-client-portal'); ?></span>
            </div>
        <?php else: ?>
            <?php foreach ($cm_threads as $thread) { $cm_render($thread); } ?>
        <?php endif; ?>
    </div>

    <?php if ($cm_asset_id): ?>
    <div class="agency-cm__composer asset-comment-form">
        <form class="new-asset-comment-form" data-asset-id="<?php echo esc_attr($cm_asset_id); ?>">
            <textarea class="asset-comment-input" rows="3" placeholder="<?php esc_attr_e('Add a comment…', 'agency-client-portal'); ?>" required></textarea>
            <div class="agency-cm__composer-foot">
                <button type="submit" class="agency-btn agency-btn-sm agency-btn-primary"><?php esc_html_e('Send', 'agency-client-portal'); ?></button>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="agency-cm__composer comment-form">
        <form id="new-comment-form">
            <textarea name="comment_content" id="comment-content" rows="3" placeholder="<?php esc_attr_e('Add a comment…', 'agency-client-portal'); ?>" required></textarea>
            <div class="agency-cm__composer-foot">
                <button type="submit" class="agency-btn agency-btn-sm agency-btn-primary"><?php esc_html_e('Send', 'agency-client-portal'); ?></button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</section>
