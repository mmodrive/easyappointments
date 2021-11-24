<link rel="stylesheet" type="text/css" href="<?= asset_url('/assets/ext/jquery-fullcalendar/main.css') ?>">

<script src="<?= asset_url('assets/ext/moment/moment.min.js') ?>"></script>
<script src="<?= asset_url('assets/ext/jquery-fullcalendar/main.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.3.0/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/moment@5.3.0/main.global.min.js"></script>
<script src="<?= asset_url('assets/ext/jquery-sticky-table-headers/jquery.stickytableheaders.min.js') ?>"></script>
<script src="<?= asset_url('assets/ext/jquery-ui/jquery-ui-timepicker-addon.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar_default_view.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar_table_view.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar_google_sync.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar_appointments_modal.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar_unavailabilities_modal.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_calendar_api.js') ?>"></script>
<script>
    var GlobalVariables = {
        'csrfToken'             : <?= json_encode($this->security->get_csrf_hash()) ?>,
        'availableProviders'    : <?= json_encode($available_providers) ?>,
        'availableServices'     : <?= json_encode($available_services) ?>,
        'baseUrl'               : <?= json_encode($base_url) ?>,
        'bookAdvanceTimeout'    : <?= $book_advance_timeout ?>,
        'dateFormat'            : <?= json_encode($date_format) ?>,
        'timeFormat'            : <?= json_encode($time_format) ?>,
        'editAppointment'       : <?= json_encode($edit_appointment) ?>,
        'customers'             : <?= json_encode($customers) ?>,
        'secretaryProviders'    : <?= json_encode($secretary_providers) ?>,
        'calendarView'          : <?= json_encode($calendar_view) ?>,
        'calendarSelections'          : <?= json_encode($calendar_selections) ?>,
        'user'                  : {
            'id'        : <?= $user_id ?>,
            'email'     : <?= json_encode($user_email) ?>,
            'role_slug' : <?= json_encode($role_slug) ?>,
            'privileges': <?= json_encode($privileges) ?>
        }
    };

    $(document).ready(function() {
        BackendCalendar.initialize(GlobalVariables.calendarView);
    });
</script>

<div id="calendar-page" class="container-fluid">
    <div id="calendar-toolbar">
        <div id="calendar-filter" class="form-inline col-xs-12 col-sm-7">
            <div class="form-group">
                <label for="select-filter-item"><?= lang('display_calendar') ?></label>
                <select id="select-filter-item" class="form-control" title="<?= lang('select_filter_item_hint') ?>">
                </select>
            </div>

            <div class="form-group" style="display: none;">
                <select id="select-filter-item-additional" multiple class="form-control" title="<?= lang('select_filter_item_hint') ?>">
                </select>
            </div>
        </div>

        <div id="calendar-actions" class="col-xs-12 col-sm-5">
            <input id="date-selector" class="btn btn-default" style="z-index: 100; position: relative;" maxlength="10" size="10">

            <select id="calendar_view" class="btn btn-default">
                <option value="timeGridDay"><?= lang('day') ?></option>
                <option value="timeGridWeek"><?= lang('week') ?></option>
                <option value="dayGridMonth"><?= lang('month') ?></option>
            </select>

            <?php if (($role_slug == DB_SLUG_ADMIN || $role_slug == DB_SLUG_PROVIDER)
                    && Config::GOOGLE_SYNC_FEATURE == TRUE): ?>
                <button id="google-sync" class="btn btn-primary"
                        title="<?= lang('trigger_google_sync_hint') ?>">
                    <span class="glyphicon glyphicon-refresh"></span>
                    <span><?= lang('synchronize') ?></span>
                </button>

                <button id="enable-sync" class="btn btn-default" data-toggle="button"
                        title="<?= lang('enable_appointment_sync_hint') ?>">
                    <span class="glyphicon glyphicon-calendar"></span>
                    <span><?= lang('enable_sync') ?></span>
                </button>
            <?php endif ?>

            <?php if ($privileges[PRIV_APPOINTMENTS]['add'] == TRUE): ?>
                <button id="insert-appointment" class="btn btn-default" title="<?= lang('new_appointment_hint') ?>">
                    <span class="glyphicon glyphicon-plus"></span>
                    <?= lang('appointment') ?>
                </button>

                <button id="insert-unavailable" class="btn btn-default" title="<?= lang('unavailable_periods_hint') ?>">
                    <span class="glyphicon glyphicon-plus"></span>
                    <?= lang('unavailable') ?>
                </button>
            <?php endif ?>

            <button id="reload-appointments" class="btn btn-default" title="<?= lang('reload_appointments_hint') ?>">
                <span class="glyphicon glyphicon-repeat"></span>
                <?= lang('reload') ?>
            </button>

            <button id="toggle-fullscreen" class="btn btn-default">
                <span class="glyphicon glyphicon-fullscreen"></span>
            </button>
        </div>
    </div>

    <div id="calendars" class="row">
        <div id="calendar" class="calendar col-sm-12"><!-- Dynamically Generated Content --></div>
    </div>
