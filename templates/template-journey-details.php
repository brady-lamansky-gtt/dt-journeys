<?php
/**
 * Template Name: Journeys Admin Page
 */

get_header();
$journey_id = get_query_var( 'dt_journey_id' );

$post_settings = DT_Posts::get_post_settings( 'journeys' );
$field_options = isset( $post_settings['fields'] ) ? $post_settings['fields'] : [];

$stage_settings = DT_Posts::get_post_settings( 'journey_stages' );
$stage_fields = isset( $stage_settings['fields'] ) ? $stage_settings['fields'] : [];

if ( empty( $journey_id ) ) {
    $journey = [
        'post_type' => 'journeys',
        'stages'    => []
    ];
} else {
    $journey = DT_Posts::get_post( 'journeys', $journey_id );

    if ( empty( $journey ) || is_wp_error( $journey ) ) {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        include get_404_template();
        exit;
    }
}

$stages = [];
$p2p_type = 'journeys_to_stages';

foreach ( $journey['stages'] ?? [] as $connected_stage ) {
    $stage_id = $connected_stage['ID'];
    $stage = DT_Posts::get_post( 'journey_stages', $stage_id );

    if ( ! is_wp_error( $stage ) && ! empty( $stage ) ) {
        $p2p_ids = p2p_get_connections( $p2p_type, array(
            'from'   => $journey_id,
            'to'     => $stage_id,
            'fields' => 'p2p_id',
        ) );

        $p2p_id = !empty( $p2p_ids ) ? (int) $p2p_ids[0] : false;

        $order_val = $p2p_id ? p2p_get_meta( $p2p_id, 'stage_order', true ) : 0;

        $stage['stage_order'] = (int) $order_val;
        $stages[] = $stage;
    }
}

// Sort stages by their contextual order
usort( $stages, function ( $a, $b ) {
    return ( $a['stage_order'] ?? 0 ) <=> ( $b['stage_order'] ?? 0 );
} );

foreach ( $stages as &$stage ) {
    foreach ( $stage_fields as $field_key => $field_config ) {
        $field_type = $field_config['type'] ?? '';

        if ( in_array( $field_type, [ 'connection', 'user_select', 'multi_select' ], true ) && ! empty( $stage[ $field_key ] ) ) {
            if ( is_array( $stage[ $field_key ] ) ) {
                foreach ( $stage[ $field_key ] as &$item ) {
                    if ( is_array( $item ) ) {
                        $item['id'] = $item['id'] ?? $item['ID'] ?? 0;
                        $item['label'] = $item['label'] ?? $item['name'] ?? $item['post_title'] ?? '';
                        $item['link'] = $item['link'] ?? $item['permalink'] ?? '';
                    }
                }
            }
        }
    }
}
unset( $stage );

?>

