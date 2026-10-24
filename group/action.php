<?php
require __DIR__ . '/../lib/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}
try {
    hyg_headers();
    hyg_schema();
    hyg_csrf();
    $action = (string)($_POST['action'] ?? '');
    // -- khi nhấn vào btn tạo nhóm sẽ chạy vô đây
    if ($action === 'create') {
        if (!hym_current_member()) {
            header('Location: ' . hym_login_gate_url('group'), true, 303);
            exit;
        }
        $account = htp_group_input($_POST, $_FILES);
        $r = hyg_create($_POST['title'] ?? '', $_POST['nickname'] ?? '', !empty($_POST['copy_cart']), (string)($_POST['request_key'] ?? ''), $account);
        // dd('create', $r);
    } elseif ($action === 'join') {
        // dd('join', $r);
        // Only signed-in members may join; guests sign in first and come back to this invite.
        $invite = (string)($_POST['invite'] ?? '');
        if (!hym_current_member()) {
            // Keep the name the guest typed so the invite form shows it again after sign-in.
            $typed = $_POST['nickname'] ?? '';
            if (is_string($typed) && trim($typed) !== '') $_SESSION['hyg_pending_nickname'] = ['invite' => $invite, 'nickname' => mb_substr(trim($typed), 0, 32), 'created_at' => time()];
            header('Location: ' . hym_login_gate_url(preg_match('/^[a-f0-9]{64}$/D', $invite) ? 'invite-' . $invite : 'group'), true, 303);
            exit;
        }
        $r = hyg_join((string)($_POST['invite'] ?? ''), $_POST['nickname'] ?? '');
    } else {
        // -- Khi nhấn btn chốt món thì sẽ chạy vô đây

        $id = hyg_code($_POST['room_id'] ?? '');
        // While the host plays this room's checkout Mini Game, the room stays as it was quoted.
        hcgs_guard_group($id);
        if ($action === 'checkout') {
            // Only the host's checkout button may open a Mini Game session for this group, like the cart's checkout button.
            $r = hyg_checkout($id);
            // dd('checkout', $r);
            if (!$r['already']) $_SESSION['hcgs_allow_open'] = $r['room']['public_id'];
            header('Location: ' . base_url('checkout.php?group=' . rawurlencode($r['room']['public_id'])), true, 303);
            exit;
        }
        hyg_mutate($id, $action, $_POST);
        $r = hyg_room($id);
        if ($action === 'coupon' && ($_POST['lock_after'] ?? '') === '1') {
            // "Lock" submits the offer form: save the offer, then lock at the revision it produced.
            hyg_mutate($id, 'lock', ['revision' => (int)$r['revision']] + $_POST);
            $r = hyg_room($id);
            // dd('action', $r);
            // The host's own share needs no review, so confirm it here instead of on the room page.
            $quote = hyg_locked_quote($r);
            $hostId = (int)($quote['payment']['host_member_id'] ?? 0);
            $hostPay = hyg_member_payment($quote['payment'], $hostId);
            if ($hostPay && !empty($hostPay['can_confirm']) && (float)$hostPay['outstanding'] > 0) {
                hyg_mutate($id, 'payment_confirm', [
                    'revision' => (int)$r['revision'],
                    'quote_signature' => $r['lock_signature'] ?? '',
                    'member_id' => $hostId,
                    'payment_amount' => (string)hyg_money((float)$hostPay['outstanding']),
                ]);
                $r = hyg_room($id);
                $quote = hyg_locked_quote($r);
            }
            // Once locked, go straight to checkout when the group can already pay; otherwise stay in the room.
            $hasInvalid = false;
            foreach ($quote['cart'] as $item) {
                if (!empty($item['_invalid'])) {
                    $hasInvalid = true;
                    break;
                }
            }
            if (!empty($quote['payment']['can_checkout']) && !empty($quote['cart']) && !$hasInvalid) {
                $_SESSION['hcgs_allow_open'] = $r['public_id'];
                header('Location: ' . base_url('checkout.php?group=' . rawurlencode($r['public_id'])), true, 303);
                exit;
            }
        }
        if ($action === 'payment_confirm') {
            dd('payment_confirm', $r);
            $quote = hyg_locked_quote($r);
            $hasInvalid = false;
            foreach ($quote['cart'] as $item) {
                if (!empty($item['_invalid'])) {
                    $hasInvalid = true;
                    break;
                }
            }
            if (!empty($quote['payment']['can_checkout']) && !empty($quote['cart']) && !$hasInvalid) {
                $_SESSION['hcgs_allow_open'] = $r['public_id'];
                header('Location: ' . base_url('checkout.php?group=' . rawurlencode($r['public_id'])), true, 303);
                exit;
            }
        }
        if (in_array($action, ['payment_reject', 'payment_refund', 'payment_correct', 'payment_extend'], true)) $_SESSION['hyg_message'] = gt('payment_controls_saved');
    }
    // dd($r);
    header('Location: ' . hyg_room_url($r['public_id']), true, 303);
    exit;
} catch (Throwable $e) {
    if ($e instanceof TransferPaymentError) {
        $_SESSION['hyg_message'] = htp_error($e);
        header('Location: ' . base_url('group/'), true, 303);
        exit;
    }
    if ($e instanceof GroupOrderError) {
        $_SESSION['hyg_message'] = gt($e->getMessage());
        $return = base_url('group/');
        try {
            if (!empty($_POST['room_id'])) {
                $r = hyg_room(hyg_code($_POST['room_id']));
                hyg_member($r);
                $return = hyg_room_url($r['public_id']);
            } elseif (!empty($_POST['invite']) && preg_match('/^[a-f0-9]{64}$/D', (string)$_POST['invite'])) $return = base_url('group/?invite=' . $_POST['invite']);
        } catch (Throwable $ignored) {
        }
        header('Location: ' . $return, true, 303);
        exit;
    }
    hyg_error_page($e);
}
