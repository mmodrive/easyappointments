<script src="<?= asset_url('assets/ext/jquery-ui/jquery-ui-timepicker-addon.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_customers_helper.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_customers.js') ?>"></script>
<script>
    var GlobalVariables = {
        csrfToken          : <?= json_encode($this->security->get_csrf_hash()) ?>,
        availableProviders : <?= json_encode($available_providers) ?>,
        availableServices  : <?= json_encode($available_services) ?>,
        secretaryProviders : <?= json_encode($secretary_providers) ?>,
        secretaryServices  : <?= json_encode($secretary_services) ?>,
        dateFormat         : <?= json_encode($date_format) ?>,
        timeFormat         : <?= json_encode($time_format) ?>,
        baseUrl            : <?= json_encode($base_url) ?>,
        customers          : <?= json_encode($customers) ?>,
        user               : {
            id         : <?= $user_id ?>,
            email      : <?= json_encode($user_email) ?>,
            role_slug  : <?= json_encode($role_slug) ?>,
            privileges : <?= json_encode($privileges) ?>
        }
    };

    $(document).ready(function() {
        BackendCustomers.initialize(true);
    });
</script>

<div id="customers-page" class="container-fluid backend-page">
    <div class="row">
    	<div id="filter-customers" class="filter-records column col-xs-12 col-sm-5 col-lg-2">
    		<form>
                <div class="input-group">
                    <input type="text" class="key form-control">

                    <div class="input-group-addon">
                        <div>
                            <button class="filter btn btn-default" type="submit" title="<?= lang('filter') ?>">
                                <span class="glyphicon glyphicon-search"></span>
                            </button>
                            <button class="clear btn btn-default" type="button" title="<?= lang('clear') ?>">
                                <span class="glyphicon glyphicon-repeat"></span>
                            </button>
                        </div>
                    </div>
                </div>
    		</form>

            <h3><?= lang('customers') ?></h3>
            <div class="results"></div>
    	</div>

    	<div class="record-details col-xs-12 col-sm-7 col-lg-10">
            <div class="btn-toolbar">
                <div id="add-edit-delete-group" class="btn-group">
                    <?php if ($privileges[PRIV_CUSTOMERS]['add'] === TRUE): ?>
                    <button id="add-customer" class="btn btn-primary">
                        <span class="glyphicon glyphicon-plus"></span>
                        <?= lang('add') ?>
                    </button>
                    <?php endif ?>

                    <?php if ($privileges[PRIV_CUSTOMERS]['edit'] === TRUE): ?>
                    <button id="edit-customer" class="btn btn-default" disabled="disabled">
                        <span class="glyphicon glyphicon-pencil"></span>
                        <?= lang('edit') ?>
                    </button>
                    <?php endif ?>

                    <?php if ($privileges[PRIV_CUSTOMERS]['delete'] === TRUE): ?>
                    <button id="delete-customer" class="btn btn-default" disabled="disabled">
                        <span class="glyphicon glyphicon-remove"></span>
                        <?= lang('delete') ?>
                    </button>
                    <?php endif ?>

                    <?php if ($privileges[PRIV_CUSTOMERS]['edit'] === TRUE): ?>
                    <button id="merge-customer" class="btn btn-default" disabled="disabled" title="Find the first customer, select and click Merge. Then find the second customer, select and click Merge again.">
                        <span class="glyphicon glyphicon-link"></span>
                        <?= lang('merge') ?>
                    </button>
                    <?php endif ?>
                </div>

                <div id="save-cancel-group" class="btn-group" style="display:none;">
                    <button id="save-customer" class="btn btn-primary">
                        <span class="glyphicon glyphicon-ok"></span>
                        <?= lang('save') ?>
                    </button>
                    <button id="cancel-customer" class="btn btn-default">
                        <i class="glyphicon glyphicon-ban-circle"></i>
                        <?= lang('cancel') ?>
                    </button>
                </div>
            </div>

            <input id="customer-id" type="hidden">

            <div class="row">
                <div class="col-xs-12 col-sm-4" style="margin-left: 0;">
                    <h3><?= lang('details') ?></h3>

                    <div id="form-message" class="alert" style="display:none;"></div>

                    <div class="form-group">
                        <label class="control-label" for="first-name"><?= lang('first_name') ?> *</label>
                        <input id="first-name" class="form-control required">
                    </div>

                    <div class="form-group">
                        <label class="control-label" for="last-name"><?= lang('last_name') ?> *</label>
                        <input id="last-name" class="form-control required">
                    </div>

                    <div class="form-group">
                        <label class="control-label" for="email"><?= lang('email') ?> *</label>
                        <input id="email" class="form-control required">
                    </div>

                    <div class="form-group">
                        <label class="control-label" for="phone-number"><?= lang('phone_number') ?> *</label>
                        <input id="phone-number" class="form-control required">
                    </div>

                    <div class="form-group">
                        <label class="control-label" for="address"><?= lang('address') ?></label>
                        <input id="address" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="control-label" for="city"><?= lang('city') ?></label>
                        <input id="city" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="control-label" for="zip-code"><?= lang('zip_code') ?></label>
                        <input id="zip-code" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="control-label" for="disc_qualify"><?= lang('disc_qualify') ?></label>
                        <input id="disc_qualify" type="checkbox" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="control-label" for="marketing_subscribe"><?= lang('marketing_subscribe') ?></label>
                        <input id="marketing_subscribe" type="checkbox" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="control-label" for="notes"><?= lang('user_notes') ?></label>
                        <textarea id="notes" rows="4" class="form-control"></textarea>
                    </div>

                    <p class="text-center">
                        <em id="form-message" class="text-danger"><?= lang('fields_are_required') ?></em>
                    </p>
                </div>

                <div class="col-xs-12 col-sm-8">
                    <div class="row">
                        <div class="col-xs-12 col-sm-6">
                            <h3><?= lang('appointments') ?></h3>
                            <div id="customer-appointments" class="well"></div>
                            <div id="appointment-details" class="well hidden"></div>
                        </div>

                        <div class="col-xs-12 col-sm-6">
                            <h3><?= lang('pets') ?></h3>
                            <button id="view-soap_report" class="btn btn-default" disabled="disabled">
                                <span class="glyphicon glyphicon-book"></span>
                                <?= lang('soap_report') ?>
                            </button>
                            <div id="customer-pets" class="well"></div>
                            <div id="pet-details" class="well hidden">
                                <div id="pet-summary" class="">
                                </div>
                                <div class="form-group">
                                    <input id="pet_active" type="checkbox" class="">
                                    <label class="control-label" for="pet_active"><?= lang('active') ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            <div class="row">
                <div class="col-xs-12 col-sm-12">
                    <h3><?= lang('pet_history_title') ?></h3>
                    <table id="pet_history" class="">
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
    	</div>
    </div>
</div>