<!-- List Section -->
<div id="content" class="grid-container" style="min-height: 80vh;">
    <div class="title-row title">
        <button class="link-button" onclick="go_back()">
            <dt-icon icon="mdi:chevron-left"></dt-icon>
            <?php esc_html_e( 'Back', 'disciple_tools' ); ?>
        </button>
    </div>
    <div class="slider-viewport">
        <div id="slider-track" class="grid-x grid-margin-x">
            <section id="section-journey-details" class="medium-7 small-12 cell">
                <div class="bordered-box">
                    <div class="title-row">
                    <h6 class="journey-header"><?php esc_html_e( 'Journey Details', 'disciple_tools' ); ?></h6>
                        <?php if ( empty( $journey_id ) ) : ?>
                            <div class="split-button-wrapper" id="save-split-button">
                                <button type="submit" form="journey-form" class="button split-main-btn" id="save-btn">
                                    <span id="top-btn-label"><?php esc_html_e( 'Save & Go Back', 'disciple_tools' ); ?></span>
                                </button>
                                <button class="button split-toggle-btn" type="button" onclick="toggle_save_dropdown(event)">
                                    <dt-icon icon="mdi:chevron-down" id="top-icon"></dt-icon>
                                </button>
                                <div class="split-dropdown-menu top" id="save-dropdown-top">
                                    <button type="button" class="dropdown-item" onclick="select_save_mode('continue')">
                                        <?php esc_html_e( 'Save & Continue', 'disciple_tools' ); ?>
                                    </button>
                                    <button type="button" class="dropdown-item" onclick="select_save_mode('add_new')">
                                        <?php esc_html_e( 'Save & Add New', 'disciple_tools' ); ?>
                                    </button>
                                    <button type="button" class="dropdown-item" onclick="select_save_mode('go_back')">
                                        <?php esc_html_e( 'Save & Go Back', 'disciple_tools' ); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <form id="journey-form" onsubmit="save_journey(event)">
                        <div class="margin-top-1 fields-container">
                            <?php

                            foreach ( $field_options as $field_key => $field ) {

                                if ( !isset( $field['tile'] ) || $field_key === 'stages' ) {
                                    continue;
                                }

                                if ( ! array_key_exists( $field_key, $journey ) ) {

                                    $array_types = [ 'tags', 'multi_select', 'connection', 'user_select' ];

                                    if ( isset( $field['type'] ) && in_array( $field['type'], $array_types, true ) ) {
                                        $journey[ $field_key ] = [];
                                    } elseif ( isset( $field['type'] ) && $field['type'] === 'key_select' ) {
                                        $journey[ $field_key ] = [ 'key' => '' ];
                                    } else {
                                        $journey[ $field_key ] = '';
                                    }
                                }

                                $is_required = ! empty( $field['required'] ) ? true : false;
                                $display_settings = $field_options;
                                $display_settings[ $field_key ]['required'] = $is_required;

                                render_field_for_display( $field_key, $display_settings, $journey, true, true, $journey_id, [] );
                            }
                            ?>
                        </div>
                    </form>
                    <div class="button-container">
                        <?php if ( ! empty( $journey_id ) ) : ?>
                            <button class="button button-delete" onclick="delete_journey(<?php echo esc_js( $journey_id ); ?>)">
                                <?php esc_html_e( 'Delete Journey', 'disciple_tools' ); ?>
                            </button>
                        <?php else : ?>
                            <div class="split-button-wrapper" id="save-split-button">
                                <button type="submit" form="journey-form" class="button split-main-btn" id="save-btn">
                                    <span id="bottom-btn-label"><?php esc_html_e( 'Save & Go Back', 'disciple_tools' ); ?></span>
                                </button>
                                <button class="button split-toggle-btn" type="button" onclick="toggle_save_dropdown(event)">
                                    <dt-icon icon="mdi:chevron-down" id="bottom-icon"></dt-icon>
                                </button>
                                <div class="split-dropdown-menu bottom" id="save-dropdown-bottom">
                                    <button type="button" class="dropdown-item" onclick="select_save_mode('continue')">
                                        <?php esc_html_e( 'Save & Continue', 'disciple_tools' ); ?>
                                    </button>
                                    <button type="button" class="dropdown-item" onclick="select_save_mode('add_new')">
                                        <?php esc_html_e( 'Save & Add New', 'disciple_tools' ); ?>
                                    </button>
                                    <button type="button" class="dropdown-item" onclick="select_save_mode('go_back')">
                                        <?php esc_html_e( 'Save & Go Back', 'disciple_tools' ); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <section id="section-stages-list" class="medium-5 small-12 cell">
                <div class="bordered-box">
                    <span class="error-text" id="stage-error-message" style="display: none;"></span>
                    <div class="title-row">
                        <div class="stage-list-header" id="stage-list-header">
                            <h6 class="journey-header"><?php esc_html_e( 'Stages', 'disciple_tools' ); ?></h6>
                        </div>
                        <button class="button" onclick="edit_stage()">
                            <?php esc_html_e( 'Add Stage', 'disciple_tools' ); ?>
                        </button>
                    </div>
                    <div>
                        <div id="no-stages-text" class="margin-top-1">
                            <?php esc_html_e( 'No journey stages created.', 'disciple_tools' ); ?>
                            <button class="link-button inline" onclick="edit_stage()"><?php esc_html_e( 'Add Stage', 'disciple_tools' ); ?></button>
                        </div>
                        <ul id="stage-list">
                            <?php foreach ( $stages as $stage ) { ?>
                            <li id="stage-<?php echo esc_attr( $stage['ID'] ); ?>" data-id="<?php echo esc_attr( $stage['ID'] ); ?>" style="border: 1px solid #ccc; padding: 1em; margin: 1em 0; display: flex; justify-content: space-between; background: #fff;">
                                <div class="item-details">
                                    <div class="stage-order">
                                        <dt-icon class="drag-handle" icon="mdi:reorder-horizontal"></dt-icon>
                                    </div>
                                    <div class="stage-data">
                                        <div class="stage-name" id="stage-name-<?php echo esc_attr( $stage['ID'] ); ?>">
                                            <?php echo esc_html( $stage['name'] ); ?>
                                        </div>
                                        <div class="stage-description" id="stage-description-<?php echo esc_attr( $stage['ID'] ); ?>">
                                            <?php echo esc_html( $stage['description'] ?? '' ); ?>
                                        </div>
                                        <div class="stage-fields" id="stage-fields-<?php echo esc_attr( $stage['ID'] ); ?>">
                                            <?php echo 'Links: ' . esc_html( count( $stage['links'] ?? [] ) ) . ' Attachments: ' . esc_html( count( $stage['attachments'] ?? [] ) ) . ' Related Fields: ' . esc_html( count( $stage['related_fields'] ?? [] ) ); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="stage-actions">
                                    <button class="icon-btn" onclick="edit_stage(<?php echo esc_js( $stage['ID'] ); ?>)"><dt-icon icon="mdi:edit"></dt-icon></button>
                                    <button class="icon-btn" onclick="delete_stage(<?php echo esc_js( $stage['ID'] ); ?>)"><dt-icon icon="mdi:delete"></dt-icon></button>
                                </div>
                            </li>
                                <?php
                            }
                            ?>
                        </ul>
                    </div>
                </div>
            </section>
            <section id="section-edit-stage" class="medium-7 small-12 cell">
                <div class="bordered-box">
                    <div class="title-row">
                        <h6 id="edit-section-title" class="journey-header"><?php esc_html_e( 'Edit Stage', 'disciple_tools' ); ?></h6>
                        <button class="icon-btn" onclick="close_edit()" style="transform: scale(1.2);">
                            <dt-icon icon="mdi:close"></dt-icon>
                        </button>
                    </div>

                    <div id="edit-stage-content" class="margin-top-1">
                        <form id="stage-form" class="stage-edit-form" onsubmit="save_stage(event)">
                            <div class="fields-container">
                            <?php
                            // Still need to empty the fields for Add Stage
                            foreach ( $stage_fields as $field_key => $field ) {
                                if ( empty( $field['tile'] ) || $field_key === 'journey' ) {
                                    continue;
                                }
                                $is_required = ! empty( $field['required'] ) ? true : false;
                                $display_settings = $stage_fields;
                                $display_settings[ $field_key ]['required'] = $is_required;

                                if ( $display_settings[$field_key]['type'] === 'multi_select' ) {
                                    $display_settings[$field_key]['display'] = 'typeahead';
                                }

                                render_field_for_display( $field_key, $display_settings, [ 'post_type' => 'journey_stages' ], true, true, '', [] );
                            }
                            ?>
                            </div>
                            <div id="stage-save-container" class="button-container">
                                <button type="button" class="button button-back" onclick="close_edit()">
                                    <?php esc_html_e( 'Cancel', 'disciple_tools' ); ?>
                                </button>
                                <button type="submit" class="button">
                                    <?php esc_html_e( 'Save Stage', 'disciple_tools' ); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<style>
    .fields-container {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem 1.5rem;
        margin-block-end: 1rem;
    }

    .slider-viewport {
        overflow-x: hidden;
        width: 100%;
        position: relative;
    }

    #slider-track {
        flex-wrap: nowrap !important;
        transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    }

    #slider-track > .cell {
        flex-shrink: 0 !important;
    }

    .shift-left {
        transform: translateX(-58.33333%);
    }

    .title-header {
        font-weight: bold;
        margin: 0;
    }
    .title-row { display: flex; justify-content: space-between; column-gap: 1em; width: 100%; }
    .title {
        padding-block: .5rem;
    }

    .help-text {
        margin-top: 0;
    }

    .button {
        padding: 0.4em 0.75em;
        border-radius: 5px;
        border: 1px solid transparent;
        cursor: pointer;
        background-color: #3f729b;
        color: #fefefe;
        margin: 0;
    }
    .button-container {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .5rem;
    }

    .split-button-wrapper {
        position: relative;
        display: inline-flex;
        border-radius: 5px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .split-main-btn {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
        border-right: 1px solid rgba(255, 255, 255, 0.2);
    }

    .split-toggle-btn {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        padding-inline: 0.4em;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .split-dropdown-menu {
        display: none;
        position: absolute;
        right: 0;
        background-color: #ffffff;
        min-width: 170px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border: 1px solid #cccccc;
        border-radius: 5px;
        z-index: 100;
        overflow: hidden;
    }
    .split-dropdown-menu.top {
        top: 100%;
        margin-top: 4px;
    }
    .split-dropdown-menu.bottom {
        bottom: 100%;
        margin-bottom: 4px;
    }
    .split-dropdown-menu.show {
        display: block;
    }

    .dropdown-item {
        display: block;
        width: 100%;
        text-align: left;
        padding: 0.5em 0.85em;
        background: transparent;
        border: none;
        color: #333333;
        cursor: pointer;
        font-size: 0.9em;
    }

    .dropdown-item:hover {
        background-color: #f0f4f8;
        color: #3f729b;
    }

    .button-delete {
        background-color: #ffffff;
        color: #ff0000;
        border-color: #cccccc;
    }
    .button-delete:hover {
        background-color: #f0f0f0;
        color: #ff0000;
    }
    .button-delete:focus {
        background-color: #f0f0f0;
        color: #ff0000;
    }

    .button-back {
        background-color: #ffffff;
        color: #000000;
        border-color: #cccccc;
    }
    .button-back:hover {
        background-color: #f0f0f0;
        color: #000000;
    }
    .button-back:focus {
        background-color: #f0f0f0;
        color: #000000;
    }

    .link-button {
        background: none;
        border: none;
        padding-left: 0.5rem;
        margin: 0;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
    }

    .link-button dt-icon {
        display: inline-flex;
        align-items: center;
        font-size: 1.25em;
    }
    .link-button.inline {
        color: #0000ee;
        text-decoration: underline;
    }

    .journey-header {
        font-weight: bold;
    }

    .sortable-placeholder {
        border: 1px dashed #a0c4d9;
        background-color: #f7fbfc;
        margin: 1em 0;
        padding: 1em;
        border-radius: 5px;
    }

    .stage-name {
        font-weight: bold;
    }

    .stage-list-header {
        display: flex;
        align-items: center;
    }

    .stage-description {
        font-style: italic;
    }

    .stage-order {
        display: flex;
        align-items: center;
        gap: .25rem;
    }

    .item-details {
        display: flex;
        align-items: center;
        gap: 0.5em;
    }

    .drag-handle {
        color: #999;
        cursor: grab;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-color: transparent;
        border: none;
        font-size: 1.5em;
        padding: 0;
    }

    .stage-actions {
        display: flex;
        align-items: center;
    }

    .icon-btn {
        background-color: transparent;
        border-width: medium;
        border-style: none;
        border-color: currentcolor;
        border-image: none;
        cursor: pointer;
        height: 0.9em;
        padding: 0px;
        color: #3f729b;
        transform: scale(1.5);
        padding-inline-start: .5em;
        padding-inline-end: .5em;
    }

    @keyframes fadeOut {
        0% {
        opacity: 1;
        }
        75% {
        opacity: 1;
        }
        100% {
        opacity: 0;
        }
    }

    .icon-overlay.fade-out {
        opacity: 0;
        animation: fadeOut 4s;
    }

    .icon-overlay {
        display: inline-flex;
        align-items: center;
        margin-left: 0.75rem;
        margin-bottom: 0.5rem;
        pointer-events: none;
    }

    .icon-overlay.success {
        color: var(--success-color);
        width: 1.4rem;
    }

    @media screen and (max-width: 39.9375em) {

        .button-container {
            justify-content: space-between;
        }

        .fields-container {
            grid-template-columns: 1fr;
        }

        #stage-list li {
            flex-direction: column;
            align-items: flex-start;
            gap: 1em;
        }
        .stage-actions {
            width: 100%;
            justify-content: flex-end;
            border-top: 1px solid #eeeeee;
            padding-top: 0.75em;
        }

        #slider-track {
            flex-wrap: wrap !important;
            transform: none !important;
            transition: none !important;
        }

        #slider-track > .cell {
            flex-shrink: 1 !important;
            width: 100%;
            margin-bottom: 1rem;
        }

        #slider-track.shift-left #section-journey-details,
        #slider-track.shift-left #section-stages-list {
            display: none;
        }

        #section-edit-stage {
            display: none;
        }

        #slider-track.shift-left #section-edit-stage {
            display: block;
        }

        ul {
            margin: 0;
        }
    }

