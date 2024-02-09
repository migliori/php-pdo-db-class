<?php

declare(strict_types=1);

namespace database;

/**
 * Pagination class
 *
 * This class is used to generate pagination links for a given set of results. It takes the following arguments:
 *
 * - `$pdo_settings`: This is an array that contains the PDO settings to be used for retrieving the results. It can either be an array containing the `function` (which should be `select` or `query`), the `from` clause, the `values` clause, the `where` clause, the `extras` clause, and the `debug` clause (which should be set to `false`), or it can be an array containing the `sql` clause and the `placeholders` clause (which should be set to `false`).
 * - `$mpp`: This is the maximum number of results to display per page.
 * - `$querystring`: This is the name of the querystring parameter that will be used to indicate the current page.
 * - `$url`: This is the URL of the page that is being paginated.
 * - `$long`: This is the number of pages to display before and after the current page.
 * - `$rewrite_links`: This is a boolean value that determines whether or not the links should be rewritten to include the querystring parameters.
 * - `$rewrite_transition`: This is the character that is used to indicate the start of the querystring parameters in the URL (for example, `-` for `example.com/page-1-2.html`).
 * - `$rewrite_extension`: This is the extension that is added to the end of the URL when the links are being rewritten (for example, `.html`).
 *
 * The function then performs the following steps:
 *
 * 1. It gets the total number of results from the database using the provided PDO settings.
 * 2. It calculates the number of pages based on the total number of results and the maximum number of results per page.
 * 3. It determines the current page based on the value of the querystring parameter.
 * 4. It determines the start and end indices for the current page.
 * 5. It builds an array of page numbers that will be displayed in the pagination links.
 * 6. It adds the appropriate `LIMIT` clause to the PDO settings based on the current page and the maximum number of results per page.
 * 7. It retrieves the results using the updated PDO settings.
 * 8. It determines the current page number based on the number of results returned.
 * 9. It builds the pagination links and the result message.
 * 10. It returns the pagination links and the result message as HTML.
 *
 * The function also allows you to customize the appearance of the pagination links by setting the following options:
 *
 * - `$active_class`: This is the CSS class that is applied to the current page link.
 * - `$disabled_class`: This is the CSS class that is applied to the disabled page links.
 * - `$first_markup`: This is the HTML markup that is displayed for the first page link.
 * - `$previous_markup`: This is the HTML markup that is displayed for the previous page link.
 * - `$next_markup`: This is the HTML markup that is displayed for the next page link.
 * - `$last_markup`: This is the HTML markup that is displayed for the last page link.
 * - `$pagination_class`: This is the CSS class that is applied to the pagination list.
 * - `$rewrite_transition`: This is the character that is used to indicate the start of the querystring parameters in the URL (for example, `-` for `example.com/page-1-2.html`).
 * - `$rewrite_extension`: This is the extension that is added to the end of the URL when the links are being rewritten (for example, `.html`).
 */
class Pagination extends DB
{
    public $pagine;
    private $active_class       = 'active';
    private $disabled_class     = 'disabled';
    private $pagination_class   = 'pagination pagination-flat';
    private $first_markup       = '<i class="fas fa-angle-double-left"></i>';
    private $previous_markup    = '<i class="fas fa-angle-left"></i>';
    private $next_markup        = '<i class="fas fa-angle-right"></i>';
    private $last_markup        = '<i class="fas fa-angle-double-right"></i>';
    private $results;
    private $rewrite_transition;
    private $rewrite_extension;

