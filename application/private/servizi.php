<?php
// application/private/servizi.php
// Lista / aggiunta servizi del fisioterapista loggato
// Template: dtml/webarch/servizi.html

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

function handleServizi(bool &$show, string &$bodyHtml): void {

    /* pagina corrente? ---------------------------------------------------- */
    if (($_GET['page'] ?? '') !== 'servizi') { $show = false; return; }
    $show = true;

    /* sessione e fisioterapista loggato ---------------------------------- */
    if (session_status() === PHP_SESSION_NONE) session_start();
    $fisioId = (int) ($_SESSION['fisio'] ?? 0);
    if ($fisioId === 0) { header('Location: index.php?page=login'); exit; }

    /* DB & flash ---------------------------------------------------------- */
    $db = Db::getConnection();  $db->set_charset('utf8');
    $flash = $_SESSION['srv_flash'] ?? ''; unset($_SESSION['srv_flash']);

    /**********************************************************************
     * 1) DELETE  – rimuove la relazione dal ponte
     *********************************************************************/
    if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
        $sid = (int) $_GET['id'];
        $db->query("DELETE FROM servizi_fisioterapista
                    WHERE fisioterapista_id = $fisioId AND servizio_id = $sid");
        $_SESSION['srv_flash'] =
            '<div class="alert alert-success">Servizio rimosso dal tuo profilo.</div>';
        header('Location: index.php?page=servizi'); exit;
    }

    /**********************************************************************
     * 2) SAVE  – aggiunge un nuovo servizio al profilo
     *********************************************************************/
    if (($_GET['action'] ?? '') === 'save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $sid  = (int) ($_POST['servizio_id'] ?? 0);
        $pp   = trim($_POST['prezzo_personalizzato'] ?? '');
        $pp   = $pp === '' ? 'NULL' : "'" . str_replace(',','.', $db->real_escape_string($pp)) . "'";

        if ($sid <= 0) {
            $flash = '<div class="alert alert-danger">Seleziona un servizio.</div>';
        } else {
            $db->query("INSERT IGNORE INTO servizi_fisioterapista
                        (fisioterapista_id, servizio_id, prezzo_personalizzato)
                        VALUES ($fisioId, $sid, $pp)");
            $_SESSION['srv_flash'] =
                '<div class="alert alert-success">Servizio aggiunto al tuo profilo.</div>';
            header('Location: index.php?page=servizi'); exit;
        }
    }

    /**********************************************************************
     * 3) DROPDOWN  – servizi NON ancora collegati al fisio
     *********************************************************************/
    $servizi_options = '';
    $rsOpt = $db->query("
        SELECT s.servizio_id, s.nome
        FROM servizi s
        WHERE s.servizio_id NOT IN
              (SELECT servizio_id
                 FROM servizi_fisioterapista
                 WHERE fisioterapista_id = $fisioId)
        ORDER BY s.nome
    ");
    while ($r = $rsOpt->fetch_assoc()) {
        $servizi_options .= "<option value='{$r['servizio_id']}'>"
                          . htmlspecialchars($r['nome'], ENT_QUOTES) . "</option>";
    }

    /**********************************************************************
     * 4) TABELLINA  – servizi del fisioterapista (con prezzo e categoria)
     *********************************************************************/
    $sqlList = "
        SELECT  s.servizio_id,
                s.nome,
                s.descrizione,
                COALESCE(sf.prezzo_personalizzato, s.prezzo_base)   AS prezzo,
                GROUP_CONCAT(DISTINCT c.nome_categoria
                             ORDER BY c.nome_categoria
                             SEPARATOR ', ')                         AS categoria
        FROM    servizi_fisioterapista sf
        JOIN    servizi s            ON s.servizio_id = sf.servizio_id
        LEFT JOIN servizi_categorie sc ON sc.servizio_id = s.servizio_id
        LEFT JOIN categorie_servizi  c ON c.categoria_id = sc.categoria_id
        WHERE   sf.fisioterapista_id = $fisioId
          AND   sf.attivo = 1
        GROUP BY s.servizio_id, s.nome, s.descrizione, prezzo
        ORDER BY s.nome
    ";
    $lista_servizi = '';
    $rs = $db->query($sqlList);
    while ($row = $rs->fetch_assoc()) {
        $sid  = (int) $row['servizio_id'];
        $prz  = number_format($row['prezzo'], 2, ',', '.');

        $lista_servizi .= "
          <tr>
            <td>" . htmlspecialchars($row['nome'], ENT_QUOTES)           . "</td>
            <td>" . htmlspecialchars($row['descrizione'], ENT_QUOTES)    . "</td>
            <td>{$prz}</td>
            <td>" . htmlspecialchars($row['categoria'] ?? '-', ENT_QUOTES) . "</td>
            <td>
              <a href='index.php?page=servizi&action=delete&id={$sid}'
                 class='btn btn-outline-modern btn-xs text-danger'
                 onclick=\"return confirm('Rimuovere questo servizio?');\">
                 <i class='fas fa-trash-alt me-1'></i>Elimina
              </a>
            </td>
          </tr>";
    }
    if ($lista_servizi === '') {
        $lista_servizi = "
          <tr><td colspan='6' class='text-center text-muted'>
            Nessun servizio associato al tuo profilo.
          </td></tr>";
    }

    /**********************************************************************
     * 5) RENDER TEMPLATE
     *********************************************************************/
    $tpl = new Template('dtml/webarch/servizi');
    $tpl->setContent('label_submit',    'Aggiungi');
    $tpl->setContent('messaggio_form',  $flash);
    $tpl->setContent('servizi_options', $servizi_options);
    $tpl->setContent('lista_servizi',   $lista_servizi);

    $bodyHtml = $tpl->get();
}
?>
