<?php

declare(strict_types=1);

namespace Migliori\Database;

use Migliori\Database\PdoSelectParams;
use Migliori\Database\PdoQueryParams;

/**
 * Pagination class
 *
 * This class is used to generate pagination links for a given set of results.
 *
 * Example of use:
 *
 * $pdo_select_params = new PdoSelectParams($from, $values, $where, $extras, $debug);
 * $db = new Pagination($pdo_select_params, $user_options);
 * $pagination_html = $db->pagine();
 *
 * or:
 *
 * $pdo_query_params = new PdoQueryParams($sql, $placeholders, $debug);
 * $db = new Pagination($pdo_query_params, $user_options);
 * $pagination_html = $db->pagine();
 *
 * It takes the following arguments:
 *
 * - `$pdo_params`: This is an instance of the PdoSelectParams or PdoQueryParams class that contains the PDO settings for the query.
 * - `$mpp`: This is the maximum number of results to display per page.
 * - `$querystring`: This is the name of the querystring parameter that will be used to indicate the current page.
 * - `$url`: This is the URL of the page that is being paginated.
 * - `$long`: This is the number of pages to display before and after the current page.
 * - `$user_options`: This is an associative array containing the options names as keys and values as data.
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
 * - `active_class`: This is the CSS class that is applied to the current page link.
 * - `disabled_class`: This is the CSS class that is applied to the disabled page links.
 * - `first_markup`: This is the HTML markup that is displayed for the first page link.
 * - `previous_markup`: This is the HTML markup that is displayed for the previous page link.
 * - `next_markup`: This is the HTML markup that is displayed for the next page link.
 * - `last_markup`: This is the HTML markup that is displayed for the last page link.
 * - `pagination_class`: This is the CSS class that is applied to the pagination list.
 * - `rewrite_transition`: This is the character that is used to indicate the start of the querystring parameters in the URL (for example, `-` for `example.com/page-1-2.html`).
 * - `rewrite_extension`: This is the extension that is added to the end of the URL when the links are being rewritten (for example, `.html`).
 */
class Pagination extends Db
{
    public string  $pagine = '';
    private PdoSelectParams|PdoQueryParams $pdo_params;
    /**
     * Default options
     * @var array<string, bool|string> $default_options The default options for the pagination.
     */
    private $default_options = [
        'active_class'        => 'active',
        'disabled_class'      => 'disabled',
        'first_markup'        => '<i class ="fas fa-angle-double-left"></i>',
        'pagination_class'    => 'pagination pagination-flat',
        'previous_markup'     => '<i class ="fas fa-angle-left"></i>',
        'next_markup'         => '<i class ="fas fa-angle-right"></i>',
        'last_markup'         => '<i class ="fas fa-angle-double-right"></i>',
        'rewrite_links'       => true,
        'rewrite_transition'  => '-',
        'rewrite_extension'   => '.html'
    ];
    private \stdClass $options;
    private string $results = '';

    /**
     * Constructor for the Pagination class.
     *
     * @param PdoSelectParams|PdoQueryParams $pdo_params PDO params to which the "LIMIT..." will be added.
     * @param array<string, bool|string> $user_options (Optional) An associative array containing the options names as keys and values as data.
     * e.g.: ['active_class' => 'active', 'disabled_class' => 'disabled', 'first_markup' => 'First', 'pagination_class' => 'pagination pagination-flat', 'previous_markup' => 'Previous', 'next_markup' => 'Next', 'last_markup' => 'Last', 'rewrite_transition' => '-', 'rewrite_extension' => '.html']
     */
    public function __construct(mixed $pdo_params, mixed $user_options = [])
    {
        $this->pdo_params = $pdo_params;
        $this->options = (object) array_merge($this->default_options, $user_options);

        // call the db constructor
        parent::__construct();
    }

