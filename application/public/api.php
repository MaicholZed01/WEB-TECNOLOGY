<?php
require '../include/dbms.inc.php';
require '../logic/Fisioterapisti.php';
require '../logic/Servizi.php';
require '../logic/FasceDisponibilita.php';
require '../logic/Richieste.php';
require '../logic/Appuntamenti.php';

$op = $_REQUEST['op'] ?? '';
header('Content-Type: application/json');
switch($op) {
  case 'list_fisioterapisti':
    echo json_encode(Fisioterapisti::listAll());
    break;
  case 'list_servizi':
    echo json_encode(Servizi::listAll());
    break;
  case 'list_fasce':
    echo json_encode(FasceDisponibilita::byFisio((int)$_GET['f']));
    break;
  case 'book':
    $rid = Richieste::create($_POST);
    if (!$rid) { echo json_encode(['error'=>'Impossibile creare richiesta']); break; }
    if (Appuntamenti::create($rid,$_POST)) echo json_encode(['status'=>'OK']);
    else echo json_encode(['error'=>'Errore prenotazione']);
    break;
  case 'add_review':
    if (Appuntamenti::addReview($_POST)) echo json_encode(['status'=>'OK']);
    else echo json_encode(['error'=>'Errore recensione']);
    break;
  default:
    echo json_encode(['error'=>'Operazione non valida']);
}
