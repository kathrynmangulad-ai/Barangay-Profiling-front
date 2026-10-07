<?php














if (!function_exists('mail_smtp_readline')) {
    function mail_smtp_readline($fp, $timeout = 15) {
        $line = '';
        $end  = time() + $timeout;
        while (!feof($fp) && time() < $end) {
            $chunk = fgets($fp, 1024);
            if ($chunk === false) { break; }
            $line .= $chunk;
             
            if (strlen($chunk) >= 4 && $chunk[3] === ' ') { break; }
            if (substr($line, -2) === "\r\n" && preg_match('/^\d{3} /m', $line)) { break; }
        }
        return $line;
    }
}

if (!function_exists('mail_smtp_cmd')) {
    function mail_smtp_cmd($fp, $cmd, $expect = [250], $timeout = 15) {
        if ($cmd !== '') { fwrite($fp, $cmd . "\r\n"); }
        $resp = mail_smtp_readline($fp, $timeout);
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $expect, true)) {
            error_log('[mailer] SMTP unexpected reply to "' . $cmd . '": ' . trim($resp));
            return false;
        }
        return $resp;
    }
}

if (!function_exists('mail_config')) {
    function mail_config() {
        return [
            'host'      => defined('MAIL_HOST')      ? (string)MAIL_HOST      : '',
            'port'      => defined('MAIL_PORT')      ? (int)MAIL_PORT         : 587,
            'user'      => defined('MAIL_USER')      ? (string)MAIL_USER      : '',
            'pass'      => defined('MAIL_PASS')      ? (string)MAIL_PASS      : '',
            'from'      => defined('MAIL_FROM')      ? (string)MAIL_FROM      : '',
            'from_name' => defined('MAIL_FROM_NAME') ? (string)MAIL_FROM_NAME : 'Barangay System',
        ];
    }
}

if (!function_exists('mail_is_configured')) {
     
    function mail_is_configured() {
        $c = mail_config();
        return $c['host'] !== '' && $c['user'] !== '' && $c['pass'] !== '' && $c['from'] !== '';
    }
}

if (!function_exists('mail_build_reset_url')) {
    function mail_build_reset_url($token) {
        $base = defined('APP_BASE_URL') ? rtrim((string)APP_BASE_URL, '/') : '';
        if ($base === '') {
             
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
            $dir    = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
            $base   = $scheme . '://' . $host . ($dir !== '' ? $dir : '');
        }
        return $base . '/reset_password.php?token=' . rawurlencode((string)$token);
    }
}