    /**
     * Build pagination
     *
     * @param array  $pdo_settings PDO settings to which the "LIMIT..." will be added. i.e:
     *
     * $pdo_settings = array(
     *
     *     'function' => 'select', // The function in the parent DB class.
     *
     *                                Values: 'select' or 'query'
     *
     *     'from'     => $from,   // refer to DB->select() arguments
     *
     *     'values'   => $columns, // refer to DB->select() arguments
     *
     *     'where'    => $where,   // refer to DB->select() arguments
     *
     *     'extras'   => $extras, // refer to DB->select() arguments
     *
     *     'debug'    => false     // refer to DB->select() arguments
     *
     * );
     *
     * or:
     *
     * $pdo_settings = array(
     *
     *     'function' => 'query', // The function in the parent DB class.
     *
     *                               Values: 'select' or 'query'
     *
     *     'sql'          => 'SELECT ...', // refer to DB->query() arguments
     *
     *     'placeholders' => false,        // refer to DB->query() arguments
     *
     *     'debug'        => false         // refer to DB->query() arguments
     *
     * );
     *
     * @param string  $mpp         Max number of lines per page
     * @param string  $querystring Querystring element indicating the page number
     * @param string  $url         URL of the page
     * @param integer $long        Max number of pages before and after the current page
     */
    /**
     * Paginate the results of a database query.
     *
     * @param array $pdo_settings The PDO settings for the database connection.
     * @param int $mpp The number of records to display per page.
     * @param string $querystring The query string to paginate.
     * @param string $url The base URL for the pagination links.
     * @param int $long The number of pages to display in the pagination navigation.
     * @param bool $rewrite_links Whether to rewrite the pagination links.
     * @param string $rewrite_transition The transition string to use for rewriting the links.
     * @param string $rewrite_extension The file extension to use for rewriting the links.
     * @return string The HTML for the pagination links.
     */
    public function pagine(array $pdo_settings, int $mpp, string $querystring, string $url, int $long = 5, bool $rewrite_links = true, string $rewrite_transition = '-', string $rewrite_extension = '.html'): string
    {
        $html_pagination = '';

        // To build the links, check if $url already contains a ?
        $t   = $this->rewrite_transition = $rewrite_transition;
        $ext = $this->rewrite_extension = $rewrite_extension;
        $url = $this->removePreviousQuerystring($url, $querystring, $rewrite_links);
        if ($rewrite_links !== true) {
            if (strpos($url, "?")) {
                $t = '&amp;';
            } else {
                $t = '?';
            }
        }
        $this->getRecords($pdo_settings);
        $nbre = parent::rowCount();  // Total number of records returned
        if (!empty($nbre)) {
            $_SESSION['result_rs'] = $nbre;    // Calculation of the number of pages
            $nbpage = ceil($nbre / $mpp);    // The current page is
            if (isset($_GET[$querystring])) {
                $p = $_GET[$querystring];
            } else {
                $p = 1;
            }
            if ($p > $nbpage) {
                $p = $nbpage;    // Length of the page list
            }
            $deb = max(1, $p - $long);
            $fin = min($nbpage, $p + $long);    // Building the list of pages
            $this->pagine = "";
            if ($nbpage > 1) {
                for ($i = $deb; $i <= $fin; $i++) { // Current page ?
                    if ($i == $p) {
                        $this->pagine .= '<li class="page-item ' . $this->active_class . '"><a class="page-link" href="#">' . $i . '</a></li>' . "\n";    // Page 1 > link without query
                    } elseif ($i == 1) {
                        if ($rewrite_links === true) {
                            $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $ext . '">' . $i . '</a></li>' . "\n";   // Other page -> link with query
                        } else {
                            $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . '">' . $i . '</a></li>' . "\n";   // Other page -> link with query
                        }
                    } else {
                        if ($rewrite_links === true) {
                            $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . $i . $ext . '">' . $i . '</a></li>' . "\n";
                        } else {
                            $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . '=' . $i . '">' . $i . '</a></li>' . "\n";
                        }
                    }
                }
                if ($this->pagine) {
                    $this->pagine = '<li class="page-item ' . $this->disabled_class . '"><a class="page-link" href="#">Page</a></li>' . $this->pagine . "\n";
                }
                if ($this->pagine && ($p > 1)) { //PREVIOUS
                    if ($p == 2) {
                        if ($rewrite_links === true) {
                            $this->pagine = '<li class="page-item"><a class="page-link" href="' . $url . $ext . '">' . $this->previous_markup . '</a></li>' . $this->pagine . "\n";
                        } else {
                            $this->pagine = '<li class="page-item"><a class="page-link" href="' . $url . '">' . $this->previous_markup . '</a></td>' . $this->pagine . "\n";
                        }
                    } else { //PREVIOUS
                        if ($rewrite_links === true) {
                            $this->pagine = '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . ($p - 1) . $ext . '">' . $this->previous_markup . '</a></li>' . $this->pagine . "\n";
                        } else {
                            $this->pagine = '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . '=' . ($p - 1)  . '">' . $this->previous_markup . '</a></li>' . $this->pagine . "\n";
                        }
                    }
                    if ($p > 1) { // FIRST
                        if ($rewrite_links === true) {
                            $this->pagine = '<li class="page-item"><a class="page-link" href="' . $url . $ext . '">' . $this->first_markup . '</a></li>' . $this->pagine . "\n";
                        } else {
                            $this->pagine = '<li class="page-item"><a class="page-link" href="' . $url . '">' . $this->first_markup . '</a></li>' . $this->pagine . "\n";
                        }
                    }
                }
                if ($this->pagine && ($p < $nbpage)) { // NEXT, LAST
                    if ($rewrite_links === true) {
                        $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . ($p + 1) . $ext . '">' . $this->next_markup . '</a></li>' . "\n"; // NEXT
                    } else {
                        $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . '=' . ($p + 1)  . '">' . $this->next_markup . '</a></li>' . "\n";
                    }
                    if ($p < $nbpage) { // LAST
                        if ($rewrite_links === true) {
                            $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . ($nbpage) . $ext . '">' . $this->last_markup . '</a></li>' . "\n";
                        } else {
                            $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . '=' . ($nbpage)  . '">' . $this->last_markup . '</a></li>' . "\n";
                        }
                    }
                }    // Modification of the request
                $pdo_settings_with_limit = $this->addRequestLimit($pdo_settings, (int) $p, $mpp);
                $this->getRecords($pdo_settings_with_limit); // new set of records with LIMIT clause
                $current_page_number = parent::rowCount(); // display 'results n to m on x // start = $start // end = $end // total = $number
                $start = $mpp * ($p - 1) + 1;    // no. per page x current page.
                $end = $start + $current_page_number - 1;
            } else {    // if there is only one page
                $this->pagine = '' . "\n";
                $start = 1;    // no. per page x current page.
                $end = $nbre;
            }

            // CRUD admin i18n
            if (defined('PAGINATION_RESULTS')) {
                $this->results = '<p class="text-right text-semibold">' . PAGINATION_RESULTS . ' ' . $start . ' ' . PAGINATION_TO . ' ' . $end . ' ' . PAGINATION_OF . ' ' . $nbre . '</p>' . "\n";
            } else {
                $this->results = '<p class="text-right text-semibold">résultats ' . $start . ' à ' . $end . ' sur ' . $nbre . '</p>' . "\n";
            }

            if (!empty($this->results)) {
                $html_pagination .= '<ul class="' . $this->pagination_class . '">' . "\n";
                $html_pagination .= $this->pagine . "\n";
                $html_pagination .= '</ul>' . "\n";
            }

            $html_pagination .= '<div class="heading-elements pt-2 pr-3">' . "\n";
            $html_pagination .= $this->results;
            $html_pagination .= '</div>' . "\n";
        }

        return $html_pagination;
    }

