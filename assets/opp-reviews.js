'use strict';
(function(){
 const grid=document.getElementById('oppReviewsGrid');
 const dialog=document.getElementById('oppReviewDialog');
 if(!grid||!dialog)return;
 const form=document.getElementById('oppReviewForm');
 const feedback=document.getElementById('oppReviewFormFeedback');
 const esc=x=>String(x??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const open=()=>{if(typeof dialog.showModal==='function')dialog.showModal();else dialog.setAttribute('open','');};
 const close=()=>{if(typeof dialog.close==='function')dialog.close();else dialog.removeAttribute('open');};
 document.getElementById('oppOpenReview')?.addEventListener('click',open);
 document.getElementById('oppEmptyReviewButton')?.addEventListener('click',open);
 document.getElementById('oppCloseReview')?.addEventListener('click',close);
 dialog.addEventListener('click',e=>{if(e.target===dialog)close()});
 document.getElementById('oppReviewPrev')?.addEventListener('click',()=>grid.scrollBy({left:-grid.clientWidth*.85,behavior:'smooth'}));
 document.getElementById('oppReviewNext')?.addEventListener('click',()=>grid.scrollBy({left:grid.clientWidth*.85,behavior:'smooth'}));
 function card(review){const name=String(review.name||'Customer');const initial=name.trim().charAt(0).toUpperCase();
  const stars='★'.repeat(Math.min(5,Math.max(1,Number(review.rating)||1)))+'☆'.repeat(5-Math.min(5,Math.max(1,Number(review.rating)||1)));
  return `<article class="opp-review-card"><div class="opp-review-card-head"><span class="opp-review-avatar" aria-hidden="true">${esc(initial)}</span><div class="opp-review-meta"><strong>${esc(name)}</strong><span>${esc(review.city)}${review.city?', Oregon':''}</span></div></div><div class="opp-review-card-body"><div class="opp-review-stars" aria-label="Rating ${Number(review.rating)} out of 5">${stars}</div><p class="opp-review-quote">“${esc(review.text)}”</p><div class="opp-review-city">Customer feedback</div></div></article>`;
 }
 async function load(){try{const r=await fetch('api/reviews.php',{cache:'no-store',credentials:'same-origin'});if(!r.ok)throw Error('Unable to read reviews.');const j=await r.json();
 const reviews=Array.isArray(j.reviews)?j.reviews:[];
 if(reviews.length){
 grid.innerHTML=reviews.map(card).join('');
 document.getElementById('oppReviewCount').textContent=reviews.length+' approved customer review'+(reviews.length===1?'':'s');
 document.getElementById('opp-reviews-heading').textContent='What Our Customers Say';
 document.getElementById('oppReviewSubtitle').textContent='Feedback shared by customers and approved for publication.';
 document.getElementById('oppReviewDisclosure')?.remove();
 } else document.getElementById('oppReviewCount').textContent='5 illustrative service stories';
 }catch{document.getElementById('oppReviewCount').textContent='5 illustrative service stories';}}
 form.addEventListener('submit',async e=>{e.preventDefault();feedback.textContent='';if(!form.reportValidity())return;
 const b=document.getElementById('oppReviewSend');b.disabled=true;b.textContent='Submitting…';
 const payload=Object.fromEntries(new FormData(form).entries());payload.consent=document.getElementById('oppReviewConsent').checked;
 try{const res=await fetch('api/reviews.php',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},credentials:'same-origin',body:JSON.stringify(payload)});
 const data=await res.json().catch(()=>({}));if(!res.ok)throw Error(data.error||'Unable to submit review.');
 feedback.style.color='#167b4e';feedback.textContent='Thank you! Your feedback has been received for review.';form.reset();setTimeout(close,1800);
 }catch(err){feedback.style.color='#a43b2e';feedback.textContent=err.message||'Please try again.';}finally{b.disabled=false;b.textContent='Submit review';}}
 );
 // Same-page Reviews nav uses SPA routing; scroll only after the home page has been displayed.
 document.querySelectorAll('[data-scroll]').forEach(link=>link.addEventListener('click',e=>{
 e.preventDefault();const target=link.getAttribute('data-scroll');
 if(location.hash!=='#home')location.hash='home';else if(typeof showPage==='function')showPage('home',false);
 setTimeout(()=>{document.getElementById(target)?.scrollIntoView({behavior:'smooth',block:'start'});},250);
 }));


 function enableMobileAutoplay(){
  if(!window.matchMedia || !grid) return;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const mobileMq = window.matchMedia('(max-width: 720px)');
  let timer = null;
  let resumeTimer = null;
  let userInteractedAt = 0;

  function clearTimers(){
   if(timer){clearInterval(timer);timer=null;}
   if(resumeTimer){clearTimeout(resumeTimer);resumeTimer=null;}
  }

  function getStep(){
   const card = grid.querySelector('.opp-review-card');
   if(!card) return Math.max(grid.clientWidth * 0.85, 1);
   const styles = getComputedStyle(grid);
   const gap = parseFloat(styles.columnGap || styles.gap || 0) || 0;
   return card.getBoundingClientRect().width + gap;
  }

  function goNext(){
   if(!mobileMq.matches || reduceMotion.matches || document.hidden) return;
   const step = getStep();
   const maxScroll = Math.max(0, grid.scrollWidth - grid.clientWidth);
   if(step <= 0 || maxScroll <= 0) return;
   if(grid.scrollLeft + step >= maxScroll - 6){
    grid.scrollTo({left:0, behavior:'smooth'});
   } else {
    grid.scrollBy({left:step, behavior:'smooth'});
   }
  }

  function scheduleResume(){
   if(!mobileMq.matches || reduceMotion.matches) return;
   if(resumeTimer) clearTimeout(resumeTimer);
   resumeTimer = setTimeout(start, 6000);
  }

  function noteInteraction(){
   userInteractedAt = Date.now();
   clearTimers();
   scheduleResume();
  }

  function start(){
   clearTimers();
   if(!mobileMq.matches || reduceMotion.matches || document.hidden) return;
   if(Date.now() - userInteractedAt < 5000){
    scheduleResume();
    return;
   }
   timer = setInterval(goNext, 4500);
  }

  ['touchstart','pointerdown','wheel','keydown'].forEach(evt=>grid.addEventListener(evt, noteInteraction, {passive:true}));
  document.getElementById('oppReviewPrev')?.addEventListener('click', noteInteraction);
  document.getElementById('oppReviewNext')?.addEventListener('click', noteInteraction);
  document.addEventListener('visibilitychange', ()=>{ if(document.hidden) clearTimers(); else start(); });
  if(mobileMq.addEventListener){ mobileMq.addEventListener('change', start); reduceMotion.addEventListener('change', start); }
  else if(mobileMq.addListener){ mobileMq.addListener(start); reduceMotion.addListener(start); }
  start();
 }

 load();
 enableMobileAutoplay();
})();
