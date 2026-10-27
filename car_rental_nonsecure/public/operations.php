<?php
declare(strict_types=1);
require_once __DIR__.'/../config/db.php'; require_once __DIR__.'/../includes/auth.php'; require_once __DIR__.'/../includes/csrf.php'; require_once __DIR__.'/../includes/audit.php'; require_once __DIR__.'/../includes/layout.php';
$currentUser=require_role($pdo,['MANAGER','RENTAL_STAFF']); $role=$currentUser['role']; $message='';
/* State-changing rental and maintenance operations are handled by REST API v1.
   This page is now the presentation layer only. */
$available=$pdo->query("SELECT id,reg_no,make,model,daily_rate FROM cars WHERE status='IDLE' ORDER BY reg_no")->fetchAll();
$rentals=$pdo->query("SELECT r.*,c.reg_no,c.make,c.model,cu.full_name,cu.mobile,cu.email,cu.licence_last4,cu.licence_verified,u.username requester FROM rentals r JOIN cars c ON c.id=r.car_id JOIN customers cu ON cu.id=r.customer_id JOIN users u ON u.id=r.requested_by ORDER BY r.id DESC")->fetchAll();
$maintenance=$pdo->query("SELECT m.*,c.reg_no,c.make,c.model FROM maintenance m JOIN cars c ON c.id=m.car_id ORDER BY m.id DESC")->fetchAll();
render_header('Operations','operations.php'); if($message)echo '<div class="notice">'.h($message).'</div>';
?>
<div id="api-action-message" class="notice" style="display:none"></div>

<div class="section-links">
<a href="#fleet">Vehicle Fleet</a>
<a href="#rental">Rental</a>
<?php if($role==='MANAGER'): ?><a href="#maintenance">Maintenance</a><?php endif; ?>
</div>
<section class="panel" id="fleet">
<h2>Vehicle Fleet</h2>
<p>Current vehicle availability and operational status.</p>
<?php
$operationCars=$pdo->query(
    "SELECT id,reg_no,make,model,daily_rate,status,purchase_date,purchase_price,road_tax_expiry,insurance_expiry
     FROM cars ORDER BY reg_no"
)->fetchAll();
?>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Registration</th><th>Vehicle</th><th>Daily Rate</th><th>Status</th>
<?php if($role==='MANAGER'): ?><th>Purchase Date</th><th>Purchase Price</th><th>Road Tax</th><th>Insurance</th><?php endif; ?>
</tr>
</thead>
<tbody>
<?php foreach($operationCars as $c): ?>
<tr>
<td><?=h($c['reg_no'])?></td>
<td><?=h($c['make'].' '.$c['model'])?></td>
<td><?=h(number_format((float)$c['daily_rate'],2))?></td>
<td><?=h($c['status'])?></td>
<?php if($role==='MANAGER'): ?>
<td><?=h((string)$c['purchase_date'])?></td>
<td><?=h(number_format((float)$c['purchase_price'],2))?></td>
<td><?=h((string)$c['road_tax_expiry'])?></td>
<td><?=h((string)$c['insurance_expiry'])?></td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>

