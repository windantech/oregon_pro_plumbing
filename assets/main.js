'use strict';
const services=[{id:'burst',name:'Burst Pipe Repair',icon:'💧',price:285,tag:'Urgent request',group:'urgent',desc:'Water on the floor, in a ceiling, or down a wall.'},{id:'leak',name:'Slow leak',icon:'🚰',price:175,tag:'Request a visit',group:'repair',desc:'A dripping tap, weeping joint, or damp patch that keeps growing.'},{id:'drain',name:'Drain Cleaning Services',icon:'🌀',price:149,tag:'Urgent request',group:'urgent',desc:'Blocked kitchen sink, bath, toilet or outside line.'},{id:'hot',name:'No hot water',icon:'♨️',price:165,tag:'Flexible timing',group:'repair',desc:'The heater is cold, or only one faucet runs warm.'},{id:'heater',name:'Water Heater Services',icon:'🔥',price:450,tag:'Booked slot',group:'install',desc:'Install or replace a failed tank or tankless water heater.'},{id:'fixture',name:'New fixture',icon:'🔧',price:225,tag:'Flexible timing',group:'install',desc:'Faucet, toilet, shower, or a short new run of pipe.'},{id:'pump',name:'Sump or well pump',icon:'⚙️',price:350,tag:'Visit first',group:'repair',desc:'A sump that quit, or a well pump that has gone quiet.'},{id:'remodel',name:'Bathroom remodel',icon:'🛁',price:null,tag:'Visit first',group:'install',desc:'Whole-room plumbing renovation. Assessed and priced on site.'},{id:'general',name:'General Plumbing',icon:'🧰',price:null,tag:'Schedule a visit',group:'repair',desc:'Everyday plumbing concerns, inspections and general service requests.'},{id:'detect',name:'Leak Detection',icon:'🔍',price:null,tag:'Inspection first',group:'repair',desc:'Find the likely source of hidden leaks, moisture or unexpected water use.'},{id:'faucet_repair',name:'Faucet Repair',icon:'🚰',price:null,tag:'Schedule a visit',group:'repair',desc:'Repair dripping, loose or poorly working kitchen and bathroom faucets.'},{id:'pipe_repair',name:'Pipe Leak Repair',icon:'🛠️',price:null,tag:'Inspection first',group:'repair',desc:'Repair leaking supply pipes, exposed joints and damaged connections.'},{id:'toilet_repair',name:'Toilet Repair',icon:'🚽',price:null,tag:'Schedule a visit',group:'repair',desc:'Help with running, leaking, blocked or poorly flushing toilets.'},{id:'toilet_install',name:'Toilet Installation',icon:'🚽',price:null,tag:'Installation request',group:'install',desc:'Replace or install a toilet and check its connections.'},{id:'faucet_install',name:'Faucet Installation',icon:'🚰',price:null,tag:'Installation request',group:'install',desc:'Install or replace a kitchen, bathroom or utility faucet.'},{id:'disposal_install',name:'Garbage Disposal Installation',icon:'⚙️',price:null,tag:'Installation request',group:'install',desc:'Install or replace an under-sink garbage disposal unit.'},{id:'sewer_lines',name:'Sewer & Water Lines',icon:'🚧',price:null,tag:'Inspection first',group:'repair',desc:'Assessment of sewer and water service lines, leaks and blockages.'},{id:'emergency',name:'Emergency Plumbing',icon:'🚨',price:null,tag:'Urgent request',group:'urgent',desc:'Request help for major leaks, sudden water loss or sewer backups; availability is confirmed.'}];
const neighborhoods=[["Portland",1],["Beaverton",1],["Hillsboro",1],["Gresham",1],["Lake Oswego",1],["Tigard",1],["Tualatin",1],["Oregon City",1],["Milwaukie",1],["West Linn",1],["Salem",1],["Keizer",1],["Woodburn",1],["McMinnville",1],["Eugene",1],["Springfield",1],["Corvallis",1],["Albany",1],["Bend",1],["Redmond",1],["Sisters",1],["Astoria",1],["Newport",1],["Hood River",1],["Medford",1],["Ashland",1],["Grants Pass",1],["Roseburg",1],["The Dalles",1],["Klamath Falls",1],["Other Oregon community",1]];
const faqs=[
['Does Oregon Pro Plumbing cover my city?','Select your Oregon city when requesting service. We confirm whether your address falls within our current coverage area before scheduling.'],
['Are online estimates final?','Prices shown are starting estimates. We confirm final charges and any additional parts or travel costs before work begins.'],
['Is there a service-call charge?','The service-call fee is displayed before checkout and confirmed with your request. Contact us if you need details about how this charge applies to your repair.'],
['Can I request assistance with a burst pipe?','Yes. Submit an urgent request, but please note that online requests do not guarantee immediate emergency dispatch. Shut off water if safe and keep clear of electrical hazards.'],
['How do payments work?','If online checkout is available, you can pay through Stripe’s secure payment page. Your booking will show an updated payment status once Stripe verifies the transaction.'],
['Can I track my job from another device?','Yes. After Stripe confirms your first payment, you will receive the booking reference to view job progress on any device.'],
['Are parts included in starting prices?','Parts, permits and additional work are discussed and confirmed based on the service required.'],
['How do I prepare plumbing for an Oregon cold snap?','Protect exposed plumbing where appropriate, learn your water shutoff location, and contact a professional if you suspect a frozen line.']
];
const $=s=>document.querySelector(s);const $$=s=>[...document.querySelectorAll(s)];const money=n=>n==null?'On-site quote':'$'+n.toLocaleString('en-US');const escapeHTML=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function notify(message){const t=$('#toast');t.textContent=message;t.classList.add('show');clearTimeout(window._toastTimeout);window._toastTimeout=setTimeout(()=>t.classList.remove('show'),3200)}
function renderServices(){const card=s=>`<article class="service-card"><div class="service-icon">${s.icon}</div><h3>${s.name}</h3><p>${s.desc}</p><div class="service-footer"><span class="price">${s.price==null?'On-site quote':'From '+money(s.price)}</span><button class="card-go" data-choose="${s.id}" aria-label="Book ${s.name}">↗</button></div></article>`;$('#homeServices').innerHTML=services.slice(0,8).map(card).join('');filterServices('all');$('#faultOptions').innerHTML=services.map(s=>`<button type="button" class="fault-option" data-fault="${s.id}"><span class="emoji">${s.icon}</span><span><strong>${s.name}</strong><small>${s.tag}</small></span></button>`).join('');$('#neighborhood').innerHTML=neighborhoods.map(([name,z])=>`<option value="${name}">${name}, Oregon</option>`).join('');$('#neighborhood').value=state.neighborhood}
function filterServices(filter){$('#allServices').innerHTML=services.filter(s=>filter==='all'||s.group===filter).map(s=>`<article class="service-large"><div class="service-icon">${s.icon}</div><div><span class="tag">${s.tag}</span><h3 style="margin:12px 0 8px">${s.name}</h3><p>${s.desc}</p><strong class="price">${s.price==null?'Quoted after site visit':'Labor from '+money(s.price)}</strong><br><button class="btn btn-outline btn-small" data-choose="${s.id}">Request service ↗</button></div></article>`).join('')}
let state={service:'burst',neighborhood:'Portland',step:0},lastCode='',lastBooking=null,paymentsAvailable=false,callFeeValue=129,payChoice='call',paymentBusy=false,activePaymentCode='',activePaymentToken='';
const pendingStorageKey='opp_pending_payment_token';
let pendingPaymentToken='';
try{pendingPaymentToken=localStorage.getItem(pendingStorageKey)||'';}catch{}
function storePendingToken(token){pendingPaymentToken=token;try{localStorage.setItem(pendingStorageKey,token);}catch{}showPendingResume();}
function clearPendingToken(){pendingPaymentToken='';try{localStorage.removeItem(pendingStorageKey);}catch{}showPendingResume();}
function showPendingResume(){const box=$('#pendingResumeBox');if(box)box.hidden=!pendingPaymentToken;}
function showPendingRequest(){
 if(!lastBooking)return;
 lastCode='';state.step=4;updateWizard();
 $('#successCode').textContent='';$('#successCode').hidden=true;$('#successCode').style.display='none';
 $('#successMessage').innerHTML='Your service request has been received. Select <strong>Proceed to Pay</strong> to continue securely.';
 $('#copyCode').hidden=true;$('#viewJob').hidden=true;
 updatePaymentButton();
}
const chosen=()=>services.find(s=>s.id===state.service);
const selectedCity=()=>state.neighborhood==='Other Oregon community'?($('#otherCity').value.trim()||'Other Oregon community'):state.neighborhood;
const callFee=()=>callFeeValue;
const quote=()=>chosen().price==null?null:callFee()+chosen().price;
const formatMoney=n=>n==null?'On-site quote':'$'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
async function api(url,options={}){
 const response=await fetch(url,{cache:'no-store',headers:{'Accept':'application/json',...(options.body?{'Content-Type':'application/json'}:{})},credentials:'same-origin',...options});
 const json=await response.json().catch(()=>({}));if(!response.ok)throw Error(json.error||'Request could not be completed.');return json;
}
async function loadSettings(){try{const s=await api('api/settings.php');paymentsAvailable=!!s.paymentsAvailable;callFeeValue=Number(s.callFee)||0;
 if(s.servicePrices){services.forEach(service=>{if(Object.prototype.hasOwnProperty.call(s.servicePrices,service.id))service.price=s.servicePrices[service.id]});renderServices()}
 updateWizard();}catch{paymentsAvailable=false}}
