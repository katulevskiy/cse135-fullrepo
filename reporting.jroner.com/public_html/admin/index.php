<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireRole('super_admin');

$db = getDb();
$users = $db->query("
    SELECT u.id, u.username, u.role, u.created_at,
           GROUP_CONCAT(s.section ORDER BY s.section SEPARATOR ',') AS sections
    FROM users u
    LEFT JOIN analyst_sections s ON s.user_id = u.id
    GROUP BY u.id
    ORDER BY u.id
")->fetch_all(MYSQLI_ASSOC);

$msg = $_GET['msg'] ?? '';

pageHead('User Management');
renderNav('admin');
?>
<div class="content">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
        <div>
            <h1>User Management</h1>
            <p class="page-sub">Manage user accounts and section permissions</p>
        </div>
        <a href="/admin/user_form.php" class="btn btn-primary">+ Add User</a>
    </div>

    <?php if ($msg === 'created'): ?>
        <div class="alert alert-success">User created successfully.</div>
    <?php elseif ($msg === 'updated'): ?>
        <div class="alert alert-success">User updated successfully.</div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="alert alert-success">User deleted.</div>
    <?php endif; ?>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Sections</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td style="color:#5a7090"><?= $u['id'] ?></td>
                    <td style="font-weight:500;color:#f0f4ff"><?= htmlspecialchars($u['username']) ?></td>
                    <td><span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
                    <td>
                        <?php if ($u['sections']): ?>
                            <?php foreach (explode(',', $u['sections']) as $sec): ?>
                                <span class="badge badge-<?= $sec ?>" style="margin-right:4px"><?= $sec ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="color:#5a7090">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#5a7090;font-size:12px"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <a href="/admin/user_form.php?id=<?= $u['id'] ?>" class="btn btn-secondary" style="padding:5px 12px;font-size:12px">Edit</a>
                        <?php $currentUser = getCurrentUser(); if ($u['id'] != $currentUser['id']): ?>
                        <form method="POST" action="/admin/user_delete.php" style="display:inline"
                              onsubmit="return confirm('Delete user <?= htmlspecialchars($u['username']) ?>?')">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding:5px 12px;font-size:12px">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
