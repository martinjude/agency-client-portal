<?php
/**
 * Champ « couleur d'accent » : pastille + hex + couleurs suggérées.
 *
 * Variables attendues : $agency_accent_field_id (préfixe des ids : -picker, -text),
 * $agency_accent_field_value (hex courant).
 * Le préfixe garde les ids historiques lus par dashboard.js / edit-client.js.
 */
if (!defined('ABSPATH')) { exit; }

$agency_accent_field_value   = sanitize_hex_color($agency_accent_field_value) ?: '#7C83FD';
$agency_accent_field_presets = array('#7C83FD', '#F59E0B', '#10B981', '#EC4899', '#60A5FA', '#F87171');
?>
<div class="agency-accent-field" data-accent-field="<?php echo esc_attr($agency_accent_field_id); ?>">
    <label class="agency-accent-field__swatch" style="background:<?php echo esc_attr($agency_accent_field_value); ?>">
        <input type="color" id="<?php echo esc_attr($agency_accent_field_id); ?>-picker" value="<?php echo esc_attr($agency_accent_field_value); ?>" aria-label="<?php esc_attr_e('Pick a color', 'agency-client-portal'); ?>">
    </label>
    <input type="text" id="<?php echo esc_attr($agency_accent_field_id); ?>-text" class="agency-form-input agency-accent-field__hex" value="<?php echo esc_attr(strtoupper($agency_accent_field_value)); ?>" maxlength="7" spellcheck="false">
    <div class="agency-accent-field__presets" role="group" aria-label="<?php esc_attr_e('Suggested colors', 'agency-client-portal'); ?>">
        <?php foreach ($agency_accent_field_presets as $agency_preset): ?>
            <button type="button" class="agency-accent-field__preset<?php echo strtolower($agency_preset) === strtolower($agency_accent_field_value) ? ' is-on' : ''; ?>" data-color="<?php echo esc_attr($agency_preset); ?>" style="background:<?php echo esc_attr($agency_preset); ?>" aria-label="<?php echo esc_attr($agency_preset); ?>"></button>
        <?php endforeach; ?>
    </div>
</div>