function updatePaymentButton(){const b=$('#payBooking');if(!b)return;b.disabled=!paymentsAvailable;b.textContent=paymentsAvailable?'Proceed to Pay':'Online payment currently unavailable';}

function showPage(page,scroll=true){if(!$('#'+page)?.classList.contains('page'))page='home';$$('.page').forEach(e=>e.classList.toggle('active',e.id===page));$$('[data-page]').forEach(e=>e.classList.toggle('active',e.dataset.page===page));$('#navlinks').classList.remove('open');$('#menuToggle').setAttribute('aria-expanded','false');if(scroll)window.scrollTo({top:0,behavior:'smooth'});if(page==='booking')updateWizard();const titles={home:'Oregon Pro Plumbing | Plumbing Services in Oregon',services:'Plumbing Repair Services in Oregon | Oregon Pro Plumbing',emergency:'Emergency Plumbing in Oregon | Oregon Pro Plumbing',about:'About Our Plumbing Company | Oregon Pro Plumbing',booking:'Request Plumbing Service in Oregon | Oregon Pro Plumbing',jobs:'Track an Oregon Plumbing Request | Oregon Pro Plumbing',faq:'Oregon Plumbing Questions | Oregon Pro Plumbing'};document.title=titles[page]||titles.home}
function updateWizard(){const s=chosen();$$('.wizard-pane').forEach(el=>el.classList.toggle('active',Number(el.dataset.step)===state.step));$('#progress').innerHTML=['Fault','Place','You','Quote'].map((label,i)=>`<div class="progress-step ${i===state.step?'current':i<state.step?'done':''}"><i>${i<state.step?'✓':i+1}</i> ${label}</div>`).join('');$$('[data-fault]').forEach(b=>{b.classList.toggle('selected',b.dataset.fault===state.service);b.setAttribute('aria-pressed',String(b.dataset.fault===state.service))});$('#sideService').textContent=s.name;$('#sideArea').textContent=selectedCity()+', OR';$('#sideCall').textContent=money(callFee());$('#sideLabor').textContent=s.price==null?'On-site quote':money(s.price);$('#sideTotal').textContent=quote()==null?'On-site':money(quote());$('#wizardActions').style.display=state.step===4?'none':'flex';$('#backStep').style.visibility=state.step===0?'hidden':'visible';$('#nextStep').textContent=state.step===3?'Submit request ✓':'Continue →';if(state.step===3){$('#quoteBreakdown').innerHTML=`<div class="summary-line"><span>Service-call fee</span><strong>${money(callFee())}</strong></div><div class="summary-line"><span>Estimated labor</span><strong>${s.price==null?'To be assessed':money(s.price)}</strong></div><div class="summary-line total-row"><span>Initial estimate</span><strong>${quote()==null?'Pending visit':money(quote())}</strong></div>`;$('#reviewInfo').innerHTML=`<p><b>Service:</b> ${escapeHTML(s.name)}</p><p><b>Location:</b> ${escapeHTML($('#address').value)}, ${escapeHTML(state.neighborhood)}, OR ${escapeHTML($('#zip').value)}</p><p><b>Contact:</b> ${escapeHTML($('#firstName').value)} ${escapeHTML($('#lastName').value)} · ${escapeHTML($('#phone').value)}</p><p><b>Timing:</b> ${escapeHTML($('#urgency').value)}</p>`}}
function validate(){if(state.step===1){if(state.neighborhood==='Other Oregon community'&&!$('#otherCity').value.trim()){notify('Please enter your Oregon city.');$('#otherCity').focus();return false}if(!$('#address').value.trim()){notify('Please enter an Oregon street address.');$('#address').focus();return false}if(!/^97\d{3}$/.test($('#zip').value.trim())){notify('Enter a valid 5-digit Oregon ZIP code (97xxx).');$('#zip').focus();return false}}if(state.step===2){for(const id of ['firstName','lastName','phone']){if(!$('#'+id).value.trim()){notify('Please complete the required contact fields.');$('#'+id).focus();return false}}if(!$('#email').value.trim()||!$('#email').validity.valid){notify('Enter a valid email address to receive booking updates.');$('#email').focus();return false}}return true}
async function saveJob(){
 if(paymentBusy)return;
 const b=$('#nextStep');b.disabled=true;b.textContent='Submitting your request…';
 try{
   const data={serviceId:state.service,city:selectedCity(),address:$('#address').value.trim(),zip:$('#zip').value.trim(),
   details:$('#details').value.trim(),urgency:$('#urgency').value,firstName:$('#firstName').value.trim(),
   lastName:$('#lastName').value.trim(),phone:$('#phone').value.trim(),email:$('#email').value.trim(),website:$('#website').value,whatsappOptIn:!!$('#whatsappOptIn')?.checked};
   const res=await api('api/bookings.php',{method:'POST',body:JSON.stringify(data)});
   if(!res.saved||!res.paymentToken||!res.booking)throw new Error('Unable to confirm your request. Please try again.');
   lastBooking=res.booking;lastCode='';paymentsAvailable=!!res.paymentsAvailable;
   storePendingToken(res.paymentToken);showPendingRequest();
   notify('Request received. Proceed to payment when ready.');
 }catch(err){notify(err.message||'Unable to send your request.');}finally{b.disabled=false;if(state.step===3)updateWizard()}
}
async function lookupPending(token){
 try{
   const res=await api('api/bookings.php?payment_token='+encodeURIComponent(token));
   paymentsAvailable=!!res.paymentsAvailable;lastBooking=res.booking;
   if(lastBooking?.code && Number(lastBooking.paidAmount)>0){
      lastCode=lastBooking.code;clearPendingToken();return lastBooking;
   }
   showPendingRequest();return lastBooking;
 }catch(err){notify(err.message||'Unable to retrieve the saved request.');return null;}
}
async function lookupJob(code){
 try{
   const res=await api('api/bookings.php?code='+encodeURIComponent(code));paymentsAvailable=!!res.paymentsAvailable;
   lastBooking=res.booking;renderTracking(res.booking);return res.booking;
 }catch(err){renderTracking(null);notify(err.message||'Booking not found.');return null;}
}
function renderTracking(job){
 const target=$('#trackingResult');
 if(!job){target.innerHTML='<div style="text-align:center;padding:40px 0"><div style="font-size:2.5rem">🔎</div><h3>We couldn’t find that reference.</h3><p>Check your booking code and try again.</p></div>';return;}
 if(!job.code){target.innerHTML='<div style="padding:24px"><h3>Request saved · Unpaid</h3><p>Your booking reference will be provided after payment is confirmed.</p></div>';return;}
 const stages=['Booked','Assigned','On the way','On site','Done'];
 const paid=Number(job.paidAmount||0),full=job.initialQuote!=null && paid>=job.initialQuote;
 const paymentLabel=paid===0?'Awaiting payment':full?'Payment received':'Payment received: '+formatMoney(paid);
 target.innerHTML=`<div class="eyebrow">Booking reference</div><h3 style="margin-bottom:7px">${escapeHTML(job.code)}</h3>
 <p>${escapeHTML(job.service)} · ${escapeHTML(job.area)}, OR</p>
 <div class="timeline">${stages.map((t,i)=>`<div class="timeline-item ${i<=job.status?'done':''}"><strong>${i<=job.status?'✓ ':''}${t}</strong><small>${i===job.status?'Current status':i<job.status?'Completed':'Pending'}</small></div>`).join('')}</div>
 <div class="tracking-controls"><h3 style="margin-bottom:5px">Payment status</h3><p><strong>${paymentLabel}</strong></p><p style="font-size:.88rem">Starting estimate: ${job.initialQuote==null?'Provided after assessment':formatMoney(job.initialQuote)}</p>
 ${paymentsAvailable&&!full?'<button class="btn btn-dark btn-small" id="trackPayNow">💳 Pay securely</button>':''}</div>`;
 $('#trackPayNow')?.addEventListener('click',()=>openPayment(job.code));
}
function navigate(page){if(location.hash!=='#'+page)location.hash=page;else showPage(page)}

