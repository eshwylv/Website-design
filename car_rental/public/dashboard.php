<?php
declare(strict_types=1);
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/layout.php';

$user=require_role($pdo,['ADMIN','MANAGER','RENTAL_STAFF']);
$role=$user['role'];

/* Dashboard statistics are loaded by JavaScript from the authenticated REST API.
   This keeps the presentation layer separate from the data/service layer. */

render_header('Dashboard','dashboard.php');
?>
<section class="hero compact-hero">
<div>
<h2><?=h(role_label($role))?></h2>
<?php if($role==='ADMIN'): ?>
<p>Manage staff accounts and access.</p>
<?php elseif($role==='MANAGER'): ?>
<p>Manage vehicles, rentals, repairs and income.</p>
<?php else: ?>
<p>Check available vehicles and manage rentals.</p>
<?php endif; ?>
</div>

</section>

<section class="grid4">
<div class="card stat"><div class="label">Total Cars</div><div class="value" id="api-total">—</div></div>
<div class="card stat"><div class="label">Idle</div><div class="value" id="api-idle">—</div></div>
<div class="card stat"><div class="label">On Rent</div><div class="value" id="api-rented">—</div></div>
<div class="card stat"><div class="label">Maintenance</div><div class="value" id="api-maintenance">—</div></div>
</section>

<section class="grid4">
<?php if($role==='ADMIN'): ?>
<a class="card quick" href="administration.php"><div><b>User Accounts</b><small>Add staff and manage account access</small></div></a>
<a class="card quick" href="administration.php"><div><b>Audit & Monitoring</b><small>View login and account activity</small></div></a>
<?php elseif($role==='MANAGER'): ?>
<a class="card quick" href="operations.php"><div><b>Car Assets</b><small>View vehicle, tax, insurance and purchase details</small></div></a>
<a class="card quick" href="operations.php#rental"><div><b>Rental Oversight</b><small>View rental records</small></div></a>
<a class="card quick" href="operations.php#maintenance"><div><b>Maintenance</b><small>Manage vehicle repairs and servicing</small></div></a>
<a class="card quick" href="reports.php"><div><b>Revenue</b><small>View income by vehicle and total income</small></div></a>
<?php else: ?>
<a class="card quick" href="operations.php"><div><b>Car Availability</b><small>View available vehicles and their status</small></div></a>
<a class="card quick" href="operations.php#rental"><div><b>Rental Transactions</b><small>Add rentals and record returns</small></div></a>
<?php endif; ?>
</section>
<script>
fetch('../api/dashboard.php', {credentials:'same-origin', headers:{'Accept':'application/json'}})
  .then(function(response){
      if(!response.ok) throw new Error('HTTP '+response.status);
      return response.json();
  })
  .then(function(payload){
      document.getElementById('api-total').textContent=payload.data.total;
      document.getElementById('api-idle').textContent=payload.data.idle;
      document.getElementById('api-rented').textContent=payload.data.rented;
      document.getElementById('api-maintenance').textContent=payload.data.maintenance;

  })
  .catch(function(){

  });
</script>
<?php render_footer(); ?>
