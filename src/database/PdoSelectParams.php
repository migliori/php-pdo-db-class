<?php

namespace Migliori\Database;

class PdoSelectParams
{
    /**
     * Class PdoSelectParams
     * Represents the parameters for a PDO select query.
     * PdoSelectParams are used in the Pagination class constructor to set the PDO parameters for the DB::Select function
     *
     * Example of use:
     *
     * $pdo_select_params = new PdoSelectParams($from, $values, $where, $extras, $debug);
     * $pagination = new Pagination($pdo_select_params, $user_options);
     * $pagination_html = $pagination->pagine();
     */
    private string  $from;
    /**
     * @var string|array<string> $values The values to be selected.
     */
    private array|string $values;
    /**
     * @var array<int|string, int<min, -1>|int<1, max>|string>|string $where The WHERE clause parameters.
     */
    private $where;
    /**
     * @var array<string, bool|null|int|string|array<string>> $extras The extra parameters for the query.
     */
    private $extras;
    private bool $debug;

    /**
     * PdoSelectParams constructor.
     *
     * @param string                                             $from       Table name with the possible joins
     * @param string|array<string>                               $values     [OPTIONAL] Array or string containing the field names
     * @param array<int|string, int<min, -1>|int<1, max>|string>|string $where      [OPTIONAL] Array containing the fields and values or a string
     * @param array<string, bool|null|int|string|array<string>>  $extras     [OPTIONAL] Array containing the optional following pairs of key => values:
     *                                                  - 'select_distinct' => If set to true the query will use 'SELECT DISTINCT' instead of 'SELECT'. Default is false.
     *                                                  - 'order_by' => Array or string containing field(s) order,
     *                                                     or null to not specify any order. Default is null.
     *                                                  - 'group_by' => Array or string containing field(s) for group. Default is null.
     *                                                  - 'limit' => Integer or string containing the maximum number of results,
     *                                                     or null to not specify any limit. E.g: 'limit' => 10 or 'limit' => '10, 20'.
     * @param bool                                               $debug      [OPTIONAL] If set to true, will output results and query info
     */
    public function __construct($from, $values, $where = [], $extras = [], $debug = false)
    {
        $this->from = $from;
        $this->values = $values;
        $this->where = $where;
        $this->extras = $extras;
        $this->debug = $debug;
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
        $this->extras['limit'] = (($p - 1) * $mpp)  . ',' . $mpp;
    }

    /**
     * Get the table name from which to select.
     *
     * @return string The table name.
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * Get the values to select.
     *
     * @return string|array<string> The values to select.
     */
    public function getValues(): mixed
    {
        return $this->values;
    }

    /**
     * Get the WHERE clause for the SELECT query.
     *
     * @return array<int|string, int<min, -1>|int<1, max>|string>|string The WHERE clause.
     */
    public function getWhere(): mixed
    {
        return $this->where;
    }

    /**
     * Get any extra parameters for the SELECT query.
     *
     * @return array<string, bool|null|int|string|array<string>> The extra parameters.
     */
    public function getExtras(): array
    {
        return $this->extras;
    }

    /**
     * Get the debug mode for the SELECT query.
     *
     * @return bool The debug mode.
     */
    public function getDebug(): bool
    {
        return $this->debug;
    }
}