function paymentDue(job,kind){const paid=Number(job.paidAmount||0);const gross=kind==='call'?Number(job.callFee||callFee()):job.initialQuote;return gross===null?null:Math.max(0,Number(gross)-paid)}
function updateCheckoutAmounts(){if(!lastBooking)return;
 const j=lastBooking;const call=paymentDue(j,'call'),full=paymentDue(j,'full');
 const fullBtn=$('[data-pay-option="full"]'),callBtn=$('[data-pay-option="call"]');
 callBtn.disabled=call===null||call<=0;fullBtn.disabled=full===null||full<=0;
 if((payChoice==='call'&&callBtn.disabled)||(payChoice==='full'&&fullBtn.disabled)){payChoice=callBtn.disabled?'full':'call'}
 const amount=paymentDue(j,payChoice);$$('[data-pay-option]').forEach(b=>{const active=b.dataset.payOption===payChoice;b.classList.toggle('selected',active);b.setAttribute('aria-pressed',String(active))});
 $('#checkoutRef').textContent=j.code||'Shown after payment';$('#checkoutService').textContent=j.service;$('#checkoutArea').textContent=j.area+', OR';
 $('#callPaymentLabel').textContent=call>0?formatMoney(call):'Paid';$('#fullPaymentLabel').textContent=full===null?'After assessment':full>0?formatMoney(full):'Paid';
 $('#checkoutAmount').textContent=amount>0?formatMoney(amount):'Paid';$('#payButtonAmount').textContent=amount>0?formatMoney(amount):'Paid';
 $('#stripePay').disabled=!paymentsAvailable||!amount||amount<=0;
}
async function openPayment(code=null){
 if(!paymentsAvailable){notify('Online payment is currently unavailable. Please contact us.');return;}
 if(code){
   if(!lastBooking||lastBooking.code!==code){const b=await lookupJob(code);if(!b)return;}
   if(!lastBooking?.code){notify('Your booking reference becomes available after payment.');return;}
   activePaymentCode=code;activePaymentToken='';
 }else{
   if(!pendingPaymentToken){notify('Please submit a service request first.');return;}
   if(!lastBooking || lastBooking.code){const b=await lookupPending(pendingPaymentToken);if(!b)return;}
   activePaymentCode='';activePaymentToken=pendingPaymentToken;
 }
 payChoice=paymentDue(lastBooking,'call')>0?'call':'full';updateCheckoutAmounts();
 $('#paymentModal').classList.add('open');$('#paymentModal').setAttribute('aria-hidden','false');document.body.style.overflow='hidden';$('#closePayment').focus();
}
function closePayment(){if(paymentBusy)return;$('#paymentModal').classList.remove('open');$('#paymentModal').setAttribute('aria-hidden','true');document.body.style.overflow=''}
async function startStripeCheckout(){if(paymentBusy||(!activePaymentCode&&!activePaymentToken))return;
 paymentBusy=true;const btn=$('#stripePay');btn.disabled=true;btn.textContent='Opening secure checkout…';
 try{const res=await api('api/checkout.php',{method:'POST',body:JSON.stringify(activePaymentToken?{paymentToken:activePaymentToken,kind:payChoice}:{code:activePaymentCode,kind:payChoice})});
 const link=new URL(res.url);if(link.protocol!=='https:'||link.hostname!=='checkout.stripe.com')throw Error('Could not verify the checkout address.');
 if(activePaymentCode){try{sessionStorage.setItem('opp_existing_paid_checkout',activePaymentCode);}catch{}}
 window.location.assign(res.url);
 }catch(err){notify(err.message||'Unable to open payment.');paymentBusy=false;btn.innerHTML='🔒 Continue to Stripe · <span id="payButtonAmount">—</span>';updateCheckoutAmounts()}
}
async function handleStripeReturn(){
 const params=new URLSearchParams(location.search);
 const sid=params.get('session_id'),cancel=params.get('payment')==='cancel';
 if(!sid&&!cancel)return;
 history.replaceState(null,'',location.pathname+'#booking');
 if(cancel){
   if(pendingPaymentToken){await lookupPending(pendingPaymentToken);navigate('booking');}
   else {
     let existing='';try{existing=sessionStorage.getItem('opp_existing_paid_checkout')||'';}catch{}
     if(existing){navigate('jobs');$('#trackingCode').value=existing;await lookupJob(existing);}
   }
   notify('Payment was not completed. Any unpaid balance remains outstanding.');
   return;
 }
 navigate('booking');
 $('#trackingResult').innerHTML='<div style="padding:32px"><h3>Confirming payment…</h3><p>Checking the payment with Stripe.</p></div>';
 try{
   const res=await api('api/checkout-status.php?session_id='+encodeURIComponent(sid));
   if(res.booking?.code && Number(res.booking.paidAmount)>0){
     lastBooking=res.booking;lastCode=res.booking.code;clearPendingToken();
     $('#trackingCode').value=lastCode;navigate('jobs');renderTracking(lastBooking);
     notify('Payment verified. Your booking reference is now available.');
   }else{
     if(pendingPaymentToken)await lookupPending(pendingPaymentToken);
     else {lastBooking=res.booking||null;if(lastBooking)showPendingRequest();}
     navigate('booking');notify('Payment has not yet been confirmed. Your request is saved as unpaid.');
   }
 }catch(err){
   if(pendingPaymentToken){await lookupPending(pendingPaymentToken);navigate('booking');}
   notify('Payment confirmation is pending. Please check again or contact our team.');
 }
}

