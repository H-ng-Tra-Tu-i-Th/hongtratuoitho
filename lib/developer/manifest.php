<?php
if (!defined('HUIYI_DEVELOPER_DOCS')) { http_response_code(404); exit; }
// Append each new release. Never remove or overwrite published records.
return [
    '19.9.1' => ['date'=>'2026-10-07','title_vi'=>'Tài liệu nền: toàn bộ chức năng và quy trình','title_zh'=>'文件基線：完整功能與操作流程'],
    '19.10.0' => ['date'=>'2026-10-07','title_vi'=>'Trang tài liệu dành cho nhà phát triển','title_zh'=>'管理後台開發者文件頁'],
    '19.11.0' => ['date'=>'2026-10-07','title_vi'=>'Tài khoản chi nhánh, thu hộ và xác nhận nhận tiền','title_zh'=>'門市收款、團主代收與店家核款'],
    '19.12.0' => ['date'=>'2026-10-08','title_vi'=>'Sơ đồ thao tác khách hàng và quản trị','title_zh'=>'客戶與管理者操作流程圖','workflows'=>true],
    '19.13.0' => ['date'=>'2026-10-08','title_vi'=>'Mỗi 10 ly một lượt và sơ đồ thao tác','title_zh'=>'每滿十杯一次抽獎與操作流程圖','workflows'=>true],
    '19.14.0' => ['date'=>'2026-10-08','title_vi'=>'Ba vai trò và menu ô biểu tượng','title_zh'=>'三角色權限與圖示卡片導覽'],
    '19.14.1' => ['date'=>'2026-10-08','title_vi'=>'Giao diện một ngôn ngữ và ẩn iPOS phía khách','title_zh'=>'單語介面、完整齒輪與前台隱藏 iPOS'],
    '19.14.2' => ['date'=>'2026-10-08','title_vi'=>'Sửa hàng nút tùy chọn đồ uống trên điện thoại','title_zh'=>'手機自訂飲料底部防重疊'],
    '19.14.3' => ['date'=>'2026-10-08','title_vi'=>'Thống nhất bố cục và khoảng cách quản trị','title_zh'=>'後台統一排版與間距'],
    '19.14.4' => ['date'=>'2026-10-08','title_vi'=>'Lưới quản trị 1/2, 1/3 và 2/3','title_zh'=>'後台等分比例網格與手機文件'],
    '19.14.5' => ['date'=>'2026-10-08','title_vi'=>'Bảng một/hai dòng và trở về menu quản trị','title_zh'=>'表格一至兩行與手機返回主目錄'],
    '19.15.0' => ['date'=>'2026-10-12','title_vi'=>'Thanh toán, đối soát và lịch sử đầy đủ','title_zh'=>'付款、對帳與完整歷程優化','workflows'=>true],
    '19.15.1' => ['date'=>'2026-10-13','title_vi'=>'Bảo trì SQL HTTPS cho kỹ sư và quy trình đầy đủ','title_zh'=>'工程師 HTTPS SQL 維護與完整操作流程','workflows'=>true],
];
