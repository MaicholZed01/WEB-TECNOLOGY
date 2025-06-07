<?php
require '../include/session.inc.php';
require '../include/dbms.inc.php';
require '../logic/Appuntamenti.php';
require '../logic/Servizi.php';
require '../logic/FasceDisponibilita.php';
require '../logic/Messaggi.php';

header('Content-Type: application/json');
$op = $_REQUEST['op'] ?? '';
switch($op) {
  case 'change_status':
    $ok = Appuntamenti::changeStatus($_POST['appuntamento_id'], $_POST['stato']);
    echo json_encode(['status'=>$ok?'OK':'Error']);
    break;
  case 'toggle_service':
    $ok = Servizi::toggle($_POST['id']);
    echo json_encode(['status'=>$ok?'OK':'Error']);
    break;
  case 'add_availability':
    $ok = FasceDisponibilita::add($_SESSION['fisio'], $_POST);
    echo json_encode(['status'=>$ok?'OK':'Error']);
    break;
  case 'send_message':
    $ok = Messaggi::send($_SESSION['fisio'], $_POST);
    echo json_encode(['status'=>$ok?'OK':'Error']);
    break;
  case 'list_notifications':
    echo json_encode(Messaggi::listNotifications($_SESSION['fisio']));
    break;
  case 'mark_read':
    $ok = Messaggi::markRead($_POST['id']);
    echo json_encode(['status'=>$ok?'OK':'Error']);
    break;
  default:
    echo json_encode(['error'=>'Operazione non valida']);
}
?>