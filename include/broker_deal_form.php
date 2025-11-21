<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$dealData = is_array($dealData ?? null) ? $dealData : [];
$unitsOptions = is_array($unitsOptions ?? null) ? $unitsOptions : [];

$val = static function ($key, $default = '') use ($dealData) {
    return htmlspecialcharsbx((string)($dealData[$key] ?? $default));
};
?>

<form method="post" enctype="multipart/form-data">
    <?= bitrix_sessid_post() ?>

    <div class="card mb-3">
        <div class="card-header">
            Buyer details
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First name</label>
                    <input type="text"
                           name="BUYER_FIRST_NAME"
                           class="form-control"
                           value="<?= $val('BUYER_FIRST_NAME') ?>"
                           required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last name</label>
                    <input type="text"
                           name="BUYER_LAST_NAME"
                           class="form-control"
                           value="<?= $val('BUYER_LAST_NAME') ?>"
                           required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email"
                           name="BUYER_EMAIL"
                           class="form-control"
                           value="<?= $val('BUYER_EMAIL') ?>"
                           required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text"
                           name="BUYER_PHONE"
                           class="form-control"
                           value="<?= $val('BUYER_PHONE') ?>"
                           required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Citizenship</label>
                    <input type="text"
                           name="BUYER_CITIZENSHIP"
                           class="form-control"
                           value="<?= $val('BUYER_CITIZENSHIP') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            Unit and project
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Unit</label>
                    <select name="UNIT_ID"
                            class="form-select"
                            id="broker-deal-unit"
                            required>
                        <option value="">Select unit</option>
                        <?php foreach ($unitsOptions as $unitId => $u): ?>
                            <option value="<?= (int)$unitId ?>"
                                    data-project="<?= htmlspecialcharsbx($u['PROJECT_NAME'] ?? '') ?>"
                                    data-label="<?= htmlspecialcharsbx($u['LABEL'] ?? '') ?>"
                                    <?= ((string)$dealData['UNIT_ID'] === (string)$unitId) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialcharsbx($u['LABEL'] ?? (string)$unitId) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Project</label>
                    <input type="text"
                           class="form-control"
                           id="broker-deal-project"
                           value=""
                           readonly>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            Documents
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Passport (scan)</label>
                    <input type="file" name="DOC_PASSPORT" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">EOI</label>
                    <input type="file" name="DOC_EOI" class="form-control">
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            Register deal
        </button>
    </div>
</form>

<script>
(function() {
    var select = document.getElementById('broker-deal-unit');
    var projectInput = document.getElementById('broker-deal-project');

    if (!select || !projectInput) {
        return;
    }

    function updateProject() {
        var opt = select.options[select.selectedIndex];
        if (!opt) {
            projectInput.value = '';
            return;
        }
        var project = opt.getAttribute('data-project') || '';
        projectInput.value = project;
    }

    select.addEventListener('change', updateProject);
    updateProject();
})();
</script>
