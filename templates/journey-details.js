document.addEventListener("DOMContentLoaded", function() {
    const el = document.getElementById('stage-list');

    function renderStageLoadingIcon(stageId) {
        const nameContainer = document.getElementById(`stage-list-header`);
        if (!nameContainer) return;

        const existingIcon = nameContainer.querySelector('.icon-overlay');
        if (existingIcon) {
            existingIcon.remove();
        }

        const icon = document.createElement('dt-spinner');
        icon.className = 'icon-overlay';

        nameContainer.appendChild(icon);
    }

    function renderStageSavedIcon(stageId) {
        const nameContainer = document.getElementById(`stage-list-header`);
        if (!nameContainer) return;

        const existingIcon = nameContainer.querySelector('.icon-overlay');
        if (existingIcon) {
            existingIcon.remove();
        }

        const icon = document.createElement('dt-checkmark');
        icon.className = 'icon-overlay success fade-out';

        nameContainer.appendChild(icon);
    }

    if (el) {
        Sortable.create(el, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'blue-background-class',
            onStart: function (evt) {
                const existingIcon = evt.item.querySelector('.icon-overlay');
                if (existingIcon) {
                    existingIcon.remove();
                }
            },
            onEnd: function (evt) {
                const draggedStageId = evt.item.getAttribute('data-id');

                renderStageLoadingIcon(draggedStageId);

                const items = el.querySelectorAll('li');
                const stageOrder = [];

                items.forEach((item, index) => {
                    stageOrder.push({
                        id: item.getAttribute('data-id'),
                        order: index+1
                    });
                });

                if (journey_details_js.journeyId) {
                    const payload = {
                        journey_id: journey_details_js.journeyId,
                        new_order: stageOrder
                    };

                    fetch(journey_details_js.rest_endpoint + `journeys/${journey_details_js.journeyId}/reorder-stages`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': window.wpApiShare.nonce
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Order updated successfully!');
                            renderStageSavedIcon(draggedStageId);

                        } else {
                            console.log(data);
                            console.error('Failed to update order.', data);
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                    });
                } else {
                    if (window.stages && Array.isArray(window.stages)) {
                        items.forEach((item, index) => {
                            const stageId = item.getAttribute('data-id');
                            const stage = window.stages.find(s => (s.ID == stageId || s.temp_id == stageId));
                            
                            if (stage) {
                                stage.stage_order = index + 1;
                            }
                        });

                        window.stages.sort((a, b) => (a.stage_order || 0) - (b.stage_order || 0));
                    }

                    renderStageSavedIcon(draggedStageId);
                }
            }
        });
    }
});