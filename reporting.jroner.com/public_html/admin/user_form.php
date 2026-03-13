<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireRole('super_admin');

$db = getDb();
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $editId > 0;
$errors = [];
$success = false;

$editUser = null;
$editSections = [];
if ($isEdit) {
    $stmt = $db->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
    if (!$editUser) {
        header('Location: /admin/index.php');
        exit;
    }
    $sr = $db->prepare("SELECT section FROM analyst_sections WHERE user_id = ?");
    $sr->bind_param("i", $editId);
    $sr->execute();
    foreach ($sr->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $editSections[] = $row['section'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = $_POST['role'] ?? '';
    $sections = $_POST['sections'] ?? [];
    $validRoles    = ['super_admin', 'analyst', 'viewer'];
    $validSections = ['visitor', 'performance', 'behavioral'];

    if (!$username) $errors[] = 'Username is required.';
    if (!$isEdit && !$password) $errors[] = 'Password is required for new users.';
    if (!in_array($role, $validRoles)) $errors[] = 'Invalid role.';

    $sections = array_filter($sections, fn($s) => in_array($s, $validSections));

    if (!$errors) {
        if ($isEdit) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET username=?, password_hash=?, role=? WHERE id=?");
                $stmt->bind_param("sssi", $username, $hash, $role, $editId);
            } else {
                $stmt = $db->prepare("UPDATE users SET username=?, role=? WHERE id=?");
                $stmt->bind_param("ssi", $username, $role, $editId);
            }
            $stmt->execute();
            if ($db->errno) $errors[] = 'DB error: ' . $db->error;
            else {
                $db->query("DELETE FROM analyst_sections WHERE user_id = $editId");
                foreach ($sections as $sec) {
                    $s = $db->prepare("INSERT IGNORE INTO analyst_sections (user_id, section) VALUES (?, ?)");
                    $s->bind_param("is", $editId, $sec);
                    $s->execute();
                }
                header('Location: /admin/index.php?msg=updated');
                exit;
            }
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?,?,?)");
            $stmt->bind_param("sss", $username, $hash, $role);
            $stmt->execute();
            if ($db->errno) {
                $errors[] = 'Username already taken or DB error.';
            } else {
                $newId = $db->insert_id;
                foreach ($sections as $sec) {
                    $s = $db->prepare("INSERT IGNORE INTO analyst_sections (user_id, section) VALUES (?, ?)");
                    $s->bind_param("is", $newId, $sec);
                    $s->execute();
                }
                header('Location: /admin/index.php?msg=created');
                exit;
            }
        }
    }

    $editUser = $editUser ?: [];
    $editUser['username'] = $username;
    $editUser['role'] = $role;
    $editSections = $sections;
}

pageHead($isEdit ? 'Edit User' : 'Create User');
renderNav('admin');
?>
<div class="content" style="max-width:600px">
    <div style="margin-bottom:24px">
        <a href="/admin/index.php" style="color:#7a8fa6;font-size:13px;text-decoration:none">← Back to Users</a>
        <h1 style="margin-top:8px"><?= $isEdit ? 'Edit User' : 'Create User' ?></h1>
        <p class="page-sub"><?= $isEdit ? 'Update account details and permissions' : 'Add a new user account' ?></p>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="card">
        <form method="POST">
            <div style="margin-bottom:18px">
                <label for="username" style="display:block;font-size:12px;font-weight:600;color:#7a8fa6;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Username</label>
                <input type="text" id="username" name="username" required
                       value="<?= htmlspecialchars($editUser['username'] ?? '') ?>"
                       style="width:100%;background:#0f1623;border:1px solid #2a3448;border-radius:6px;padding:10px 13px;font-size:14px;color:#e2e8f0;outline:none">
            </div>

            <div style="margin-bottom:18px">
                <label for="password" style="display:block;font-size:12px;font-weight:600;color:#7a8fa6;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">
                    Password <?= $isEdit ? '<span style="color:#5a7090;font-weight:400">(leave blank to keep current)</span>' : '' ?>
                </label>
                <input type="password" id="password" name="password" <?= $isEdit ? '' : 'required' ?>
                       style="width:100%;background:#0f1623;border:1px solid #2a3448;border-radius:6px;padding:10px 13px;font-size:14px;color:#e2e8f0;outline:none">
            </div>

            <div style="margin-bottom:18px">
                <label for="role" style="display:block;font-size:12px;font-weight:600;color:#7a8fa6;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Role</label>
                <select id="role" name="role" onchange="toggleSections(this.value)"
                        style="width:100%;background:#0f1623;border:1px solid #2a3448;border-radius:6px;padding:10px 13px;font-size:14px;color:#e2e8f0;outline:none">
                    <?php foreach (['super_admin','analyst','viewer'] as $r): ?>
                        <option value="<?= $r ?>" <?= ($editUser['role'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="sections-row" style="margin-bottom:18px;<?= ($editUser['role'] ?? '') !== 'analyst' ? 'display:none' : '' ?>">
                <div style="font-size:12px;font-weight:600;color:#7a8fa6;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px">Section Access</div>
                <div style="display:flex;gap:16px;flex-wrap:wrap">
                    <?php foreach (['visitor','performance','behavioral'] as $sec): ?>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;color:#c8d6e8">
                        <input type="checkbox" name="sections[]" value="<?= $sec ?>"
                               <?= in_array($sec, $editSections) ? 'checked' : '' ?>
                               style="accent-color:#4f8ef7;width:15px;height:15px">
                        <?= ucfirst($sec) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:flex;gap:12px;margin-top:24px">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create User' ?></button>
                <a href="/admin/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSections(role) {
    document.getElementById('sections-row').style.display = role === 'analyst' ? 'block' : 'none';
}
</script>
</body>
</html>