if (!function_exists('mail_send_reset')) {
    function mail_send_reset($to_email, $to_name, $reset_url) {
        $to_email = trim((string)$to_email);
        if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) { return false; }
        if (!mail_is_configured()) {
            error_log('[mailer] skipped: SMTP not configured (see config.php MAIL_*).');
            return false;
        }
        if (!function_exists('stream_socket_client')) {
            error_log('[mailer] skipped: stream_socket_client unavailable.');
            return false;
        }

        $c    = mail_config();
        $host = $c['host'];
        $port = $c['port'] > 0 ? $c['port'] : 587;

        $fp = @stream_socket_client(
            'tcp://' . $host . ':' . $port, $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT
        );
        if (!$fp) {
            error_log('[mailer] connect failed.');
            return false;
        }
        stream_set_timeout($fp, 15);

         
        $banner = mail_smtp_readline($fp);
        if ((int)substr($banner, 0, 3) !== 220) {
            error_log('[mailer] bad banner.');
            fclose($fp);
            return false;
        }

        $local = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (mail_smtp_cmd($fp, 'EHLO ' . $local) === false) { fclose($fp); return false; }
         
        if (mail_smtp_cmd($fp, 'STARTTLS', [220]) === false) { fclose($fp); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log('[mailer] TLS negotiation failed.');
            fclose($fp);
            return false;
        }
        if (mail_smtp_cmd($fp, 'EHLO ' . $local) === false) { fclose($fp); return false; }

         
        if (mail_smtp_cmd($fp, 'AUTH LOGIN', [334]) === false) { fclose($fp); return false; }
        if (mail_smtp_cmd($fp, base64_encode($c['user']), [334]) === false) { fclose($fp); return false; }
        if (mail_smtp_cmd($fp, base64_encode($c['pass']), [235]) === false) {
            error_log('[mailer] authentication rejected - check MAIL_USER/MAIL_PASS.');
            fclose($fp);
            return false;
        }

        if (mail_smtp_cmd($fp, 'MAIL FROM:<' . $c['from'] . '>') === false) { fclose($fp); return false; }
        if (mail_smtp_cmd($fp, 'RCPT TO:<' . $to_email . '>', [250, 251]) === false) { fclose($fp); return false; }
        if (mail_smtp_cmd($fp, 'DATA', [354]) === false) { fclose($fp); return false; }


        $from_name = $c['from_name'] !== '' ? $c['from_name'] : 'Barangay System';
         
        $safe_name = trim(preg_replace('/[\r\n]+/', ' ', (string)$to_name));
        $subject   = 'Reset your Barangay System password';
        $boundary  = 'bms_' . bin2hex(random_bytes(12));

        $text = "Hello" . ($safe_name !== '' ? ' ' . $safe_name : '') . ",\r\n\r\n"
              . "Someone requested a password reset for your Barangay System account.\r\n"
              . "Use the link below within 1 hour (single use):\r\n\r\n"
              . $reset_url . "\r\n\r\n"
              . "If you did not request this, you can ignore this email.\r\n";

        $html = '<p>Hello' . ($safe_name !== '' ? ' ' . htmlspecialchars($safe_name, ENT_QUOTES, 'UTF-8') : '') . ',</p>'
              . '<p>Someone requested a password reset for your Barangay System account. '
              . 'Click the link below within <strong>1 hour</strong> (single use):</p>'
              . '<p><a href="' . htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8') . '">Reset my password</a></p>'
              . '<p>If you did not request this, you can ignore this email.</p>';

        $headers  = 'From: "' . addcslashes($from_name, '"\\') . '" <' . $c['from'] . ">\r\n";
        $headers .= 'To: <' . $to_email . ">\r\n";
        $headers .= 'Subject: ' . $subject . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n";

        $body  = "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $text . "\r\n";
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $html . "\r\n";
        $body .= "--" . $boundary . "--\r\n.";

        fwrite($fp, $headers . "\r\n" . $body . "\r\n");
        $resp = mail_smtp_readline($fp);
        $ok   = ((int)substr($resp, 0, 3) === 250);
        if (!$ok) { error_log('[mailer] DATA rejected.'); }

        @fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return $ok;
    }
}
if (!function_exists('mail_send_registration')) {
    function mail_send_registration($to_email, $to_name, $username, $barangay_name = '') {
        $to_email = trim((string)$to_email);
        if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) { return false; }
        if (!mail_is_configured()) { return false; }
        if (!function_exists('stream_socket_client')) { return false; }
        $c = mail_config();
        $fp = @stream_socket_client('tcp://'.$c['host'].':'.($c['port']>0?$c['port']:587), $en, $es, 15, STREAM_CLIENT_CONNECT);
        if (!$fp) { return false; }
        stream_set_timeout($fp, 15);
        if ((int)substr(mail_smtp_readline($fp),0,3) !== 220) { fclose($fp); return false; }
        $local = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (mail_smtp_cmd($fp,'EHLO '.$local)===false) { fclose($fp); return false; }
        if (mail_smtp_cmd($fp,'STARTTLS',[220])===false) { fclose($fp); return false; }
        if (!@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; }
        if (mail_smtp_cmd($fp,'EHLO '.$local)===false) { fclose($fp); return false; }
        if (mail_smtp_cmd($fp,'AUTH LOGIN',[334])===false) { fclose($fp); return false; }
        if (mail_smtp_cmd($fp,base64_encode($c['user']),[334])===false) { fclose($fp); return false; }
        if (mail_smtp_cmd($fp,base64_encode($c['pass']),[235])===false) { fclose($fp); return false; }
        if (mail_smtp_cmd($fp,'MAIL FROM:<'.$c['from'].'>')===false) { fclose($fp); return false; }
        if (mail_smtp_cmd($fp,'RCPT TO:<'.$to_email.'>',[250,251])===false) { fclose($fp); return false; }
        if (mail_smtp_cmd($fp,'DATA',[354])===false) { fclose($fp); return false; }
        $safe_name = trim(preg_replace('/[\r\n]+/',' ',(string)$to_name));
        if ($safe_name==='') { $safe_name = trim(preg_replace('/[\r\n]+/',' ',(string)$username)); }
        $safe_user = trim(preg_replace('/[\r\n]+/',' ',(string)$username));
        $safe_brgy = trim(preg_replace('/[\r\n]+/',' ',(string)$barangay_name));
        $subject = 'Your Barangay System account is awaiting approval';
        $b = 'bms_'.bin2hex(random_bytes(12));
        $text = "Hello".($safe_name!==''?' '.$safe_name:'').",\r\n\r\n"
              . "Your resident account (username: ".$safe_user.") was created and is pending review.\r\n"
              . ($safe_brgy!=='' ? "Barangay: ".$safe_brgy."\r\n" : "")
              . "You can sign in once your barangay secretary approves it.\r\n\r\n"
              . "If you did not register, please ignore this email.\r\n";
        $html = '<p>Hello'.($safe_name!==''?' '.htmlspecialchars($safe_name,ENT_QUOTES,'UTF-8'):'').',</p>'
              . '<p>Your resident account (username: <strong>'.htmlspecialchars($safe_user,ENT_QUOTES,'UTF-8').'</strong>) was created and is <strong>pending review</strong>.</p>'
              . ($safe_brgy!=='' ? '<p>Barangay: <strong>'.htmlspecialchars($safe_brgy,ENT_QUOTES,'UTF-8').'</strong></p>' : '')
              . '<p>You can sign in once your barangay secretary approves it.</p>';
        $from_name = $c['from_name']!==''?$c['from_name']:'Barangay System';
        $h = 'From: "'.addcslashes($from_name,'"\\').'" <'.$c['from'].">\r\n";
        $h .= 'To: <'.$to_email.">\r\n".'Subject: '.$subject."\r\nMIME-Version: 1.0\r\n";
        $h .= 'Content-Type: multipart/alternative; boundary="'.$b."\"\r\n";
        $body = "--".$b."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$text."\r\n";
        $body .= "--".$b."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".$html."\r\n--".$b."--\r\n.";
        fwrite($fp,$h."\r\n".$body."\r\n");
        $ok = ((int)substr(mail_smtp_readline($fp),0,3)===250);
        @fwrite($fp,"QUIT\r\n"); fclose($fp); return $ok;
    }
}




if (!function_exists('users_has_email_column')) {
     
    function users_has_email_column($conn) {
        if (!($conn instanceof mysqli)) { return false; }
        $rs = @$conn->query("SHOW COLUMNS FROM users LIKE 'email'");
        if (!$rs) { return false; }
        $has = (bool)$rs->fetch_assoc();
        $rs->close();
        return $has;
    }
}

if (!function_exists('reset_issue_token')) {
    




    function reset_issue_token($conn, $uid, $created_by = null) {
        $uid = (int)$uid;
        if ($uid <= 0) { return ''; }
        $plain = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $plain);

        $inv = $conn->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL');
        if ($inv === false) { return ''; }
        $inv->bind_param('i', $uid);
        $inv->execute();
        $inv->close();

        $ins = $conn->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_by)
             VALUES (?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR),?)'
        );
        if ($ins === false) { return ''; }
        $by = ($created_by === null) ? null : (int)$created_by;
        $ins->bind_param('isi', $uid, $hash, $by);
        $ok = $ins->execute();
        $ins->close();
        return $ok ? $plain : '';
    }
}

