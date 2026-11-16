'use strict';
(()=>{
  const $=(id)=>document.getElementById(id);
  const all=(selector)=>Array.from(document.querySelectorAll(selector));
  const cfg=window.OPP_PHONE || {};
  let device=null, call=null, muted=false, started=false, inbound=null, toastTimer=null;
  const tabs={overview:'Overview',dialer:'Phone & Dialer',history:'Call History',whatsapp:'WhatsApp',team:'Team Members',settings:'Settings'};
  const toast=(msg,isError=false)=>{const el=$('toast');if(!el)return;el.textContent=msg;el.className='toast show'+(isError?' error':'');clearTimeout(toastTimer);toastTimer=setTimeout(()=>{el.className='toast'},5300);};
  const setStatus=(msg,online=false)=>{
    if($('deviceStatus'))$('deviceStatus').textContent=msg;
    if($('sideStatus'))$('sideStatus').textContent=msg;
    if($('cardStatus'))$('cardStatus').textContent=online?'Online':'Offline';
    if($('cardStatusNote'))$('cardStatusNote').textContent=online?'Ready for incoming calls':'Your phone is not registered';
    $('deviceLed')?.classList.toggle('live',online);
    document.querySelector('.pulse-ring')?.classList.toggle('live',online);
    if($('startPhoneText'))$('startPhoneText').textContent=online?'Restart phone':'Start phone';
    if($('dialBtn'))$('dialBtn').disabled=!online||Boolean(call);
  };
  const showCall=(title,description)=>{if($('callTitle'))$('callTitle').textContent=title;if($('callDescription'))$('callDescription').textContent=description;};
  const uiCall=(incoming=false,active=false)=>{
    if($('incomingActions'))$('incomingActions').hidden=!incoming;
    if($('liveControls'))$('liveControls').hidden=!active;
    if($('hangupBtn'))$('hangupBtn').disabled=!(incoming||active);
    if($('dialBtn'))$('dialBtn').disabled=!started||incoming||active;
  };
  function go(tab){
    if(!tabs[tab])return;
    all('.tab').forEach(p=>p.classList.toggle('active',p.id==='tab-'+tab));
    all('.nav-item').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));
    if($('breadcrumb'))$('breadcrumb').textContent=tabs[tab];
    $('sidebar')?.classList.remove('open');
    if(tab==='history')refreshCalls();
    document.dispatchEvent(new CustomEvent('opp:tab',{detail:tab}));
    window.scrollTo({top:0,behavior:'smooth'});
  }
  all('[data-tab]').forEach(b=>b.addEventListener('click',()=>go(b.dataset.tab)));
  all('[data-go]').forEach(b=>b.addEventListener('click',()=>go(b.dataset.go)));
  $('mobileMenu')?.addEventListener('click',()=>$('sidebar')?.classList.toggle('open'));
  $('overviewStart')?.addEventListener('click',()=>go('dialer'));
  all('[data-copy]').forEach(b=>b.addEventListener('click',async()=>{
    const value=$(b.dataset.copy)?.textContent||'';
    try{await navigator.clipboard.writeText(value);toast('Webhook URL copied.');}catch{toast('Copy the URL manually.',true);}
  }));
  async function getToken(){
    const res=await fetch('api/token.php',{credentials:'same-origin',cache:'no-store'});
    const json=await res.json().catch(()=>({}));
    if(!res.ok||!json.token)throw new Error(json.error||'Unable to get a Twilio access token.');
    return json.token;
  }
  function attachCallListeners(item){
    item.on('accept',()=>{call=item;inbound=null;uiCall(false,true);showCall('Connected','You are now speaking through Oregon Pro Plumbing.');});
    item.on('disconnect',()=>endView('Call ended'));
    item.on('cancel',()=>endView('Caller hung up'));
    item.on('reject',()=>endView('Call declined'));
    item.on('error',err=>{toast(err.message||'Call connection error',true);endView('Call failed');});
  }
  function endView(reason){
    call=null;inbound=null;muted=false;uiCall(false,false);showCall(reason,'Your phone is available for another call.');
    setTimeout(()=>{if(!call&&!inbound)showCall('No active call','Calls will appear here when you dial or receive a call.');},3500);
    refreshCalls();
  }
  async function startPhone(){
    go('dialer');
    if(!cfg.sdk||!window.Twilio?.Device){
      toast('The Twilio Voice SDK is not installed yet. Ask your administrator to upload twilio.min.js in Settings.',true);return;
    }
    if(!window.isSecureContext){toast('Voice calls require HTTPS and a secure browser connection.',true);return;}
    if(!cfg.ready){toast('Twilio API keys and TwiML App have not been configured.',true);return;}
    try{
      if(device){try{device.destroy();}catch{}device=null;}
      setStatus('Connecting…');
      const token=await getToken();
      device=new window.Twilio.Device(token,{logLevel:0,closeProtection:true});
      device.on('registering',()=>setStatus('Connecting…'));
      device.on('registered',()=>{started=true;setStatus('Ready for calls',true);toast('Your browser phone is ready.');});
      device.on('unregistered',()=>{started=false;setStatus('Disconnected');});
      device.on('error',err=>{toast('Twilio: '+(err.message||'Connection error'),true);setStatus('Connection error');});
      device.on('incoming',incoming=>{
        inbound=incoming;call=incoming;
        attachCallListeners(incoming);
        const from=incoming.parameters?.From||'Unknown caller';
        showCall('Incoming call',String(from)+' is calling your business.');
        uiCall(true,false);
        go('dialer');
        toast('Incoming call from '+String(from));
      });
      device.on('tokenWillExpire',async()=>{
        try{const fresh=await getToken();await device?.updateToken(fresh);}catch(err){toast('Unable to renew phone session: '+err.message,true);}
      });
      await device.register();
    }catch(e){started=false;setStatus('Unable to connect');toast(e.message||'Unable to start phone.',true);}
  }
  $('startPhone')?.addEventListener('click',startPhone);
  $('answerBtn')?.addEventListener('click',()=>{if(!inbound)return;try{inbound.accept();showCall('Connecting caller…','Please wait while the call connects.');uiCall(false,true);}catch(e){toast(e.message,true);}});
  $('rejectBtn')?.addEventListener('click',()=>{if(inbound){inbound.reject();endView('Call declined');}});
  const hangup=()=>{if(call){call.disconnect();endView('Call ended');}};
  $('hangupBtn')?.addEventListener('click',hangup);$('endCallBtn')?.addEventListener('click',hangup);
  $('muteBtn')?.addEventListener('click',()=>{if(!call)return;muted=!muted;try{call.mute(muted);$('muteBtn').textContent=muted?'🎙 Unmute':'🎙 Mute';}catch(e){toast(e.message,true);}});
  async function dial(){
    if(!device||!started||call){toast('Start your browser phone and finish any active call first.',true);return;}
    const raw=($('dialNumber')?.value||'').replace(/[\s().-]/g,'');
    if(!/^\+1[2-9]\d{2}[2-9]\d{6}$/.test(raw)||/^\+1(?:900|976)/.test(raw)){
      toast('Enter a standard US/Canada number beginning +1, such as +15035550123.',true);return;
    }
    try{
      showCall('Calling '+raw,'Connecting through your Oregon Pro Plumbing business number…');
      if($('dialBtn'))$('dialBtn').disabled=true;
      const c=await device.connect({params:{To:raw}});
      call=c;uiCall(false,true);attachCallListeners(c);
    }catch(e){toast('Unable to dial: '+e.message,true);endView('Unable to dial');}
  }
  $('dialBtn')?.addEventListener('click',dial);
  $('dialNumber')?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();dial();}});
  all('[data-digit]').forEach(b=>b.addEventListener('click',()=>{
    const digit=b.dataset.digit;if(call&&typeof call.sendDigits==='function'){
      try{call.sendDigits(digit);}catch(e){toast(e.message,true);}return;
    }
    const input=$('dialNumber');if(!input)return;
    const char=digit==='0'&&input.value.trim()===''?'+':digit;
    const pos=input.selectionStart??input.value.length;
    input.value=input.value.slice(0,pos)+char+input.value.slice(input.selectionEnd??pos);
    input.focus();input.setSelectionRange(pos+char.length,pos+char.length);
  }));
  async function refreshCalls(){
    const table=$('callsTable');if(!table)return;
    try{const r=await fetch('api/calls.php',{credentials:'same-origin',cache:'no-store'});if(!r.ok)throw Error('Unable to load calls');
      const j=await r.json();const rows=Array.isArray(j.calls)?j.calls:[];
      const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
      table.innerHTML=rows.length?rows.map(x=>`<tr><td>${esc(x.direction)}</td><td>${esc(x.from_number)}</td><td>${esc(x.to_number)}</td><td><span class="table-state">${esc(x.status)}</span></td><td>${esc(x.created_at)}</td></tr>`).join(''):'<tr><td colspan="5" class="empty-cell">No calls recorded yet.</td></tr>';
    }catch(e){table.innerHTML='<tr><td colspan="5" class="empty-cell">Unable to load call history. Please try again.</td></tr>';}
  }
  $('refreshCalls')?.addEventListener('click',refreshCalls);
  all('.admin-action').forEach(form=>form.addEventListener('submit',async e=>{
    e.preventDefault();const submit=form.querySelector('[type=submit]');if(submit)submit.disabled=true;
    try{const r=await fetch('api/admin.php',{method:'POST',headers:{'X-CSRF-Token':cfg.csrf},credentials:'same-origin',body:new FormData(form)});
      const j=await r.json().catch(()=>({}));if(!r.ok||!j.ok)throw new Error(j.error||'Unable to save changes.');
      toast(j.message||'Saved successfully.');setTimeout(()=>location.reload(),1100);
    }catch(err){toast(err.message||'Unable to save',true);if(submit)submit.disabled=false;}
  }));
  uiCall(false,false);
  setStatus('Phone offline',false);
  if(!cfg.sdk&&cfg.admin){console.info('Upload the official Twilio Voice SDK to assets/vendor/twilio.min.js using Admin Settings.');}
})();
