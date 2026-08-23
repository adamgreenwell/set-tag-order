/**
 * Set Tag Order Admin JavaScript
 *
 * Drives the Classic Editor tag box: adding and removing tags, and reordering
 * them by drag, by keyboard, or alphabetically.
 *
 * @package Set_Tag_Order
 */

jQuery(document).ready(function ($) {
    var tagInput = $('#new-tag-input');
    var sortableList = $('#sortable-tags');
    var tagOrderInput = $('#tag-order-input');
    var postTagsInput = $('#post-tags-input');
    var allTags = settagordAdminData.allTags || [];
    var i18n = settagordAdminData.i18n || {};

    /**
     * Announce a change to assistive technology.
     *
     * Reordering by button gives no visible focus change, so without this a
     * screen reader user gets no feedback that anything happened.
     */
    function announce(message) {
        if (window.wp && wp.a11y && wp.a11y.speak) {
            wp.a11y.speak(message, 'polite');
        }
    }

    /**
     * Fill placeholders in a localized string.
     *
     * Supports both "%s" and the numbered "%1$s" form, which translators need
     * whenever a string has more than one placeholder and word order varies
     * between languages.
     */
    function format(template) {
        var values = Array.prototype.slice.call(arguments, 1);
        var index = 0;

        return String(template || '').replace(/%(?:(\d+)\$)?s/g, function (match, position) {
            var value = position ? values[parseInt(position, 10) - 1] : values[index++];
            return typeof value === 'undefined' ? match : String(value);
        });
    }

    function escapeHtml(text) {
        var map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
        return String(text).replace(/[&<>"']/g, function (m) {
            return map[m];
        });
    }

    // --- Sorting -----------------------------------------------------------

    if (sortableList.length > 0) {
        sortableList.sortable({
            update: function () {
                updateTagOrder();
                announce(i18n.orderUpdated);
            }
        });
    } else {
        console.error('Set Tag Order Admin JS: Sortable list #sortable-tags not found!');
    }

    /**
     * Move a tag one position up or down.
     *
     * Drag-and-drop is mouse and touch only, so these buttons are the sole
     * keyboard route to reordering. Focus is deliberately kept on the button
     * after the move so a run of presses works without re-tabbing.
     */
    function moveTag($button, direction) {
        var $item = $button.closest('li');
        var $swapWith = direction === 'up' ? $item.prev('li') : $item.next('li');

        if (!$swapWith.length) {
            announce(direction === 'up' ? i18n.alreadyFirst : i18n.alreadyLast);
            return;
        }

        if (direction === 'up') {
            $item.insertBefore($swapWith);
        } else {
            $item.insertAfter($swapWith);
        }

        updateTagOrder();

        // The element moved in the DOM, so the button lost focus. Put it back
        // on the equivalent button in its new position.
        $item.find('.settagord-move[data-direction="' + direction + '"]').trigger('focus');

        announce(format(
            i18n.movedTo,
            $item.data('tag-name'),
            $item.index() + 1,
            sortableList.children('li').length
        ));
    }

    sortableList.on('click', '.settagord-move', function (event) {
        event.preventDefault();
        moveTag($(this), $(this).data('direction'));
    });

    $('.settagord-sort-alpha').on('click', function (event) {
        event.preventDefault();

        var items = sortableList.children('li').get();

        items.sort(function (a, b) {
            return String($(a).data('tag-name')).localeCompare(String($(b).data('tag-name')), undefined, {sensitivity: 'base'});
        });

        sortableList.append(items);
        updateTagOrder();
        announce(i18n.sortedAlpha);
    });

    // --- Adding tags -------------------------------------------------------

    var dataListId = 'existing-tags-list';
    if ($('#' + dataListId).length === 0) {
        $('<datalist />').attr('id', dataListId).appendTo('body');
    }
    var dataList = $('#' + dataListId);
    dataList.empty();
    allTags.forEach(function (tag) {
        $('<option />').val(tag.text).appendTo(dataList);
    });
    tagInput.attr('list', dataListId);

    function addTag() {
        var tagName = tagInput.val().trim();
        if (!tagName) {
            return;
        }

        var alreadyAdded = false;
        sortableList.find('li').each(function () {
            if (String($(this).data('tag-name')).toLowerCase() === tagName.toLowerCase()) {
                alreadyAdded = true;
                return false;
            }
        });

        if (alreadyAdded) {
            tagInput.val('');
            announce(format(i18n.alreadyAdded, tagName));
            return;
        }

        var existingTag = allTags.find(function (tag) {
            return tag.text.toLowerCase() === tagName.toLowerCase();
        });

        if (existingTag) {
            addTagToList(existingTag.id, existingTag.text);
            updateTagOrder();
            tagInput.val('');
            announce(format(i18n.tagAdded, existingTag.text));
            return;
        }

        $.ajax({
            url: settagordAdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'settagord_add_tag',
                tag_name: tagName,
                _wpnonce: settagordAdminData.addTagNonce
            },
            success: function (response) {
                if (response.success) {
                    allTags.push({id: response.data.term_id, text: response.data.name});
                    $('<option />').val(response.data.name).appendTo(dataList);
                    addTagToList(response.data.term_id, response.data.name);
                    updateTagOrder();
                    tagInput.val('');
                    announce(format(i18n.tagAdded, response.data.name));
                } else {
                    window.alert(format(i18n.addError, response.data || i18n.unknownError));
                }
            },
            error: function () {
                window.alert(i18n.ajaxError);
            }
        });
    }

    $('.tagadd').on('click', addTag);

    // Enter in the tag field should add the tag, not submit the post.
    tagInput.on('keydown', function (event) {
        if (event.key === 'Enter' || event.keyCode === 13) {
            event.preventDefault();
            addTag();
        }
    });

    function addTagToList(tagId, tagName) {
        var exists = false;
        sortableList.find('li').each(function () {
            if ($(this).data('tag-id') === tagId && tagId !== null) {
                exists = true;
                return false;
            }
        });

        if (exists) {
            return;
        }

        var safeName = escapeHtml(tagName);
        var listItem =
            '<li data-tag-id="' + tagId + '" data-tag-name="' + safeName + '">' +
            '<span class="settagord-tag-name">' + safeName + '</span>' +
            '<span class="settagord-tag-actions">' +
            '<button type="button" class="settagord-move" data-direction="up">' +
            '<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>' +
            '<span class="screen-reader-text">' + escapeHtml(format(i18n.moveUp, tagName)) + '</span>' +
            '</button>' +
            '<button type="button" class="settagord-move" data-direction="down">' +
            '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>' +
            '<span class="screen-reader-text">' + escapeHtml(format(i18n.moveDown, tagName)) + '</span>' +
            '</button>' +
            '<button type="button" class="ntdelbutton" data-tag-id="' + tagId + '">' +
            '<span class="remove-tag-icon" aria-hidden="true"></span>' +
            '<span class="screen-reader-text">' + escapeHtml(format(i18n.remove, tagName)) + '</span>' +
            '</button>' +
            '</span>' +
            '</li>';

        sortableList.append(listItem);
    }

    // --- Removing tags -----------------------------------------------------

    sortableList.on('click', '.ntdelbutton', function () {
        var $item = $(this).closest('li');
        var name = $item.data('tag-name');

        $item.remove();
        updateTagOrder();
        announce(format(i18n.tagRemoved, name));
    });

    // --- Persisting --------------------------------------------------------

    function updateTagOrder() {
        var orderedIds = [];

        sortableList.find('li').each(function () {
            var tagId = parseInt($(this).data('tag-id'), 10);

            if (!isNaN(tagId)) {
                orderedIds.push(tagId);
            }
        });

        tagOrderInput.val(orderedIds.join(','));
        postTagsInput.val(orderedIds.join(','));
    }
});