</div>

<!-- MANAGE APPOINTMENT MODAL -->

<div id="manage-appointment" class="modal fade" data-keyboard="true" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3 class="modal-title"><?= lang('edit_appointment_title') ?></h3>
            </div>

            <div class="modal-body">
                <div class="modal-message alert hidden"></div>

                <form>
                    <fieldset>
                        <legend><?= lang('appointment_details_title') ?></legend>

                        <input id="appointment-id" type="hidden">

                        <div class="row">
                            <div class="col-xs-12 col-sm-6">
                                <div class="form-group">
                                    <label for="select-service" class="control-label"><?= lang('service') ?> *</label>
                                    <select id="select-service" class="required form-control">
                                        <?php
                                        // Group services by category, only if there is at least one service
                                        // with a parent category.
                                        $has_category = FALSE;
                                        foreach($available_services as $service) {
                                            if ($service['category_id'] != NULL) {
                                                $has_category = TRUE;
                                                break;
                                            }
                                        }

                                        echo '<option value=""></option>';

                                        if ($has_category) {
                                            $grouped_services = array();

                                            foreach($available_services as $service) {
                                                if ($service['category_id'] != NULL) {
                                                    if (!isset($grouped_services[$service['category_name']])) {
                                                        $grouped_services[$service['category_name']] = array();
                                                    }

                                                    $grouped_services[$service['category_name']][] = $service;
                                                }
                                            }

                                            // We need the uncategorized services at the end of the list so
                                            // we will use another iteration only for the uncategorized services.
                                            $grouped_services['uncategorized'] = array();
                                            foreach($available_services as $service) {
                                                if ($service['category_id'] == NULL) {
                                                    $grouped_services['uncategorized'][] = $service;
                                                }
                                            }

                                            foreach($grouped_services as $key => $group) {
                                                $group_label = ($key != 'uncategorized')
                                                    ? $group[0]['category_name'] : 'Uncategorized';

                                                if (count($group) > 0) {
                                                    echo '<optgroup label="' . $group_label . '">';
                                                    foreach($group as $service) {
                                                        echo '<option value="' . $service['id'] . '">'
                                                            . $service['name'] . '</option>';
                                                    }
                                                    echo '</optgroup>';
                                                }
                                            }
                                        }  else {
                                            foreach($available_services as $service) {
                                                echo '<option value="' . $service['id'] . '">'
                                                    . $service['name'] . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="select-provider" class="control-label"><?= lang('provider') ?> *</label>
                                    <select id="select-provider" class="required form-control"></select>
                                </div>
                            </div>

                            <div class="col-xs-12 col-sm-6">
                                <div class="form-group">
                                    <label for="start-datetime" class="control-label"><?= lang('start_date_time') ?></label>
                                    <input id="start-datetime" class="required form-control">
                                </div>

                                <div class="form-group">
                                    <label for="end-datetime" class="control-label"><?= lang('end_date_time') ?></label>
                                    <input id="end-datetime" class="required form-control">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xs-12 col-sm-6">
                                <div class="form-group">
                                    <label for="appointment-notes" class="control-label"><?= lang('notes') ?></label>
                                    <textarea id="appointment-notes" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-xs-12 col-sm-6">
                                <div class="form-group">
                                    <label class="appointment-discount-reset" for="appointment-discount-reset" title="<?= lang('disc_reset_title') ?>"><?= lang('disc_reset') ?></label>
                                    <input id="appointment-discount-reset" type="checkbox" class="form-control" title="<?= lang('disc_reset_title') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="appointment-discount-count" for="appointment-discount-count" title="<?= lang('disc_count') ?>"><?= lang('disc_count') ?></label>
                                    <input id="appointment-discount-count" readonly="true" class="form-control" title="<?= lang('disc_count') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="appointment-discount-last-reset" for="appointment-discount-last-reset" title="<?= lang('disc_last_reset') ?>"><?= lang('disc_last_reset') ?></label>
                                    <input id="appointment-discount-last-reset" readonly="true" class="form-control" data-title-pre="<?= lang('disc_reset_last_manual') ?>">
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <br>

                    <fieldset>
                        <legend>
                            <?= lang('customer_details_title') ?>
                            <button id="new-customer" class="btn btn-default btn-xs"
                                    title="<?= lang('clear_fields_add_existing_customer_hint') ?>"
                                    type="button"><?= lang('new') ?>
                            </button>
                            <button id="select-customer" class="btn btn-primary btn-xs"
                                    title="<?= lang('pick_existing_customer_hint') ?>"
                                    type="button"><?= lang('select') ?>
                            </button>
                            <input id="filter-existing-customers"
                                   placeholder="<?= lang('type_to_filter_customers') ?>"
                                   style="display: none;" class="input-sm form-control">
                            <div id="existing-customers-list" style="display: none;"></div>
                        </legend>

                        <input id="customer-id" type="hidden">

                        <div class="row">
                            <div class="col-xs-12 col-sm-6">
                                <div class="form-group">
                                    <label for="first-name" class="control-label"><?= lang('first_name') ?> *</label>
                                    <input id="first-name" class="required form-control">
                                </div>

                                <div class="form-group">
                                    <label for="last-name" class="control-label"><?= lang('last_name') ?> *</label>
                                    <input id="last-name" class="required form-control">
                                </div>

                                <div class="form-group">
                                    <label for="email" class="control-label"><?= lang('email') ?> *</label>
                                    <input id="email" class="required form-control">
                                </div>

                                <div class="form-group">
                                    <label for="phone-number" class="control-label"><?= lang('phone_number') ?> *</label>
                                    <input id="phone-number" class="required form-control">
                                </div>
                            </div>
                            <div class="col-xs-12 col-sm-6">
                                <div class="form-group">
                                    <label for="address" class="control-label"><?= lang('address') ?></label>
                                    <input id="address" class="form-control">
                                </div>

                                <div class="form-group">
                                    <label for="city" class="control-label"><?= lang('city') ?></label>
                                    <input id="city" class="form-control">
                                </div>

                                <div class="form-group">
                                    <label for="zip-code" class="control-label"><?= lang('zip_code') ?></label>
                                    <input id="zip-code" class="form-control">
                                </div>

                                <div class="form-group">
                                    <label for="customer-notes" class="control-label"><?= lang('user_notes') ?></label>
                                    <textarea id="customer-notes" rows="2" class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <br>
                    <fieldset>
                        <legend>
                            <?= lang('pet_details_title') ?>
                            <button id="view-soap_report" type="button" class="btn btn-default">
                                <span class="glyphicon glyphicon-book"></span>
                                <?= lang('soap_report') ?>
                            </button>
                            <div class="form-group">
                                <select id="pet_id" class="required form-control" >
                                    <option selected value="new"> Click to Select </option>
                                </select>
                            </div>
                        </legend>

                        <div class="row">
                            <div class="col-xs-12 col-sm-6">
                                <div class="form-group">
                                    <label for="pet_name" class="control-label"><?= lang('pet_name') ?> *</label>
                                    <input id="pet_name" class="required form-control" maxlength="100" />
                                </div>
                                <div class="form-group">
                                    <label for="pet_breed" class="control-label"><?= lang('pet_breed') ?> *</label>
                                    <input id="pet_breed" class="required form-control" maxlength="120" />
                                </div>
                                <div class="form-group">
                                    <label for="pet_colours" class="control-label"><?= lang('pet_colours') ?> *</label>
                                    <input id="pet_colours" class="required form-control" maxlength="250" />
                                </div>
                                <div class="form-group">
                                    <label for="pet_sex" class="control-label"><?= lang('pet_sex') ?> *</label>
                                    <select id="pet_sex" class="required form-control" >
                                        <option disabled selected value> -- select an option -- </option>
                                        <?php
                                            $this->load->model('settings_model');
                                            $pet_natures = json_decode($this->settings_model->get_setting('pet_sex'));

                                            foreach($pet_natures as $id => $name) {
                                                echo '<option value="' . $id . '">' . $name . '</option>';
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="pet_dob" class="control-label"><?= lang('pet_dob') ?> *</label>
                                    <input id="pet_dob" class="required form-control" maxlength="120" autocomplete="off" />
                                </div>
                                <div class="form-group">
                                    <label for="pet_nature" class="control-label"><?= lang('pet_nature') ?> *</label>
                                    <select id="pet_nature" class="required form-control" >
                                        <option disabled selected value> -- select an option -- </option>
                                        <?php
                                            $this->load->model('settings_model');
                                            $pet_natures = json_decode($this->settings_model->get_setting('pet_nature'));

                                            foreach($pet_natures as $id => $name) {
                                                echo '<option value="' . $id . '">' . $name . '</option>';
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-xs-12 col-sm-6">
                                <div class="form-group">
                                    <label for="pet_pathology" class="control-label"><?= lang('pet_pathology') ?></label>
                                    <input id="pet_pathology" class="form-control" maxlength="250" />
                                </div>
                                <div class="form-group">
                                    <label for="pet_age" class="control-label"><?= lang('pet_age') ?></label>
                                    <input id="pet_age" disabled class="form-control" maxlength="120" />
                                </div>
                                <div class="form-group">
                                    <label class="control-label"><?= lang('vet_details') ?></label>
                                    <input id="pet_vet_name" class="required form-control" maxlength="512" placeholder="<?= lang('name') ?> *" />
                                    <input id="pet_vet_phone" class="form-control" maxlength="128" placeholder="<?= lang('phone_number') ?>" />
                                    <input id="pet_vet_email" class="form-control" maxlength="512" placeholder="<?= lang('email') ?>" />
                                </div>
                                <div class="form-group">
                                    <label class="control-label"><?= lang('therapist_details') ?></label>
                                    <input id="pet_therapist_name" class="form-control" maxlength="512" placeholder="<?= lang('name') ?>" />
                                    <input id="pet_therapist_phone" class="form-control" maxlength="128" placeholder="<?= lang('phone_number') ?>" />
                                    <input id="pet_therapist_email" class="form-control" maxlength="512" placeholder="<?= lang('email') ?>" />
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xs-24 col-sm-12">
                                <label class="control-label"><?= lang('attachment') ?></label>
                                <div class="form-group">
                                    <form enctype="multipart/form-data">
                                        <input type="file" id="pet_attachment" class="form-control" maxlength="120" multiple />
                                    </form>
                                </div>
                                <div class="col-xs-24 col-sm-12">
                                    <div id="pet_attachments"></div>
                                </div>
                            </div>
                        </div>

                        <legend>
                            <?= lang('pet_appointment_details_title') ?>
                        </legend>

                        <div class="row">
                            <div class="col-xs-12 col-sm-6">
                                <div class="form-group">
                                    <label for="depth" class="control-label"><?= lang('depth') ?></label>
                                    <input id="depth" type="number" step="0.01" placeholder="Depth in cm" class="form-control">
                                </div>

                                <div class="form-group">
                                    <label for="speed" class="control-label"><?= lang('speed') ?></label>
                                    <input id="speed" type="number" step="0.01" placeholder="Speed in km/h" class="form-control">
                                </div>

                                <div class="form-group">
                                    <label for="time" class="control-label"><?= lang('time') ?></label>
                                    <input id="time" placeholder="Time in minutes" class="form-control" maxlength="250">
                                </div>
                            </div>
                            <div class="col-xs-12 col-sm-6">
                                <div class="form-group">
                                    <label for="observ_previous" class="control-label"><?= lang('previous').' '.lang('appointment').' '.lang('observations') ?></label>
                                    <textarea id="observ_previous" rows="3" class="form-control"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="observ_current" class="control-label"><?= lang('current').' '.lang('appointment').' '.lang('observations') ?></label>
                                    <textarea id="observ_current" rows="3" class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <legend>
                            <?= lang('pet_history_title') ?>
                        </legend>

                        <div class="row">
                            <div class="col-xs-24 col-sm-12">
                                <table id="pet_history">
                                    <thead>
                                        <tr>
                                            <th><?= lang('date') ?></th>
                                            <th><?= lang('service') ?></th>
                                            <th><?= lang('provider') ?></th>
                                            <th><?= lang('depth') ?></th>
                                            <th><?= lang('speed') ?></th>
                                            <th><?= lang('time') ?></th>
                                            <th><?= lang('previous').' '.lang('observations') ?></th>
                                            <th><?= lang('current').' '.lang('observations') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </fieldset>
                </form>
            </div>

            <div class="modal-footer">
                <button id="save-appointment" class="btn btn-primary"><?= lang('save') ?></button>
                <button id="cancel-appointment" class="btn btn-default" data-dismiss="modal"><?= lang('cancel') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- MANAGE UNAVAILABLE MODAL -->

<div id="manage-unavailable" class="modal fade">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3 class="modal-title"><?= lang('new_unavailable_title') ?></h3>
            </div>
            <div class="modal-body">
                <div class="modal-message alert hidden"></div>

                <form>
                    <fieldset>
                        <input id="unavailable-id" type="hidden">
                        
                        <div class="form-group">
                            <label for="unavailable-provider" class="control-label"><?= lang('provider') ?></label>
                            <select id="unavailable-provider" class="form-control"></select>
                        </div>

                        <div class="form-group">
                            <label for="unavailable-start" class="control-label"><?= lang('start') ?></label>
                            <input id="unavailable-start" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="unavailable-end" class="control-label"><?= lang('end') ?></label>
                            <input id="unavailable-end" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="unavailable-notes" class="control-label"><?= lang('notes') ?></label>
                            <textarea id="unavailable-notes" rows="3" class="form-control"></textarea>
                        </div>
                    </fieldset>
                </form>
            </div>
            <div class="modal-footer">
                <button id="save-unavailable" class="btn btn-primary"><?= lang('save') ?></button>
                <button id="cancel-unavailable" class="btn btn-default" data-dismiss="modal"><?= lang('cancel') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- SELECT GOOGLE CALENDAR MODAL -->

<div id="select-google-calendar" class="modal fade">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3 class="modal-title"><?= lang('select_google_calendar') ?></h3>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="google-calendar" class="control-label"><?= lang('select_google_calendar_prompt') ?></label>
                    <select id="google-calendar" class="form-control"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button id="select-calendar" class="btn btn-primary"><?= lang('select') ?></button>
                <button id="close-calendar" class="btn btn-default" data-dismiss="modal"><?= lang('close') ?></button>
            </div>
        </div>
    </div>
</div>
