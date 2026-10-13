(() => {
 const lang=document.documentElement.lang||'vi';
 const labels=lang.startsWith('zh')?{hint:'左右滑動查看完整欄位',table:'可左右捲動的資料表'}:lang.startsWith('en')?{hint:'Swipe horizontally to view all columns',table:'Horizontally scrollable data table'}:{hint:'Vuốt ngang để xem đầy đủ các cột',table:'Bảng dữ liệu cuộn ngang'};
 const wraps=[...document.querySelectorAll('.admin-shell .table-wrap')].filter(e=>e.querySelector('table')&&!e.querySelector('td[data-label]'));
 for(const wrap of wraps){
  const hint=document.createElement('div');hint.className='table-scroll-hint';hint.hidden=true;
  const arrow=document.createElement('span');arrow.textContent='↔';arrow.setAttribute('aria-hidden','true');
  const text=document.createElement('span');text.textContent=labels.hint;hint.append(arrow,text);wrap.prepend(hint);
  const sync=()=>{const wide=wrap.scrollWidth>wrap.clientWidth+1;hint.hidden=!wide;if(wide){wrap.tabIndex=0;wrap.setAttribute('role','region');wrap.setAttribute('aria-label',labels.table);}else{wrap.removeAttribute('tabindex');wrap.removeAttribute('role');wrap.removeAttribute('aria-label');}};
  sync();if('ResizeObserver' in window){const observer=new ResizeObserver(sync);observer.observe(wrap);observer.observe(wrap.querySelector('table'));}else window.addEventListener('resize',sync);
 }
})();
