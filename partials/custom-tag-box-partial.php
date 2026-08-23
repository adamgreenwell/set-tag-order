<?php
/**
 * Partial template for displaying the custom tag box.
 *
 * Expects $post, $post_tags, and $tag_order to be available in the including
 * scope. See settagord_render_custom_tag_box().
 *
 * @package Set_Tag_Order
 */

// Ensure this file is loaded within WordPress.
if (!defined('ABSPATH')) {
	exit;
}

wp_nonce_field('settagord_meta_box', 'settagord_meta_box_nonce');
?>
<div class="tagsdiv settagord-tagsdiv" id="custom-tags">
    <div class="jaxtag">
        <label class="screen-reader-text" for="new-tag-input">
            <?php esc_html_e('Add a tag', 'set-tag-order'); ?>
        </label>
        <input type="text"
               id="new-tag-input"
               class="newtag form-input-tip"
               size="16"
               autocomplete="off"
               value="" />
        <input type="button"
               class="button tagadd"
               value="<?php esc_attr_e('Add', 'set-tag-order'); ?>" />
    </div>

    <div class="tagchecklist">
        <ul id="sortable-tags" class="tag-list">
            <?php foreach ($post_tags as $tag): ?>
                <li data-tag-id="<?php echo esc_attr($tag->term_id); ?>"
                    data-tag-name="<?php echo esc_attr($tag->name); ?>">
                    <span class="settagord-tag-name"><?php echo esc_html($tag->name); ?></span>
                    <span class="settagord-tag-actions">
                        <button type="button" class="settagord-move" data-direction="up">
                            <span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
                            <span class="screen-reader-text">
                                <?php
                                    /* translators: %s: tag name */
                                    printf(esc_html__('Move %s up', 'set-tag-order'), esc_html($tag->name));
                                ?>
                            </span>
                        </button>
                        <button type="button" class="settagord-move" data-direction="down">
                            <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                            <span class="screen-reader-text">
                                <?php
                                    /* translators: %s: tag name */
                                    printf(esc_html__('Move %s down', 'set-tag-order'), esc_html($tag->name));
                                ?>
                            </span>
                        </button>
                        <button type="button"
                                class="ntdelbutton"
                                data-tag-id="<?php echo esc_attr($tag->term_id); ?>">
                            <span class="remove-tag-icon" aria-hidden="true"></span>
                            <span class="screen-reader-text">
                                <?php
                                    /* translators: %s: tag name */
                                    printf(esc_html__('Remove %s', 'set-tag-order'), esc_html($tag->name));
                                ?>
                            </span>
                        </button>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <input type="hidden"
           name="settagord"
           id="tag-order-input"
           value="<?php echo esc_attr($tag_order); ?>" />
    <input type="hidden"
           name="post_tags"
           id="post-tags-input"
           value="<?php echo esc_attr(implode(',', wp_list_pluck($post_tags, 'term_id'))); ?>" />

    <div class="ajaxtag hide-if-no-js">
        <p class="settagord-help">
            <?php esc_html_e('Drag tags to reorder, or use the arrow buttons. Start typing above to search existing tags, or add new ones.', 'set-tag-order'); ?>
        </p>
        <button type="button" class="button-link settagord-sort-alpha">
            <?php esc_html_e('Sort A–Z', 'set-tag-order'); ?>
        </button>
    </div>
</div>
