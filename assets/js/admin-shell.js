(() => {
 const shell=document.querySelector('.admin-shell');if(!shell)return;
 const button=document.querySelector('.admin-menu-button'),sidebar=document.getElementById('admin-sidebar'),overlay=document.querySelector('.admin-sidebar-overlay');
 if(button&&sidebar&&overlay){
  shell.classList.add('js-admin-shell');button.hidden=false;
  const mobile=()=>window.matchMedia('(max-width:850px)').matches;
  const sync=()=>{const open=mobile()?shell.classList.contains('nav-mobile-open'):!shell.classList.contains('nav-collapsed');button.setAttribute('aria-expanded',String(open));sidebar.inert=!open;overlay.hidden=!(mobile()&&open);};
  try{if(localStorage.getItem('huiyi-admin-nav-collapsed')==='1')shell.classList.add('nav-collapsed');}catch(_){}
  button.addEventListener('click',()=>{if(mobile())shell.classList.toggle('nav-mobile-open');else{shell.classList.toggle('nav-collapsed');try{localStorage.setItem('huiyi-admin-nav-collapsed',shell.classList.contains('nav-collapsed')?'1':'0');}catch(_){}}sync();});
  const close=()=>{shell.classList.remove('nav-mobile-open');sync();button.focus();};overlay.addEventListener('click',close);
  sidebar.addEventListener('click',event=>{if(mobile()&&event.target.closest('a')){shell.classList.remove('nav-mobile-open');sync();}});
  document.addEventListener('keydown',event=>{if(!mobile()||!shell.classList.contains('nav-mobile-open'))return;if(event.key==='Escape'){event.preventDefault();close();}else if(event.key==='Tab'){const list=[button,...sidebar.querySelectorAll('a[href],button:not([disabled])')];const first=list[0],last=list[list.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}}});
  const openMainMenu=()=>{if(location.hash!=='#admin-sidebar')return;if(mobile())shell.classList.add('nav-mobile-open');else shell.classList.remove('nav-collapsed');sync();const first=sidebar.querySelector('a[href]');first?.focus({preventScroll:true});};
  window.addEventListener('resize',sync);window.addEventListener('hashchange',openMainMenu);sync();openMainMenu();
 }
 const search=document.querySelector('[data-admin-account-search]'),cards=[...document.querySelectorAll('[data-admin-account-card]')];
 if(search)search.addEventListener('input',()=>{const query=search.value.trim().toLocaleLowerCase();for(const card of cards)card.hidden=!card.dataset.search.toLocaleLowerCase().includes(query);});
 for(const form of document.querySelectorAll('[data-admin-delete]'))form.addEventListener('submit',event=>{if(!window.confirm(form.dataset.adminDelete))event.preventDefault();});
})();
