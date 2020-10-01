<script src="<?= asset_url('assets/js/backend_soapreport.js') ?>"></script>
<script>

    $(document).ready(function() {
        BackendSOAPReport.initialize();
    });
</script>
<div id="soap_report" class="modal fade">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3 class="modal-title"><?= lang('soap_report') ?></h3>
            </div>
            <div class="modal-body">
                <div class="modal-message alert hidden"></div>

                <form id="soap_report_form">
                    <fieldset >
                        <input id="id_pets" type="hidden" class="form-control">
                        <input id="id" type="hidden" class="form-control">

                        <div class="form-group">
                            <label for="date" class="control-label"><?= lang('date') ?></label>
                            <input id="date" class="form-control required" autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label for="subjective" class="control-label"><?= lang('subjective') ?></label>
                            <textarea id="subjective" rows="3" class="form-control" autocomplete="off" placeholder="<?= lang('subjective_desc') ?>"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="objective" class="control-label"><?= lang('objective') ?></label>
                            <textarea id="objective" rows="3" class="form-control" autocomplete="off" placeholder="<?= lang('objective_desc') ?>"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="assessment" class="control-label"><?= lang('assessment') ?></label>
                            <textarea id="assessment" rows="3" class="form-control" autocomplete="off" placeholder="<?= lang('assessment_desc') ?>"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="plan" class="control-label"><?= lang('plan') ?></label>
                            <textarea id="plan" rows="3" class="form-control" autocomplete="off" placeholder="<?= lang('plan_desc') ?>"></textarea>
                        </div>

                    </fieldset>

                    <div>
                        <button type="button" class="edit-report btn btn-primary">
                            <span class="glyphicon glyphicon-pencil"></span>
                            <?= lang('edit') ?>
                        </button>
                        <button type="button" class="add-report btn btn-primary">
                            <span class="glyphicon glyphicon-plus"></span>
                            <?= lang('add') ?>
                        </button>                        
                        <button type="button" class="cancel-report btn btn-primary">
                            <?= lang('cancel') ?>
                        </button>
                    </div>

                </form>

                <br>

                <table class="reports table table-striped">
                    <thead>
                        <tr>
                            <th><?= lang('date') ?></th>
                            <th><?= lang('subjective') ?></th>
                            <th><?= lang('objective') ?></th>
                            <th><?= lang('assessment') ?></th>
                            <th><?= lang('plan') ?></th>
                            <th><?= lang('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody><!-- Dynamic Content --></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button id="cancel-soap" class="cancel-soap btn btn-default"><?= lang('close') ?></button>
            </div>
        </div>
    </div>
</div>