</style>

<script>
    const SAVE_MODES = {
        continue: '<?php echo esc_js( __( 'Save & Continue', 'disciple_tools' ) ); ?>',
        add_new:  '<?php echo esc_js( __( 'Save & Add New', 'disciple_tools' ) ); ?>',
        go_back:  '<?php echo esc_js( __( 'Save & Go Back', 'disciple_tools' ) ); ?>'
    };

    let currentSaveMode = localStorage.getItem('dt_journey_save_action') || 'go_back';

    const journeyId = <?php echo absint( $journey_id ); ?>;
    const journeysBaseUrl = '<?php echo esc_js( site_url( '/admin/journeys/' ) ); ?>';
    const journeyName = '<?php echo esc_js( $journey['name'] ?? $journey['post_title'] ?? 'Unknown Journey' ); ?>';

    let stageTempId = 1;

    function updateSaveButtonUI() {
        let labelEl = document.getElementById('top-btn-label');
        if (labelEl && SAVE_MODES[currentSaveMode]) {
            labelEl.innerHTML = SAVE_MODES[currentSaveMode];
        }
        labelEl = document.getElementById('bottom-btn-label');
        if (labelEl && SAVE_MODES[currentSaveMode]) {
            labelEl.innerHTML = SAVE_MODES[currentSaveMode];
        }
    }

    function toggle_save_dropdown(event) {
        const id = event.srcElement.id;
        event.stopPropagation();
        let menu = document.getElementById('save-dropdown-top');
        if (menu && id === "top-icon") {
            menu.classList.toggle('show');
        }
        menu = document.getElementById('save-dropdown-bottom');
        if (menu && id === "bottom-icon") {
            menu.classList.toggle('show');
        }
    }

    function select_save_mode(mode) {
        if (SAVE_MODES[mode]) {
            currentSaveMode = mode;
            localStorage.setItem('dt_journey_save_action', mode);
            updateSaveButtonUI();
        }
        let menu = document.getElementById('save-dropdown-top');
        if (menu) {
            menu.classList.remove('show');
        }
        menu = document.getElementById('save-dropdown-bottom');
        if (menu) {
            menu.classList.remove('show');
        }
    }

    // Close split dropdown on outside clicks
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('save-split-button');
        let menu = document.getElementById('save-dropdown-top');
        if (menu && wrapper && !wrapper.contains(e.target)) {
            menu.classList.remove('show');
        }
        menu = document.getElementById('save-dropdown-bottom');
        if (menu && wrapper && !wrapper.contains(e.target)) {
            menu.classList.remove('show');
        }
    });

    function go_back() {
        window.location.href = journeysBaseUrl;
    }

    async function save_stage(event) {
        hideError();

        event.preventDefault();

        const activeForm = event.target.closest('#stage-form');

        if (!activeForm) {
            console.error("Could not find the active stage form.");
            return;
        }

        const isUpdating = journeyId == 0 && currentStageId !== null && currentStageId !== 0;

        const payload = {};

        if (isUpdating) {
            payload.ID = currentStageId;
        } else {
            let nextStageOrder = 0;
            if (stages && stages.length > 0) {
                const currentOrders = stages.map(stage => parseInt(stage.stage_order || 0));
                nextStageOrder = Math.max(...currentOrders) + 1;
            }
            payload['stage_order'] = nextStageOrder;
        }

        const stageFields = <?php
        $js_fields = array_map( function( $field ) {
            return $field['type'] ?? 'text';
        }, $stage_fields );

        echo wp_json_encode( $js_fields );
        ?>;

        for ( const [fieldKey, fieldType] of Object.entries(stageFields) ) {
            const el = activeForm.querySelector(`[id="${fieldKey}"]`);
            if (fieldKey === 'journey') {
                payload[fieldKey] = [
                    {
                        "id": journeyId
                    }
                ];
            }
            if ( ! el ) continue;
            if (fieldKey === 'attachments' && !el.value) {
                continue
            }
            payload[fieldKey] = el.value;
        }

        if (journeyId > 0) {
            try {
                let response = await fetch(window.journey_details_js.rest_endpoint + `journeys/stage`, {
                    method: 'POST',
                    headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.wpApiShare.nonce,
                    },
                    body: JSON.stringify(payload)
                });

                if (response.ok) {
                    let result = await response.json();

                    const newId = result.id || result.ID;
                    if (newId) {
                        render_new_stage(newId, payload);
                    }
                    close_edit();
                } else {
                    let result = await response.json();

                    showError(result.message || 'Failed to save stage');
                }
            } catch (error) {
                console.error("Create/Update Stage Error:", error);
                showError('An error occurred while saving the stage. Please try again.');
            }
        } else if (!isUpdating) {
            payload.temp_id = stageTempId;
            stageTempId += 1;
            render_new_stage(payload.temp_id, payload);
            close_edit();
        } else {
            close_edit();
        }
    }

    let currentStageId = null;
    window.stages = <?php echo wp_json_encode( $stages ); ?>;
    let stages = window.stages;

    function edit_stage(stage_id) {
        if (journeyId > 0 && currentStageId !== null && currentStageId !== stage_id) {
            sync_stage_list();
        }

        const titleEl = document.getElementById('edit-section-title');
        const activeForm = document.getElementById('stage-form');
        currentStageId = stage_id || null;

        document.querySelectorAll('.stage-edit-form').forEach(function(form) {
            form.style.display = 'none';
        });

        if (activeForm) {
            activeForm.querySelectorAll('[post-id]').forEach(el => {
                el.setAttribute('post-id', currentStageId);
            });

            for (const formField of activeForm) {
                if ( formField.tagName.startsWith('DT-') ) {
                    formField.reset();
                }
            }
        }

        if (stage_id) {
            if (journeyId == 0) {
                document.querySelector('#stage-save-container').style.display = 'flex';
            } else {
                document.querySelector('#stage-save-container').style.display = 'none';
            }
            if (titleEl) titleEl.innerText = 'Edit Stage';
            const stageData = stages.find(s => s.ID == stage_id);

            if (activeForm) {
                for (const formField of activeForm) {
                    if (formField.id) {
                        formField.value = stageData[formField.id] !== undefined ? stageData[formField.id] : '';
                    }
                }
                activeForm.style.display = 'grid';
            }

            if (window.componentService) {
                window.componentService.postType = 'journey_stages';
                window.componentService.postId = stage_id;
            }

        } else {
            document.querySelector('#stage-save-container').style.display = 'flex';
            if (titleEl) titleEl.innerText = 'Add Stage';

            if (activeForm) activeForm.style.display = 'grid';

            if (window.componentService) {
                window.componentService.postType = 'journey_stages';
                window.componentService.postId = 0;
            }
        }

        document.getElementById('slider-track').classList.add('shift-left');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function close_edit() {
        sync_stage_list();

        currentStageId = null;
        document.getElementById('slider-track').classList.remove('shift-left');

        if (window.componentService) {
            window.componentService.postType = 'journeys';
            window.componentService.postId = journeyId;
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function delete_stage(stage_id) {
        hideError();

        const stageRow = document.getElementById(`stage-${stage_id}`);

        if (journeyId === 0) {
            const index = stages.findIndex(stage => stage.ID === stage_id || stage.temp_id === stage_id);
    
            if (index !== -1) {
                stages.splice(index, 1);
            }

            if (stageRow) {
                stageRow.remove();
            }

        } else {
            try {
                let response = await fetch(window.journey_details_js.rest_endpoint + `journeys/stage/${stage_id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.wpApiShare.nonce,
                    },
                });

                if (response.ok) {
                    let result = await response.json();

                    if (stageRow) {
                        stageRow.remove();
                    }

                    const index = stages.findIndex(stage => stage.ID == stage_id);
                    if (index !== -1) {
                        stages.splice(index, 1);
                    }
                } else {
                    let result = await response.json();

                    showError(result.message || 'Failed to delete stage');
                }
            } catch (error) {
                console.error("Delete Stage Error:", error);
                showError('An error occurred while deleting the stage. Please try again.');
            }
        }

        toggleEmptyStageText();
    }

    async function delete_journey(journey_id) {

        try {
            let response = await fetch(window.journey_details_js.rest_endpoint + `journeys/${journey_id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.wpApiShare.nonce,
                },
            });

            if (response.ok) {
                window.location.href = journeysBaseUrl;
            } else {
                let result = await response.json();
                showError(result.message || 'Failed to delete journey');
            }
        } catch (error) {
            console.error("Delete Journey Error:", error);
            showError('An error occurred while deleting the journey. Please try again.');
        }
    }

    async function save_journey(event) {
        hideError();

        event.preventDefault();

        const journeyFields = <?php
            $js_fields = array_map( function( $field ) {
                return $field['type'] ?? 'text';
            }, $field_options );

            echo wp_json_encode( $js_fields );
            ?>;

        const payload = {};

        for ( const [fieldKey, fieldType] of Object.entries(journeyFields) ) {
            const el = document.getElementById(fieldKey);
            if ( ! el ) continue;
            
            payload[fieldKey] = el.value;
        }

        payload['stages'] = window.stages;

        try {
            let response = await fetch(window.journey_details_js.rest_endpoint + `journeys`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.wpApiShare.nonce,
                },
                body: JSON.stringify(payload)
            });

            if (response.ok) {

                let result = await response.json();

                const newId = result.ID || '';

                if (currentSaveMode === 'continue' && newId) {
                    window.location.href = `${journeysBaseUrl}${newId}/`;
                } else if (currentSaveMode === 'add_new') {
                    window.location.href = `${journeysBaseUrl}new/`;
                } else {
                    // Default: 'go_back'
                    window.location.href = journeysBaseUrl;
                }
            } else {
                let result = await response.json();

                showError(result.message || 'Failed to save journey');
            }
        } catch (error) {
            console.error("Create Journey Error:", error);
            showError('An error occurred while saving the journey. Please try again.');
        }
    }

    function toggleEmptyStageText() {
        const emptyMsg = document.getElementById('no-stages-text');
        const listItems = document.querySelectorAll('#stage-list li');

        if (emptyMsg) {
            if (listItems.length > 0) {
                emptyMsg.style.display = 'none';
            } else {
                emptyMsg.style.display = 'block';
            }
        }
    }
    
    function sync_stage_list() {
        if (currentStageId !== null && currentStageId !== 0) {
            const activeForm = document.getElementById('stage-form');

            const newName = activeForm.querySelector('[id="name"]')?.value || '';
            const newDesc = activeForm.querySelector('[id="description"]')?.value || '';

            const links = activeForm.querySelector('[id="links"]')?.value || [];
            const validLinks = Array.isArray(links) ? links.filter(item => item.value && item.value.trim() !== '') : [];
            const attachments = activeForm.querySelector('[id="attachments"]')?.value || [];
            const related_fields = activeForm.querySelector('[id="related_fields"]')?.value || [];

            const linksCount = Array.isArray(validLinks) ? validLinks.length : 0;
            const attachmentsCount = Array.isArray(attachments) ? attachments.length : 0;
            const relatedCount = Array.isArray(related_fields) ? related_fields.length : 0;

            const nameEl = document.getElementById(`stage-name-${currentStageId}`);
            if (nameEl) nameEl.textContent = newName;

            const descEl = document.getElementById(`stage-description-${currentStageId}`);
            if (descEl) descEl.textContent = newDesc;

            const stageFieldsEl = document.getElementById(`stage-fields-${currentStageId}`);
            if (stageFieldsEl) stageFieldsEl.textContent = `Links: ${linksCount} Attachments: ${attachmentsCount} Related Fields: ${relatedCount}`;

            stages = stages.map(stage => {
                if (stage.ID == currentStageId) {
                    const newStage = { ...stage };

                    for (const [key, item] of Object.entries(stage)) {
                        const el = activeForm.querySelector(`[id="${key}"]`);

                        if (el && el.value !== undefined) {
                            newStage[key] = el.value;
                        }
                    }

                    return newStage;
                }
                return stage;
            });

            window.stages = stages;
        }
    }

    function render_new_stage(stageId, payload) {
        const template = document.getElementById('stage-row-template');
        const clone = template.content.cloneNode(true); // true means clone all children

        const li = clone.querySelector('li');
        li.id = `stage-${stageId}`;
        li.setAttribute('data-id', stageId);

        const nameEl = clone.querySelector('.stage-name');
        nameEl.id = `stage-name-${stageId}`;
        nameEl.textContent = payload.name || 'New Stage';

        const descEl = clone.querySelector('.stage-description');
        descEl.id = `stage-description-${stageId}`;
        descEl.textContent = payload.description || '';

        const links = payload.links?.value ?? payload.links ?? [];
        const validLinks = Array.isArray(links) ? links.filter(item => item.value && item.value.trim() !== '') : [];
        const linksCount = Array.isArray(validLinks) ? validLinks.length : 0;
        const attachmentsCount = Array.isArray(payload.attachments) ? payload.attachments.length : 0;
        const relatedCount = Array.isArray(payload.related_fields) ? payload.related_fields.length : 0;

        const fieldsEl = clone.querySelector('.stage-fields');
        fieldsEl.id = `stage-fields-${stageId}`;
        fieldsEl.textContent = `Links: ${linksCount} Attachments: ${attachmentsCount} Related Fields: ${relatedCount}`;

        clone.querySelector('.edit-btn').setAttribute('onclick', `edit_stage(${stageId})`);
        clone.querySelector('.delete-btn').setAttribute('onclick', `delete_stage(${stageId})`);

        document.getElementById('stage-list').appendChild(clone);

        toggleEmptyStageText();

        payload.ID = stageId;
        stages.push(payload);
    }

    function showError(message) {
        const errorMessage = document.getElementById('stage-error-message');
        if (errorMessage) {
            errorMessage.innerText = 'Error: ' + message;
            errorMessage.style.display = 'block';
        }
    }
    
    function hideError() {
        const errorMessage = document.getElementById('stage-error-message');
        if (errorMessage) errorMessage.style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateSaveButtonUI();
        toggleEmptyStageText();

        const currentPostType = 'journeys'; // Hardcoded since this is the Journeys admin page
        
        const apiNonce = window.wpApiSettings ? window.wpApiSettings.nonce : (window.wpApiShare ? window.wpApiShare.nonce : '');
        const apiRoot = window.wpApiSettings ? window.wpApiSettings.root : (window.wpApiShare ? window.wpApiShare.root : '/wp-json/');

        if (window.DtWebComponents && window.DtWebComponents.ComponentService) {
            const service = new window.DtWebComponents.ComponentService(
                currentPostType,
                journeyId,
                apiNonce,
                apiRoot
            );
            
            service.initialize();
            
            window.componentService = service;
        }

        const stageForm = document.getElementById('stage-form');
        if (stageForm) {
            stageForm.addEventListener('change', function(e) {
                if (window.componentService && window.componentService.postId === 0) {
                    e.stopImmediatePropagation();
                }
            }, true);
        }

    });
</script>

<template id="stage-row-template">
    <li data-id="" style="border: 1px solid #ccc; padding: 1em; margin: 1em 0; display: flex; justify-content: space-between; background: #fff;">
        <div class="item-details">
            <div class="stage-order">
                <dt-icon class="drag-handle" icon="mdi:reorder-horizontal"></dt-icon>
            </div>
            <div class="stage-data">
                <div class="stage-name" class="stage-name"></div>
                <div class="stage-description" class="stage-description"></div>
                <div class="stage-fields" class="stage-fields"></div>
            </div>
        </div>
        <div class="stage-actions">
            <button class="icon-btn edit-btn"><dt-icon icon="mdi:edit"></dt-icon></button>
            <button class="icon-btn delete-btn"><dt-icon icon="mdi:delete"></dt-icon></button>
        </div>
    </li>
</template>

<?php
// Load the Disciple.Tools footer
get_footer();


?>
