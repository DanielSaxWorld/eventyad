<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../includes/PHPMailer.php';
require_once '../../includes/SMTP.php';
require_once '../../includes/Exception.php';

function sendVerificationEmail($to, $fullname, $code) {
    $lastname = explode(' ', $fullname)[1] ?? $fullname;

    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host = "eventyad.com.ng";
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = "ssl";
    $mail->Port = 465;
    $mail->Username = "support@eventyad.com.ng";
    $mail->Password = "8Xe)w3spwX,aTnZ_";
    $mail->setFrom('support@eventyad.com.ng', 'Verification Code');
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = "Vendor Account Verification";

    $mail->Body = "
        <h2>EVENT YAD Vendor Support</h2>
        <p>Hello $lastname,</p>
        <p>Your verification code is <strong>$code</strong></p>
        <p>If this wasn't you, please contact support.</p>
    ";

    return $mail->send();
}
