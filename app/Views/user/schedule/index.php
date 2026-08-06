<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Schedule</h1>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3>Add a task</h3>
  <?php if (empty($projects)): ?>
    <p class="help-text">Create a project first before scheduling tasks.</p>
  <?php else: ?>
    <form method="post" action="/app/schedule" class="form-row" style="align-items:end;grid-template-columns:2fr 1fr 1fr 1fr auto;">
      <?= Csrf::field() ?>
      <div class="form-group" style="margin:0;">
        <label>Task</label>
        <input type="text" name="title" required placeholder="e.g. Electrical rough-in">
      </div>
      <div class="form-group" style="margin:0;">
        <label>Project</label>
        <select name="project_id" required>
          <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= View::e($p['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;"><label>Start</label><input type="date" name="start_date"></div>
      <div class="form-group" style="margin:0;"><label>End</label><input type="date" name="end_date"></div>
      <button type="submit" class="btn btn-primary">Add</button>
    </form>
  <?php endif; ?>
</div>

<?php if (empty($tasks)): ?>
  <div class="card empty-state">
    <div class="icon">📅</div>
    <h3>No tasks scheduled</h3>
    <p>Add tasks above to start building your project timeline.</p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Task</th><th>Project</th><th>Start</th><th>End</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tasks as $t): ?>
      <tr>
        <td><?= View::e($t['title']) ?></td>
        <td><a href="/app/projects/<?= $t['project_id'] ?>"><?= View::e($t['project_name']) ?></a></td>
        <td><?= View::e($t['start_date']) ?></td>
        <td><?= View::e($t['end_date']) ?></td>
        <td>
          <form method="post" action="/app/schedule/<?= $t['id'] ?>/status" style="display:inline;">
            <?= Csrf::field() ?>
            <select name="status" onchange="this.form.submit()" class="badge badge-<?= $t['status']==='done'?'green':($t['status']==='in_progress'?'yellow':'gray') ?>" style="border:none;padding:4px 8px;">
              <option value="pending" <?= $t['status']==='pending'?'selected':'' ?>>Pending</option>
              <option value="in_progress" <?= $t['status']==='in_progress'?'selected':'' ?>>In Progress</option>
              <option value="done" <?= $t['status']==='done'?'selected':'' ?>>Done</option>
            </select>
          </form>
        </td>
        <td>
          <form method="post" action="/app/schedule/<?= $t['id'] ?>/delete" onsubmit="return confirm('Remove this task?');">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-light">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
