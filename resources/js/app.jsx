import React from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Claude Scraper React Application Entry Point
 *
 * Mounts React components for interactive features like
 * scan results editing and import workflows.
 *
 * @author J.J. Johnson <visionquest716@gmail.com>
 */

/**
 * ScanItemEditor - Inline editable table for scan results
 */
function ScanItemEditor({ items: initialItems, scanId }) {
    const [items, setItems] = React.useState(initialItems || []);
    const [saving, setSaving] = React.useState(false);

    const updateItem = (index, field, value) => {
        const updated = [...items];
        updated[index] = { ...updated[index], [field]: value };
        setItems(updated);
    };

    const toggleSelect = (index) => {
        const updated = [...items];
        updated[index].is_selected = !updated[index].is_selected;
        setItems(updated);
    };

    const saveItems = async () => {
        setSaving(true);
        try {
            const resp = await fetch(`/api/scans/${scanId}/items`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN,
                },
                body: JSON.stringify({ items }),
            });
            const data = await resp.json();
            if (data.success) {
                alert(`Saved ${data.count} items.`);
            }
        } catch (err) {
            alert('Save failed: ' + err.message);
        }
        setSaving(false);
    };

    if (!items.length) {
        return React.createElement('div', { className: 'empty-state' },
            React.createElement('p', null, 'No items to display.')
        );
    }

    return React.createElement('div', null,
        React.createElement('button', {
            className: 'btn btn-primary btn-sm mb-3',
            onClick: saveItems,
            disabled: saving,
        }, saving ? 'Saving...' : 'Save All Changes'),
        React.createElement('table', { className: 'table table-sm' },
            React.createElement('thead', null,
                React.createElement('tr', null,
                    React.createElement('th', null, ''),
                    React.createElement('th', null, 'Name'),
                    React.createElement('th', null, 'Price'),
                    React.createElement('th', null, 'Category'),
                )
            ),
            React.createElement('tbody', null,
                items.map((item, i) =>
                    React.createElement('tr', { key: i },
                        React.createElement('td', null,
                            React.createElement('input', {
                                type: 'checkbox',
                                checked: !!item.is_selected,
                                onChange: () => toggleSelect(i),
                            })
                        ),
                        React.createElement('td', null,
                            React.createElement('input', {
                                type: 'text',
                                className: 'form-control form-control-sm',
                                value: item.name || '',
                                onChange: (e) => updateItem(i, 'name', e.target.value),
                            })
                        ),
                        React.createElement('td', null,
                            React.createElement('input', {
                                type: 'number',
                                className: 'form-control form-control-sm',
                                value: item.price || '',
                                step: '0.01',
                                onChange: (e) => updateItem(i, 'price', parseFloat(e.target.value) || null),
                            })
                        ),
                        React.createElement('td', null,
                            React.createElement('input', {
                                type: 'text',
                                className: 'form-control form-control-sm',
                                value: item.category || '',
                                onChange: (e) => updateItem(i, 'category', e.target.value),
                            })
                        ),
                    )
                )
            )
        )
    );
}

// Mount React components if containers exist
document.addEventListener('DOMContentLoaded', () => {
    const editorEl = document.getElementById('react-item-editor');
    if (editorEl) {
        const items = JSON.parse(editorEl.dataset.items || '[]');
        const scanId = editorEl.dataset.scanId;
        const root = createRoot(editorEl);
        root.render(React.createElement(ScanItemEditor, { items, scanId }));
    }
});
