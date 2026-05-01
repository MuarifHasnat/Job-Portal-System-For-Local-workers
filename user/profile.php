<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../lib/functions.php';
require_login();
if (session_status() === PHP_SESSION_NONE) session_start();

$user_id   = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role'];
$baseUrl   = '/jobportalsystem';

// Fixed Rangpur areas
$rangpur_areas = [
  'Jahaj Company Mor','Lalbagh','Modern Mor','Shapla Chattar',
  'Paira Chattar','Keranipara','Dhap','Police Line',
  'Carmichael College Area','Satmatha'
];

// Fetch user info
$user_stmt = $conn->prepare("SELECT name, email, phone, profile_photo, last_name_update FROM users WHERE id=?");
$user_stmt->bind_param('i',$user_id);
$user_stmt->execute();
$user_basic = $user_stmt->get_result()->fetch_assoc();
$current_photo = $user_basic['profile_photo'] ?? '';
$last_name_update = $user_basic['last_name_update'] ?? null;

// Address
$addr_stmt = $conn->prepare("SELECT id, area FROM addresses WHERE user_id=? ORDER BY id DESC LIMIT 1");
$addr_stmt->bind_param('i',$user_id);
$addr_stmt->execute();
$address = $addr_stmt->get_result()->fetch_assoc();
$address_id = $address['id'] ?? null;
$current_area = $address['area'] ?? '';

// Worker profile
$wp_stmt = $conn->prepare("SELECT headline, bio, years_experience, hourly_rate, primary_category_id, category_changed FROM worker_profiles WHERE user_id=? LIMIT 1");
$wp_stmt->bind_param('i',$user_id);
$wp_stmt->execute();
$worker_profile = $wp_stmt->get_result()->fetch_assoc();
$category_changed = $worker_profile['category_changed'] ?? 0;

// Restriction logic
$can_change_name = true;
if ($last_name_update) {
    $days_since_change = (time() - strtotime($last_name_update)) / 86400;
    if ($days_since_change < 60) $can_change_name = false;
}

$success_message = '';
$error_message   = '';

