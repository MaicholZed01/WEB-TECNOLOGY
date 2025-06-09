<?php
// application/private/macchinari.php
require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

function handleMacchinari(bool &$show, string &$body)
{
    if (($_GET['page'] ?? '') !== 'macchinari') {
        $show = false;
        return;
    }
    $show = true;
    session_start();

    $db = Db::getConnection();
    $db->set_charset('utf8');

    // flash message
    $flash = $_SESSION['mach_flash'] ?? '';
    unset($_SESSION['mach_flash']);

    $action = $_GET['action'] ?? '';

    // ---- DELETE ----
    if ($action === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $db->query("DELETE FROM macchinari WHERE macchinario_id = $id");
        $db->query("DELETE FROM macchinari_servizi WHERE macchinario_id = $id");
        $_SESSION['mach_flash'] = '<div class="alert alert-success">Macchinario eliminato.</div>';
        header('Location: index.php?page=macchinari');
        exit;
    }

    // ---- SAVE (INSERT / UPDATE) ----
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id         = (int)($_POST['macchinario_id'] ?? 0);
        $nome       = trim($_POST['nome_macchinario'] ?? '');
        $modello    = trim($_POST['modello'] ?? '');
        $marca      = trim($_POST['marca'] ?? '');
        $descr      = trim($_POST['descrizione'] ?? '');
        $quantita   = (int)($_POST['quantita'] ?? 0);
        $dataAcq    = $_POST['data_acquisto'] ?? '';
        $stato      = $_POST['stato'] ?? '';
        $categoria  = (int)($_POST['categoria_macchinario'] ?? 0);
        $serviziSel = array_map('intval', $_POST['servizi_id'] ?? []);

        // validazioni
        if ($nome === '' || $dataAcq === '' || $quantita < 1 || $stato === '' || $categoria <= 0) {
            $_SESSION['mach_flash'] = "<div class='alert alert-danger'>
                Compila tutti i campi obbligatori (quantità ≥1, nome, data acquisto, stato, categoria).</div>";
            header('Location: index.php?page=macchinari');
            exit;
        }

        // esc
        $nomeEsc     = $db->real_escape_string($nome);
        $modEsc      = $db->real_escape_string($modello);
        $marcaEsc    = $db->real_escape_string($marca);
        $descrEsc    = $db->real_escape_string($descr);
        $statoEsc    = $db->real_escape_string($stato);

        // costruisco SQL
        if ($id > 0) {
            $sql = "UPDATE macchinari SET
                        nome_macchinario = '$nomeEsc',
                        modello          = '$modEsc',
                        marca            = '$marcaEsc',
                        descrizione      = '$descrEsc',
                        quantita         = $quantita,
                        data_acquisto    = '$dataAcq',
                        stato            = '$statoEsc',
                        categoria_id     = $categoria
                    WHERE macchinario_id = $id";
            $ok = $db->query($sql);
        } else {
            $sql = "INSERT INTO macchinari
                        (nome_macchinario, modello, marca, descrizione,
                         quantita, data_acquisto, stato, categoria_id)
                    VALUES
                        ('$nomeEsc', '$modEsc', '$marcaEsc', '$descrEsc',
                         $quantita, '$dataAcq', '$statoEsc', $categoria)";
            $ok = $db->query($sql);
            if ($ok) {
                $id = $db->insert_id;
            }
        }

        if ($ok) {
            // sincronizzo pivot tutti i servizi selezionati
            $db->query("DELETE FROM macchinari_servizi WHERE macchinario_id = $id");
            foreach ($serviziSel as $sid) {
                $sid = (int)$sid;
                $db->query("INSERT IGNORE INTO macchinari_servizi (macchinario_id, servizio_id)
                            VALUES ($id, $sid)");
            }
            $_SESSION['mach_flash'] = '<div class="alert alert-success">Macchinario salvato.</div>';
        } else {
            $_SESSION['mach_flash'] = '<div class="alert alert-danger">
                Errore SQL: '.htmlspecialchars($db->error,ENT_QUOTES).'</div>';
        }

        header('Location: index.php?page=macchinari');
        exit;
    }

    // ---- PREPARO FORM (edit) ----
    $form = [
        'macchinario_id'=>0,
        'nome_macchinario'=>'',
        'modello'=>'',
        'marca'=>'',
        'descrizione'=>'',
        'quantita'=>1,
        'data_acquisto'=>date('Y-m-d'),
        'stato'=>'',
        'categoria_id'=>0
    ];
    $serviti = [];
    $label   = 'Aggiungi';

    if ($action === 'edit' && isset($_GET['id'])) {
        $rid = (int)$_GET['id'];
        $res = $db->query("SELECT * FROM macchinari WHERE macchinario_id = $rid");
        if ($res && $res->num_rows) {
            $form = $res->fetch_assoc();
            $label = 'Modifica';
        }
        $rs = $db->query("SELECT servizio_id FROM macchinari_servizi WHERE macchinario_id = $rid");
        while ($r = $rs->fetch_assoc()) {
            $serviti[] = (int)$r['servizio_id'];
        }
    }

    // ---- STATI e CATEGORIE ----
    $optStati = '';
    foreach (['Attivo','In Manutenzione','Fuori Servizio'] as $s) {
        $sel = ($form['stato'] === $s) ? ' selected' : '';
        $optStati .= "<option value=\"$s\"$sel>$s</option>";
    }

    $optCat = "<option value=''>-- Seleziona Categoria --</option>";
    $rc = $db->query("SELECT categoria_id, nome_categoria FROM categorie_macchinari ORDER BY nome_categoria");
    while ($c = $rc->fetch_assoc()) {
        $sel = ((int)$c['categoria_id'] === (int)$form['categoria_id']) ? ' selected' : '';
        $optCat .= "<option value=\"{$c['categoria_id']}\"$sel>"
                 .htmlspecialchars($c['nome_categoria'],ENT_QUOTES)."</option>";
    }

    // ---- CHECKBOX SERVIZI ----
    $chkServ = '';
    $rsAll = $db->query("SELECT servizio_id, nome FROM servizi ORDER BY nome");
    while ($s = $rsAll->fetch_assoc()) {
        $sid     = (int)$s['servizio_id'];
        $checked = in_array($sid, $serviti, true) ? ' checked' : '';
        $chkServ .= "
        <div class=\"form-check form-check-inline me-3 mb-2\">
          <input class=\"form-check-input\" type=\"checkbox\"
                 id=\"srv_$sid\" name=\"servizi_id[]\" value=\"$sid\"$checked>
          <label class=\"form-check-label\" for=\"srv_$sid\">"
             .htmlspecialchars($s['nome'],ENT_QUOTES).
          "</label>
        </div>";
    }

    // ---- TABELLA MACCHINARI ----
    $tbl = '';
    /* --- TABELLA MACCHINARI ----------------------------------------- */
  /* ↑↑  AGGIUNGI QUESTA RIGA  ↑↑ */
  $db->query("SET SESSION group_concat_max_len = 1000000");

  /* query con GROUP_CONCAT */
  $sql = "
    SELECT m.macchinario_id,
          m.nome_macchinario,
          m.modello,
          m.marca,
          m.descrizione,
          m.quantita,
          DATE_FORMAT(m.data_acquisto,'%d/%m/%Y') AS data_acq,
          m.stato,
          c.nome_categoria,
          GROUP_CONCAT(s.nome ORDER BY s.nome SEPARATOR ', ') AS servizi
    FROM macchinari m
    LEFT JOIN categorie_macchinari c ON c.categoria_id = m.categoria_id
    LEFT JOIN macchinari_servizi ms ON ms.macchinario_id = m.macchinario_id
    LEFT JOIN servizi             s ON s.servizio_id    = ms.servizio_id
    GROUP BY m.macchinario_id
    ORDER BY m.macchinario_id DESC";

    $rl = $db->query($sql);
    while ($row = $rl->fetch_assoc()) {
        $tbl .= "<tr>
          <td>".htmlspecialchars($row['nome_macchinario'],ENT_QUOTES)."</td>
          <td>".htmlspecialchars($row['modello'],ENT_QUOTES)."</td>
          <td>".htmlspecialchars($row['marca'],ENT_QUOTES)."</td>
          <td>".htmlspecialchars($row['descrizione'],ENT_QUOTES)."</td>
          <td>".($row['data_acquisto']?date('d/m/Y',strtotime($row['data_acquisto'])):'-')."</td>
          <td>{$row['quantita']}</td>
          <td>".htmlspecialchars($row['servizi'] ?: '-',ENT_QUOTES)."</td>
          <td>".htmlspecialchars($row['nome_categoria'] ?: '-',ENT_QUOTES)."</td>
          <td>".htmlspecialchars($row['stato'],ENT_QUOTES)."</td>
          <td>
            <a href=\"index.php?page=macchinari&action=edit&id={$row['macchinario_id']}\" 
               class=\"btn btn-outline-modern btn-xs me-1\"><i class=\"fas fa-edit\"></i></a>
            <a href=\"index.php?page=macchinari&action=delete&id={$row['macchinario_id']}\" 
               class=\"btn btn-outline-modern btn-xs text-danger\" 
               onclick=\"return confirm('Eliminare questo macchinario?');\">
               <i class=\"fas fa-trash-alt\"></i>
            </a>
          </td>
        </tr>";
    }
    if ($tbl === '') {
        $tbl = "<tr><td colspan='10' class='text-center text-muted'>Nessun macchinario inserito.</td></tr>";
    }

    // ---- RENDER TEMPLATE ----
    $tpl = new Template('dtml/webarch/macchinari');
    $tpl->setContent('messaggio_form',           $flash);
    $tpl->setContent('label_submit',             $label);
    $tpl->setContent('old_macchinario_id',       $form['macchinario_id']);
    $tpl->setContent('old_nome_macchinario',     htmlspecialchars($form['nome_macchinario'],ENT_QUOTES));
    $tpl->setContent('old_modello',              htmlspecialchars($form['modello'],ENT_QUOTES));
    $tpl->setContent('old_marca',                htmlspecialchars($form['marca'],ENT_QUOTES));
    $tpl->setContent('old_descrizione',          htmlspecialchars($form['descrizione'],ENT_QUOTES));
    $tpl->setContent('old_quantita',             $form['quantita']);
    $tpl->setContent('old_data_acquisto',        $form['data_acquisto']);
    $tpl->setContent('lista_servizi_macchinario',$chkServ);
    $tpl->setContent('lista_stati_macchinario',  $optStati);
    $tpl->setContent('lista_categorie_macchinario',$optCat);
    $tpl->setContent('lista_macchinari',         $tbl);

    $body = $tpl->get();
}
