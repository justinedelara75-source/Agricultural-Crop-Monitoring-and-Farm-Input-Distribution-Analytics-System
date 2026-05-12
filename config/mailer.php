<?php
require_once __DIR__ . '/../Mail/phpmailer/PHPMailerAutoload.php';

function sendMail($to, $subject, $body)
{
    $mail = new PHPMailer();

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'justinedelara75@gmail.com';
    $mail->Password = 'zlihjywqmefuuhhm';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('justinedelara75@gmail.com', 'Agri System');
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;

    if (!$mail->send()) {
        die("Mailer Error: " . $mail->ErrorInfo);
    }

    return true;
}
?>