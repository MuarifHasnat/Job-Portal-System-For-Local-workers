<?php include __DIR__.'/../partials/header.php'; ?>
<?php
$cats = $conn->query("SELECT id,name FROM service_categories ORDER BY display_order");
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
$customer_id = $_SESSION['user']['id'];
$category_id = (int)($_POST['category_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$budget = $_POST['budget'] !== '' ? (float)$_POST['budget'] : null;
$stmt = $conn->prepare("INSERT INTO service_requests (customer_id, category_id, title, description, budget) VALUES (?,?,?,?,?)");
$stmt->bind_param('iissd', $customer_id, $category_id, $title, $description, $budget);
if ($stmt->execute()) { $msg='Request posted!'; }
}
?>
<h3>Post a Job</h3>
<?php if($msg): ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<form method="post" class="row g-3">
<div class="col-md-6">
<label class="form-label">Category</label>
<select name="category_id" class="form-select" required>
<option value="">-- Select --</option>
<?php while($c=$cats->fetch_assoc()): ?>
<option value="<?=$c['id']?>"><?=$c['name']?></option>
<?php endwhile; ?>
</select>
</div>
<div class="col-md-6">
<label class="form-label">Budget (optional)</label>
<input type="number" step="0.01" name="budget" class="form-control">
</div>
<div class="col-12">
<label class="form-label">Title</label>
<input name="title" class="form-control" required>
</div>
<div class="col-12">
<label class="form-label">Description</label>
<textarea name="description" class="form-control" rows="4" required></textarea>
</div>
<div class="col-12">
<button class="btn btn-success">Post</button>
</div>
</form>
<?php include __DIR__.'/../partials/footer.php'; ?>