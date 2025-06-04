<?php
// application/public/avvisi.php

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 * handleAvvisi(&$showAvvisi, &$bodyHtmlAvvisi)
 *   - Se $_GET['page']=='avvisi', imposta $showAvvisi=true.
 *   - Recupera tutti gli annunci dal DB e costruisce le card HTML.
 */
function handleAvvisi(bool &$showAvvisi, string &$bodyHtmlAvvisi): void {
    $showAvvisi      = false;
    $bodyHtmlAvvisi  = '';

    if (!isset($_GET['page']) || $_GET['page'] !== 'avvisi') {
        return;
    }
    $showAvvisi = true;

    $conn = Db::getConnection();

    // 1) Recupero tutti gli annunci, più recenti per primi
    $res = $conn->query("
        SELECT 
          annuncio_id,
          titolo,
          contenuto,
          pubblicato_il
        FROM annunci
        ORDER BY pubblicato_il DESC
    ");

    if ($res === false) {
        // In caso di errore SQL, mostro un messaggio generico
        $htmlCards = '<div class="col-12"><div class="alert alert-danger">'
                   . 'Errore durante il caricamento degli avvisi.'
                   . '</div></div>';
    } else {
        // 2) Costruisco le card dinamiche
        $htmlCards = '';
        while ($row = $res->fetch_assoc()) {
            $id          = (int) $row['annuncio_id'];
            $titolo      = htmlspecialchars($row['titolo'], ENT_QUOTES);
            // Estraggo i primi 100 caratteri di contenuto per l’anteprima
            $anteprima   = htmlspecialchars(mb_substr($row['contenuto'], 0, 100, 'UTF-8'), ENT_QUOTES) . '…';
            $data_pub    = date_create($row['pubblicato_il']);
            $data_form   = $data_pub ? date_format($data_pub, 'j F Y') : '';

            // Link (potrebbe puntare a una pagina di dettaglio futura; per ora usiamo “#”)
            // Se in futuro si crea un dettaglio, basterà cambiare questo href.
            $linkCard = "#";

            $htmlCards .= "
              <div class=\"col-md-4 col-sm-6 mb-4\">
                <div class=\"news-thumb shadow-sm\" style=\"border-radius:.5rem; overflow:hidden;\">
                  <a href=\"{$linkCard}\">
                    <!-- Se desideri un'immagine rappresentativa, sostituisci il src qui di seguito -->
                    <img src=\"/tec-web/application/dtml/2098_health/images/default-news.jpg\" 
                         class=\"img-responsive\" alt=\"{$titolo}\">
                  </a>
                  <div class=\"news-info p-3\">
                    <span class=\"d-block text-muted mb-1\">{$data_form}</span>
                    <h3 class=\"mb-2\"><a href=\"{$linkCard}\">{$titolo}</a></h3>
                    <p>{$anteprima}</p>
                    <div class=\"author d-flex align-items-center mt-3\">
                      <img src=\"/tec-web/application/dtml/2098_health/images/author-image.jpg\" 
                           class=\"img-responsive rounded-circle me-2\" 
                           style=\"width:40px; height:40px;\" alt=\"Autore\">
                      <div class=\"author-info\">
                        <h5 class=\"mb-0\">Staff Centro Fisio</h5>
                        <p class=\"mb-0 text-muted small\">Comunicazione</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ";
        }
        // Se non ci sono annunci nel DB:
        if ($res->num_rows === 0) {
            $htmlCards = '<div class="col-12"><div class="alert alert-info">'
                       . 'Al momento non ci sono avvisi o novità.'
                       . '</div></div>';
        }
    }

    // 3) Carico il template 'avvisi.html' e sostituisco <[lista_avvisi]>
    $tpl = new Template('dtml/2098_health/avvisi');
    $tpl->setContent('lista_avvisi', $htmlCards);
    $bodyHtmlAvvisi = $tpl->get();
}
?>