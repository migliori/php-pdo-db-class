<?php

namespace Migliori\Database;

class PdoQueryParams
{
    /**
     * Class PdoQueryParams
     * Represents the parameters of a PDO query.
     * PdoQueryParams are used in the Pagination class constructor to set the PDO parameters for the DB::Query function
     *
     * Example of use:
     *
     * $pdo_query_params = new PdoQueryParams($sql, $placeholders, $debug);
     * $pagination = new Pagination($pdo_query_params, $user_options);
     * $pagination_html = $pagination->pagine();
     */
    private string  $sql;
    /**
     * @var array<string, string|int|bool|\DateTime|null> $placeholders The values to be bound to the SQL.
     */
    private array $placeholders;
    private bool $debug;

    /**
     * PdoQueryParams constructor.
     *
     * @param string $sql SQL
     * @param array<string, string|int|bool|\DateTime|null> $placeholders [OPTIONAL] Associative array placeholders for binding to SQL
     *                            array('name' => 'Cathy', 'city' => 'Cardiff')
     * @param bool $debug [OPTIONAL] If set to true, will output results and query info
     * @return void
     */
    public function __construct(string $sql, array $placeholders = [], bool  $debug = false)
    {
        $this->sql           = $sql;
        $this->placeholders  = $placeholders;
        $this->debug         = $debug;
    }

    /**
     * Adds a request limit for pagination to the PDO settings based on the current page and items per page.
     *
     * @param int $p The current page.
     * @param int $mpp The items per page.
     * @return void
     */
    public function addRequestLimit(int $p, int $mpp): void
    {
        $suppr = 'LIMIT';
        $find = (string) strstr($this->sql, $suppr);
        $this->sql = str_replace($find, '', $this->sql);    // if empty delete the "LIMIT" clause
        $this->sql .= ' LIMIT ' . (($p - 1) * $mpp)  . ',' . $mpp;
    }

    /**
     * Get the debug flag.
     *
     * @return bool The debug flag.
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * Get the SQL query.
     *
     * @return string The SQL query.
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * Get the placeholders.
     *
     * @return array<string, string|int|bool|\DateTime|null> The placeholders.
     */
    public function getPlaceholders()
    {
        return $this->placeholders;
    }
}
