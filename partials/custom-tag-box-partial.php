<?php
/**
 * Partial template for displaying the custom tag box.
 *
 * @package Set_Tag_Order
 */

// Ensure this file is loaded within WordPress.
if (!defined('ABSPATH')) {
	exit;
}

$post_tags = get_the_tags($post->ID) ?: [];
$tag_order = get_post_meta($post->ID, '_settagord', true) ?: '';

// Expects $post, $all_tags, $post_tags, $tag_order, $ordered_ids to be available in the scope.
wp_nonce_field('settagord_meta_box', 'settagord_meta_box_nonce');
?>
<div class="tagsdiv" id="custom-tags">
    <div class="jaxtag">
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
                    <?php echo esc_html($tag->name); ?>
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
        <p><?php esc_html_e('Drag tags to reorder. Start typing above to search existing tags, or add new ones.', 'set-tag-order'); ?></p>
    </div>
</div>
