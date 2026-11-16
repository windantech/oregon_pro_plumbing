'use strict';
// WhatsApp inbox for the staff portal. Polls the server; no external libraries.
(()=>{
  const $=id=>document.getElementById(id);
  if(!$('tab-whatsapp'))return;
  const cfg=window.OPP_PHONE||{};
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const when=s=>{if(!s)return'';const d=new Date(String(s).replace(' ','T')+'Z');if(isNaN(d))return s;
    const today=new Date().toDateString()===d.toDateString();
    return today?d.toLocaleTimeString([], {hour:'numeric',minute:'2-digit'}):d.toLocaleString([], {month:'short',day:'numeric',hour:'numeric',minute:'2-digit'});};
  const linkify=t=>esc(t).replace(/(https:\/\/[^\s<]+)/g,'<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>').replace(/\n/g,'<br>');
  const ticks={queued:'🕓',accepted:'🕓',sending:'🕓',sent:'✓',delivered:'✓✓',read:'<span class="wa-read">✓✓</span>',failed:'⚠ Failed',undelivered:'⚠ Not delivered'};
  const toast=(msg,err=false)=>{const el=$('toast');if(!el)return;el.textContent=msg;el.className='toast show'+(err?' error':'');setTimeout(()=>{el.className='toast'},5000);};
  let threads=[],current='',activeTab=false,lastSig='',lastUnread=-1,sending=false;
  const baseTitle=document.title;

  async function getJSON(url){const r=await fetch(url,{credentials:'same-origin',cache:'no-store'});const j=await r.json().catch(()=>({}));if(!r.ok)throw new Error(j.error||'Request failed');return j;}

  function renderThreads(){
    const f=($('waFilter').value||'').toLowerCase().trim();
    const list=threads.filter(t=>!f||t.contact.includes(f.replace(/[^\d+]/g,'')||'§')||(t.name||'').toLowerCase().includes(f));
    $('waThreads').innerHTML=list.length?list.map(t=>`<button type="button" class="wa-thread${t.contact===current?' active':''}" data-contact="${esc(t.contact)}">
      <span class="wa-avatar">${esc((t.name||'#').trim().charAt(0).toUpperCase())}</span>
      <span class="wa-thread-main"><span class="wa-thread-top"><b>${esc(t.name||t.contact)}</b><small>${esc(when(t.at))}</small></span>
      <span class="wa-thread-bottom"><span class="wa-preview">${t.direction==='out'?'You: ':''}${esc(t.preview)}</span>${t.unread?`<span class="wa-count">${t.unread}</span>`:''}</span></span></button>`).join('')
      :`<p class="wa-empty">${threads.length?'No matches.':'No WhatsApp messages yet. When a customer messages your WhatsApp number it will appear here.'}</p>`;
  }
  function setBadge(n){
    const b=$('waBadge');if(b){b.hidden=!n;b.textContent=n>99?'99+':String(n);}
    document.title=(n?'('+n+') ':'')+baseTitle;
  }
  async function loadThreads(){
    try{const j=await getJSON('api/whatsapp.php?op=threads');threads=j.threads||[];
      $('waNotReady').hidden=!!j.ready;
      if(lastUnread>=0&&j.unread>lastUnread){const t=threads.find(x=>x.unread>0);toast('New WhatsApp message'+(t?' from '+(t.name||t.contact):''));}
      lastUnread=j.unread;setBadge(j.unread);renderThreads();
    }catch(e){if(activeTab)$('waThreads').innerHTML=`<p class="wa-empty">${esc(e.message)}</p>`;}
  }
  function bubble(m){
    const media=(m.media||[]).map(f=>f.type.startsWith('image/')?`<a href="${esc(f.url)}" target="_blank" rel="noopener"><img src="${esc(f.url)}" alt="Customer photo" loading="lazy"></a>`
      :f.type.startsWith('audio/')?`<audio controls preload="none" src="${esc(f.url)}"></audio>`
      :f.type.startsWith('video/')?`<video controls preload="none" src="${esc(f.url)}"></video>`
      :`<a class="wa-file" href="${esc(f.url)}" target="_blank" rel="noopener">📎 Open attachment (${esc(f.type)})</a>`).join('');
    const meta=m.direction==='out'?`${m.agent?esc(m.agent)+' · ':''}${esc(when(m.at))} ${ticks[m.status]||''}${m.error?' (error '+esc(m.error)+')':''}`:esc(when(m.at));
    return `<div class="wa-msg ${m.direction==='out'?'out':'in'}">${media}${m.body?`<div class="wa-body">${linkify(m.body)}</div>`:''}<small>${meta}</small></div>`;
  }
  async function loadMessages(scrollForce=false){
    if(!current)return;
    try{const j=await getJSON('api/whatsapp.php?op=messages&contact='+encodeURIComponent(current));
      const box=$('waMessages');const sig=JSON.stringify(j.messages.map(m=>[m.id,m.status]));
      if(sig!==lastSig){const near=box.scrollHeight-box.scrollTop-box.clientHeight<120;
        box.innerHTML=j.messages.length?j.messages.map(bubble).join(''):'<p class="wa-empty">No messages.</p>';
        if(near||scrollForce)box.scrollTop=box.scrollHeight;lastSig=sig;}
      const open=j.windowOpen&&j.ready;
      $('waText').disabled=!open;$('waSend').disabled=!open||sending;
      $('waNote').textContent=!j.ready?'Replies need WhatsApp settings to be completed by an administrator.'
        :j.windowOpen?'Reply window open until '+when(new Date(new Date(j.lastInbound.replace(' ','T')+'Z').getTime()+864e5).toISOString().slice(0,19).replace('T',' '))+'.'
        :'The 24-hour reply window has closed. WhatsApp only lets you reply after the customer messages again, or by phone.';
      const t=threads.find(x=>x.contact===current);if(t&&t.unread){t.unread=0;lastUnread=Math.max(0,lastUnread-1);renderThreads();loadThreads();}
    }catch(e){toast(e.message,true);}
  }
  function openThread(contact){
    current=contact;lastSig='';const t=threads.find(x=>x.contact===contact)||{};
    $('waName').textContent=t.name||contact;$('waNumber').textContent=t.name?contact:'WhatsApp';
    const call=$('waCall');call.hidden=false;call.onclick=e=>{e.preventDefault();const d=$('dialNumber');if(d)d.value=contact;document.querySelector('[data-tab="dialer"]')?.click();};
    $('waLayout').classList.add('show-chat');$('waMessages').innerHTML='<p class="wa-empty">Loading…</p>';
    renderThreads();loadMessages(true);
  }
  $('waThreads').addEventListener('click',e=>{const b=e.target.closest('[data-contact]');if(b)openThread(b.dataset.contact);});
  $('waFilter').addEventListener('input',renderThreads);
  $('waBack').addEventListener('click',()=>$('waLayout').classList.remove('show-chat'));
  $('waRefresh').addEventListener('click',()=>{loadThreads();loadMessages();});
  $('waText').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();$('waReply').requestSubmit();}});
  $('waReply').addEventListener('submit',async e=>{
    e.preventDefault();const text=$('waText').value.trim();if(!text||!current||sending)return;
    sending=true;$('waSend').disabled=true;$('waSend').textContent='Sending…';
    try{const fd=new FormData();fd.append('contact',current);fd.append('body',text);
      const r=await fetch('api/whatsapp.php',{method:'POST',headers:{'X-CSRF-Token':cfg.csrf},credentials:'same-origin',body:fd});
      const j=await r.json().catch(()=>({}));if(!r.ok||!j.ok)throw new Error(j.error||'Message not sent.');
      $('waText').value='';await loadMessages(true);loadThreads();
    }catch(err){toast(err.message,true);}
    finally{sending=false;$('waSend').textContent='Send';$('waSend').disabled=$('waText').disabled;}
  });
  document.addEventListener('opp:tab',e=>{activeTab=e.detail==='whatsapp';if(activeTab){loadThreads();loadMessages();}});
  loadThreads();
  setInterval(()=>{if(!document.hidden)loadThreads();},15000);
  setInterval(()=>{if(activeTab&&current&&!document.hidden)loadMessages();},6000);
})();