<div class="transaction-grid"><section class="panel" id="rental"><h3>Rental Transaction</h3><p class="muted">Licence is sighted by authorised staff; only the last 4 characters and verification result are retained.</p><form method="post" id="rental-create-form"><?=csrf_field()?><div class="formgrid two"><label>Car<select name="car_id" required><option value="">Select available car</option><?php foreach($available as $c):?><option value="<?=$c['id']?>"><?=h($c['reg_no'].' - '.$c['make'].' '.$c['model'].' (SGD '.number_format((float)$c['daily_rate'],2).'/day)')?></option><?php endforeach;?></select></label><label>Customer Full Name<input name="customer_name" maxlength="120" required></label><label>Mobile<input name="mobile" maxlength="30" required></label><label>Email<input type="email" name="customer_email" maxlength="254" required></label><label>Address (optional)<input name="address" maxlength="255"></label><label>Licence last 4 characters<input name="licence_last4" minlength="4" maxlength="4" pattern="[A-Za-z0-9]{4}" required></label><label>Licence Expiry<input type="date" name="licence_expiry" required></label><label>Rental Date<input type="date" name="rental_date" required></label><label>Return Date<input type="date" name="return_date" required></label><label>Rental Amount (SGD)<input type="number" min="0.01" step="0.01" name="amount" required></label><label class="span2"><input type="checkbox" name="licence_verified" value="1" required> I have sighted and verified the customer's driving licence.</label></div><br><button type="submit">Save Rental</button></form></section>
<?php if($role==='MANAGER'):?><section class="panel" id="maintenance"><h3>Maintenance / Repair Transaction</h3><form method="post" id="maintenance-create-form"><?=csrf_field()?><div class="formgrid two"><label>Car<select name="maintenance_car_id" required><option value="">Select idle car</option><?php foreach($available as $c):?><option value="<?=$c['id']?>"><?=h($c['reg_no'].' - '.$c['make'].' '.$c['model'])?></option><?php endforeach;?></select></label><label>Start Date<input type="date" name="start_date" required></label><label>Cost (SGD)<input type="number" min="0" step="0.01" name="cost" required></label><label class="span2">Description<textarea name="description" required></textarea></label></div><br><button type="submit">Save Maintenance</button></form></section><?php endif;?></div>
<div class="panel"><h3>Rental Records</h3><div class="tablewrap"><table><tr><th>ID</th><th>Customer</th><th>Car</th><th>Dates</th><th>Amount</th><th>Discount</th><th>Status</th><th>Action</th></tr><?php foreach($rentals as $r):?><tr><td><?=$r['id']?></td><td><?=h($r['full_name'])?><br><small><?=h($r['mobile'].' · '.$r['email'])?><br>Licence ••••<?=h($r['licence_last4'])?> verified</small></td><td><?=h($r['reg_no'].' '.$r['make'].' '.$r['model'])?></td><td><?=h($r['rental_date'].' → '.$r['return_date'])?></td><td>SGD <?=number_format((float)$r['amount'],2)?><br><small>Standard <?=number_format((float)$r['standard_amount'],2)?></small></td><td><?=number_format((float)$r['discount_percent'],1)?>%</td><td><span class="badge <?=h($r['status'])?>"><?=h($r['status'])?></span></td><td><?php if($r['status']==='ACTIVE'):?><form method="post" class="api-rental-complete"><?=csrf_field()?><input type="hidden" name="rental_id" value="<?=$r['id']?>"><button class="btn secondary">Return Car</button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></table></div></div>
<?php if($role==='MANAGER'):?><div class="panel"><h3>Maintenance / Repair Records</h3><div class="tablewrap"><table><tr><th>ID</th><th>Car</th><th>Description</th><th>Cost</th><th>Start</th><th>Completion</th><th>Status</th><th>Action</th></tr><?php foreach($maintenance as $m):?><tr><td><?=$m['id']?></td><td><?=h($m['reg_no'].' '.$m['make'].' '.$m['model'])?></td><td><?=h($m['description'])?></td><td>SGD <?=number_format((float)$m['cost'],2)?></td><td><?=h($m['start_date'])?></td><td><?=h($m['completion_date']?:'—')?></td><td><?=h($m['status'])?></td><td><?php if($m['status']==='OPEN'):?><form method="post" class="api-maintenance-complete"><?=csrf_field()?><input type="hidden" name="maintenance_id" value="<?=$m['id']?>"><button class="btn success">Complete</button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></table></div></div><?php endif;?><script>
const API_CSRF = <?=json_encode(csrf_token())?>;

function apiMessage(text, ok=true){
    const box=document.getElementById('api-action-message');
    box.style.display='block';
    box.textContent=text;
    box.className='notice'+(ok?'':' warning');
    box.scrollIntoView({behavior:'smooth',block:'center'});
}

async function apiPost(url, formData){
    const response=await fetch(url,{
        method:'POST',
        credentials:'same-origin',
        headers:{
            'Accept':'application/json',
            'X-CSRF-Token':API_CSRF
        },
        body:formData
    });
    const body=await response.json().catch(()=>({ok:false,message:'Invalid API response'}));
    if(!response.ok || !body.ok){
        throw new Error(body.message || body.error || ('HTTP '+response.status));
    }
    return body;
}

const rentalForm=document.getElementById('rental-create-form');
if(rentalForm){
    rentalForm.addEventListener('submit',async function(event){
        event.preventDefault();
        const data=new FormData(rentalForm);
        data.set('action','create');
        try{
            const result=await apiPost('../api/rentals.php',data);
            apiMessage(result.message);
            setTimeout(()=>location.reload(),700);
        }catch(error){ apiMessage(error.message,false); }
    });
}

const maintenanceForm=document.getElementById('maintenance-create-form');
if(maintenanceForm){
    maintenanceForm.addEventListener('submit',async function(event){
        event.preventDefault();
        const data=new FormData(maintenanceForm);
        data.set('action','create');
        data.set('car_id',data.get('maintenance_car_id'));
        try{
            const result=await apiPost('../api/maintenance.php',data);
            apiMessage(result.message);
            setTimeout(()=>location.reload(),700);
        }catch(error){ apiMessage(error.message,false); }
    });
}

document.querySelectorAll('.api-rental-complete').forEach(function(form){
    form.addEventListener('submit',async function(event){
        event.preventDefault();
        const data=new FormData(form);
        data.set('action','complete');
        try{
            const result=await apiPost('../api/rentals.php',data);
            apiMessage(result.message);
            setTimeout(()=>location.reload(),700);
        }catch(error){ apiMessage(error.message,false); }
    });
});

document.querySelectorAll('.api-maintenance-complete').forEach(function(form){
    form.addEventListener('submit',async function(event){
        event.preventDefault();
        const data=new FormData(form);
        data.set('action','complete');
        try{
            const result=await apiPost('../api/maintenance.php',data);
            apiMessage(result.message);
            setTimeout(()=>location.reload(),700);
        }catch(error){ apiMessage(error.message,false); }
    });
});
</script>
<?php render_footer();?>
