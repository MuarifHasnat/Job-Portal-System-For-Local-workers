<?php include __DIR__.'/../partials/header.php'; ?>
<?php require_once __DIR__.'/../lib/functions.php'; ?>
<?php if (!is_logged_in()) redirect('/jobportalsystem/auth/login.php'); ?>
<?php
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
$reviewer_id = $_SESSION['user']['id'];
$reviewee_id = (int)$_POST['reviewee_id'];
$rating = (int)$_POST['rating'];
$comment = trim($_POST['comment'] ?? '');
$stmt = $conn->prepare("INSERT INTO reviews (reviewer_id, reviewee_id, rating, comment) VALUES (?,?,?,?)");
$stmt->bind_param('iiis', $reviewer_id, $reviewee_id, $rating, $comment);
if ($stmt->execute()) { $msg='Thanks for your review!'; }
}
?>
<h3>Leave a Review</h3>
<?php if($msg): ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<form method="post" class="row g-3">
<div class="col-md-6">
<label class="form-label">Worker ID</label>
<input type="number" name="reviewee_id" class="form-control" required>
</div>
<div class="col-md-6">
<label class="form-label">Rating (1-5)</label>
<input type="number" name="rating" class="form-control" min="1" max="5" required>
</div>
<div class="col-12">
<label class="form-label">Comment</label>
<textarea name="comment" class="form-control" rows="3"></textarea>
</div>
<div class="col-12">
<button class="btn btn-success">Submit</button>
</div>
</form>
<?php include __DIR__.'/../partials/footer.php'; ?>