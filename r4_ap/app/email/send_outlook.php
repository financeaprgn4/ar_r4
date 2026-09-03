<?php
if ($argc < 2) {
    echo "Path file JSON tidak diberikan.\n";
    exit(1);
}

$jsonFile = $argv[1];
if (!file_exists($jsonFile)) {
    echo "File tidak ditemukan: $jsonFile\n";
    exit(1);
}

$data = json_decode(file_get_contents($jsonFile), true);
if (!$data) {
    echo "Gagal parse JSON\n";
    exit(1);
}

define('olMailItem', 0);
$outlook = new COM("Outlook.Application.15") or die("Unable to instantiate Outlook");
$mail = $outlook->CreateItem(olMailItem);

$mail->To = implode('; ', $data['to']);
$mail->Cc = implode('; ', $data['cc']);
$mail->Subject = $data['subject'];
$mail->HTMLBody = $data['body'];

foreach ($data['attachments'] as $file) {
    if (file_exists($file)) {
        $mail->Attachments->Add($file);
    }
}

$mail->Send();
if (file_exists($jsonFile)) {
    unlink($jsonFile);
}
