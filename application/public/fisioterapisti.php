<?php
// application/public/fisioterapisti.php

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 * handleFisioterapisti(&$showList, &$bodyHtmlList)
 *   - Se $_GET['page']=='fisioterapisti', imposta $showList=true.
 *   - Recupera tutti i fisioterapisti e costruisce le card HTML con link a dettagli.
 */
function handleFisioterapisti(bool &$showList, string &$bodyHtmlList): void {
    $showList      = false;
    $bodyHtmlList  = '';

    if (!isset($_GET['page']) || $_GET['page'] !== 'fisioterapisti') {
        return;
    }
    $showList = true;

    $conn = Db::getConnection();

    // 1) Recupero tutti i fisioterapisti
    $res = $conn->query("
        SELECT 
            fisioterapista_id,
            nome,
            cognome,
            telefono,
            email,
            url_foto_profilo
        FROM fisioterapisti
        ORDER BY cognome, nome
    ");
    if ($res === false) {
        // In caso di errore SQL, mostro un messaggio generico
        $htmlCards = '<div class="alert alert-danger">Errore durante il caricamento dei fisioterapisti.</div>';
    } else {
        // 2) Costruisco le card dinamicamente
        $htmlCards = '';
        while ($row = $res->fetch_assoc()) {
            $id       = (int) $row['fisioterapista_id'];
            $nome     = htmlspecialchars($row['nome'], ENT_QUOTES);
            $cognome  = htmlspecialchars($row['cognome'], ENT_QUOTES);
            $telefono = htmlspecialchars($row['telefono'], ENT_QUOTES);
            $email    = htmlspecialchars($row['email'], ENT_QUOTES);
            $foto     = htmlspecialchars($row['url_foto_profilo'], ENT_QUOTES);

            // Se l'immagine non fosse presente, puoi usare un placeholder
            $imgTag = $foto !== ''
                ? "<img src=\"/tec-web/application/{$foto}\" class=\"img-responsive\" alt=\"Foto di {$nome} {$cognome}\">"
                : "<img src=\"/tec-web/application/upload/fisioterapisti/placeholder.jpg\" class=\"img-responsive\" alt=\"Placeholder\">";

            // Link alla pagina di dettaglio: index.php?page=dettagli_fisioterapista&id=X
            $linkDettagli = "index.php?page=dettagli_fisioterapista&id={$id}";

            $htmlCards .= "
                <div class=\"col-md-4 col-sm-6 mb-4\">
                  <div class=\"team-thumb shadow-sm\" style=\"border-radius:.5rem; overflow:hidden;\">
                    <a href=\"{$linkDettagli}\">
                      {$imgTag}
                    </a>
                    <div class=\"team-info p-3 text-center\">
                      <h3><a href=\"{$linkDettagli}\" class=\"text-dark\">{$nome} {$cognome}</a></h3>
                      <div class=\"team-contact-info\">
                        <p class=\"mb-1\"><i class=\"fa fa-phone\"></i> {$telefono}</p>
                        <p><i class=\"fa fa-envelope-o\"></i> {$email}</p>
                      </div>
                    </div>
                  </div>
                </div>
            ";
        }
        // Se non ci sono fisioterapisti nel DB:
        if ($res->num_rows === 0) {
            $htmlCards = '<div class="col-12"><div class="alert alert-info">Nessun fisioterapista disponibile al momento.</div></div>';
        }
    }

    // 3) Carico il template 'fisioterapisti.html' e sostituisco <[lista_fisioterapisti]>
    $tpl = new Template('dtml/2098_health/fisioterapisti');
    $tpl->setContent('lista_fisioterapisti', $htmlCards);
    $bodyHtmlList = $tpl->get();
}
?>