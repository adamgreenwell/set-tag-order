/**
 * Set Tag Order Admin JavaScript
 *
 * @package Set_Tag_Order
 */

jQuery(document).ready(function($) {
    var tagInput = $('#new-tag-input');
    var sortableList = $('#sortable-tags');
    var tagOrderInput = $('#tag-order-input');
    var postTagsInput = $('#post-tags-input');
    var allTags = settagordAdminData.allTags || []; // Renamed prefix

    // 1. Initialize Sortable
    if (sortableList.length > 0) {
        sortableList.sortable({
            update: function(event, ui) {
                updateTagOrder();
            }
        });
    } else {
        console.error('Set Tag Order Admin JS: Sortable list #sortable-tags not found!');
    }

    // 2. Initialize Tag Autocomplete/Input Logic
    // Use a library like Select2 or jQuery UI Autocomplete if available
    // For simplicity, this uses a basic datalist approach if no advanced library is enqueued.
    // If you have Select2 or similar enqueued, initialize it here.

    // Simple Autocomplete (using datalist for basic suggestion)
    var dataListId = 'existing-tags-list';
    if ($('#' + dataListId).length === 0) {
        $('<datalist />').attr('id', dataListId).appendTo('body');
    }
    var dataList = $('#' + dataListId);
    dataList.empty(); // Clear previous options
    allTags.forEach(function(tag) {
        $('<option />').val(tag.text).appendTo(dataList);
    });
    tagInput.attr('list', dataListId);


    // 3. Add Tag Button Click
    $('.tagadd').on('click', function() {
        var tagName = tagInput.val().trim();
        if (!tagName) return;

        // Check if tag already added to the post
        var alreadyAdded = false;
        sortableList.find('li').each(function() {
            if ($(this).data('tag-name').toLowerCase() === tagName.toLowerCase()) {
                alreadyAdded = true;
                return false; // break loop
            }
        });

        if (alreadyAdded) {
            tagInput.val(''); // Clear input
            // Maybe provide user feedback (e.g., shake the existing tag)
            return;
        }

        // Find existing tag from allTags list
        var existingTag = allTags.find(function(tag) {
            return tag.text.toLowerCase() === tagName.toLowerCase();
        });

	        if (existingTag) {
	            addTagToList(existingTag.id, existingTag.text);
	            updateTagOrder(); // Update hidden inputs
	            tagInput.val(''); // Clear input
	        } else {
	            $.ajax({
	                url: settagordAdminData.ajaxurl,
	                type: 'POST',
	                data: {
	                    action: 'settagord_add_tag',
	                    tag_name: tagName,
                    _wpnonce: settagordAdminData.addTagNonce
                },
	                success: function(response) {
	                    if (response.success) {
	                        allTags.push({id: response.data.term_id, text: response.data.name});
	                        $('<option />').val(response.data.name).appendTo(dataList);
	                        addTagToList(response.data.term_id, response.data.name);
	                        updateTagOrder();
	                        tagInput.val('');
	                    } else {
	                        alert('Error adding tag: ' + (response.data || 'Unknown error'));
	                    }
	                },
	                error: function() {
	                    alert('AJAX error trying to add tag.');
	                }
	            });
	        }
	    });

    // 4. Remove Tag Button Click (using event delegation)
    if (sortableList.length > 0) {
        sortableList.on('click', '.ntdelbutton', function() {
            $(this).closest('li').remove();
            updateTagOrder();
        });
    } else {
        console.warn('Set Tag Order Admin JS: Could not attach remove handler, sortable list not found.');
    }

    // 5. Function to add a tag item to the sortable list
    function addTagToList(tagId, tagName, isNew) {
        // Prevent adding duplicates visually
        var alreadyExists = false;
        sortableList.find('li').each(function() {
            if ($(this).data('tag-id') === tagId && tagId !== null) {
                alreadyExists = true;
                return false;
            } // Check by name only if it's a new tag (no ID yet)
            else if (isNew && $(this).data('tag-name').toLowerCase() === tagName.toLowerCase()) {
                alreadyExists = true;
                return false;
            }
        });

        if (!alreadyExists) {
            var liClass = isNew ? 'new-tag-item' : ''; // Optional class for styling new tags
            var tagIdAttr = tagId ? ' data-tag-id="' + tagId + '"' : ''; // Only add data-tag-id if it exists

            var listItem = '<li' + tagIdAttr + ' data-tag-name="' + escapeHtml(tagName) + '" class="' + liClass + '">' +
                           escapeHtml(tagName) +
                           ' <button type="button" class="ntdelbutton" data-tag-id="' + (tagId || 'temp-' + Date.now()) + '">' + // Use temp ID if new
                           '  <span class="remove-tag-icon" aria-hidden="true"></span>' +
                           '  <span class="screen-reader-text">Remove ' + escapeHtml(tagName) + '</span>' + // Add screen reader text
                           ' </button>' +
                           '</li>';
            sortableList.append(listItem);
        }
    }

    // 6. Function to update the hidden input fields
    function updateTagOrder() {
	        var orderedIds = [];
	        var currentTagIds = []; // All tags currently in the list
	        sortableList.find('li').each(function() {
	            var tagId = $(this).data('tag-id');
	            var numericTagId = parseInt(tagId, 10);

	            if (!isNaN(numericTagId)) {
	                orderedIds.push(numericTagId);
	                currentTagIds.push(numericTagId);
	            }
	        });
        tagOrderInput.val(orderedIds.join(','));
        postTagsInput.val(currentTagIds.join(',')); // Update this with *all* present tags
    }

    // 7. Helper function to escape HTML (basic version)
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Initial setup: Ensure the sortable list reflects the initial tag order
    // The PHP partial should render the list items correctly based on $post_tags.
    // We just need to ensure the hidden input reflects this initial state if needed.
    // updateTagOrder(); // Call once on load to set initial values
});
