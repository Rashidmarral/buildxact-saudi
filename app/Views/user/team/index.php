<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head">
  <h1>Team</h1>
</div>

<?php if (Auth::isCompanyOwner()): ?>
<div class="card" style="margin-bottom:24px;">
  <h3>Invite a team member</h3>
  <form method="post" action="/app/team" class="form-row" style="align-items:end;grid-template-columns:1fr 1fr 1fr auto;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;"><label>Name</label><input type="text" name="name" required></div>
    <div class="form-group" style="margin:0;"><label>Email</label><input type="email" name="email" required></div>
    <div class="form-group" style="margin:0;">
      <label>Role</label>
      <select name="role">
        <option value="staff">Staff</option>
        <option value="manager">Project Manager</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Invite</button>
  </form>
</div>
<?php endif; ?>

<table class="data">
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($members as $m): ?>
    <tr>
      <td><?= View::e($m['name']) ?></td>
      <td><?= View::e($m['email']) ?></td>
      <td><span class="badge badge-blue"><?= View::e(ucfirst($m['role'])) ?></span></td>
      <td><span class="badge badge-green"><?= View::e($m['status']) ?></span></td>
      <td>
        <?php if (Auth::isCompanyOwner() && (int)$m['id'] !== (int)Auth::user()['id']): ?>
        <form method="post" action="/app/team/<?= $m['id'] ?>/delete" onsubmit="return confirm('Remove this team member?');">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-light">Remove</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