function init(){
 renderServices();
 $('#faqList').innerHTML=faqs.map(([q,a],i)=>`<div class="faq-item ${i===0?'open':''}"><button class="faq-q" aria-expanded="${i===0}"><span>${q}</span><span>+</span></button><div class="faq-a">${a}</div></div>`).join('');
 $$('.faq-q').forEach(b=>b.addEventListener('click',()=>{const item=b.parentElement,willOpen=!item.classList.contains('open');$$('.faq-item').forEach(x=>{x.classList.remove('open');x.querySelector('.faq-q').setAttribute('aria-expanded','false')});if(willOpen){item.classList.add('open');b.setAttribute('aria-expanded','true')}}));
 $('#year').textContent=new Date().getFullYear();
 $$('[data-page]').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();navigate(a.dataset.page)}));
 $('#menuToggle').addEventListener('click',()=>{const open=$('#navlinks').classList.toggle('open');$('#menuToggle').setAttribute('aria-expanded',String(open))});
 document.addEventListener('click',ev=>{const b=ev.target.closest('[data-choose]');if(b){state.service=b.dataset.choose;state.step=0;updateWizard();navigate('booking')}
 const f=ev.target.closest('[data-fault]');if(f){state.service=f.dataset.fault;updateWizard()}});
 $$('.filter').forEach(b=>b.addEventListener('click',()=>{$$('.filter').forEach(x=>x.classList.remove('active'));b.classList.add('active');filterServices(b.dataset.filter)}));
 $('#neighborhood').addEventListener('change',e=>{state.neighborhood=e.target.value;updateWizard()});
 $('#otherCity').addEventListener('input',updateWizard);
 $('#backStep').addEventListener('click',()=>{state.step=Math.max(0,state.step-1);updateWizard()});
 $('#nextStep').addEventListener('click',async()=>{if(!validate())return;if(state.step===3)await saveJob();else{state.step++;updateWizard()}});
 $('#copyCode').addEventListener('click',async()=>{if(!lastCode)return;try{await navigator.clipboard.writeText(lastCode);notify('Booking reference copied!')}catch{notify('Reference: '+lastCode)}});
 $('#viewJob').addEventListener('click',async()=>{if(!lastCode)return;$('#trackingCode').value=lastCode;navigate('jobs');await lookupJob(lastCode)});
 $('#newBooking').addEventListener('click',()=>{state.step=0;['address','zip','details','firstName','lastName','phone','email'].forEach(id=>$('#'+id).value='');if($('#whatsappOptIn'))$('#whatsappOptIn').checked=false;updateWizard()});
 $('#trackingForm').addEventListener('submit',async e=>{e.preventDefault();await lookupJob($('#trackingCode').value.trim().toUpperCase())});
 $('#payBooking').addEventListener('click',()=>openPayment());
 $('#resumePendingPayment')?.addEventListener('click',async()=>{if(pendingPaymentToken){const b=await lookupPending(pendingPaymentToken);if(b){navigate('booking');await openPayment();}}});
 $('#closePayment').addEventListener('click',closePayment);
 $('#cancelPayment').addEventListener('click',closePayment);
 $('#paymentModal').addEventListener('click',ev=>{if(ev.target===$('#paymentModal'))closePayment()});
 document.addEventListener('keydown',ev=>{if(ev.key==='Escape')closePayment()});
 $$('[data-pay-option]').forEach(b=>b.addEventListener('click',()=>{payChoice=b.dataset.payOption;updateCheckoutAmounts()}));
 $('#stripePay').addEventListener('click',startStripeCheckout);
 window.addEventListener('hashchange',()=>showPage(location.hash.slice(1)||'home'));
 showPage(location.hash.slice(1)||'home',false);updateWizard();updatePaymentButton();showPendingResume();
 loadSettings();
 handleStripeReturn();
}

