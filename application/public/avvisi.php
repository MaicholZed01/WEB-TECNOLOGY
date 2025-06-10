<?php
// application/public/avvisi.php

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 * handleAvvisi(&$showAvvisi, &$bodyHtmlAvvisi)
 *   - Se $_GET['page']=='avvisi', imposta $showAvvisi=true.
 *   - Recupera tutti gli annunci dal DB con info autore e costruisce le card HTML.
 */
function handleAvvisi(bool &$showAvvisi, string &$bodyHtmlAvvisi): void {
    $showAvvisi     = false;
    $bodyHtmlAvvisi = '';

    if (!isset($_GET['page']) || $_GET['page'] !== 'avvisi') {
        return;
    }
    $showAvvisi = true;

    $conn = Db::getConnection();

    // Recupero annunci con dati del fisioterapista autore
    $sql = "
        SELECT
          a.annuncio_id,
          a.titolo,
          a.contenuto,
          a.pubblicato_il,
          p.nome AS fisio_nome,
          p.cognome AS fisio_cognome,
          p.url_foto_profilo
        FROM annunci a
        LEFT JOIN fisioterapisti p ON p.fisioterapista_id = a.fisioteraprista_id
        ORDER BY a.pubblicato_il DESC
    ";
    $res = $conn->query($sql);
    
    if ($res === false) {
        $htmlCards = '<div class="col-12"><div class="alert alert-danger">'
                   . 'Errore durante il caricamento degli avvisi.';
    } else {
        $htmlCards = '';
        while ($row = $res->fetch_assoc()) {
            $id        = (int)$row['annuncio_id'];
            $titolo    = htmlspecialchars($row['titolo'], ENT_QUOTES);
            $anteprima = htmlspecialchars(mb_substr($row['contenuto'], 0, 100, 'UTF-8'), ENT_QUOTES) . '…';
            $data_pub  = date_create($row['pubblicato_il']);
            $data_form = $data_pub ? date_format($data_pub, 'j F Y') : '';
            
            // Autore
            $authorName = trim(($row['fisio_nome'] ?: 'Staff') . ' ' . ($row['fisio_cognome'] ?: 'Centro Fisio'));
            $authorImg = $row['url_foto_profilo']
                ? $row['url_foto_profilo']
                : '/tec-web/application/dtml/2098_health/images/author-image.jpg';

            $linkCard = "index.php?page=news-detail&id={$id}";

            // costruzione url immagine news
            $img_avviso = '/tec-web/application/dtml/2098_health/images/news-image' . $id % 4 . '.jpg';


            // Card markup con altezza uniforme
            $htmlCards .= <<<HTML
            <div class="col-md-4 col-sm-6 mb-4 d-flex">
              <div class="news-thumb shadow-sm d-flex flex-column h-100" style="border-radius:.5rem; overflow:hidden;">
                <a href="{$linkCard}" class="d-block" style="flex-shrink:0;">
                  <img src="{$img_avviso}"
                      class="img-fluid" alt="{$titolo}" style="width:100%; height:180px; object-fit:cover;">
                </a>
                <div class="news-info p-3 d-flex flex-column flex-grow-1">
                  <span class="text-muted small mb-1">{$data_form}</span>
                  <h5 class="mb-2" style="min-height:3em;"><a href="{$linkCard}" class="text-dark">{$titolo}</a></h5>
                  <p class="flex-grow-1 overflow-hidden" style="min-height:4em;">{$anteprima}</p>
                  <div class="author d-flex align-items-center mt-3" style="flex-shrink:0;">
                    <img src="{$authorImg}" class="rounded-circle me-2" style="width:40px; height:40px; object-fit:cover;" alt="Autore">
                    <div class="author-info">
                      <h6 class="mb-0">{$authorName}</h6>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            HTML;
          }
          if ($res->num_rows === 0) {
              $htmlCards = '<div class="col-12"><div class="alert alert-info">'
                        . 'Al momento non ci sono avvisi o novità';
          }
      }

      // Iniezione template
      $tpl = new Template('dtml/2098_health/avvisi');
      $tpl->setContent('lista_avvisi', $htmlCards);
      $bodyHtmlAvvisi = $tpl->get();
}
?>
