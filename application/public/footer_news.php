<?php
// application/public/footer_news.php

require_once __DIR__ . '/../include/dbms.inc.php';

/**
 * getFooterNews(int $maxItems = 3): string
 *   - Recupera le ultime $maxItems notizie dalla tabella annunci
 *   - Restituisce un HTML da iniettare in <[footer_news]> del frame
 */
function getFooterNews(int $maxItems = 3): string {
    $conn = Db::getConnection();

    $res = $conn->query("
        SELECT 
          annuncio_id,
          titolo,
          DATE_FORMAT(pubblicato_il, '%d/%m/%Y') AS data_pub
        FROM annunci
        ORDER BY pubblicato_il DESC
        LIMIT $maxItems
    ");
    if ($res === false) {
        return '<div class="text-danger small">Errore nel caricamento delle ultime news.</div>';
    }

    if ($res->num_rows === 0) {
        return '<div class="text-muted small">Nessuna news disponibile.</div>';
    }

    $html = '<ul class="list-unstyled">';
    while ($row = $res->fetch_assoc()) {
        $id       = (int) $row['annuncio_id'];
        $titolo   = htmlspecialchars($row['titolo'], ENT_QUOTES);
        $data_pub = htmlspecialchars($row['data_pub'], ENT_QUOTES);

        // Link alla sezione avvisi (scorrendo alla card corrispondente)
        // In questo esempio portiamo al render completo della pagina avvisi
        $linkAvviso = "index.php?page=avvisi#annuncio-{$id}";

        $html .= "
          <li class=\"mb-2\">
            <small class=\"text-muted me-1\">{$data_pub}</small>
            <a href=\"{$linkAvviso}\" class=\"text-dark small\">{$titolo}</a>
          </li>
        ";
    }
    $html .= '</ul>';

    return $html;
}
?>