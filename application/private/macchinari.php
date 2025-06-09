<?php
// application/private/macchinari.php
// Gestione macchinari + servizi (many-to-many)

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 *  entry-point richiamato da index.php
 *  ----------------------------------------------------------------
 *  $show    → true  se page=macchinari, altrimenti false
 *  $bodyTpl → HTML già renderizzato (viene iniettato nel frame)
 */
function handleMacchinari(bool &$show, string &$bodyTpl): void
{
    /* pagina sbagliata? non faccio nulla */
    if (($_GET['page'] ?? '') !== 'macchinari') { $show = false; return; }
    $show = true;

    /* ----------------------------------------------------------------- */
    $db = Db::getConnection();
    $db->set_charset('utf8');

    /* flash */
    session_start();
    $flash = $_SESSION['mach_flash'] ?? '';
    unset($_SESSION['mach_flash']);

    /* azione */
    $action = $_GET['action'] ?? '';

    /* -------------------------------------------------- DELETE ------- */
    if ($action === 'delete' && isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $db->query("DELETE FROM macchinari WHERE macchinario_id = $id");
        $db->query("DELETE FROM macchinari_servizi WHERE macchinario_id = $id");
        $_SESSION['mach_flash'] = '<div class="alert alert-success">Macchinario eliminato.</div>';
        header('Location: index.php?page=macchinari');
        exit;
    }

    /* -------------------------------------------------- SAVE ---------- */
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $id   = (int) ($_POST['macchinario_id'] ?? 0);
        $nome = $db->real_escape_string(trim($_POST['nome_macchinario'] ?? ''));
        $mod  = $db->real_escape_string(trim($_POST['modello'] ?? ''));
        $mar  = $db->real_escape_string(trim($_POST['marca'] ?? ''));
        $desc = $db->real_escape_string(trim($_POST['descrizione'] ?? ''));
        $qta  = (int) ($_POST['quantita'] ?? 0);
        $data = $db->real_escape_string($_POST['data_acquisto'] ?? '');
        $cat  = (int) ($_POST['categoria_macchinario'] ?? 0);
        $sta  = $db->real_escape_string($_POST['stato'] ?? 'Attivo');
        $srv  = array_map('intval', $_POST['servizi_id'] ?? []);

        /* INSERT -- UPDATE ------------------------------------------- */
        if ($id > 0) {
            $sql = "
              UPDATE macchinari SET
                nome_macchinario = '$nome',
                modello          = '$mod',
                marca            = '$mar',
                descrizione      = '$desc',
                quantita         = $qta,
                data_acquisto    = " . ($data ? "'$data'" : 'NULL') . ",
                categoria_id     = $cat,
                stato            = '$sta'
              WHERE macchinario_id = $id
            ";
            $ok = $db->query($sql);
        } else {
            $sql = "
              INSERT INTO macchinari
                (nome_macchinario, modello, marca, descrizione,
                 quantita, data_acquisto, categoria_id, stato)
              VALUES
                ('$nome', '$mod', '$mar', '$desc',
                 $qta, " . ($data ? "'$data'" : 'NULL') . ", $cat, '$sta')
            ";
            $ok = $db->query($sql);
            if ($ok) $id = $db->insert_id;
        }

        /* sincronizzo tabella ponte */
        if ($ok) {
            /* svuoto relazioni non più valide */
            $db->query("DELETE FROM macchinari_servizi WHERE macchinario_id = $id");
            /* reinserisco quelle spuntate */
            if ($srv) {
                foreach ($srv as $s) {
                    $db->query("INSERT IGNORE INTO macchinari_servizi (macchinario_id, servizio_id)
                                VALUES ($id, $s)");
                }
            }
            $_SESSION['mach_flash'] = '<div class="alert alert-success">Macchinario salvato.</div>';
        } else {
            $_SESSION['mach_flash'] = '<div class="alert alert-danger">Errore SQL: '
                                     . htmlspecialchars($db->error, ENT_QUOTES) . '</div>';
        }
        header('Location: index.php?page=macchinari');
        exit;
    }

    /* -------------------------------------------------- FORM (edit) --- */
    $form   = [
        'macchinario_id'=>0,'nome_macchinario'=>'','modello'=>'','marca'=>'',
        'descrizione'=>'','quantita'=>'','data_acquisto'=>'',
        'categoria_id'=>0,'stato'=>'Attivo'
    ];
    $srvSel = [];
    $label  = 'Aggiungi';

    if ($action === 'edit' && isset($_GET['id'])) {
        $rid = (int) $_GET['id'];
        $r   = $db->query("SELECT * FROM macchinari WHERE macchinario_id = $rid");
        if ($r && $r->num_rows) {
            $form  = $r->fetch_assoc();
            $label = 'Modifica';
        }
        $rs = $db->query("SELECT servizio_id FROM macchinari_servizi WHERE macchinario_id = $rid");
        while ($row = $rs->fetch_assoc()) $srvSel[] = (int) $row['servizio_id'];
    }

    /* --- dropdown stati --------------------------------------------- */
    $stati = ['Attivo','In Manutenzione','Fuori Servizio'];
    $optStati = '';
    foreach ($stati as $s)
        $optStati .= "<option value=\"$s\"".($form['stato']===$s?' selected':'').">$s</option>";

    /* --- dropdown categorie ----------------------------------------- */
    $optCat = '';
    $rc = $db->query("SELECT categoria_id, nome_categoria
                      FROM categorie_macchinari ORDER BY nome_categoria");
    while ($c = $rc->fetch_assoc()) {
        $sel = ((int)$c['categoria_id']===(int)$form['categoria_id'])?' selected':'';
        $optCat .= "<option value=\"{$c['categoria_id']}\"$sel>"
                 . htmlspecialchars($c['nome_categoria'],ENT_QUOTES)."</option>";
    }

    /* --- checkbox servizi ------------------------------------------- */
    $chkServ = '';
    $rsAll = $db->query("SELECT servizio_id, nome FROM servizi ORDER BY nome");
    while ($s = $rsAll->fetch_assoc()) {
        $sid     = (int)$s['servizio_id'];
        $checked = in_array($sid, $srvSel, true) ? ' checked' : '';
        $chkServ .= "
          <div class=\"form-check form-check-inline me-3 mb-2\">
            <input class=\"form-check-input\" type=\"checkbox\"
                   id=\"srv_$sid\" name=\"servizi_id[]\"
                   value=\"$sid\"$checked>
            <label class=\"form-check-label\" for=\"srv_$sid\">"
                . htmlspecialchars($s['nome'],ENT_QUOTES) . "
            </label>
          </div>";
    }

    /* --- lista macchinari (tabella) ---------------------------------- */
    $sqlList = "
      SELECT m.*, c.nome_categoria,
             GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') AS servizi
      FROM macchinari m
      LEFT JOIN categorie_macchinari c ON c.categoria_id = m.categoria_id
      LEFT JOIN macchinari_servizi   ms ON ms.macchinario_id = m.macchinario_id
      LEFT JOIN servizi              s  ON s.servizio_id    = ms.servizio_id
      GROUP BY m.macchinario_id
      ORDER BY m.creato_il DESC";
    $tbl = '';
    $rl  = $db->query($sqlList);
    while ($row = $rl->fetch_assoc()) {
        $tbl .= "
          <tr>
            <td>".htmlspecialchars($row['nome_macchinario'],ENT_QUOTES)."</td>
            <td>".htmlspecialchars($row['modello'],ENT_QUOTES)."</td>
            <td>".htmlspecialchars($row['marca'],ENT_QUOTES)."</td>
            <td>".htmlspecialchars($row['descrizione'],ENT_QUOTES)."</td>
            <td>".($row['data_acquisto']?date('d/m/Y',strtotime($row['data_acquisto'])):'-')."</td>
            <td>{$row['quantita']}</td>
            <td>".htmlspecialchars($row['servizi'] ?: '-',ENT_QUOTES)."</td>
            <td>".htmlspecialchars($row['nome_categoria'] ?: '-',ENT_QUOTES)."</td>
            <td>{$row['stato']}</td>
            <td>
              <a href=\"index.php?page=macchinari&action=edit&id={$row['macchinario_id']}\"
                 class=\"btn btn-outline-modern btn-xs me-1\"><i class=\"fas fa-edit\"></i></a>
              <a href=\"index.php?page=macchinari&action=delete&id={$row['macchinario_id']}\"
                 class=\"btn btn-outline-modern btn-xs text-danger\"
                 onclick=\"return confirm('Eliminare questo macchinario?');\">
                 <i class=\"fas fa-trash-alt\"></i></a>
            </td>
          </tr>";
    }
    if ($tbl==='') {
        $tbl = "<tr><td colspan='10' class='text-center text-muted'>Nessun macchinario inserito.</td></tr>";
    }

    /* --- carico template -------------------------------------------- */
    $template = file_get_contents(__DIR__.'/../dtml/webarch/macchinari.html');

    $bodyTpl = str_replace(
        [
          '<[messaggio_form]>',       '<[label_submit]>',
          '<[old_macchinario_id]>',   '<[old_nome_macchinario]>', '<[old_modello]>',
          '<[old_marca]>',            '<[old_descrizione]>',      '<[old_quantita]>',
          '<[old_data_acquisto]>',    '<[lista_servizi_macchinario]>',
          '<[lista_stati_macchinario]>','<[lista_categorie_macchinario]>',
          '<[lista_macchinari]>'
        ],
        [
          $flash,
          $label,
          $form['macchinario_id'],
          htmlspecialchars($form['nome_macchinario'],ENT_QUOTES),
          htmlspecialchars($form['modello'],ENT_QUOTES),
          htmlspecialchars($form['marca'],ENT_QUOTES),
          htmlspecialchars($form['descrizione'],ENT_QUOTES),
          $form['quantita'],
          $form['data_acquisto'],
          $chkServ,
          $optStati,
          $optCat,
          $tbl
        ],
        $template
    );
}

/* ---------------------------------------------------- HELPERS ------ */

/** checkbox list (per form) */
function buildServiziCheckboxes(PDO $pdo, array $selected=[]): string {
    $html='';
    foreach ($pdo->query('SELECT servizio_id, nome FROM servizi ORDER BY nome') as $s) {
        $sid=(int)$s['servizio_id'];
        $chk=in_array($sid,$selected,true)?' checked':'';
        $html.="<div class='form-check form-check-inline me-3 mb-2'>
                  <input class='form-check-input' type='checkbox'
                         id='srv_$sid' name='servizi_id[]'
                         value='$sid'$chk>
                  <label class='form-check-label' for='srv_$sid'>"
                    .htmlspecialchars($s['nome'],ENT_QUOTES)."</label>
                </div>";
    }
    return $html;
}

/** dropdown stati */
function buildStatiOptions(string $current=''): string{
    $out=''; $stati=['Attivo','In Manutenzione','Fuori Servizio'];
    foreach($stati as $s) $out.="<option value='$s'\".($current===$s?' selected':'').\">$s</option>";
    return $out;
}

/** dropdown categorie */
function buildCategorieOptions(PDO $pdo, ?int $current=null): string {
    $out='';
    foreach ($pdo->query('SELECT categoria_id, nome_categoria FROM categorie_macchinari ORDER BY nome_categoria') as $c) {
        $sel=((int)$c['categoria_id']===$current)?' selected':'';
        $out.="<option value='{$c['categoria_id']}'$sel>"
             .htmlspecialchars($c['nome_categoria'],ENT_QUOTES)."</option>";
    }
    return $out;
}
?>
