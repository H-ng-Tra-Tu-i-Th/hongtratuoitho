'use strict';
(() => {
const {t,e,icon}=U;
const app=document.getElementById('app');
const memories=[['plane','Máy bay giấy','紙飛機'],['top','Con quay','陀螺'],['marbles','Viên bi','彈珠'],['candy','Kẹo tuổi thơ','童年糖果'],['tea','Ly trà thân quen','熟悉的茶香'],['friends','Đôi bạn nhỏ','童年好朋友']];
const S={data:null,page:'home',mode:'demo',game:null,busy:false,sound:false,wallet:null,boards:{},results:{},boardErrors:{},loadingKey:null,notice:'ready'};
const money=U.money,date=U.date;
const field=U.field;
function navButton(page,vi,zh,name){return `<button class="nav-btn ${S.page===page?'active':''}" data-page="${page}">${icon(name,18)}${t(vi,zh)}</button>`}
function header(){return `<header class="site-header"><a href="./" class="brand" aria-label="Hồng Trà Tuổi Thơ"><img src="assets/logo.png" alt="Hồng Trà Tuổi Thơ"><span class="brand-label">KÝ ỨC<br>10 LY</span></a><nav class="desktop-nav" aria-label="${t('Điều hướng','主要導覽')}">${navButton('home','Trò chơi','翻牌遊戲','home')}${navButton('wallet','Ví quà của tôi','我的優惠券','ticket')}${navButton('rules','Thể lệ','活動規則','info')}</nav><div class="header-tools"><div class="lang-switch" aria-label="Language"><button data-lang="vi" class="${U.lang==='vi'?'active':''}">VI</button><button data-lang="zh" class="${U.lang==='zh'?'active':''}">繁中</button></div><button class="user-btn" id="accountBtn">${icon('user',18)}<span>${S.data.user?e(S.data.user.name):t('Đăng nhập','登入')}</span></button></div></header>`}
function footer(){return `<footer class="site-footer"><div>© HỒNG TRÀ TUỔI THƠ · ${e(field(S.data.settings,'campaign'))}</div><div class="footer-links"><span>${t('Một chút trà. Một trời ký ức.','一口茶香，一整個童年。')}</span><a href="admin.php">${t('Quản trị','管理後台')}</a></div></footer><nav class="mobile-nav" aria-label="${t('Điều hướng','主要導覽')}">${[['home','home','Trò chơi','翻牌遊戲'],['wallet','ticket','Ví quà','我的優惠券'],['rules','info','Thể lệ','活動規則']].map(([page,ic,vi,zh])=>`<button class="${S.page===page?'active':''}" data-page="${page}">${icon(ic,22)}${t(vi,zh)}</button>`).join('')}</nav>`}
function hero(){return `<div class="hero-intro"><div class="eyebrow">${t('MỘT LY TRÀ · MỘT MIỀN KÝ ỨC','一杯紅茶 · 一段美好回憶')}</div><h1>${t('Ghép đôi ký ức,<br>gặp lại <em>tuổi thơ.</em>','配對童年回憶，<br>遇見<em>小時候。</em>')}</h1><p class="lead">${t('Lật hai thẻ, tìm hai hình giống nhau.<br>Ghép hết 6 cặp để mở một món quà ngọt ngào.','翻開兩張卡，找出相同的童年圖案。<br>完成 6 組配對，開啟一份甜甜的驚喜。')}</p><span class="chinese-caption">${t('翻 開 童 年 回 憶','MỞ KÝ ỨC TUỔI THƠ')}</span><div class="mascot-stage"><span class="floating-memory one">${icon('plane',32)}</span><span class="floating-memory two">${icon('candy',30)}</span><span class="floating-memory three">${icon('spark',23)}</span><img class="boy" src="assets/boy.webp" alt="${t('Linh vật bé trai','品牌男孩吉祥物')}" fetchpriority="high"><img class="girl" src="assets/girl.webp" alt="${t('Linh vật bé gái','品牌女孩吉祥物')}" fetchpriority="high"></div><div class="mascot-note">HỒNG TRÀ TUỔI THƠ · YOUR LITTLE HAPPY PLACE</div><div class="proof-pills"><span>${icon('cup',15)}${e(S.data.settings.threshold)} ${t('ly mở khóa','杯解鎖')}</span><span>${icon('gift',15)}${t('Lượt hợp lệ luôn có quà','有效遊戲機會一定有獎')}</span><span>${icon('ticket',15)}${t('Dùng cho lần sau','優惠下次使用')}</span></div></div>`}
function cardGrid(locked){
 const b=currentBoard();
 return `<div id="cards" class="card-grid matching-grid ${locked?'locked':''}" aria-label="${t('12 thẻ, 6 cặp hình giống nhau','12 張卡片，6 組相同圖案')}">${Array.from({length:12},(_,i)=>{
  const gone=b?.matched.includes(i),open=b?.open.includes(i),face=b?.faces?.[i];
  const m=Number.isInteger(face)?memories[face]:null;
  return `<button type="button" class="memory-card ${open?'flipped':''} ${gone?'matched':''}" data-card="${i}" ${locked||gone||open?'disabled':''} ${gone?'tabindex="-1" aria-hidden="true"':''} aria-label="${e(open&&m?t(m[1],m[2]):t('Thẻ úp số ','第 ')+(i+1)+t('',' 張蓋牌'))}" aria-pressed="${!!open}"><span class="card-inner"><span class="card-face card-back"><span class="card-corner">${String(i+1).padStart(2,'0')}</span><span class="card-back-mark">${icon('spark',34)}</span><span class="card-label">KÝ ỨC</span><span class="card-brand">TUỔI THƠ</span><span class="card-star">✦</span></span><span class="card-face card-front">${m?`${icon(m[0],44)}<strong>${e(t(m[1],m[2]))}</strong>`:''}</span></span></button>`;
 }).join('')}</div>`;
}
function claimPanel(){
 if(!S.data.user)return `<div class="claim-panel"><p>${t('Đã có mã chơi từ cửa hàng? Đăng nhập đúng tài khoản được cấp mã để mở quà thật.','已有門市發放的遊戲碼？請登入綁定該遊戲碼的會員帳號，開啟正式獎勵。')}</p><button id="loginReal" class="btn primary wide">${t('Đăng nhập để nhập mã','登入並輸入遊戲碼')}${icon('arrow',18)}</button></div>`;
 if(!S.data.settings.active)return `<div class="notice">${e(U.err('campaign_paused'))}</div>`;
 if(S.game&&S.results[boardKey()])return `<div class="unlock-banner">${icon('check',25)}<div><strong>${t('Đã hoàn thành và lưu quà','配對完成，獎勵已保存')}</strong><p>${e(S.game.order_ref)}</p></div></div>`;
 if(S.game)return `<div class="unlock-banner">${icon('check',25)}<div><strong>${t('Đã mở khóa ký ức!','童年回憶已解鎖！')}</strong><p>${e(S.game.order_ref)} · ${t('1 lượt chơi duy nhất','限一次遊戲機會')}</p></div><span class="count">${e(S.game.threshold)}/${e(S.game.threshold)}</span></div>`;
 return `<div class="claim-panel"><p>${t('Nhập mã một lần do cửa hàng cấp sau khi xác nhận đơn đã hoàn tất.','輸入門市確認訂單完成後，發給您的一次性遊戲碼。')}</p><form id="claimForm"><input name="code" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="60" placeholder="KC-XXXX-XXXX-XXXX-XXXX" aria-label="${t('Mã chơi','遊戲碼')}" required><button class="btn primary" type="submit">${t('Mở khóa','解鎖')}</button></form></div>`;
}
function gameBox(){
 const ready=S.mode==='real'&&S.game&&S.data.settings.active;
 const unavailable=S.mode==='demo'?!S.data.settings.demo:!ready;
 const b=currentBoard(),done=!!b?.complete,hasResult=!!S.results[boardKey()];
 const locked=unavailable||!b||!!S.boardErrors[boardKey()];
 return `<section class="game-box matching-box" aria-label="${t('Trò chơi ghép cặp','翻牌配對遊戲區')}"><div class="box-top"><div class="box-title"><span class="box-icon">${icon('friends',23)}</span>${t('GHÉP ĐÔI KÝ ỨC','童年回憶配對')}</div><button class="icon-btn" id="soundBtn" aria-label="${S.sound?t('Tắt âm thanh','關閉音效'):t('Bật âm thanh','開啟音效')}" aria-pressed="${S.sound}">${icon(S.sound?'sound':'mute',19)}</button></div><div class="mode-switch"><button id="demoMode" class="${S.mode==='demo'?'active':''}">${icon('spark',16)}${t('Thử một chút vui','免費體驗')}</button><button id="realMode" class="${S.mode==='real'?'active':''}">${icon('lock',16)}${t('Tôi có mã chơi','我有遊戲碼')}</button></div>${S.mode==='real'?claimPanel():''}<div class="board-heading"><div><h3>${unavailable?t('Ký ức đang đợi bạn','回憶正在等著你'):t('Tìm hai hình giống nhau','找出兩張相同的圖案')}</h3><p>${t('12 thẻ · 6 cặp · Ghép đúng để xóa thẻ','12 張卡片 · 6 組配對 · 配對成功即可消除')}</p></div><span class="badge ${S.mode==='demo'?'demo':ready?'ready':'issued'}">${S.mode==='demo'?t('CHƠI THỬ','試玩模式'):ready?t('QUÀ THẬT','正式獎勵'):t('CẦN MÃ','需要遊戲碼')}</span></div><div class="matching-stats"><div>${icon('friends',17)}<span>${t('Đã ghép','配對進度')}</span><strong id="matchPairs">${b?.pairs||0}/6</strong></div><div>${icon('refresh',16)}<span>${t('Lượt','配對次數')}</span><strong id="matchMoves">${b?.moves||0}</strong></div><div>${icon('clock',17)}<span>${t('Thời gian','遊戲時間')}</span><strong id="matchTime">${formatTime(boardSeconds(b))}</strong></div></div><progress id="matchProgress" class="matching-progress" max="6" value="${b?.pairs||0}" aria-label="${t('Số cặp đã tìm','已找到的配對數')}"></progress><div class="matching-board-wrap">${cardGrid(locked)}<div id="boardCleared" class="board-cleared" ${!done&&!hasResult?'hidden':''}>${icon('check',52)}<span class="eyebrow">ALL MEMORIES FOUND</span><h3>${t('6 cặp ký ức, trọn niềm vui!','六組回憶，全部找到了！')}</h3><p>${t('Bạn đã hoàn thành trò chơi.','你已完成本局配對。')}</p></div></div><div id="matchNotice" class="matching-notice" role="status" aria-live="polite">${noticeText(unavailable?'locked':done?'complete':'ready')}</div><div class="match-actions" id="matchActions">${boardActions(unavailable)}</div><p class="board-footer">${icon('info',15)}${t('Không giới hạn thời gian · Ghép sai không mất quà','不限時間 · 配對錯誤不影響獎勵')}</p>${S.mode==='demo'?`<p class="demo-disclaimer">${t('Chơi thử không cấp phiếu thật. Ghép hết 6 cặp để xem quà minh họa.','試玩不發放真實優惠券。完成 6 組配對後可查看展示獎勵。')}</p>`:''}</section>`;
}
function home(){return `<main class="game-page"><section class="hero">${hero()}${gameBox()}</section><div class="section-intro"><div><div class="eyebrow">${t('NIỀM VUI RẤT ĐƠN GIẢN','快樂，就是這麼簡單')}</div><h2>${t('Ba bước, một cuộc hẹn mới.','三個小步驟，相約下一次。')}</h2></div><p>${t('Không cần tải ứng dụng. Chơi ngay trên điện thoại.','不必下載 App，手機直接玩。')}</p></div><section class="journey">${[[t('Cùng nhau đủ '+S.data.settings.threshold+' ly','一起買滿 '+S.data.settings.threshold+' 杯'),t('Một đơn đủ số ly trả phí. Cửa hàng xác nhận đơn hoàn tất.','單筆付費飲料達門檻，並由門市確認訂單完成。')],[t('Nhận mã, ghép đôi ký ức','領取遊戲碼，配對童年回憶'),t('Lật mỗi lần hai thẻ. Ghép đúng để xóa thẻ, ghép hết 6 cặp để hoàn thành.','每次翻開兩張卡，相同圖案就會消除；完成 6 組配對即可過關。')],[t('Giữ quà cho lần gặp tới','把好禮留給下一次'),t('Phiếu tự lưu vào ví. Dùng ở lần mua tiếp theo, trong hạn trên phiếu.','優惠券自動存入帳號，於有效期限內的下一次消費使用。')]].map(([h,p],i)=>`<article class="journey-card"><span class="journey-num">${i+1}</span><div><h3>${h}</h3><p>${p}</p></div></article>`).join('')}</section></main>`}
function couponHTML(c){return `<article class="coupon-card"><div class="coupon-main"><div class="coupon-symbol">${icon(c.reward.icon,36)}</div><div class="coupon-details"><span class="badge ${c.status}">${e(U.status(c.status))}</span><h3>${e(field(c.reward,'name'))}</h3><p>${e(field(c.reward,'terms'))}</p></div></div><div class="coupon-bottom"><small>${t('Hạn dùng','有效至')} ${e(date(c.expires_at))}</small><button data-coupon="${e(c.id)}">${t('Xem phiếu','查看優惠券')} →</button></div></article>`}
function wallet(){
 if(!S.data.user)return `<main class="page-main"><div class="page-title"><div><div class="eyebrow">YOUR LITTLE TREASURES</div><h1>${t('Ví quà của tôi','我的回憶優惠券')}</h1></div></div><div class="empty-state">${icon('ticket',54)}<h2>${t('Những niềm vui đang đợi','讓驚喜有個收藏的地方')}</h2><p>${t('Đăng nhập để xem phiếu quà và tiếp tục lượt chơi còn lại.','登入後即可查看優惠券與尚未完成的遊戲機會。')}</p><button id="walletLogin" class="btn primary">${t('Đăng nhập / Đăng ký','登入／建立帳號')}</button></div></main>`;
 const w=S.wallet;
 return `<main class="page-main"><div class="page-title"><div><div class="eyebrow">YOUR LITTLE TREASURES</div><h1>${t('Ví quà của tôi','我的回憶優惠券')}</h1><p>${t('Quà tự lưu vào tài khoản. Đưa phiếu cho nhân viên khi mua lần sau.','獎勵已自動存入帳號。下次消費時，出示優惠券由店員核銷。')}</p></div><button id="refreshWallet" class="btn ghost small">${icon('refresh',17)}${t('Làm mới','重新整理')}</button></div>${w?.coupons.length?`<section class="wallet-grid">${w.coupons.map(couponHTML).join('')}</section>`:`<div class="empty-state">${icon('gift',51)}<h2>${t('Chiếc ví đang chờ kỷ niệm đầu tiên','等待第一份回憶好禮')}</h2><p>${t('Phiếu thật chỉ được tạo từ lượt chơi bằng mã hợp lệ. Chơi thử sẽ không tạo phiếu.','使用有效遊戲碼完成翻牌，才會產生真實優惠券。試玩不會產生優惠券。')}</p><button class="btn primary" data-page="home">${t('Trở về trò chơi','返回翻牌遊戲')}</button></div>`}${w?.games.length?`<section class="game-history"><h2>${t('Lượt chơi của bạn','我的遊戲紀錄')}</h2>${w.games.map(g=>`<div class="history-item"><div><strong>${e(g.order_ref)} <span class="badge ${g.status}">${e(U.status(g.status))}</span></strong><small>${t('Mã hết hạn','遊戲碼到期')} ${e(date(g.expires_at))}</small></div>${g.status==='ready'?`<button class="btn ghost small" data-resume="${e(g.id)}">${t('Chơi tiếp','繼續翻牌')} →</button>`:''}</div>`).join('')}</section>`:''}<p class="fineprint gap-top">${t('Thời gian hiển thị theo giờ Việt Nam (UTC+7).','時間均以越南時間（UTC+7）顯示。')}</p></main>`;
}
function rules(){
 const s=S.data.settings,n=s.threshold;
 const rs=[
 t(`Mỗi đơn đã hoàn tất từ ${n} ly đồ uống trả phí được cấp 1 mã, gắn với 1 tài khoản. Topping và ly tặng không tính. Không cộng dồn nhiều đơn; đơn gấp đôi số ly vẫn chỉ 1 lượt.`,`單筆已完成訂單購買至少 ${n} 杯付費飲料，可獲 1 組綁定會員帳號的遊戲碼。配料及贈送飲料不計杯數；不同訂單不累計，加倍杯數仍只發 1 次機會。`),
 t(`Cửa hàng xác nhận mua hàng và cấp mã. Đây là trò chơi độc lập, không tự kiểm tra giỏ hàng, thanh toán hay đơn đặt món. Mã có hạn ${s.code_days} ngày từ lúc cấp; chỉ tài khoản được cấp mã mới dùng được.`,`由門市人工確認消費並發碼。本遊戲獨立運作，不會自動讀取購物車、付款或訂單。遊戲碼自發放起 ${s.code_days} 天內有效，僅綁定帳號可以使用。`),
 t('Có 12 thẻ úp với 6 cặp hình. Mỗi lần lật hai thẻ: giống nhau thì biến mất, khác nhau sẽ úp lại. Ghép hết 6 cặp mới nhận quà. Không giới hạn thời gian; số lượt và thời gian không ảnh hưởng phần thưởng.','共 12 張蓋牌、6 組圖案。每次翻開兩張，相同圖案消除，不同圖案稍後蓋回。完成全部 6 組配對後才發獎；不限時間，配對次數與遊戲時間不影響獎勵。'),
 t('Phiếu tự lưu vào tài khoản sau khi hoàn thành 6 cặp. Quà được máy chủ chọn theo trọng số, không theo kỹ năng hay vị trí thẻ; tải lại không cấp thêm phiếu. Chỉ dùng cho một đơn mua tiếp theo, không dùng cho đơn đã cấp mã. Mỗi đơn dùng tối đa 1 phiếu, không cộng dồn khuyến mãi khác.','完成 6 組配對後，優惠券自動存入帳號。獎項由伺服器按權重決定，與技巧或卡片位置無關，重新整理不會重複發獎。僅限下一筆消費，不得用於發碼的原訂單。每筆訂單限用 1 張，不與其他促銷或優惠疊加。'),
 t('Đáp ứng mức chi tiêu và số ly trả phí ghi trên phiếu. Số tiền là giá trị đồ uống đủ điều kiện sau ưu đãi khác, trước khi trừ phiếu; không gồm phí giao hàng. Nhân viên đối chiếu tài khoản, đơn hàng và điều kiện trước khi xác nhận dùng phiếu.','須符合券面最低金額與付費杯數。核銷金額指符合資格飲料扣除其他折讓後、使用本券前的金額，不含配送費。店員應確認帳號、訂單及使用條件後才核銷。'),
 t('Hạn dùng được ghi chính xác trên từng phiếu, tính từ thời điểm hoàn thành và nhận quà. Phiếu hết hạn, đã dùng hoặc bị hủy không dùng lại được. Đơn gốc bị hủy/hoàn tiền có thể bị thu hồi mã và phiếu chưa dùng.','有效期限以每張優惠券所載時間為準，自完成配對並發獎時計算。已過期、已使用或已撤銷的優惠券不可使用。原訂單取消或退款時，遊戲碼及尚未使用的券可被撤銷。'),
 t('Chơi thử không cần mua hàng. Quà trong chơi thử chỉ minh họa, không phải phiếu có giá trị đổi quà.','免費試玩不需要購物。試玩中的獎勵僅為展示，不具兌換效力。')
 ];
 return `<main class="page-main"><div class="page-title"><div><div class="eyebrow">THE LITTLE DETAILS</div><h1>${t('Thể lệ & những điều cần biết','活動規則與使用說明')}</h1><p>${e(field(s,'campaign'))} · ${e(field(s,'store'))}</p></div></div><section class="rules-card"><h2>${t('Chơi vui, nhận quà rõ ràng.','開心翻牌，安心領獎。')}</h2>${rs.map((r,i)=>`<div class="rule"><span class="step">${i+1}</span><p>${e(r)}</p></div>`).join('')}</section><section class="rules-card"><h2>${t('Những món quà có thể gặp','可能遇見的回憶好禮')}</h2><p>${t('Tỷ lệ dưới đây được tính từ trọng số quà hiện có lúc tải trang. Có thể thay đổi khi quản trị cập nhật hoặc quà giới hạn hết.','以下比例依頁面載入時可發獎項的權重計算；後台調整或限量獎項送完時，比例可能改變。')}</p>${S.data.rewards.map(r=>`<div class="reward-rule">${icon(r.icon,31)}<div><h3>${e(field(r,'name'))}</h3><p>${e(field(r,'terms'))}</p><p>${e(r.days)} ${t('ngày từ lúc nhận','天內有效（自領取起算）')} · ${t('Từ','最低')} ${money(r.min_spend)} · ${e(r.min_cups)} ${t('ly trả phí','杯付費飲料')}</p></div><span class="chance">${e(r.chance)}%</span></div>`).join('')}</section><section class="rules-card"><h2>${t('Quyền riêng tư & hỗ trợ','隱私與門市聯絡資訊')}</h2><p>${e(field(s,'privacy'))}</p><p>${t('Mật khẩu được băm. Trang không xác minh điện thoại bằng OTP. Tài khoản và dữ liệu chỉ thuộc trò chơi này; không dùng chung với các dự án khác. Cookie phiên được dùng để đăng nhập; lựa chọn ngôn ngữ được lưu trên thiết bị.','密碼採雜湊儲存。本網站不提供手機簡訊驗證。會員與資料僅供此獨立遊戲使用，不與其他專案共用。網站使用工作階段 Cookie 維持登入，並在裝置保存語言偏好。')}</p><strong>${t('Cửa hàng áp dụng: ','適用門市：')}${e(field(s,'store'))}</strong><p>${t('Liên hệ: ','聯絡方式：')}${e(s.support||t('Vui lòng liên hệ nhân viên cửa hàng.','請洽門市人員。'))}</p><p class="fineprint">${t('Thời gian hiển thị: Việt Nam (UTC+7).','顯示時間：越南時間（UTC+7）。')}</p></section></main>`;
}
async function bootstrap(){S.data=await U.api('bootstrap'); if(S.game)S.game=S.data.ready.find(g=>g.id===S.game.id)||null;}
function render(){if(!S.data)return;app.innerHTML=header()+(S.page==='home'?home():S.page==='wallet'?wallet():rules())+footer();bind();if(S.page==='home'){paintBoard();ensureBoard();}}
async function go(page){if(S.busy)return;S.page=page;try{if(page==='wallet'&&S.data.user)S.wallet=await U.api('wallet');render();window.scrollTo({top:0,behavior:'smooth'})}catch(ex){U.toast(ex.message,true);if(ex.code==='login_required'){S.data.user=null;render()}}}
function bind(){
 app.querySelectorAll('[data-page]').forEach(b=>b.onclick=()=>go(b.dataset.page));
 app.querySelectorAll('[data-lang]').forEach(b=>b.onclick=()=>{if(!S.busy)U.setLang(b.dataset.lang)});
 document.getElementById('accountBtn').onclick=()=>{if(!S.busy)account()};
 document.getElementById('demoMode')?.addEventListener('click',()=>{if(S.busy)return;S.mode='demo';render()});
 document.getElementById('realMode')?.addEventListener('click',()=>{if(S.busy)return;S.mode='real';if(!S.game)S.game=S.data.ready[0]||null;render()});
 document.getElementById('soundBtn')?.addEventListener('click',()=>{S.sound=!S.sound;if(S.sound)tone();const b=document.getElementById('soundBtn');b.innerHTML=icon(S.sound?'sound':'mute',19);b.setAttribute('aria-pressed',String(S.sound));b.setAttribute('aria-label',S.sound?t('Tắt âm thanh','關閉音效'):t('Bật âm thanh','開啟音效'))});
 document.getElementById('loginReal')?.addEventListener('click',()=>U.auth(async()=>{await bootstrap();S.game=S.data.ready[0]||null;render()}));
 document.getElementById('walletLogin')?.addEventListener('click',()=>U.auth(async()=>{await bootstrap();await go('wallet')}));
 document.getElementById('refreshWallet')?.addEventListener('click',()=>go('wallet'));
 document.getElementById('claimForm')?.addEventListener('submit',async ev=>{
  ev.preventDefault();const f=ev.currentTarget;U.buttonBusy(f,true);
  try{const r=await U.api('claim',{code:f.elements.code.value});await bootstrap();if(r.coupon){showCoupon(r.coupon,false);render()}else{S.game=r.game;render();tone()}}catch(ex){U.toast(ex.message,true)}finally{U.buttonBusy(f,false)}
 });
 app.querySelectorAll('[data-card]').forEach(b=>b.onclick=()=>flipCard(Number(b.dataset.card)));
 bindBoardActions();
 app.querySelectorAll('[data-coupon]').forEach(b=>b.onclick=()=>showCoupon(S.wallet.coupons.find(c=>c.id===b.dataset.coupon),false));
 app.querySelectorAll('[data-resume]').forEach(b=>b.onclick=()=>{S.game=S.wallet.games.find(g=>g.id===b.dataset.resume);S.mode='real';go('home')});
}
function account(){
 if(!S.data.user){U.auth(async()=>{await bootstrap();if(S.page==='wallet')await go('wallet');else render()});return;}
 const d=U.modal(`<div class="eyebrow">MY ACCOUNT</div><h2>${e(S.data.user.name)}</h2><p class="muted">@${e(S.data.user.username)} · ${t('Tài khoản trò chơi độc lập','獨立遊戲會員帳號')}</p><div class="account-actions"><button id="accountWallet" class="btn ghost">${icon('ticket',20)}${t('Ví quà của tôi','我的優惠券')}</button><button id="changePassword" class="btn ghost">${icon('lock',20)}${t('Đổi mật khẩu','更改密碼')}</button>${S.data.user.role==='admin'?`<a class="btn ghost" href="admin.php">${icon('dashboard',20)}${t('Quản trị','管理後台')}</a>`:''}<button id="logout" class="btn light">${icon('logout',20)}${t('Đăng xuất','登出')}</button></div>`);
 d.querySelector('#accountWallet').onclick=()=>{U.close();go('wallet')};d.querySelector('#changePassword').onclick=()=>U.password(async()=>{await bootstrap();render()});
 d.querySelector('#logout').onclick=async()=>{try{await U.api('logout',{});U.close();S.game=null;S.mode='demo';S.wallet=null;S.boards={};S.results={};S.boardErrors={};await bootstrap();render()}catch(ex){U.toast(ex.message,true)}};
}
let audio;
function tone(win=false){if(!S.sound)return;try{audio??=new (window.AudioContext||window.webkitAudioContext)();audio.resume();const ns=win?[523.25,659.25,783.99,1046.5]:[659.25];ns.forEach((f,i)=>{const o=audio.createOscillator(),g=audio.createGain();o.type='sine';o.frequency.value=f;o.connect(g);g.connect(audio.destination);const at=audio.currentTime+i*.12;g.gain.setValueAtTime(0,at);g.gain.linearRampToValueAtTime(.09,at+.02);g.gain.exponentialRampToValueAtTime(.001,at+.3);o.start(at);o.stop(at+.32)})}catch{}}
function confetti(){if(matchMedia('(prefers-reduced-motion: reduce)').matches)return;const c=document.getElementById('confetti');c.innerHTML='';for(let i=0;i<45;i++){const el=document.createElement('i');el.style.setProperty('--x',Math.random()*100+'%');el.style.setProperty('--delay',Math.random()*.6+'s');el.style.setProperty('--drift',(Math.random()-.5)*170+'px');el.style.setProperty('--c',['#bb242d','#edbd69','#e98b6c','#e6d5b5'][i%4]);c.appendChild(el)}setTimeout(()=>c.innerHTML='',3300)}
/* Matching board state is server-owned. The browser only receives already exposed faces. */
function boardKey(){return S.mode==='demo'?'demo':`real:${S.game?.id||'none'}`;}
function currentBoard(){return S.boards[boardKey()]||null;}
function boardAvailable(){return S.mode==='demo'?S.data.settings.demo:!!(S.game&&S.data.settings.active);}
function formatTime(n){n=Math.max(0,Math.floor(n||0));return `${String(Math.floor(n/60)).padStart(2,'0')}:${String(n%60).padStart(2,'0')}`;}
function boardSeconds(b){return b?(b.elapsed_seconds+(b.started&&!b.complete?Math.floor((performance.now()-b.receivedAt)/1000):0)):0;}
function setBoard(b,key=boardKey()){S.boards[key]={...b,receivedAt:performance.now()};}
function noticeText(event){
 const msg={
  ready:['Lật hai thẻ để tìm một cặp giống nhau.','翻開兩張卡，找出一組相同的圖案。'],
  first:['Nhớ hình này nhé! Lật thêm một thẻ.','記住這個圖案，再翻開另一張卡。'],
  mismatch:['Chưa giống nhau! Ghi nhớ vị trí và thử lại nhé.','圖案不同！記住位置，再試一次。'],
  match:['Đúng một cặp! Hai thẻ đã được xóa.','配對成功！兩張卡片已消除。'],
  complete:['Đã ghép hết 6 cặp. Món quà đang đợi bạn!','全部 6 組配對完成，驚喜好禮等著你！'],
  loading:['Đang tải bàn chơi…','正在載入牌局……'],
  saving:['Đã hoàn thành! Đang lưu phần thưởng…','配對完成！正在保存獎勵……'],
  locked:['Nhập mã hợp lệ để mở khóa lượt nhận quà thật.','輸入有效遊戲碼，即可解鎖正式遊戲。'],
  sync:['Đã đồng bộ tiến độ. Tiếp tục tìm cặp nhé!','已同步遊戲進度，繼續找出成對圖案！']
 };
 return t(...(msg[event]||msg.ready));
}
function boardActions(unavailable=false){
 const k=boardKey(),b=currentBoard(),c=S.results[k];
 if(S.boardErrors[k])return `<button type="button" class="btn ghost small" id="syncBoard">${icon('refresh',16)}${t('Tải lại tiến độ','重新載入遊戲進度')}</button>`;
 if(c)return `<button type="button" class="btn primary small" id="viewResult">${icon('gift',17)}${t('Xem phần thưởng','查看本局獎勵')}</button><button type="button" class="btn ghost small" id="${S.mode==='demo'?'restartMatch':'nextGame'}">${t(S.mode==='demo'?'Chơi lại':'Dùng mã khác',S.mode==='demo'?'再玩一局':'使用另一個遊戲碼')}</button>`;
 if(b?.complete)return `<button type="button" class="btn primary small" id="finishMatch">${icon('gift',17)}${t('Nhận phần thưởng','領取本局獎勵')}</button>`;
 if(unavailable)return '';
 return S.mode==='demo'?`<button type="button" class="btn ghost small" id="restartMatch">${icon('refresh',15)}${t('Xáo bài, chơi lại','重新洗牌')}</button>`:`<span class="fineprint">${t('Tiến độ thật được lưu theo tài khoản.','正式遊戲進度會隨帳號保存。')}</span>`;
}
function bindBoardActions(){
 document.getElementById('syncBoard')?.addEventListener('click',()=>{if(!S.busy)startBoard(false)});
 document.getElementById('finishMatch')?.addEventListener('click',()=>finishMatch());
 document.getElementById('viewResult')?.addEventListener('click',()=>{if(!S.busy)showCoupon(S.results[boardKey()],false)});
 document.getElementById('nextGame')?.addEventListener('click',()=>{if(S.busy)return;S.game=S.data.ready[0]||null;S.notice='ready';render()});
 document.getElementById('restartMatch')?.addEventListener('click',()=>{
  if(S.busy)return;
  const b=currentBoard();
  if(b?.started&&!b.complete&&!confirm(t('Xóa tiến độ chơi thử này và xáo lại bài?','要清除這一局試玩進度，重新洗牌嗎？')))return;
  startBoard(true);
 });
}
function paintBoard(fresh=[]){
 if(S.page!=='home')return;
 const k=boardKey(),b=currentBoard(),err=S.boardErrors[k];
 const locked=!boardAvailable()||!b||!!err;
 document.querySelectorAll('[data-card]').forEach(el=>{
  const i=Number(el.dataset.card),isFresh=fresh.includes(i),gone=!!b?.matched.includes(i)&&!isFresh;
  const open=!!b?.open.includes(i)||isFresh,face=b?.faces?.[i],m=Number.isInteger(face)?memories[face]:null;
  if(m)el.querySelector('.card-front').innerHTML=`${icon(m[0],44)}<strong>${e(t(m[1],m[2]))}</strong>`;
  el.classList.toggle('flipped',open);el.classList.toggle('matched',gone);el.classList.toggle('pair-found',isFresh);
  el.disabled=locked||S.busy||gone||open||!!b?.complete;
  el.setAttribute('aria-pressed',String(open));el.setAttribute('aria-label',open&&m?t(m[1],m[2]):t('Thẻ úp số ','第 ')+(i+1)+t('',' 張蓋牌'));
  if(gone){el.setAttribute('aria-hidden','true');el.tabIndex=-1;}else{el.removeAttribute('aria-hidden');el.removeAttribute('tabindex');}
 });
 const grid=document.getElementById('cards');grid?.classList.toggle('locked',locked);grid?.setAttribute('aria-busy',String(S.busy));
 const pairs=document.getElementById('matchPairs'),moves=document.getElementById('matchMoves'),time=document.getElementById('matchTime'),progress=document.getElementById('matchProgress');
 if(pairs)pairs.textContent=`${b?.pairs||0}/6`;if(moves)moves.textContent=b?.moves||0;if(time)time.textContent=formatTime(boardSeconds(b));if(progress)progress.value=b?.pairs||0;
 const cleared=document.getElementById('boardCleared');if(cleared)cleared.hidden=!(b?.complete||S.results[k])||fresh.length>0;
 const notice=document.getElementById('matchNotice');
 if(notice){notice.textContent=err||noticeText(S.loadingKey===k?'loading':!boardAvailable()?'locked':b?.complete?(S.busy?'saving':'complete'):S.notice);notice.classList.toggle('has-error',!!err);}
 const controls=document.getElementById('matchActions');if(controls){controls.innerHTML=boardActions(!boardAvailable());bindBoardActions();controls.querySelectorAll('button').forEach(btn=>btn.disabled=S.busy);}
 // Prevent header/mode actions during a request or while a mismatched pair is being shown.
 document.querySelectorAll('#demoMode,#realMode,#accountBtn,[data-page],[data-lang]').forEach(el=>el.disabled=S.busy);
}
const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const motionDelay=ms=>matchMedia('(prefers-reduced-motion: reduce)').matches?0:ms;
function requestContext(){return {mode:S.mode,...(S.mode==='real'?{game_id:S.game.id}:{})};}
function ensureBoard(){
 if(S.page!=='home'||S.busy||S.loadingKey||!boardAvailable()||currentBoard()||S.results[boardKey()]||S.boardErrors[boardKey()])return;
 startBoard(false);
}
async function startBoard(restart=false){
 if(S.busy||!boardAvailable())return;
 const k=boardKey();S.busy=true;S.loadingKey=k;delete S.boardErrors[k];
 if(restart){delete S.boards[k];delete S.results[k];}
 S.notice='loading';paintBoard();
 try{
  const r=await U.api('match_start',{...requestContext(),restart:S.mode==='demo'&&restart});
  S.loadingKey=null;
  if(r.coupon){rememberResult(r.coupon,k);S.busy=false;render();return;}
  setBoard(r.board,k);S.notice=r.board.open.length?'first':'ready';
  render();
  const b=currentBoard();
  if(b.wait_ms>0){S.notice='mismatch';paintBoard();await sleep(b.wait_ms+40);b.open=[];b.wait_ms=0;paintBoard();await sleep(motionDelay(280));}
  S.busy=false;paintBoard();
  // Reloading a finished, unissued board retries the same server-side reward transaction.
  if(b.complete&&!S.results[k])await finishMatch();
 }catch(ex){S.loadingKey=null;S.busy=false;S.boardErrors[k]=ex.message;paintBoard();U.toast(ex.message,true);}
}
function rememberResult(c,key=boardKey()){
 S.results[key]=c;
 if(!c.demo){S.data.ready=S.data.ready.filter(g=>g.id!==c.game_id);}
}
async function flipCard(position){
 const b=currentBoard();
 if(S.busy||!boardAvailable()||!b||b.complete||S.boardErrors[boardKey()]||b.matched.includes(position)||b.open.includes(position))return;
 const k=boardKey(),oldMatched=[...b.matched];
 if(!b.started&&matchMedia('(max-width:760px)').matches){const box=document.querySelector('.matching-box');if(box)window.scrollTo({top:box.getBoundingClientRect().top+window.scrollY-12,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});}
 S.busy=true;paintBoard();
 try{
  const r=await U.api('match_flip',{...requestContext(),board_id:b.id,seq:b.seq,position});
  if(r.coupon){rememberResult(r.coupon,k);S.busy=false;render();showCoupon(r.coupon,false);return;}
  setBoard(r.board,k);S.notice=r.event==='wait'?'mismatch':r.event;
  const next=currentBoard(),fresh=next.matched.filter(i=>!oldMatched.includes(i));
  paintBoard(fresh);tone();
  if(fresh.length){await sleep(motionDelay(650));paintBoard();await sleep(motionDelay(280));}
  else if(next.wait_ms>0){await sleep(Math.max(next.wait_ms,850)+40);next.open=[];next.wait_ms=0;paintBoard();await sleep(motionDelay(300));}
  else await sleep(motionDelay(230));
  S.busy=false;paintBoard();
  if(fresh.length){const focused=document.activeElement;if(focused?.classList.contains('matched'))document.querySelector('.memory-card:not(:disabled)')?.focus({preventScroll:true});}
  if(next.complete)await finishMatch();
 }catch(ex){S.busy=false;S.boardErrors[k]=ex.message;paintBoard();U.toast(ex.message,true);}
}
async function finishMatch(){
 if(S.busy)return;
 const k=boardKey(),b=currentBoard();
 if(S.results[k]){showCoupon(S.results[k],false);return;}
 if(!b?.complete)return;
 S.busy=true;S.notice='saving';delete S.boardErrors[k];paintBoard();
 try{
  const r=await U.api(S.mode==='demo'?'demo':'play',{...requestContext(),board_id:b.id});
  const c=r.coupon||r;rememberResult(c,k);S.busy=false;render();showCoupon(c,true);
 }catch(ex){S.busy=false;S.boardErrors[k]=ex.message;paintBoard();U.toast(ex.message,true);}
}
setInterval(()=>{const el=document.getElementById('matchTime');if(el)el.textContent=formatTime(boardSeconds(currentBoard()));},1000);

function showCoupon(c,celebrate){
 const demo=!!c.demo;const r=c.reward;const stats=c.game_stats;
 const d=U.modal(`<div class="result-top">${icon('spark',14)}${demo?t('TRẢI NGHIỆM THỬ','試玩體驗'):t('KÝ ỨC CỦA BẠN','屬於你的童年回憶')}</div><h2>${celebrate?t('Ghép trọn ký ức,<br>mở quà thật vui!','回憶配對完成，<br>開啟你的驚喜！'):e(field(r,'name'))}</h2><p class="result-sub">${t('Những mảnh ký ức đã tìm thấy nhau.','熟悉的童年回憶，終於找到彼此。')}</p>${stats?`<div class="result-stats"><div><strong>${stats.pairs}/6</strong><span>${t('Cặp đã ghép','完成配對')}</span></div><div><strong>${stats.moves}</strong><span>${t('Lượt ghép','配對次數')}</span></div><div><strong>${formatTime(stats.elapsed_seconds)}</strong><span>${t('Thời gian','遊戲時間')}</span></div></div>`:''}<div class="reward-art"><img class="boy" src="assets/boy.webp" alt=""><div class="reward-icon">${icon(r.icon,57)}</div><img class="girl" src="assets/girl.webp" alt=""></div><div class="reward-title">${e(field(r,'name'))}</div><p class="reward-terms">${e(field(r,'terms'))}</p>${demo?`<div class="notice"><strong>${t('Quà minh họa · Không có giá trị đổi quà','展示獎勵 · 不具兌換效力')}</strong><p>${t('Chỉ lượt dùng mã cửa hàng mới nhận phiếu thật.','只有使用門市正式遊戲碼，才會收到真實優惠券。')}</p></div>`:`<div class="reward-ticket"><span class="badge ${c.status}">${e(U.status(c.status))}</span><div class="copy-line"><span class="code-text">${e(c.code)}</span><button id="copyCoupon" class="icon-btn" aria-label="${t('Sao chép mã','複製優惠碼')}">${icon('copy',18)}</button></div><div class="expiry">${icon('clock',15)}${t('Hạn dùng: ','有效至：')}${e(date(c.expires_at))}</div><small>${t('Đã tự lưu vào tài khoản · Chỉ dùng cho đơn tiếp theo','已自動存入帳號 · 僅限下一筆消費')}</small></div>`}<div class="result-actions"><button id="resultPrimary" class="btn primary">${demo?t('Chơi một ván mới','再玩一局'):t('Xem ví quà của tôi','查看我的優惠券')}${icon(demo?'refresh':'arrow',20)}</button><button id="resultSecondary" class="btn ghost">${demo?t('Tôi có mã chơi thật','我有正式遊戲碼'):t('Đóng phiếu','關閉優惠券')}</button></div><p class="fineprint gap-top">${demo?t('Không phát hành mã thưởng trong chế độ thử.','試玩模式不發行可用優惠碼。'):t('Đưa phiếu này cho nhân viên cửa hàng để đối chiếu và xác nhận dùng.','請將此券出示給店員，由店員確認條件後核銷。')}</p>`,'result-modal');
 d.querySelector('#copyCoupon')?.addEventListener('click',()=>U.copy(c.code));
 d.querySelector('#resultPrimary').onclick=()=>{U.close();if(demo){S.mode='demo';S.page='home';startBoard(true)}else go('wallet')};
 d.querySelector('#resultSecondary').onclick=()=>{U.close();if(demo){S.mode='real';S.page='home';render()}};
 if(celebrate)d.addEventListener('close',()=>render(),{once:true});
 if(celebrate){tone(true);confetti()}
}
window.addEventListener('languagechange',()=>{if(!S.busy){U.close();render()}});
async function start(){try{await bootstrap();if(S.data.ready.length){S.game=S.data.ready[0];S.mode='real'}else if(!S.data.settings.demo)S.mode='real';render()}catch(ex){app.innerHTML=`<main class="page-main"><div class="empty-state">${icon('info',50)}<h2>${t('Một chút gián đoạn','連線暫時中斷')}</h2><p>${e(ex.message)}</p><button class="btn primary" id="retry">${t('Thử lại','重新載入')}</button></div></main>`;document.getElementById('retry').onclick=start}}
start();
})();
