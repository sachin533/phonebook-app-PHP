<?php
/**
 * inc/form.php - the shared create / edit form.
 *
 * Expects:
 *   $action      string  the URL the form posts to
 *   $d           array   the current values, keyed by field name
 *   $errors      array   validation messages, keyed by field name
 *   $submitLabel string  the text on the submit button
 */
declare(strict_types=1);

$action      = $action      ?? '/new.php';
$errors      = $errors      ?? [];
$d           = $d           ?? [];
$submitLabel = $submitLabel ?? 'Save application';
$statusValue = (string) ($d['status'] ?? 'Applied');

/** Print the error message for a field, if there is one. */
$fieldError = static function (string $name) use ($errors): string {
    if (!isset($errors[$name])) {
        return '';
    }
    return '<p class="field-error" id="err-' . e($name) . '">' . e((string) $errors[$name]) . '</p>';
};

/** The CSS class for a field that failed validation. */
$fieldClass = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? 'form-control invalid' : 'form-control';
};

/** The extra attributes for a field that failed validation. */
$fieldAttrs = static function (string $name) use ($errors): string {
    if (!isset($errors[$name])) {
        return '';
    }
    return ' aria-invalid="true" aria-describedby="err-' . e($name) . '"';
};
?>
<form method="post" action="<?= e($action) ?>" novalidate class="card form-card">
  <?= csrf_field() ?>

  <div class="form-grid">
    <div class="form-group">
      <label for="company">Company name <span class="req" aria-hidden="true">*</span></label>
      <input type="text" id="company" name="company" class="<?= $fieldClass('company') ?>"
             value="<?= e((string) ($d['company'] ?? '')) ?>"
             maxlength="100" placeholder="e.g. Acme Labs" required<?= $fieldAttrs('company') ?>>
      <?= $fieldError('company') ?>
    </div>

    <div class="form-group">
      <label for="applied_on">Application date <span class="req" aria-hidden="true">*</span></label>
      <input type="date" id="applied_on" name="applied_on" class="<?= $fieldClass('applied_on') ?>"
             value="<?= e((string) ($d['applied_on'] ?? '')) ?>" required<?= $fieldAttrs('applied_on') ?>>
      <?php if (!isset($errors['applied_on'])): ?>
        <p class="field-hint">Required for every status except Wishlist.</p>
      <?php endif; ?>
      <?= $fieldError('applied_on') ?>
    </div>

    <div class="form-group">
      <label for="role">Role / position <span class="req" aria-hidden="true">*</span></label>
      <input type="text" id="role" name="role" class="<?= $fieldClass('role') ?>"
             value="<?= e((string) ($d['role'] ?? '')) ?>"
             maxlength="100" placeholder="e.g. Software Engineer" required<?= $fieldAttrs('role') ?>>
      <?= $fieldError('role') ?>
    </div>

    <div class="form-group">
      <label for="location">Location</label>
      <input type="text" id="location" name="location" class="<?= $fieldClass('location') ?>"
             value="<?= e((string) ($d['location'] ?? '')) ?>"
             maxlength="120" placeholder="e.g. Bengaluru (Optional)"<?= $fieldAttrs('location') ?>>
      <?= $fieldError('location') ?>
    </div>

    <div class="form-group">
      <label for="status">Status <span class="req" aria-hidden="true">*</span></label>
      <select id="status" name="status" class="<?= $fieldClass('status') ?>" required<?= $fieldAttrs('status') ?>>
        <?php foreach (STATUSES as $option): ?>
          <option value="<?= e($option) ?>"<?= $statusValue === $option ? ' selected' : '' ?>><?= e($option) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $fieldError('status') ?>
    </div>

    <div class="form-group">
      <label for="salary">Salary</label>
      <input type="text" id="salary" name="salary" class="<?= $fieldClass('salary') ?>"
             value="<?= e((string) ($d['salary'] ?? '')) ?>"
             maxlength="50" placeholder="e.g. 12 LPA (Optional)"<?= $fieldAttrs('salary') ?>>
      <?= $fieldError('salary') ?>
    </div>

    <div class="form-group form-group-wide">
      <label for="job_url">Job URL</label>
      <input type="url" id="job_url" name="job_url" class="<?= $fieldClass('job_url') ?>"
             value="<?= e((string) ($d['job_url'] ?? '')) ?>"
             maxlength="500" placeholder="https://"<?= $fieldAttrs('job_url') ?>>
      <?= $fieldError('job_url') ?>
    </div>

    <div class="form-group form-group-wide">
      <label for="notes">Notes</label>
      <textarea id="notes" name="notes" class="<?= $fieldClass('notes') ?>"
                rows="5" maxlength="1000"
                placeholder="Add any additional notes..."<?= $fieldAttrs('notes') ?>><?= e((string) ($d['notes'] ?? '')) ?></textarea>
      <?= $fieldError('notes') ?>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary"><?= e($submitLabel) ?></button>
    <a class="btn btn-secondary" href="/index.php">Cancel</a>
  </div>
</form>