    /**
     * Sets form layout options to match your framework
     *
     * @param array $user_options (Optional) An associative array containing the
     *                            options names as keys and values as data.
     * @return void
     */
    public function setOptions(array $user_options = array()): void
    {
        $options = array('active_class', 'disabled_class', 'first_markup', 'pagination_class', 'previous_markup', 'next_markup', 'last_markup', 'rewrite_transition', 'rewrite_extension');
        foreach ($user_options as $key => $value) {
            if (in_array($key, $options)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Adds a request limit to the PDO settings based on the current page and items per page.
     *
     * @param array $pdo_settings The PDO settings array
     * @param int $p The current page
     * @param int $mpp The items per page
     * @return array The updated PDO settings array
     */
    private function addRequestLimit(array $pdo_settings, int $p, int $mpp): array
    {
        if ($pdo_settings['function'] === 'select') {
            $pdo_settings['extras']['limit'] = (($p - 1) * $mpp)  . ',' . $mpp;
        } else {
            $suppr = 'LIMIT';
            $find = strstr($pdo_settings['sql'], $suppr);
            $pdo_settings['sql'] = str_replace($find, '', $pdo_settings['sql']);    // if empty delete the "LIMIT" clause
            $pdo_settings['sql'] .= ' LIMIT ' . (($p - 1) * $mpp)  . ',' . $mpp;
        }

        return $pdo_settings;
    }

    /**
     * Executes the selected PDO function based on the provided PDO settings array.
     *
     * @param array $pdo_settings The PDO settings array
     * @return void
     */
    private function getRecords(array $pdo_settings): void
    {
        if ($pdo_settings['function'] === 'select') {
            parent::select(
                $pdo_settings['from'],
                $pdo_settings['values'],
                $pdo_settings['where'],
                $pdo_settings['extras'],
                $pdo_settings['debug']
            );
        } else {
            parent::query(
                $pdo_settings['sql'],
                $pdo_settings['placeholders'],
                $pdo_settings['debug']
            );
        }
    }

    /**
     * Removes any previous querystring parameters from the URL.
     *
     * @param string $url The URL to remove the querystring from
     * @param string $querystring The name of the querystring parameter to remove
     * @param bool $rewrite_links Whether or not the links are being rewritten
     * @return string The updated URL
     */
    private function removePreviousQuerystring(string $url, string $querystring, bool $rewrite_links): string
    {
        if ($rewrite_links === true) {
            $find = array('`' . $this->rewrite_transition . $querystring . '[0-9]+`', '`' . $this->rewrite_extension . '`');
            $replace = array('', '');
        } else {
            $find = array('`\?' . $querystring . '=([0-9]+)&(amp;)?`', '`\?' . $querystring . '=([0-9]+)`', '`&(amp;)?' . $querystring . '=([0-9]+)`');
            $replace = array('?', '', '');
        }
        $url = preg_replace($find, $replace, $url);

        return $url;
    }
}