    /**
     * Build pagination
     *
     * @param string  $url         The base URL for the pagination links.
     * @param int  $mpp            The number of records to display per page.
     * @param string  $querystring Querystring element indicating the page number
     * @param int $long            The number of pages to display in the pagination navigation.
     *
     * @return string The HTML for the pagination links.
     */
    public function pagine(string $url, int $mpp = 10, string $querystring = '', int $long = 5): string
    {
        $html_pagination = '';

        // To build the links, check if $url already contains a ?
        $t   = $this->options->rewrite_transition;
        $ext = $this->options->rewrite_extension;
        $url = $this->removePreviousQuerystring($url, $querystring);
        if ($this->options->rewrite_links !== true) {
            if (strpos($url, "?")) {
                $t = '&amp;';
            } else {
                $t = '?';
            }
        }
        $this->getRecords();
        $nbre = parent::rowCount();  // Total number of records returned
        if (!empty($nbre)) {
            $_SESSION['result_rs'] = $nbre;    // Calculation of the number of pages
            $nbpage = (int) ceil($nbre / $mpp);    // The current page is
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
                        $this->pagine .= '<li class="page-item ' . $this->options->active_class . '"><a class="page-link" href="#">' . $i . '</a></li>' . "\n";    // Page 1 > link without query
                    } elseif ($i == 1) {
                        if ($this->options->rewrite_links === true) {
                            $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $ext . '">' . $i . '</a></li>' . "\n";   // Other page -> link with query
                        } else {
                            $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . '">' . $i . '</a></li>' . "\n";   // Other page -> link with query
                        }
                    } else {
                        if ($this->options->rewrite_links === true) {
                            $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . $i . $ext . '">' . $i . '</a></li>' . "\n";
                        } else {
                            $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . '=' . $i . '">' . $i . '</a></li>' . "\n";
                        }
                    }
                }
                if ($this->pagine) {
                    $this->pagine = '<li class="page-item ' . $this->options->disabled_class . '"><a class="page-link" href="#">Page</a></li>' . $this->pagine . "\n";
                }
                if ($this->pagine && ($p > 1)) { //PREVIOUS
                    if ($p == 2) {
                        if ($this->options->rewrite_links === true) {
                            $this->pagine = '<li class="page-item"><a class="page-link" href="' . $url . $ext . '">' . $this->options->previous_markup . '</a></li>' . $this->pagine . "\n";
                        } else {
                            $this->pagine = '<li class="page-item"><a class="page-link" href="' . $url . '">' . $this->options->previous_markup . '</a></td>' . $this->pagine . "\n";
                        }
                    } else { //PREVIOUS
                        if ($this->options->rewrite_links === true) {
                            $this->pagine = '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . ($p - 1) . $ext . '">' . $this->options->previous_markup . '</a></li>' . $this->pagine . "\n";
                        } else {
                            $this->pagine = '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . '=' . ($p - 1)  . '">' . $this->options->previous_markup . '</a></li>' . $this->pagine . "\n";
                        }
                    }
                    if ($p > 1) { // FIRST
                        if ($this->options->rewrite_links === true) {
                            $this->pagine = '<li class="page-item"><a class="page-link" href="' . $url . $ext . '">' . $this->options->first_markup . '</a></li>' . $this->pagine . "\n";
                        } else {
                            $this->pagine = '<li class="page-item"><a class="page-link" href="' . $url . '">' . $this->options->first_markup . '</a></li>' . $this->pagine . "\n";
                        }
                    }
                }
                if ($this->pagine && ($p < $nbpage)) { // NEXT, LAST
                    if ($this->options->rewrite_links === true) {
                        $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . ($p + 1) . $ext . '">' . $this->options->next_markup . '</a></li>' . "\n"; // NEXT
                    } else {
                        $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . '=' . ($p + 1)  . '">' . $this->options->next_markup . '</a></li>' . "\n";
                    }
                    if ($p < $nbpage) { // LAST
                        if ($this->options->rewrite_links === true) {
                            $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . ($nbpage) . $ext . '">' . $this->options->last_markup . '</a></li>' . "\n";
                        } else {
                            $this->pagine .= '<li class="page-item"><a class="page-link" href="' . $url . $t . $querystring . '=' . ($nbpage)  . '">' . $this->options->last_markup . '</a></li>' . "\n";
                        }
                    }
                }    // Modification of the request
                $this->pdo_params->addRequestLimit((int) $p, $mpp);
                $this->getRecords(); // new set of records with LIMIT clause
                $current_page_number = parent::rowCount(); // display 'results n to m on x // start = $start // end = $end // total = $number
                $start = $mpp * ($p - 1) + 1;    // no. per page x current page.
                $end = $start + $current_page_number - 1;
            } else {    // if there is only one page
                $this->pagine = '' . "\n";
                $start = 1;    // no. per page x current page.
                $end = $nbre;
            }

            // CRUD admin i18n
            if (defined('PAGINATION_RESULTS') && defined('PAGINATION_OF') && defined('PAGINATION_TO')) {
                /** @disregard P1011 */
                $this->results = '<p class="text-right text-semibold">' . PAGINATION_RESULTS . ' ' . $start . ' ' . PAGINATION_TO . ' ' . $end . ' ' . PAGINATION_OF . ' ' . $nbre . '</p>' . "\n";
            } else {
                $this->results = '<p class="text-right text-semibold">results ' . $start . ' to ' . $end . ' of ' . $nbre . '</p>' . "\n";
            }

            if (!empty($this->results)) {
                $html_pagination .= '<ul class="' . $this->options->pagination_class . '">' . "\n";
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
     * Retrieves the records for the pagination.
     *
     * This method is responsible for fetching the records from the database
     * based on the current pagination settings.
     *
     * @return void
     */
    private function getRecords(): void
    {
        if ($this->pdo_params instanceof PdoSelectParams) {
            parent::select(
                $this->pdo_params->getFrom(),
                $this->pdo_params->getValues(),
                $this->pdo_params->getWhere(),
                $this->pdo_params->getExtras(),
                $this->pdo_params->getDebug()
            );
        } else {
            parent::query(
                $this->pdo_params->getSql(),
                $this->pdo_params->getPlaceholders(),
                $this->pdo_params->getDebug()
            );
        }
    }

    /**
     * Removes any previous querystring parameters from the URL.
     *
     * @param string $url The URL to remove the querystring from
     * @param string $querystring The name of the querystring parameter to remove
     * @return string The updated URL
     */
    private function removePreviousQuerystring(string $url, string $querystring): string
    {
        if ($this->options->rewrite_links === true) {
            $find = array('`' . $this->options->rewrite_transition . $querystring . '[0-9]+`', '`' . $this->options->rewrite_extension . '`');
            $replace = array('', '');
        } else {
            $find = array('`\?' . $querystring . '=([0-9]+)&(amp;)?`', '`\?' . $querystring . '=([0-9]+)`', '`&(amp;)?' . $querystring . '=([0-9]+)`');
            $replace = array('?', '', '');
        }
        $url = preg_replace($find, $replace, $url);

        return (string) $url;
    }
}
