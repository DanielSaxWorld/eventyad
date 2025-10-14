<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../includes/PHPMailer.php';
require_once '../../includes/SMTP.php';
require_once '../../includes/Exception.php';

function sendGenericEmail($to, $subject, $body) {
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host = "eventyad.com.ng";
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = "ssl";
    $mail->Port = 465;
    $mail->Username = "support@eventyad.com.ng";
    $mail->Password = "8Xe)w3spwX,aTnZ_";
    $mail->setFrom('support@eventyad.com.ng', 'EventYad Support');
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = "<p>$body</p>";

    return $mail->send();
}
