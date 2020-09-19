<script src="<?= asset_url('assets/js/backend_calendar_api.js') ?>"></script>
<!-- <script>
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

 -->
<div id="soap_report" class="modal fade">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3 class="modal-title"><?= lang('soap_report') ?></h3>
            </div>
            <div class="modal-body">
                <div class="modal-message alert hidden"></div>

                <form>
                    <fieldset>
                        <input id="soap_report_id" type="hidden">

                        <div class="form-group">
                            <label for="date" class="control-label"><?= lang('date') ?></label>
                            <input id="date" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="subjective" class="control-label"><?= lang('subjective') ?></label>
                            <textarea id="subjective" rows="3" class="form-control" placeholder="<?= lang('subjective_desc') ?>"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="objective" class="control-label"><?= lang('objective') ?></label>
                            <textarea id="objective" rows="3" class="form-control" placeholder="<?= lang('objective_desc') ?>"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="assessment" class="control-label"><?= lang('assessment') ?></label>
                            <textarea id="assessment" rows="3" class="form-control" placeholder="<?= lang('assessment_desc') ?>"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="plan" class="control-label"><?= lang('plan') ?></label>
                            <textarea id="plan" rows="3" class="form-control" placeholder="<?= lang('plan_desc') ?>"></textarea>
                        </div>

                    </fieldset>
                </form>
            </div>
            <div class="modal-footer">
                <button id="save-soap" class="btn btn-primary"><?= lang('save') ?></button>
                <button id="cancel-soap" class="btn btn-default" data-dismiss="modal"><?= lang('cancel') ?></button>
            </div>
        </div>
    </div>
</div>