const seasonalTips={rain:{icon:'🌧',heading:'Keep an eye on hidden moisture.',body:'Check around sinks, under cabinets and near water heaters for damp spots. If a leak is active, use the appropriate shutoff valve and request professional help.'},winter:{icon:'❄',heading:'Give exposed pipes some protection.',body:'Cold snaps can affect exposed plumbing. Know where the water shutoff is, protect outdoor faucets appropriately, and contact a licensed professional if a pipe freezes.'},summer:{icon:'☀',heading:'Make outdoor water use count.',body:'Inspect hose connections and outdoor spigots for drips, and check for unusually high water use that may signal a hidden leak.'}};
document.querySelectorAll('[data-quick-city]').forEach(btn=>btn.addEventListener('click',()=>{state.neighborhood=btn.dataset.quickCity;state.step=1;updateWizard();navigate('booking')}));
document.querySelectorAll('[data-season]').forEach(tab=>tab.addEventListener('click',()=>{
document.querySelectorAll('[data-season]').forEach(t=>{const selected=t===tab;t.classList.toggle('active',selected);t.setAttribute('aria-selected',String(selected))});const tip=seasonalTips[tab.dataset.season];document.getElementById('seasonPanel').innerHTML='<div class="season-icon">'+tip.icon+'</div><h3>'+tip.heading+'</h3><p>'+tip.body+'</p>'}));
init();