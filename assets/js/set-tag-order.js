/**
 * Set Tag Order - Block Editor Integration
 *
 * Adds a custom panel to the Block Editor sidebar for ordering post tags.
 *
 * @package    SetTagOrder
 * @author     Adam Greenwell
 * @since      1.0.0
 * @file       assets/js/set-tag-order.js
 */

(function (wp) {
    const {registerPlugin} = wp.plugins;

    // PluginDocumentSettingPanel moved from @wordpress/edit-post to
    // @wordpress/editor in WordPress 6.6. Prefer the current location and fall
    // back to the deprecated one, guarding both: @wordpress/edit-post is on a
    // deprecation path and is not guaranteed to be present.
    const PluginDocumentSettingPanel =
        (wp.editor && wp.editor.PluginDocumentSettingPanel) ||
        (wp.editPost && wp.editPost.PluginDocumentSettingPanel);

    if (!PluginDocumentSettingPanel) {
        return;
    }

    const {useSelect, useDispatch, subscribe} = wp.data;
    const {useState, useEffect, createElement: h} = wp.element;
    const {Button} = wp.components;
    const {__, sprintf} = wp.i18n;
    const speak = (wp.a11y && wp.a11y.speak) ? wp.a11y.speak : function () {};

    /**
     * Tag Order Panel Component
     *
     * Renders a sidebar panel in the block editor that displays
     * the post's tags and allows reordering them.
     *
     * @since 1.0.0
     * @return {JSX.Element|null} The rendered component or null if tags aren't loaded
     */
    const TagOrderPanel = () => {
        const [tagOrder, setTagOrder] = useState([]);
        const [initialLoad, setInitialLoad] = useState(true);

        // Get post type
        const postType = useSelect(select =>
            select('core/editor').getCurrentPostType()
        );

        /**
         * Get all available tags from WordPress data store
         */
        const tags = useSelect(select =>
            select('core').getEntityRecords('taxonomy', 'post_tag', {
                per_page: -1
            })
        );

        /**
         * Get tags assigned to the current post
         */
        const postTags = useSelect(select =>
            select('core/editor').getEditedPostAttribute('tags')
        );

        /**
         * Get post meta containing the saved tag order
         */
        const meta = useSelect(select =>
            select('core/editor').getEditedPostAttribute('meta')
        );

        const {editPost} = useDispatch('core/editor');

        /**
         * Update tag order whenever postTags changes
         *
         * This effect handles several cases:
         * 1. When tags are first loaded
         * 2. When tags are added or removed
         * 3. When the post is first loaded with existing tags
         */
        useEffect(() => {
            if (!postTags || !tags) {
                return;
            }

            if (!postTags.length) {
                // Clear the order if there are no tags
                setTagOrder([]);
                editPost({meta: {...meta, _settagord: ''}});
                return;
            }

            // Get the saved order if it exists
            const savedOrder = meta?._settagord ? meta._settagord.split(',').map(Number) : [];

            if (initialLoad && savedOrder.length > 0) {
                // On initial load, respect the saved order from meta
                // This preserves order from Classic Editor
                const validSavedOrder = savedOrder.filter(id => postTags.includes(id));

                // Add any missing tags to the end
                const newOrder = [...validSavedOrder];
                postTags.forEach(tagId => {
                    if (!newOrder.includes(tagId)) {
                        newOrder.push(tagId);
                    }
                });

                setTagOrder(newOrder);
                // We don't need to update meta here since we're using the existing one
                setInitialLoad(false);
            } else {
                // When tags change after initial load
                // First check if we just need to remove tags from order
                if (tagOrder.length > 0) {
                    const updatedOrder = tagOrder.filter(id => postTags.includes(id));

                    // Add any new tags that aren't in the current order
                    postTags.forEach(tagId => {
                        if (!updatedOrder.includes(tagId)) {
                            updatedOrder.push(tagId);
                        }
                    });

                    setTagOrder(updatedOrder);
                    editPost({meta: {...meta, _settagord: updatedOrder.join(',')}});
                } else {
                    // If tag order is empty but we have tags, initialize it
                    setTagOrder([...postTags]);
                    editPost({meta: {...meta, _settagord: postTags.join(',')}});
                }
            }
        }, [postTags, tags, initialLoad]);

        if (!tags || !postTags) return null;

        /**
         * Sort tags based on the stored tag order
         *
         * This creates an array of tag objects sorted according to the order
         * specified in the tagOrder state.
         */
        const orderedTags = tags.filter(tag =>
            postTags.includes(tag.id)
        ).sort((a, b) => {
            const aIndex = tagOrder.indexOf(a.id);
            const bIndex = tagOrder.indexOf(b.id);
            if (aIndex === -1) return 1;
            if (bIndex === -1) return -1;
            return aIndex - bIndex;
        });

        /**
         * Moves a tag up or down in the order
         *
         * @param {number} tagId     The ID of the tag to move
         * @param {string} direction Direction to move ('up' or 'down')
         */
        const moveTag = (tagId, direction) => {
            const currentIndex = tagOrder.indexOf(tagId);
            if (currentIndex === -1) return;

            const newOrder = [...tagOrder];
            const newIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

            if (newIndex >= 0 && newIndex < newOrder.length) {
                [newOrder[currentIndex], newOrder[newIndex]] =
                    [newOrder[newIndex], newOrder[currentIndex]];

                setTagOrder(newOrder);
                editPost({meta: {...meta, _settagord: newOrder.join(',')}});

                // Reordering moves a row with no visible focus change, so
                // without this a screen reader user gets no feedback.
                const moved = tags.find(tag => tag.id === tagId);
                if (moved) {
                    speak(sprintf(
                        /* translators: 1: tag name, 2: new position, 3: total number of tags */
                        __('%1$s moved to position %2$s of %3$s', 'set-tag-order'),
                        moved.name,
                        newIndex + 1,
                        newOrder.length
                    ), 'polite');
                }
            }
        };

        /**
         * Re-sort the tags alphabetically.
         *
         * An escape hatch for when an order has drifted and stepping every tag
         * back into place one arrow press at a time would be tedious.
         */
        const sortAlphabetically = () => {
            const sorted = [...orderedTags]
                .sort((a, b) => a.name.localeCompare(b.name, undefined, {sensitivity: 'base'}))
                .map(tag => tag.id);

            setTagOrder(sorted);
            editPost({meta: {...meta, _settagord: sorted.join(',')}});
            speak(__('Tags sorted alphabetically', 'set-tag-order'), 'polite');
        };

        if (orderedTags.length === 0) {
            return h('p', null, __('Add tags to customize their display order.', 'set-tag-order'));
        }

        return h('div', {className: 'settagord-panel'},
            h('ul', {className: 'settagord-panel__list'},
                orderedTags.map((tag, index) =>
                    h('li', {
                            key: tag.id,
                            className: 'settagord-panel__item'
                        },
                        h('span', {className: 'settagord-panel__name'}, tag.name),
                        h('div', {className: 'settagord-panel__actions'},
                            index > 0 && h(Button, {
                                size: 'small',
                                onClick: () => moveTag(tag.id, 'up'),
                                icon: 'arrow-up-alt2',
                                label: sprintf(__('Move %s up', 'set-tag-order'), tag.name)
                            }),
                            index < orderedTags.length - 1 && h(Button, {
                                size: 'small',
                                onClick: () => moveTag(tag.id, 'down'),
                                icon: 'arrow-down-alt2',
                                label: sprintf(__('Move %s down', 'set-tag-order'), tag.name)
                            })
                        )
                    )
                )
            ),
            orderedTags.length > 1 && h(Button, {
                variant: 'link',
                className: 'settagord-panel__sort',
                onClick: sortAlphabetically
            }, __('Sort A–Z', 'set-tag-order'))
        );
    };

    /**
     * Find a panel by its title text
     *
     * @param {string} titleText Text to search for in panel titles
     * @return {Element|null} Found panel or null
     */
    function findPanelByTitle(titleText) {
        const allPanels = document.querySelectorAll('.components-panel__body');

        for (const panel of allPanels) {
            const titleElement = panel.querySelector('.components-panel__body-title');
            if (titleElement && titleElement.textContent.includes(titleText)) {
                return panel;
            }
        }

        return null;
    }

    /**
     * Position the Tag Order panel after the Tags panel
     *
     * @since 1.0.5
     */
    function positionTagOrderPanel() {
        // Find panels by their titles
        const tagsPanel = findPanelByTitle('Tags');
        const tagOrderPanel = findPanelByTitle('Tag Order');
        const categoriesPanel = findPanelByTitle('Categories');

        // Add CSS classes to panels for styling
        if (tagsPanel) {
            tagsPanel.classList.add('sto-tags-panel');
        }

        if (tagOrderPanel) {
            tagOrderPanel.classList.add('sto-tag-order-panel');
            tagOrderPanel.classList.add('sto-connected-panel');
        }

        if (categoriesPanel) {
            categoriesPanel.classList.add('sto-categories-panel');
        }

        if (tagsPanel && tagOrderPanel) {
            // Check if they're already in the right order
            if (tagsPanel.nextElementSibling !== tagOrderPanel) {
                try {
                    // Get parent and make sure both panels are in it
                    const parent = tagsPanel.parentNode;
                    if (parent && parent.contains(tagOrderPanel)) {
                        // Move tag order panel to be right after tags panel
                        parent.removeChild(tagOrderPanel);

                        // If tags panel has a next sibling, insert before it
                        if (tagsPanel.nextElementSibling) {
                            parent.insertBefore(tagOrderPanel, tagsPanel.nextElementSibling);
                        } else {
                            // If tags panel is the last child, append tag order panel
                            parent.appendChild(tagOrderPanel);
                        }
                        console.log('[Set Tag Order] Positioned Tag Order panel after Tags panel');
                    }
                } catch (e) {
                    console.error('[Set Tag Order] Error positioning panels:', e);
                }
            }
        }
    }

    /**
     * Set up observers to detect when panels are rendered
     */
    function setupObservers() {
        if (document.readyState !== 'complete') {
            window.addEventListener('load', setupObservers);
            return;
        }

        // Try to position initially
        setTimeout(positionTagOrderPanel, 500);

        // Set up mutation observer for future changes
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // When new nodes are added, check if we need to reposition
                    setTimeout(positionTagOrderPanel, 100);
                }
            }
        });

        // Find sidebar to observe
        const findAndObserveSidebar = () => {
            // Try different sidebar selectors for compatibility
            const sidebar = document.querySelector('.edit-post-sidebar') ||
                document.querySelector('.interface-interface-skeleton__sidebar');

            if (sidebar) {
                observer.observe(sidebar, {
                    childList: true,
                    subtree: true
                });
                return true;
            }
            return false;
        };

        // Try to find sidebar now, retry if not found
        if (!findAndObserveSidebar()) {
            const retryInterval = setInterval(() => {
                if (findAndObserveSidebar()) {
                    clearInterval(retryInterval);
                }
            }, 500);

            // Safety cleanup after 10 seconds
            setTimeout(() => clearInterval(retryInterval), 10000);
        }
    }

    /**
     * Register the plugin with WordPress
     */
    registerPlugin('tag-order-plugin', {
        render: () => {
            return h(PluginDocumentSettingPanel, {
                name: 'tag-order-panel',
                title: __('Tag Order', 'set-tag-order')
            }, h(TagOrderPanel));
        }
    });

    // Make positioning function available globally for debugging
    window.positionTagOrderPanel = positionTagOrderPanel;

    // Setup observers for panel positioning
    setupObservers();

})(window.wp);