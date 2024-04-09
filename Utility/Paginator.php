<?php

namespace Utility;

class Paginator
{
    public static function paginate(int $totalItems, int $currentPage, array $content)
    {
        $config = require(__DIR__ . "../../config.php");
        $itemsPerPage = $config['MAX_RESOURCE'];

        return ["current_page" => $currentPage, "data" => $content, "per_page" => $itemsPerPage, "total_items" => $totalItems];
    }
}
