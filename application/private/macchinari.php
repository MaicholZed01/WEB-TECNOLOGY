<?php
// application/private/macchinari.php
require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 * Gestisce CRUD macchinari
 * @param bool &$showForm
 * @param string &$bodyHtml
 */
function handleMacchinari(&$showForm, &$bodyHtml) {
    $showForm = false;
    $bodyHtml = '';
    $db = Db::getConnection();
    $message = '';
    
    if (($_GET['page'] ?? '') !== 'macchinari') {
        return;
    }
    $action = $_GET['action'] ?? '';
    // SAVE (create o update)
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // recupera campi
        $id   = (int)($_POST['macchinario_id'] ?? 0);
        $nome = $db->real_escape_string($_POST['nome_macchinario']);
        $mod  = $db->real_escape_string($_POST['modello']);
        $mar  = $db->real_escape_string($_POST['marca']);
        $desc = $db->real_escape_string($_POST['descrizione']);
        $da   = $db->real_escape_string($_POST['data_acquisto']);
        $qt   = (int)($_POST['quantita']);
        $st   = $db->real_escape_string($_POST['stato']);
        if ($id > 0) {
            $sql = "UPDATE macchinari SET nome_macchinario='$nome', modello='$mod', marca='$mar', descrizione='$desc', data_acquisto='$da', quantita=$qt, stato='$st' WHERE macchinario_id=$id";
        } else {
            $sql = "INSERT INTO macchinari(nome_macchinario,modello,marca,descrizione,data_acquisto,quantita,stato) VALUES('$nome','$mod','$mar','$desc','$da',$qt,'$st')";
        }
        if ($db->query($sql)) {
            $message = "<div class='alert alert-success'>Salvataggio riuscito.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Errore: {$db->error}</div>";
        }
    }
    // DELETE
    if ($action === 'delete' && isset($_GET['id'])) {
        $delId = (int)$_GET['id'];
        $db->query("DELETE FROM macchinari WHERE macchinario_id=$delId");
        header('Location: index.php?page=macchinari'); exit;
    }
    // PREPARA FORM
    $showForm = true;
    // se edit
    $old = ['macchinario_id'=>0,'nome_macchinario'=>'','modello'=>'','marca'=>'','descrizione'=>'','data_acquisto'=>'','quantita'=>'','stato'=>''];
    if ($action==='edit' && isset($_GET['id'])) {
        $rid = (int)$_GET['id'];
        $res = $db->query("SELECT * FROM macchinari WHERE macchinario_id=$rid");
        if ($row = $res->fetch_assoc()) {
            $old = $row;
        }
    }
    // lista stati (esempio)
    $stati = ['Attivo','In Manutenzione','Fuori Servizio'];
    $optStati = '';
    foreach ($stati as $s) {
        $sel = $old['stato']===$s? 'selected':'';
        $optStati .= "<option value='$s' $sel>$s</option>";
    }
    // lista macchinari
    $rows = $db->query("SELECT * FROM macchinari ORDER BY creato_il DESC");
    $tbl = '';
    while ($r = $rows->fetch_assoc()) {
        $tbl .= "<tr>"
            ."<td>{$r['nome_macchinario']}</td>"
            ."<td>{$r['modello']}</td>"
            ."<td>{$r['marca']}</td>"
            ."<td>{$r['descrizione']}</td>"
            ."<td>{$r['data_acquisto']}</td>"
            ."<td>{$r['quantita']}</td>"
            ."<td>{$r['stato']}</td>"
            ."<td>{$r['creato_il']}</td>"
            ."<td>"
            ."<a href='index.php?page=macchinari&action=edit&id={$r['macchinario_id']}' class='btn btn-primary-modern btn-xs me-1'><i class='fas fa-edit'></i></a>"
            ."<a href='index.php?page=macchinari&action=delete&id={$r['macchinario_id']}' class='btn btn-outline-modern btn-xs text-danger' onclick='return confirm(" . '"Eliminare questo macchinario?"' . ");'><i class='fas fa-trash-alt'></i></a>"
            ."</td></tr>";
    }
    // render template
    $tpl = new Template('dtml/webarch/macchinari');
    $tpl->setContent('messaggio_form',$message);
    $tpl->setContent('label_submit',$old['macchinario_id']?'Modifica':'Aggiungi');
    foreach (['nome_macchinario','modello','marca','descrizione','data_acquisto','quantita','macchinario_id'] as $f) {
        $tpl->setContent("old_$f",htmlspecialchars($old[$f]));
    }
    $tpl->setContent('lista_stati_macchinario',$optStati);
    $tpl->setContent('lista_macchinari',$tbl);
    $bodyHtml = $tpl->get();
}
?>