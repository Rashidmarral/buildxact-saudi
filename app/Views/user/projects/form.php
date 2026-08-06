<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= $project ? 'Edit Project' : 'New Project' ?></h1>
  <a href="/app/projects" class="btn btn-light">← Back to projects</a>
</div>

<form method="post" action="<?= $project ? '/app/projects/' . $project['id'] : '/app/projects' ?>" class="card" style="max-width:680px;">
  <?= Csrf::field() ?>
  <div class="form-group">
    <label>Project name</label>
    <input type="text" name="name" required value="<?= View::e($project['name'] ?? '') ?>">
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>Client</label>
      <select name="client_id">
        <option value="">— No client —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (($project['client_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= View::e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Status</label>
      <?php $status = $project['status'] ?? 'planning'; ?>
      <select name="status">
        <?php foreach (['planning' => 'Planning', 'in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed'] as $val => $label): ?>
          <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Budget (SAR)</label><input type="number" step="0.01" name="budget" value="<?= View::e((string)($project['budget'] ?? '0')) ?>"></div>
    <div class="form-group"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Start date</label><input type="date" name="start_date" value="<?= View::e($project['start_date'] ?? '') ?>"></div>
    <div class="form-group"><label>End date</label><input type="date" name="end_date" value="<?= View::e($project['end_date'] ?? '') ?>"></div>
  </div>
  <div class="form-group">
    <label>Description</label>
    <textarea name="description"><?= View::e($project['description'] ?? '') ?></textarea>
  </div>
  <button type="submit" class="btn btn-primary"><?= $project ? 'Save changes' : 'Create project' ?></button>
</form>