// Form submit
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_mode']) && $_POST['edit_mode']=='1') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $area = trim($_POST['area'] ?? '');
  $city='Rangpur'; $district='Rangpur';

  // Upload photo
  $new_photo=null;
  if(!empty($_FILES['profile_photo']['name'])){
      $upload_dir=__DIR__.'/../uploads/';
      if(!is_dir($upload_dir)) mkdir($upload_dir,0755,true);
      $ext=pathinfo($_FILES['profile_photo']['name'],PATHINFO_EXTENSION) ?: 'jpg';
      $safe='user_'.$user_id.'_'.time().'.'.$ext;
      if(move_uploaded_file($_FILES['profile_photo']['tmp_name'],$upload_dir.$safe)){
          $new_photo=$safe;
          $current_photo=$safe;
      }
  }

  // If worker, validate experience & rate ranges
  if ($user_role === 'worker') {
      $headline=trim($_POST['headline'] ?? '');
      $bio=trim($_POST['bio'] ?? '');
      $exp=(int)($_POST['years_experience'] ?? 0);
      $rate=(int)($_POST['hourly_rate'] ?? 0);
      $cat=$_POST['category_id'] ?? null;

      // Server-side rules
      if ($exp < 1 || $exp > 10) {
          $error_message = 'Years of experience must be between 1 and 10.';
      }
      if ($rate < 500 || $rate > 1000) {
          $error_message = $error_message ? ($error_message.' Hourly rate must be between 500 and 1000 BDT.') : 'Hourly rate must be between 500 and 1000 BDT.';
      }
  }

  // Only proceed with DB updates if no errors
  if (!$error_message) {
      // Update user info (respect name restriction)
      if($can_change_name){
          if($new_photo){
              $u=$conn->prepare("UPDATE users SET name=?,email=?,phone=?,profile_photo=?,last_name_update=NOW() WHERE id=?");
              $u->bind_param('ssssi',$name,$email,$phone,$new_photo,$user_id);
          } else {
              $u=$conn->prepare("UPDATE users SET name=?,email=?,phone=?,last_name_update=NOW() WHERE id=?");
              $u->bind_param('sssi',$name,$email,$phone,$user_id);
          }
      } else {
          if($new_photo){
              $u=$conn->prepare("UPDATE users SET email=?,phone=?,profile_photo=? WHERE id=?");
              $u->bind_param('sssi',$email,$phone,$new_photo,$user_id);
          } else {
              $u=$conn->prepare("UPDATE users SET email=?,phone=? WHERE id=?");
              $u->bind_param('ssi',$email,$phone,$user_id);
          }
      }
      $u->execute();

      // Address update/insert
      if($address_id){
          $a=$conn->prepare("UPDATE addresses SET area=?,city=?,district=? WHERE id=?");
          $a->bind_param('sssi',$area,$city,$district,$address_id);
          $a->execute();
      } else {
          $a=$conn->prepare("INSERT INTO addresses(user_id,area,city,district) VALUES(?,?,?,?)");
          $a->bind_param('isss',$user_id,$area,$city,$district);
          $a->execute();
      }

      // Worker profile update
      if($user_role==='worker'){
          if($worker_profile){
              if(!$category_changed && !empty($cat) && $cat!=$worker_profile['primary_category_id']){
                  $category_changed=1;
                  $wp=$conn->prepare("UPDATE worker_profiles SET headline=?,bio=?,years_experience=?,hourly_rate=?,primary_category_id=?,category_changed=1 WHERE user_id=?");
                  $wp->bind_param('ssisii',$headline,$bio,$exp,$rate,$cat,$user_id);
              } else {
                  $wp=$conn->prepare("UPDATE worker_profiles SET headline=?,bio=?,years_experience=?,hourly_rate=? WHERE user_id=?");
                  $wp->bind_param('ssisi',$headline,$bio,$exp,$rate,$user_id);
              }
          } else {
              $wp=$conn->prepare("INSERT INTO worker_profiles(user_id,headline,bio,years_experience,hourly_rate,primary_category_id,category_changed) VALUES(?,?,?,?,?,?,0)");
              $wp->bind_param('issdii',$user_id,$headline,$bio,$exp,$rate,$cat);
          }
          $wp->execute();
      }

      $success_message="Profile updated successfully!";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Profile</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{margin:0;font-family:'Inter',sans-serif;background:radial-gradient(circle at top,#e0f2fe 0%,#bae6fd 50%,#93c5fd 100%);color:#0f172a;}
.container{max-width:850px;margin:60px auto;padding:0 1rem;}
.profile-card{background:#fff;border-radius:18px;padding:25px 28px 35px;box-shadow:0 12px 30px rgba(15,23,42,0.1);}
h2{font-size:1.4rem;margin-bottom:1rem;}
label{font-weight:600;font-size:.9rem;margin-top:.7rem;display:block;}
input,select,textarea{width:100%;padding:.55rem .7rem;margin-top:.25rem;border-radius:10px;border:1px solid #cbd5e1;font-family:inherit;}
input[readonly],select[disabled],textarea[readonly]{background:#f8fafc;color:#64748b;}
button{background:#0ea5e9;color:white;border:none;border-radius:999px;padding:.6rem 1.6rem;font-weight:600;margin-top:1rem;cursor:pointer;}
button:hover{background:#0369a1;}
.alert-success{background:#dcfce7;color:#166534;padding:.6rem .8rem;border-radius:12px;margin-bottom:1rem;font-size:.85rem;}
.alert-error{background:#fee2e2;color:#991b1b;padding:.6rem .8rem;border-radius:12px;margin-bottom:1rem;font-size:.85rem;}
.topbar{display:flex;justify-content:space-between;align-items:center;padding:.7rem 1.2rem;background:rgba(255,255,255,0.5);backdrop-filter:blur(10px);}
.back-btn{text-decoration:none;background:#0ea5e9;color:white;padding:6px 14px;border-radius:999px;font-weight:600;}
.back-btn:hover{background:#0369a1;}
footer{text-align:center;font-size:.8rem;color:#64748b;margin-top:60px;}
.helper{font-size:.8rem;color:#64748b;margin-top:.25rem;}
</style>
</head>
<body>
<header class="topbar">
  <div>💼 Job Portal System For Local Workers</div>
  <a href="<?=$baseUrl?>/index.php" class="back-btn">← Back</a>
</header>

<div class="container">
  <div class="profile-card">
    <h2>Your Profile</h2>
    <?php if(!empty($success_message)): ?><div class="alert-success"><?=$success_message?></div><?php endif; ?>
    <?php if(!empty($error_message)): ?><div class="alert-error"><?=$error_message?></div><?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data" id="profileForm">
      <input type="hidden" name="edit_mode" id="edit_mode" value="0">

      <label>Profile Photo</label>
      <div class="photo-box">
        <?php if($current_photo): ?>
          <img src="<?=$baseUrl?>/uploads/<?=htmlspecialchars($current_photo)?>" width="200" style="border-radius:60%;">
        <?php else: ?>
          <div style="width:100px;height:100px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;">No Photo</div>
        <?php endif; ?>
        <input type="file" name="profile_photo" accept="image/*" disabled>
      </div>

      <label>Full Name</label>
      <input type="text" name="name" value="<?=htmlspecialchars($user_basic['name'])?>" <?= !$can_change_name ? 'readonly' : 'disabled' ?>>
      <?php if(!$can_change_name): ?><small style="color:#64748b;">Name can only be changed after 60 days.</small><?php endif; ?>

      <label>Email</label>
      <input type="email" name="email" value="<?=htmlspecialchars($user_basic['email'])?>" disabled>

      <label>Phone</label>
      <input type="text" name="phone" value="<?=htmlspecialchars($user_basic['phone'])?>" disabled>

      <label>Area (Rangpur)</label>
      <select name="area" disabled>
        <option value="">Select area</option>
        <?php foreach($rangpur_areas as $opt): ?>
          <option value="<?=htmlspecialchars($opt)?>" <?=($current_area===$opt)?'selected':''?>><?=$opt?></option>
        <?php endforeach; ?>
      </select>

      <?php if($user_role==='worker'): ?>
        <hr>
        <h3>Worker Profile</h3>

        <label>Headline</label>
        <input type="text" name="headline" value="<?=htmlspecialchars($worker_profile['headline'] ?? '')?>" disabled>

        <label>Bio</label>
        <textarea name="bio" rows="3" disabled><?=htmlspecialchars($worker_profile['bio'] ?? '')?></textarea>

        <label>Years of Experience</label>
        <input type="number" name="years_experience"
               value="<?=htmlspecialchars($worker_profile['years_experience'] ?? 0)?>"
               min="1" max="10" disabled>
        <div class="helper">Allowed: 1–10 years.</div>

        <label>Hourly Rate (BDT)</label>
        <input type="number" name="hourly_rate"
               value="<?=htmlspecialchars($worker_profile['hourly_rate'] ?? 0)?>"
               min="500" max="1000" step="1" disabled>
        <div class="helper">Allowed: 500–1000 ৳ per hour.</div>

        <label>Category</label>
        <select name="category_id" <?= $category_changed ? 'disabled' : 'disabled' ?>>
          <option value="">-- Select --</option>
          <?php
            $cats=$conn->query("SELECT id,name FROM service_categories ORDER BY display_order ASC,name ASC");
            while($cat=$cats->fetch_assoc()):
          ?>
            <option value="<?=$cat['id']?>" <?=($cat['id']==($worker_profile['primary_category_id']??0))?'selected':''?>><?=htmlspecialchars($cat['name'])?></option>
          <?php endwhile; ?>
        </select>
        <?php if($category_changed): ?><small style="color:#64748b;">Category can only be changed once.</small><?php endif; ?>
      <?php endif; ?>

      <button type="button" id="editBtn">Edit Profile</button>
      <button type="submit" id="saveBtn" style="display:none;">Save Changes</button>
    </form>
  </div>
</div>

<footer>© <?=date('Y')?> Job Portal System For Local Workers</footer>

<script>
const editBtn=document.getElementById('editBtn');
const saveBtn=document.getElementById('saveBtn');
editBtn.onclick=()=>{
  document.querySelectorAll('input,select,textarea').forEach(el=>{
    if(el.name!=='city' && el.name!=='district' && el.getAttribute('readonly')===null) el.disabled=false;
  });
  document.getElementById('edit_mode').value='1';
  editBtn.style.display='none';
  saveBtn.style.display='inline-block';
};
</script>
</body>
</html>
