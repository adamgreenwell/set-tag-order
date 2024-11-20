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

        const {editPost} = useDispatch('core/editor');

        // Initialize tagOrder with current post tags if no order is saved
        useEffect(() => {
            if (postTags && postTags.length > 0) {
                const meta = wp.data.select('core/editor').getEditedPostAttribute('meta') || {};
                const savedOrder = meta._tag_order ? meta._tag_order.split(',').map(Number) : null;

                if (!savedOrder) {
                    setTagOrder(postTags);
                    editPost({meta: {_tag_order: postTags.join(',')}});
                } else {
                    setTagOrder(savedOrder);
                }
            }
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

            [newOrder[currentIndex], newOrder[newIndex]] =
                [newOrder[newIndex], newOrder[currentIndex]];

            setTagOrder(newOrder);
            editPost({meta: {_tag_order: newOrder.join(',')}});
        };

        return h('div', null,
            orderedTags.map((tag, index) =>
                h('div', {
                        key: tag.id,
                        style: {
                            display: 'flex',
                            alignItems: 'center',
                            marginBottom: '8px'
                        }
                    },
                    h('span', null, tag.name),
                    h('div', {style: {marginLeft: 'auto'}},
                        index > 0 && h(Button, {
                            isSmall: true,
                            onClick: () => moveTag(tag.id, 'up')
                        }, '↑'),
                        index < orderedTags.length - 1 && h(Button, {
                            isSmall: true,
                            onClick: () => moveTag(tag.id, 'down')
                        }, '↓')
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