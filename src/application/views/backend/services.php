<script src="<?= asset_url('assets/js/backend_services_helper.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_categories_helper.js') ?>"></script>
<script src="<?= asset_url('assets/js/backend_services.js') ?>"></script>
<script>
    var GlobalVariables = {
        csrfToken     : <?= json_encode($this->security->get_csrf_hash()) ?>,
        baseUrl       : <?= json_encode($base_url) ?>,
        dateFormat    : <?= json_encode($date_format) ?>,
        timeFormat    : <?= json_encode($time_format) ?>,
        services      : <?= json_encode($services) ?>,
        categories    : <?= json_encode($categories) ?>,
        user          : {
            id        : <?= $user_id ?>,
            email     : <?= json_encode($user_email) ?>,
            role_slug : <?= json_encode($role_slug) ?>,
            privileges: <?= json_encode($privileges) ?>
        }
    };

    $(document).ready(function() {
        BackendServices.initialize(true);
    });
</script>

<div id="services-page" class="container-fluid backend-page">
    <ul class="nav nav-tabs" role="tablist">
        <li role="presentation" class="active"><a href="#services" aria-controls="services" role="tab" data-toggle="tab"><?= lang('services') ?></a></li>
        <li role="presentation"><a href="#categories" aria-controls="categories" role="tab" data-toggle="tab"><?= lang('categories') ?></a></li>
    </ul>

    <div class="tab-content">

        <!-- SERVICES TAB -->

        <div role="tabpanel" class="tab-pane active" id="services">
            <div class="row">
                <div id="filter-services" class="filter-records column col-xs-12 col-sm-5">
                    <form>
                        <div class="input-group">
                            <input type="text" class="key form-control">

                            <span class="input-group-addon">
                        <div>
                            <button class="filter btn btn-default" type="submit" title="<?= lang('filter') ?>">
                                <span class="glyphicon glyphicon-search"></span>
                            </button>
                            <button class="clear btn btn-default" type="button" title="<?= lang('clear') ?>">
                                <span class="glyphicon glyphicon-repeat"></span>
                            </button>
                        </div>
                    </span>
                        </div>
                    </form>

                    <h3><?= lang('services') ?></h3>
                    <div class="results"></div>
                </div>

                <div class="record-details column col-xs-12 col-sm-5">
                    <div class="btn-toolbar">
                        <div class="add-edit-delete-group btn-group">
                            <button id="add-service" class="btn btn-primary">
                                <span class="glyphicon glyphicon-plus"></span>
                                <?= lang('add') ?>
                            </button>
                            <button id="edit-service" class="btn btn-default" disabled="disabled">
                                <span class="glyphicon glyphicon-pencil"></span>
                                <?= lang('edit') ?>
                            </button>
                            <button id="delete-service" class="btn btn-default" disabled="disabled">
                                <span class="glyphicon glyphicon-remove"></span>
                                <?= lang('delete') ?>
                            </button>
                        </div>

                        <div class="save-cancel-group btn-group" style="display:none;">
                            <button id="save-service" class="btn btn-primary">
                                <span class="glyphicon glyphicon-ok"></span>
                                <?= lang('save') ?>
                            </button>
                            <button id="cancel-service" class="btn btn-default">
                                <span class="glyphicon glyphicon-ban-circle"></span>
                                <?= lang('cancel') ?>
                            </button>
                        </div>
                    </div>

                    <h3><?= lang('details') ?></h3>

                    <div class="form-message alert" style="display:none;"></div>

                    <input type="hidden" id="service-id">

                    <div class="form-group">
                        <label for="service-name"><?= lang('name') ?> *</label>
                        <input id="service-name" class="form-control required" maxlength="128">
                    </div>

                    <div class="form-group">
                        <label for="service-color"><?= lang('calendar_color') ?> *</label>
                        <input id="service-color" type="color" class="form-control required">
                    </div>
                    
                    <div class="form-group">
                        <label for="service-duration"><?= lang('duration_minutes') ?> *</label>
                        <input id="service-duration" class="form-control required" type="number" min="15">
                    </div>

                    <div class="form-group">
                        <label for="service-price"><?= lang('price') ?> *</label>
                        <input id="service-price" class="form-control required">
                    </div>

                    <div class="form-group">
                        <label for="service-currency"><?= lang('currency') ?></label>
                        <br>
                        <small>Note: Use {price} for substition</small>
                        <input id="service-currency" class="form-control" maxlength="32">
                    </div>

                    <div class="form-group">
                        <label for="service-default-provider"><?= lang('default_provider') ?></label>
                        <select id="service-default-provider" class="form-control" >
                            <?php
                                echo '<option value="">[Not Set]</option>';
                                foreach($providers as $provider) {
                                    $service_ids = json_encode($provider['services']);
                                    echo '<option value="' . $provider['id'] . '" data-services=\'' . $service_ids . '\'>' . $provider['first_name'] . ' ' . $provider['last_name'] . '</option>';
                                }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="service-category"><?= lang('category') ?></label>
                        <select id="service-category" class="form-control"></select>
                    </div>

                    <div class="form-group">
                        <label for="service-availabilities-type"><?= lang('availabilities_type') ?></label>
                        <select id="service-availabilities-type" class="form-control">
                            <option value="<?= AVAILABILITIES_TYPE_FLEXIBLE ?>">
                                <?= lang('flexible') ?>
                            </option>
                            <option value="<?= AVAILABILITIES_TYPE_FIXED ?>">
                                <?= lang('fixed') ?>
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="pets_option"><?= lang('pets_option') ?> *</label>
                        <select id="pets_option" class="form-control" >
                            <?php
                                $this->load->model('settings_model');
                                $pet_natures = json_decode($this->settings_model->get_setting('pet_appointments'));

                                foreach($pet_natures as $id => $name) {
                                    echo '<option value="' . $id . '">' . $name . '</option>';
                                }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="service-description"><?= lang('description') ?></label>
                        <textarea id="service-description" rows="4" class="form-control"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="service-disc_num_of_apps_before"><?= lang('disc_num_of_apps_before') ?> *</label>
                        <input id="service-disc_num_of_apps_before" class="form-control required" type="number">
                    </div>

                    <div class="form-group">
                        <label for="service-disc_timeframe_days"><?= lang('disc_timeframe_days') ?> *</label>
                        <input id="service-disc_timeframe_days" class="form-control required" type="number">
                    </div>

                    <div class="form-group">
                        <label for="service-link"><?= lang('direct_book_link') ?></label>
                        <a id="service-link" class="form-control"></a>
                    </div>

                    <h4><?= lang('email_first_appointment') ?></h4>

                    <div class="form-group">
                        <label for="email_first_appointment_subject"><?= lang('subject') ?></label>
                        <input id="email_first_appointment_subject" data-field="email_first_appointment_subject" class="form-control">
                    </div>

                    <div class="form-group">
                        <textarea id="email_first_appointment" cols="30" rows="10" class="form-control show-replaced-template"></textarea>
                    </div>

                    <p id="form-message" class="text-danger">
                        <em><?= lang('fields_are_required') ?></em>
                    </p>
                </div>
            </div>
        </div>

        <!-- CATEGORIES TAB -->

        <div role="tabpanel" class="tab-pane" id="categories">
            <div class="row">
                <div id="filter-categories" class="filter-records column col-xs-12 col-sm-5">
                    <form class="input-append">
                        <div class="input-group">
                            <input type="text" class="key form-control">

                            <span class="input-group-addon">
                        <div>
                            <button class="filter btn btn-default" type="submit" title="<?= lang('filter') ?>">
                                <span class="glyphicon glyphicon-search"></span>
                            </button>
                            <button class="clear btn btn-default" type="button" title="<?= lang('clear') ?>">
                                <span class="glyphicon glyphicon-repeat"></span>
                            </button>
                        </div>
                    </span>
                        </div>
                    </form>

                    <h3><?= lang('categories') ?></h3>
                    <div class="results"></div>
                </div>

                <div class="record-details col-xs-12 col-sm-5">
                    <div class="btn-toolbar">
                        <div class="add-edit-delete-group btn-group">
                            <button id="add-category" class="btn btn-primary">
                                <span class="glyphicon glyphicon-plus glyphicon glyphicon-white"></span>
                                <?= lang('add') ?>
                            </button>
                            <button id="edit-category" class="btn btn-default" disabled="disabled">
                                <span class="glyphicon glyphicon-pencil"></span>
                                <?= lang('edit') ?>
                            </button>
                            <button id="delete-category" class="btn btn-default" disabled="disabled">
                                <span class="glyphicon glyphicon-remove"></span>
                                <?= lang('delete') ?>
                            </button>
                        </div>

                        <div class="save-cancel-group btn-group" style="display:none;">
                            <button id="save-category" class="btn btn-primary">
                                <span class="glyphicon glyphicon-ok glyphicon glyphicon-white"></span>
                                <?= lang('save') ?>
                            </button>
                            <button id="cancel-category" class="btn btn-default">
                                <span class="glyphicon glyphicon-ban-circle"></span>
                                <?= lang('cancel') ?>
                            </button>
                        </div>
                    </div>

                    <h3><?= lang('details') ?></h3>

                    <div class="form-message alert" style="display:none;"></div>

                    <input type="hidden" id="category-id">

                    <div class="form-group">
                        <label for="category-name"><?= lang('name') ?> *</label>
                        <input id="category-name" class="form-control required">
                    </div>

                    <div class="form-group">
                        <label for="category-description"><?= lang('description') ?></label>
                        <textarea id="category-description" rows="4" class="form-control"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
