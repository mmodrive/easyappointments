<script src="<?= asset_url('assets/js/backend_settings_system.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_settings_user.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_settings.js') ?>"></script>
<script src="<?= asset_url('assets/js/working_plan.js') ?>"></script>
<script src="<?= asset_url('assets/ext/jquery-ui/jquery-ui-timepicker-addon.js') ?>"></script>
<script src="<?= asset_url('assets/ext/jquery-jeditable/jquery.jeditable.min.js') ?>"></script>
<script>
    var GlobalVariables = {
        'csrfToken'     : <?= json_encode($this->security->get_csrf_hash()) ?>,
        'baseUrl'       : <?= json_encode($base_url) ?>,
        'dateFormat'    : <?= json_encode($date_format) ?>,
        'timeFormat'    : <?= json_encode($time_format) ?>,
        'userSlug'      : <?= json_encode($role_slug) ?>,
        'settings'      : {
            'system'    : <?= json_encode($system_settings) ?>,
            'user'      : <?= json_encode($user_settings) ?>
        },
        'user'          : {
            'id'        : <?= $user_id ?>,
            'email'     : <?= json_encode($user_email) ?>,
            'role_slug' : <?= json_encode($role_slug) ?>,
            'privileges': <?= json_encode($privileges) ?>
        }
    };

    $(document).ready(function() {
        BackendSettings.initialize(true);
    });
</script>

<div id="more-page" class="container-fluid">
        
    <?php $hidden = ($privileges[PRIV_APPOINTMENTS]['view'] ==  TRUE) ? '' : 'hidden' ?>
    <div class="<?= $hidden ?>">
        <a href="<?= site_url('backend/print_appointments') ?>" class="menu-item"
                title="<?= lang('print_appointment') ?>">
            <?= lang('print_appointment') ?>
        </a>
    </div>
    
    <?php $hidden = ($privileges[PRIV_USERS]['view'] ==  TRUE) ? '' : 'hidden' ?>
    <div class="<?= $hidden ?>">
        <a href="<?= site_url('backend/export_user_csv') ?>" class="menu-item"
                title="<?= lang('export_user_csv_title') ?>">
            <?= lang('export_user_csv') ?>
        </a>
    </div>

</div>
