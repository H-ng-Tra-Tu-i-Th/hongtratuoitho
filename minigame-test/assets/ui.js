'use strict';
(() => {
 const base=location.pathname.replace(/[^/]*$/,'');
 const key='kyuc10:'+base+':lang';
 let lang='vi'; try{lang=localStorage.getItem(key)||'vi'}catch{}
 if(!['vi','zh'].includes(lang))lang='vi';
 const icons={
 plane:'<path d="m5 29 38-22-12 38-9-13-17-3Z"/><path d="m22 32 21-25M22 32l-2 10 7-5"/>',
 top:'<path d="M26 5v8M9 21c0-6 34-6 34 0S9 27 9 21Z"/><path d="M9 21c3 13 17 22 17 26 0-4 14-13 17-26M13 30c9 4 17 4 26 0M19 38h14"/>',
 marbles:'<circle cx="19" cy="31" r="12"/><circle cx="35" cy="17" r="12"/><path d="M13 32c7 0 11-3 11-8M31 13c0 5 3 8 8 8M14 23l2-1M30 8l2-1"/>',
 candy:'<path d="m13 22-8-9 13-2 4 7m16 12 8 9-13 2-4-7"/><rect x="15" y="16" width="22" height="22" rx="8" transform="rotate(-32 26 27)"/><path d="m22 20 11 9M17 26l11 9"/>',
 tea:'<path d="m15 19 3 28h21l3-28M12 18h33v-5H12zM31 13l4-10h5l-4 10"/><path d="M18 26h21"/><circle cx="24" cy="35" r="2"/><circle cx="32" cy="40" r="2"/><circle cx="35" cy="32" r="2"/><circle cx="23" cy="43" r="1"/>',
 cup:'<path d="m14 17 4 30h21l4-30H14ZM12 12h33v5H12zM32 12l4-10"/><path d="M19 28c7 4 12-4 20 0"/><path d="M24 38h9"/>',
 friends:'<circle cx="17" cy="19" r="8"/><circle cx="37" cy="20" r="7"/><path d="M3 43v-4c0-14 28-14 28 0v4M32 30c13-2 16 6 16 13M10 16c3-4 8-5 14-1M33 16c3-3 5-3 8 0"/>',
 ticket:'<path d="M5 13h42v10a5 5 0 0 0 0 10v9H5v-9a5 5 0 0 0 0-10V13Z"/><path d="M34 16v3m0 4v3m0 4v3m0 4v2M14 28h10"/>',
 gift:'<path d="M9 24h34v23H9zM5 17h42v8H5zM26 17v30"/><path d="M26 17C5 18 10-5 24 12l2 5ZM26 17c21 1 16-22 2-5l-2 5Z"/>',
 star:'<path d="m26 4 7 14 16 3-12 11 3 16-14-8-14 8 3-16L3 21l16-3 7-14Z"/>',
 check:'<path d="m11 27 10 10 22-24"/>',
 arrow:'<path d="M9 26h32M29 14l12 12-12 12"/>',
 lock:'<rect x="10" y="22" width="32" height="25" rx="6"/><path d="M16 22V13a10 10 0 0 1 20 0v9M26 32v6"/>',
 sound:'<path d="m6 21 10 0 13-10v30L16 31H6zM36 17a13 13 0 0 1 0 18M42 10a22 22 0 0 1 0 32"/>',
 mute:'<path d="m6 21 10 0 13-10v30L16 31H6zM37 21l10 10m0-10L37 31"/>',
 user:'<circle cx="26" cy="17" r="9"/><path d="M8 46v-5c0-19 36-19 36 0v5"/>',
 home:'<path d="m6 24 20-18 20 18M12 21v25h28V21M21 46V31h10v15"/>',
 info:'<circle cx="26" cy="26" r="21"/><path d="M26 24v14M26 14v2"/>',
 close:'<path d="m13 13 26 26m0-26L13 39"/>',
 copy:'<rect x="17" y="17" width="27" height="29" rx="5"/><path d="M33 17V8H7v28h10"/>',
 clock:'<circle cx="26" cy="28" r="18"/><path d="M26 17v12l9 5M11 5 4 12M41 5l7 7"/>',
 spark:'<path d="m27 6 4 13 14 5-14 5-4 14-5-14-14-5 14-5 5-13ZM44 37v11m-5-5h10"/>',
 logout:'<path d="M23 7H8v38h15M24 26h22L35 15m11 11L35 37"/>',
 dashboard:'<rect x="5" y="6" width="17" height="17" rx="4"/><rect x="30" y="6" width="17" height="27" rx="4"/><rect x="5" y="31" width="17" height="16" rx="4"/><path d="M30 41h17"/>',
 refresh:'<path d="M43 19A19 19 0 1 0 45 32M43 7v13H30"/>',
 plus:'<path d="M26 9v34M9 26h34"/>'
 };
 const errs={
  board_missing:['Chưa có bàn chơi. Nhấn bắt đầu hoặc tải lại bàn chơi.','尚未建立牌局，請按開始或重新載入牌局。'],
  board_changed:['Bàn chơi đã thay đổi. Vui lòng tải lại bàn chơi.','牌局已變更，請重新載入牌局。'],
  matching_incomplete:['Hãy ghép hết 6 cặp trước khi nhận quà.','請先完成全部 6 組配對，再領取獎勵。'],
  invalid:['Thông tin không hợp lệ. Vui lòng kiểm tra lại.','資料格式不正確，請重新確認。'],
  csrf:['Phiên đã thay đổi. Tải lại trang rồi thử lại.','頁面驗證已更新，請重新整理後再試。'],
  login_required:['Vui lòng đăng nhập lại.','請重新登入。'],forbidden:['Bạn không có quyền thực hiện.','您沒有操作權限。'],
  login_failed:['Tài khoản hoặc mật khẩu không đúng, hoặc tài khoản đã bị tạm khóa.','帳號或密碼不正確，或帳號已停用。'],
  username:['Dùng 4–32 ký tự: chữ không dấu, số, dấu chấm, gạch ngang hoặc gạch dưới.','帳號須為 4–32 位英文字母、數字、點、連字號或底線。'],
  username_taken:['Tên tài khoản này đã được sử dụng.','此帳號已有人使用。'],password:['Mật khẩu cần 10–72 ký tự (tối đa 72 byte).','密碼須至少 10 字元，最多 72 位元組。'],
  consent:['Vui lòng nhập tên và đồng ý thể lệ/quyền riêng tư.','請填寫暱稱並同意活動規則與隱私說明。'],
  rate_limit:['Bạn thao tác quá nhanh. Vui lòng chờ một lúc rồi thử lại.','操作過於頻繁，請稍後再試。'],
  campaign_paused:['Chương trình nhận quà thật đang tạm dừng. Ví quà vẫn sử dụng được.','正式活動目前暫停，已有優惠券仍可查看及核銷。'],
  demo_disabled:['Chế độ chơi thử đã tắt.','試玩模式已關閉。'],no_rewards:['Hiện chưa có quà khả dụng. Mã của bạn chưa bị sử dụng.','目前沒有可發獎項，您的遊戲機會尚未使用。'],
  code_invalid:['Mã không hợp lệ, đã bị hủy hoặc không thuộc tài khoản này.','遊戲碼無效、已撤銷，或不屬於目前帳號。'],
  code_expired:['Mã đã hết hạn hoặc chưa được kích hoạt.','遊戲碼已到期或尚未啟用。'],
  confirm_purchase:['Vui lòng xác nhận đơn đã hoàn tất và số ly trả phí.','請確認訂單已完成及實際付費杯數。'],
  order_format:['Mã đơn cần 3–100 ký tự: chữ không dấu, số, . _ - / (không có khoảng trắng).','訂單編號需為 3–100 位英數字或 . _ - /，不可有空白。'],
  member_missing:['Không tìm thấy tài khoản thành viên đang hoạt động.','找不到有效的會員帳號。'],
  order_duplicate:['Đơn này đã được cấp mã. Hãy cấp lại mã cũ thay vì tạo đơn trùng.','此訂單已發過遊戲碼，請使用原紀錄的重新發碼功能。'],
  not_enough_cups:['Chưa đủ số ly trả phí để cấp mã.','付費飲料杯數尚未達門檻。'],
  fallback_required:['Phải có ít nhất 1 quà đang bật, trọng số > 0 và số lượng -1 (không giới hạn).','至少保留 1 項啟用、權重大於 0、庫存 -1（不限量）的保底獎項。'],
  support_required:['Hãy điền thông tin liên hệ trước khi mở chương trình.','正式啟用前請填寫門市聯絡資訊。'],
  coupon_missing:['Không tìm thấy phiếu quà.','找不到此優惠券。'],
  coupon_unusable:['Phiếu đã dùng, hết hạn, bị hủy hoặc tài khoản bị tạm khóa.','優惠券已使用、過期、撤銷，或會員帳號已停用。'],
  next_order_only:['Chỉ dùng cho đơn tiếp theo, không được dùng cho đơn đã cấp mã.','僅限下一筆訂單，不可折抵發放遊戲碼的原訂單。'],
  one_coupon_order:['Đơn này đã dùng một phiếu quà của chương trình.','此訂單已核銷過一張活動優惠券。'],
  minimum_not_met:['Đơn chưa đủ giá trị hoặc số ly trả phí yêu cầu.','訂單金額或付費杯數尚未達到優惠券門檻。'],
  confirm_redeem:['Vui lòng xác nhận chủ tài khoản, điều kiện và không cộng dồn ưu đãi.','請確認會員身分、適用條件及不疊加其他優惠。'],
  https_required:['Hãy mở trang bằng HTTPS trước khi tiếp tục.','請先使用 HTTPS 開啟網站。'],
  server_error:['Không thể lưu dữ liệu. Vui lòng thử lại; không làm mới nhiều lần.','暫時無法儲存資料，請稍後再試。不要連續重新抽取。'],
  not_found:['Không tìm thấy dữ liệu.','找不到資料。'],network:['Mất kết nối. Thử lại để lấy đúng kết quả đã lưu.','網路中斷。重試會取回原本已儲存的結果。'],
  reward_limit:['Tối đa 30 loại quà.','最多可設定 30 種獎項。'],
  discount_invalid:['Giá trị giảm phải lớn hơn 0 và không vượt mức chi tiêu tối thiểu.','折抵金額須大於 0，且不可超過最低消費金額。']
 };
 let csrf=''; let lastFocus=null;
 const U={
  get lang(){return lang}, setLang(v){lang=v;try{localStorage.setItem(key,v)}catch{};document.documentElement.lang=v==='vi'?'vi':'zh-Hant';window.dispatchEvent(new Event('languagechange'));},
  t:(vi,zh)=>lang==='vi'?vi:zh,
  e:v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),
  icon:(name,size=24)=>`<svg width="${size}" height="${size}" viewBox="0 0 52 52" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icons[name]||icons.gift}</svg>`,
  field:(o,k)=>o[k+'_'+lang]||o[k+'_vi']||'',
  money:n=>new Intl.NumberFormat(lang==='vi'?'vi-VN':'zh-TW').format(Number(n))+'đ',
  date:ts=>ts?new Intl.DateTimeFormat(lang==='vi'?'vi-VN':'zh-TW',{timeZone:'Asia/Ho_Chi_Minh',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).format(new Date(ts*1000)):'—',
  err:key=>errs[key]?U.t(...errs[key]):U.t('Không thể thực hiện thao tác.','無法完成此操作。'),
  async api(action,data){
   let r,j;try{r=await fetch('api.php?action='+encodeURIComponent(action),data===undefined?{credentials:'same-origin',cache:'no-store'}:{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(data)});j=await r.json()}catch{throw new Error(U.err('network'))}
   if(j.csrf)csrf=j.csrf;
   if(!r.ok||!j.ok){const ex=new Error(U.err(j.error));ex.code=j.error;throw ex}return j.data;
  },
  toast(msg,bad=false){const el=document.getElementById('toast');el.textContent=msg;el.className='toast visible'+(bad?' error':'');clearTimeout(U.toastTimer);U.toastTimer=setTimeout(()=>el.className='toast',4500)},
  modal(html,cls=''){
   const d=document.getElementById('modal');if(d.open)d.close();lastFocus=document.activeElement;
   d.className='modal '+cls;d.innerHTML=`<button class="icon-btn modal-close" type="button" aria-label="${U.t('Đóng','關閉')}">${U.icon('close',22)}</button>`+html;
   d.querySelector('.modal-close').onclick=()=>d.close();const heading=d.querySelector('h2');if(heading){heading.id='modalTitle';d.setAttribute('aria-labelledby','modalTitle')}else{d.removeAttribute('aria-labelledby')}d.showModal();
   d.addEventListener('close',()=>{d.querySelectorAll('input[type=password]').forEach(i=>i.value='');if(lastFocus?.isConnected)lastFocus.focus()},{once:true});return d;
  },
  close(){document.getElementById('modal').close()},
  async copy(s){try{await navigator.clipboard.writeText(s);U.toast(U.t('Đã sao chép','已複製'))}catch{const d=U.modal(`<h2>${U.t('Sao chép mã','複製代碼')}</h2><input class="code-text" readonly value="${U.e(s)}"><p>${U.t('Nhấn giữ để sao chép.','長按或全選以複製。')}</p>`);d.querySelector('input').select()}},
  buttonBusy(form,busy){form.querySelectorAll('button[type=submit]').forEach(b=>{b.disabled=busy})},
  formError(form,msg){let e=form.querySelector('.form-error');if(!e){e=document.createElement('p');e.className='form-error';e.setAttribute('role','alert');form.prepend(e)}e.textContent=msg},
  status(status){const m={active:['Còn hiệu lực','可使用'],expired:['Đã hết hạn','已到期'],redeemed:['Đã sử dụng','已核銷'],revoked:['Đã hủy','已撤銷'],issued:['Chưa kích hoạt','尚未啟用'],ready:['Sẵn sàng','可翻牌'],played:['Đã chơi','已完成']};return U.t(...(m[status]||[status,status]))},
  async auth(after,register=false,admin=false){
   const d=U.modal(`<div class="modal-brand"><img src="assets/logo.png" alt="Hồng Trà Tuổi Thơ"></div><div class="eyebrow">${admin?'ADMIN ACCESS':U.t('HẸN NHAU Ở MIỀN KÝ ỨC','相約在回憶裡')}</div><h2>${register?U.t('Làm quen nhé!','建立回憶帳號'):U.t('Chào bạn trở lại','歡迎回來')}</h2><p class="muted">${admin?U.t('Đăng nhập bằng tài khoản quản trị viên.','請使用管理員帳號登入。'):U.t('Tài khoản độc lập, không dùng chung với website đặt món.','這是獨立會員帳號，不與訂餐網站共用。')}</p><form id="authForm" class="stack">
   ${register?`<label>${U.t('Biệt danh','暱稱')}<input name="name" maxlength="40" autocomplete="nickname" required></label>`:''}
   <label>${U.t('Tên tài khoản','帳號')}<input name="username" autocapitalize="none" autocomplete="username" minlength="4" maxlength="32" required placeholder="${U.t('Ví dụ: banhmi_01','例如：tea_lover01')}"></label>
   <label>${U.t('Mật khẩu','密碼')}<input name="password" type="password" minlength="${register?10:1}" maxlength="72" autocomplete="${register?'new-password':'current-password'}" required></label>
   ${register?`<small>${U.t('Mật khẩu từ 10–72 ký tự. Không yêu cầu số điện thoại hoặc OTP.','密碼為 10–72 字元。不要求手機號碼或簡訊驗證。')}</small><label class="check"><input name="consent" type="checkbox" required><span>${U.t('Tôi đã đọc và đồng ý thể lệ cùng thông tin quyền riêng tư trên trang này.','我已閱讀並同意本站的活動規則與隱私說明。')}</span></label>`:''}
   <button class="btn primary wide" type="submit">${register?U.t('Tạo tài khoản','建立帳號'):U.t('Đăng nhập','登入')} ${U.icon('arrow',20)}</button></form>
   ${admin?'':`<button id="switchAuth" class="text-btn wide">${register?U.t('Đã có tài khoản? Đăng nhập','已有帳號？登入'):U.t('Chưa có tài khoản? Đăng ký','還沒有帳號？註冊')}</button>`}<p class="fineprint">${U.t('Quên mật khẩu? Liên hệ cửa hàng để được xác minh và đặt lại.','忘記密碼？請洽門市確認身分後重設。')}</p>`);
   const f=d.querySelector('form'); f.onsubmit=async ev=>{ev.preventDefault();U.buttonBusy(f,true);try{const p=Object.fromEntries(new FormData(f));p.consent=p.consent==='on';await U.api(register?'register':'login',p);U.close();await after()}catch(e){U.formError(f,e.message)}finally{U.buttonBusy(f,false)}};
   d.querySelector('#switchAuth')?.addEventListener('click',()=>U.auth(after,!register,admin));
  },
  password(after){
   const d=U.modal(`<h2>${U.t('Đổi mật khẩu','更改密碼')}</h2><form class="stack"><label>${U.t('Mật khẩu hiện tại','目前密碼')}<input type="password" name="old_password" autocomplete="current-password" required></label><label>${U.t('Mật khẩu mới (10–72 ký tự)','新密碼（10–72 字元）')}<input type="password" name="new_password" minlength="10" maxlength="72" autocomplete="new-password" required></label><button class="btn primary wide" type="submit">${U.t('Lưu mật khẩu','儲存密碼')}</button></form>`);
   const f=d.querySelector('form');f.onsubmit=async ev=>{ev.preventDefault();U.buttonBusy(f,true);try{await U.api('password',Object.fromEntries(new FormData(f)));U.close();U.toast(U.t('Đã đổi mật khẩu','密碼已更新'));await after()}catch(e){U.formError(f,e.message)}finally{U.buttonBusy(f,false)}};
  }
 };
 window.U=U;document.documentElement.lang=lang==='vi'?'vi':'zh-Hant';
})();
