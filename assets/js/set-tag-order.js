/*
* File Name: assets/js/set-tag-order.js
*/

(function (wp) {
    const {registerPlugin} = wp.plugins;
    const {PluginDocumentSettingPanel} = wp.editor;
    const {useSelect, useDispatch} = wp.data;
    const {useState, useEffect, createElement: h} = wp.element;
    const {Button} = wp.components;

    const TagOrderPanel = () => {
        const [tagOrder, setTagOrder] = useState([]);

        // Get post type
        const postType = useSelect(select =>
            select('core/editor').getCurrentPostType()
        );

        const tags = useSelect(select =>
            select('core').getEntityRecords('taxonomy', 'post_tag', {
                per_page: -1
            })
        );

        const postTags = useSelect(select =>
            select('core/editor').getEditedPostAttribute('tags')
        );

        const meta = useSelect(select =>
            select('core/editor').getEditedPostAttribute('meta')
        );

        const {editPost} = useDispatch('core/editor');

        // Update tag order whenever postTags changes
        useEffect(() => {
            if (!postTags || !postTags.length) {
                // Clear the order if there are no tags
                setTagOrder([]);
                editPost({meta: {...meta, _tag_order: ''}});
                return;
            }

            const savedOrder = meta?._tag_order ? meta._tag_order.split(',').map(Number) : [];

            // Filter out any saved order IDs that are no longer in postTags
            const validSavedOrder = savedOrder.filter(id => postTags.includes(id));

            // Add any new tags that aren't in the saved order
            const newOrder = [...validSavedOrder];
            postTags.forEach(tagId => {
                if (!newOrder.includes(tagId)) {
                    newOrder.push(tagId);
                }
            });

            setTagOrder(newOrder);
            editPost({meta: {...meta, _tag_order: newOrder.join(',')}});
        }, [postTags]);

        if (!tags || !postTags) return null;

        const orderedTags = tags.filter(tag =>
            postTags.includes(tag.id)
        ).sort((a, b) => {
            const aIndex = tagOrder.indexOf(a.id);
            const bIndex = tagOrder.indexOf(b.id);
            if (aIndex === -1) return 1;
            if (bIndex === -1) return -1;
            return aIndex - bIndex;
        });

        const moveTag = (tagId, direction) => {
            const currentIndex = tagOrder.indexOf(tagId);
            if (currentIndex === -1) return;

            const newOrder = [...tagOrder];
            const newIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

            if (newIndex >= 0 && newIndex < newOrder.length) {
                [newOrder[currentIndex], newOrder[newIndex]] =
                    [newOrder[newIndex], newOrder[currentIndex]];

                setTagOrder(newOrder);
                editPost({meta: {...meta, _tag_order: newOrder.join(',')}});
            }
        };

        return h('div', null,
            orderedTags.length === 0 ?
                h('p', null, 'Add tags to customize their display order.') :
                orderedTags.map((tag, index) =>
                    h('div', {
                            key: tag.id,
                            style: {
                                display: 'flex',
                                alignItems: 'center',
                                marginBottom: '8px',
                                padding: '8px',
                                backgroundColor: '#f0f0f0',
                                borderRadius: '4px'
                            }
                        },
                        h('span', null, tag.name),
                        h('div', {style: {marginLeft: 'auto'}},
                            index > 0 && h(Button, {
                                isSmall: true,
                                onClick: () => moveTag(tag.id, 'up'),
                                icon: 'arrow-up-alt2'
                            }),
                            index < orderedTags.length - 1 && h(Button, {
                                isSmall: true,
                                onClick: () => moveTag(tag.id, 'down'),
                                icon: 'arrow-down-alt2'
                            })
                        )
                    )
                )
        );
    };

    registerPlugin('tag-order-plugin', {
        render: () => h(PluginDocumentSettingPanel, {
            name: 'tag-order-panel',
            title: 'Tag Order'
        }, h(TagOrderPanel))
    });
})(window.wp);